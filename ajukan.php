<?php
// Halaman publik: form pengajuan peminjaman oleh peminjam (tanpa login)
require_once __DIR__ . '/includes/auth.php';

$errors = [];
$hariIni = date('Y-m-d');

if (is_post()) {
    csrf_check();

    // jebakan bot: kolom tersembunyi ini harus kosong
    if (!empty($_POST['website'])) redirect('katalog.php');

    $d = [
        'nama'      => trim($_POST['nama'] ?? ''),
        'instansi'  => trim($_POST['instansi'] ?? ''),
        'no_hp'     => trim($_POST['no_hp'] ?? ''),
        'email'     => trim($_POST['email'] ?? ''),
        'pinjam'    => $_POST['tanggal_pinjam'] ?? '',
        'kembali'   => $_POST['tanggal_kembali_rencana'] ?? '',
        'keperluan' => trim($_POST['keperluan'] ?? ''),
    ];
    $items = [];
    foreach (($_POST['item_barang'] ?? []) as $i => $idb) {
        $idb = (int)$idb;
        $jml = (int)($_POST['item_jumlah'][$i] ?? 0);
        if ($idb && $jml > 0) $items[$idb] = ($items[$idb] ?? 0) + $jml;
    }

    if ($d['nama'] === '')      $errors[] = 'Nama penanggung jawab wajib diisi.';
    if ($d['instansi'] === '')  $errors[] = 'Instansi/organisasi wajib diisi.';
    if (strlen(hanya_angka($d['no_hp'])) < 10 || strlen(hanya_angka($d['no_hp'])) > 15)
                                $errors[] = 'Nomor WhatsApp tidak valid (10–15 digit).';
    if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL))
                                $errors[] = 'Format email tidak valid.';
    if (!valid_date($d['pinjam']))       $errors[] = 'Tanggal pinjam tidak valid.';
    elseif ($d['pinjam'] < $hariIni)     $errors[] = 'Tanggal pinjam tidak boleh sebelum hari ini.';
    if (!valid_date($d['kembali']))      $errors[] = 'Tanggal kembali tidak valid.';
    elseif (valid_date($d['pinjam']) && $d['kembali'] < $d['pinjam']) $errors[] = 'Tanggal kembali tidak boleh sebelum tanggal pinjam.';
    elseif (valid_date($d['pinjam']) && (strtotime($d['kembali']) - strtotime($d['pinjam'])) / 86400 > 14)
                                         $errors[] = 'Lama peminjaman maksimal 14 hari.';
    if (mb_strlen($d['keperluan']) < 5)  $errors[] = 'Jelaskan keperluan peminjaman.';
    if (!$items)                         $errors[] = 'Pilih minimal satu barang.';

    // cek ketersediaan saat ini (pengecekan final dilakukan PSDO saat menyetujui)
    if ($items) {
        $s = $pdo->prepare("SELECT nama_barang, satuan, jumlah_tersedia, kondisi FROM barang WHERE id_barang = ?");
        foreach ($items as $idb => $jml) {
            $s->execute([$idb]);
            $b = $s->fetch();
            if (!$b || $b['kondisi'] !== 'baik') { $errors[] = 'Ada barang yang tidak bisa dipinjam.'; continue; }
            if ($jml > $b['jumlah_tersedia']) $errors[] = "{$b['nama_barang']}: tersedia {$b['jumlah_tersedia']} {$b['satuan']}, diminta {$jml}.";
        }
    }

    if (!$errors) {
        $pdo->beginTransaction();
        $kode = buat_kode_pengajuan($pdo);
        $pdo->prepare(
            "INSERT INTO pengajuan (kode_pengajuan, nama, instansi, no_hp, email, tanggal_pinjam, tanggal_kembali_rencana, keperluan)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([$kode, $d['nama'], $d['instansi'], $d['no_hp'], $d['email'] ?: null, $d['pinjam'], $d['kembali'], $d['keperluan']]);
        $idAju = (int)$pdo->lastInsertId();

        $ins = $pdo->prepare("INSERT INTO detail_pengajuan (id_pengajuan, id_barang, jumlah) VALUES (?, ?, ?)");
        foreach ($items as $idb => $jml) $ins->execute([$idAju, $idb, $jml]);
        $pdo->commit();

        $_SESSION['pengajuan_baru'] = $kode;
        redirect('status.php?kode=' . urlencode($kode));
    }
}

$barang = $pdo->query(
    "SELECT b.id_barang, b.nama_barang, b.satuan, b.jumlah_tersedia, b.biaya_sewa, k.nama_kategori
     FROM barang b JOIN kategori k ON k.id_kategori = b.id_kategori
     WHERE b.kondisi = 'baik' AND b.jumlah_tersedia > 0
     ORDER BY k.nama_kategori, b.nama_barang"
)->fetchAll();

$oldItems = [];
foreach (($_POST['item_barang'] ?? []) as $i => $idb) $oldItems[] = [(int)$idb, (int)($_POST['item_jumlah'][$i] ?? 1)];
if (!$oldItems) $oldItems = [[(int)($_GET['barang'] ?? 0), 1]];

$title     = 'Ajukan Peminjaman';
$pubActive = 'ajukan';
require __DIR__ . '/includes/public_header.php';
?>

<div class="mb-4">
  <h1 class="page-title">Form pengajuan peminjaman</h1>
  <p class="page-sub">Pengajuan akan diperiksa Biro PSDO. Simpan kode pengajuan yang muncul setelah mengirim untuk mengecek status.</p>
</div>

<?= show_errors($errors) ?>

<form method="post" id="form-aju">
  <?= csrf_field() ?>
  <input type="text" name="website" class="d-none" tabindex="-1" autocomplete="off">

  <div class="row g-3 align-items-start">
    <div class="col-lg-5">
      <div class="card mb-3">
        <div class="card-header"><span>1. Data peminjam</span></div>
        <div class="card-body row g-3">
          <div class="col-12"><label class="form-label">Nama penanggung jawab</label>
            <input name="nama" class="form-control" maxlength="100" required value="<?= e(old('nama')) ?>"></div>
          <div class="col-12"><label class="form-label">Instansi / organisasi / kepanitiaan</label>
            <input name="instansi" class="form-control" maxlength="100" required placeholder="mis. HIMA Matematika" value="<?= e(old('instansi')) ?>"></div>
          <div class="col-md-6"><label class="form-label">No. WhatsApp</label>
            <input name="no_hp" class="form-control" maxlength="20" required inputmode="tel" placeholder="08xxxxxxxxxx" value="<?= e(old('no_hp')) ?>"></div>
          <div class="col-md-6"><label class="form-label">Email <span class="text-muted fw-normal">(opsional)</span></label>
            <input type="email" name="email" class="form-control" maxlength="100" value="<?= e(old('email')) ?>"></div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><span>2. Waktu dan keperluan</span></div>
        <div class="card-body row g-3">
          <div class="col-6"><label class="form-label">Tanggal pinjam</label>
            <input type="date" name="tanggal_pinjam" id="tgl-pinjam" class="form-control" required min="<?= $hariIni ?>" value="<?= e(old('tanggal_pinjam', date('Y-m-d', strtotime('+1 day')))) ?>"></div>
          <div class="col-6"><label class="form-label">Rencana kembali</label>
            <input type="date" name="tanggal_kembali_rencana" id="tgl-kembali" class="form-control" required value="<?= e(old('tanggal_kembali_rencana', date('Y-m-d', strtotime('+3 days')))) ?>"></div>
          <div class="col-12"><label class="form-label">Keperluan / nama acara</label>
            <textarea name="keperluan" class="form-control" rows="3" required placeholder="mis. Seminar nasional di aula FMIPA"><?= e(old('keperluan')) ?></textarea></div>
        </div>
      </div>
    </div>

    <div class="col-lg-7">
      <div class="card">
        <div class="card-header">
          <span>3. Barang yang ingin dipinjam</span>
          <button type="button" class="btn btn-sm btn-light" id="tambah-baris"><i class="bi bi-plus-lg me-1"></i>Tambah barang</button>
        </div>
        <div class="table-responsive">
          <table class="table">
            <thead><tr><th>Barang</th><th style="width:110px">Jumlah</th><th class="text-end" style="width:130px">Estimasi</th><th style="width:50px"></th></tr></thead>
            <tbody id="baris-barang">
            <?php foreach ($oldItems as [$oidb, $ojml]): ?>
              <tr class="item-row">
                <td>
                  <select name="item_barang[]" class="form-select pilih-barang">
                    <option value="">— pilih barang —</option>
                    <?php $katSeb = null; foreach ($barang as $b):
                        if ($b['nama_kategori'] !== $katSeb) { if ($katSeb !== null) echo '</optgroup>'; $katSeb = $b['nama_kategori']; echo '<optgroup label="' . e($katSeb) . '">'; } ?>
                      <option value="<?= (int)$b['id_barang'] ?>" data-max="<?= (int)$b['jumlah_tersedia'] ?>" data-sewa="<?= (float)$b['biaya_sewa'] ?>" <?= sel($oidb, $b['id_barang']) ?>>
                        <?= e($b['nama_barang']) ?> (tersedia <?= (int)$b['jumlah_tersedia'] ?>)
                      </option>
                    <?php endforeach; if ($katSeb !== null) echo '</optgroup>'; ?>
                  </select>
                  <div class="cell-sub mt-1 info-sewa"></div>
                </td>
                <td><input type="number" name="item_jumlah[]" class="form-control jumlah" min="1" value="<?= (int)$ojml ?>"></td>
                <td class="text-end fw-semibold subtotal">Rp 0</td>
                <td><button type="button" class="btn btn-sm btn-light text-danger hapus-baris"><i class="bi bi-x-lg"></i></button></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="card-body border-top">
          <div class="total-box d-flex justify-content-between align-items-center">
            <span class="text-muted">Estimasi biaya sewa</span>
            <span class="fs-5 fw-bold" id="total">Rp 0</span>
          </div>
          <div class="form-text mt-2">Biaya final ditentukan Biro PSDO. Peminjam internal HIMA Ilmu Komputer tidak dikenai biaya.</div>
        </div>
        <div class="card-footer bg-transparent p-3 px-4">
          <button class="btn btn-primary px-4"><i class="bi bi-send me-2"></i>Kirim pengajuan</button>
        </div>
      </div>
    </div>
  </div>
</form>

<script>
const rp = n => 'Rp ' + Math.round(n).toLocaleString('id-ID');
const tbody = document.getElementById('baris-barang');
function hitung() {
  let total = 0;
  tbody.querySelectorAll('tr').forEach(tr => {
    const opt = tr.querySelector('.pilih-barang').selectedOptions[0];
    const inp = tr.querySelector('.jumlah');
    const ok = opt && opt.value;
    const sewa = ok ? +opt.dataset.sewa : 0;
    if (ok) inp.max = opt.dataset.max; else inp.removeAttribute('max');
    const sub = sewa * (+inp.value || 0);
    tr.querySelector('.subtotal').textContent = rp(sub);
    tr.querySelector('.info-sewa').textContent = ok ? (sewa > 0 ? rp(sewa) + ' / unit · maks ' + opt.dataset.max : 'gratis · maks ' + opt.dataset.max) : '';
    total += sub;
  });
  document.getElementById('total').textContent = rp(total);
}
document.getElementById('tambah-baris').addEventListener('click', () => {
  const baru = tbody.querySelector('tr').cloneNode(true);
  baru.querySelector('.pilih-barang').value = '';
  baru.querySelector('.jumlah').value = 1;
  tbody.appendChild(baru); hitung();
});
tbody.addEventListener('click', e => {
  if (e.target.closest('.hapus-baris') && tbody.children.length > 1) { e.target.closest('tr').remove(); hitung(); }
});
tbody.addEventListener('input', hitung);
tbody.addEventListener('change', hitung);
const tp = document.getElementById('tgl-pinjam'), tk = document.getElementById('tgl-kembali');
const aturMin = () => { tk.min = tp.value; if (tk.value < tp.value) tk.value = tp.value; };
tp.addEventListener('change', aturMin); aturMin();
hitung();
</script>

<?php require __DIR__ . '/includes/public_footer.php'; ?>
