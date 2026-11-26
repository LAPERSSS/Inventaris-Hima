<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['psdo']);

$errors = [];

if (is_post()) {
    csrf_check();

    $mode       = $_POST['mode_peminjam'] ?? 'lama';
    $idPeminjam = (int)($_POST['id_peminjam'] ?? 0);
    $pj = [
        'nama'     => trim($_POST['nama'] ?? ''),
        'instansi' => trim($_POST['instansi'] ?? ''),
        'no_hp'    => trim($_POST['no_hp'] ?? ''),
        'email'    => trim($_POST['email'] ?? ''),
    ];
    $tglPinjam = $_POST['tanggal_pinjam'] ?? '';
    $tglTempo  = $_POST['tanggal_jatuh_tempo'] ?? '';
    $gratis    = !empty($_POST['gratis']);

    // gabungkan baris barang yang sama
    $items = [];
    foreach (($_POST['item_barang'] ?? []) as $i => $idb) {
        $idb = (int)$idb;
        $jml = (int)($_POST['item_jumlah'][$i] ?? 0);
        if ($idb && $jml > 0) $items[$idb] = ($items[$idb] ?? 0) + $jml;
    }

    // validasi
    if ($mode === 'lama' && !$idPeminjam) $errors[] = 'Pilih peminjam, atau tambahkan peminjam baru.';
    if ($mode === 'baru') {
        if ($pj['nama'] === '')     $errors[] = 'Nama peminjam wajib diisi.';
        if ($pj['instansi'] === '') $errors[] = 'Instansi wajib diisi.';
        if (!preg_match('/^[0-9+\-\s x]{8,20}$/', $pj['no_hp'])) $errors[] = 'Nomor HP tidak valid.';
        if ($pj['email'] !== '' && !filter_var($pj['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Format email tidak valid.';
    }
    if (!valid_date($tglPinjam)) $errors[] = 'Tanggal pinjam tidak valid.';
    if (!valid_date($tglTempo))  $errors[] = 'Tanggal jatuh tempo tidak valid.';
    if (valid_date($tglPinjam) && valid_date($tglTempo) && $tglTempo < $tglPinjam)
        $errors[] = 'Jatuh tempo tidak boleh sebelum tanggal pinjam.';
    if (!$items) $errors[] = 'Tambahkan minimal satu barang yang dipinjam.';

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            // kunci baris barang agar stok tidak berebut
            $rinci = [];
            $total = 0;
            $s = $pdo->prepare("SELECT * FROM barang WHERE id_barang = ? FOR UPDATE");
            foreach ($items as $idb => $jml) {
                $s->execute([$idb]);
                $b = $s->fetch();
                if (!$b) { $errors[] = 'Barang tidak ditemukan.'; continue; }
                if ($b['kondisi'] !== 'baik') { $errors[] = "{$b['nama_barang']} sedang {$b['kondisi']}, tidak bisa dipinjam."; continue; }
                if ($jml > $b['jumlah_tersedia']) {
                    $errors[] = "{$b['nama_barang']}: diminta {$jml}, tersedia {$b['jumlah_tersedia']} {$b['satuan']}.";
                    continue;
                }
                $sub = $gratis ? 0 : $jml * $b['biaya_sewa'];
                $total += $sub;
                $rinci[] = [$idb, $jml, $sub];
            }
            if ($errors) throw new RuntimeException('validasi');

            if ($mode === 'baru') {
                $pdo->prepare("INSERT INTO peminjam (nama, instansi, no_hp, email) VALUES (?, ?, ?, ?)")
                    ->execute([$pj['nama'], $pj['instansi'], $pj['no_hp'], $pj['email'] ?: null]);
                $idPeminjam = (int)$pdo->lastInsertId();
            }

            $pdo->prepare(
                "INSERT INTO peminjaman (id_peminjam, id_pengguna, tanggal_pinjam, tanggal_jatuh_tempo, status, total_biaya)
                 VALUES (?, ?, ?, ?, 'dipinjam', ?)"
            )->execute([$idPeminjam, user()['id_pengguna'], $tglPinjam, $tglTempo, $total]);
            $idPinjam = (int)$pdo->lastInsertId();

            $ins = $pdo->prepare(
                "INSERT INTO detail_peminjaman (id_peminjaman, id_barang, jumlah, kondisi_keluar, subtotal_biaya)
                 VALUES (?, ?, ?, 'baik', ?)"
            );
            foreach ($rinci as [$idb, $jml, $sub]) {
                $ins->execute([$idPinjam, $idb, $jml, $sub]);
                sync_tersedia($pdo, $idb);
            }
            $pdo->commit();

            flash('success', 'Peminjaman PJ-' . str_pad($idPinjam, 4, '0', STR_PAD_LEFT) . ' dicatat. Stok tersedia sudah dikurangi.');
            redirect('peminjaman/detail.php?id=' . $idPinjam);
        } catch (RuntimeException $ex) {
            $pdo->rollBack();
        }
    }
}

$peminjam = $pdo->query("SELECT * FROM peminjam ORDER BY nama")->fetchAll();
$barang   = $pdo->query(
    "SELECT id_barang, kode_inventaris, nama_barang, satuan, jumlah_tersedia, biaya_sewa
     FROM barang WHERE kondisi = 'baik' AND jumlah_tersedia > 0 ORDER BY nama_barang"
)->fetchAll();

$mode = old('mode_peminjam', $peminjam ? 'lama' : 'baru');
$oldItems = [];
foreach (($_POST['item_barang'] ?? []) as $i => $idb) $oldItems[] = [(int)$idb, (int)($_POST['item_jumlah'][$i] ?? 1)];
if (!$oldItems) $oldItems = [[0, 1]];

$title    = 'Peminjaman Baru';
$subtitle = 'Hanya barang berkondisi baik yang masih tersedia yang bisa dipilih.';
$active   = 'peminjaman';
$back     = 'peminjaman/index.php';
require __DIR__ . '/../includes/header.php';
?>

<?= show_errors($errors) ?>

<form method="post" id="form-pinjam">
  <?= csrf_field() ?>
  <div class="row g-3 align-items-start">
    <div class="col-xl-5">
      <!-- peminjam -->
      <div class="card mb-3">
        <div class="card-header"><span>1. Data peminjam</span></div>
        <div class="card-body">
          <div class="btn-group w-100 mb-3" role="group">
            <input type="radio" class="btn-check" name="mode_peminjam" id="m-lama" value="lama" <?= $mode === 'lama' ? 'checked' : '' ?>>
            <label class="btn btn-outline-primary" for="m-lama">Peminjam lama</label>
            <input type="radio" class="btn-check" name="mode_peminjam" id="m-baru" value="baru" <?= $mode === 'baru' ? 'checked' : '' ?>>
            <label class="btn btn-outline-primary" for="m-baru">Peminjam baru</label>
          </div>

          <div id="box-lama">
            <select name="id_peminjam" class="form-select">
              <option value="">— pilih peminjam —</option>
              <?php foreach ($peminjam as $p): ?>
                <option value="<?= (int)$p['id_peminjam'] ?>" <?= sel(old('id_peminjam'), $p['id_peminjam']) ?>>
                  <?= e($p['nama']) ?> — <?= e($p['instansi']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div id="box-baru" class="row g-2">
            <div class="col-md-6"><label class="form-label">Nama penanggung jawab</label><input name="nama" class="form-control" maxlength="100" value="<?= e(old('nama')) ?>"></div>
            <div class="col-md-6"><label class="form-label">Instansi / organisasi</label><input name="instansi" class="form-control" maxlength="100" value="<?= e(old('instansi')) ?>"></div>
            <div class="col-md-6"><label class="form-label">No. HP</label><input name="no_hp" class="form-control" maxlength="20" placeholder="08xxxxxxxxxx" value="<?= e(old('no_hp')) ?>"></div>
            <div class="col-md-6"><label class="form-label">Email <span class="text-muted fw-normal">(opsional)</span></label><input type="email" name="email" class="form-control" maxlength="100" value="<?= e(old('email')) ?>"></div>
          </div>
        </div>
      </div>

      <!-- tanggal -->
      <div class="card">
        <div class="card-header"><span>2. Waktu peminjaman</span></div>
        <div class="card-body row g-2">
          <div class="col-6"><label class="form-label">Tanggal pinjam</label>
            <input type="date" name="tanggal_pinjam" id="tgl-pinjam" class="form-control" required value="<?= e(old('tanggal_pinjam', date('Y-m-d'))) ?>"></div>
          <div class="col-6"><label class="form-label">Jatuh tempo</label>
            <input type="date" name="tanggal_jatuh_tempo" id="tgl-tempo" class="form-control" required value="<?= e(old('tanggal_jatuh_tempo', date('Y-m-d', strtotime('+3 days')))) ?>"></div>
        </div>
      </div>
    </div>

    <div class="col-xl-7">
      <!-- barang -->
      <div class="card">
        <div class="card-header">
          <span>3. Barang yang dipinjam</span>
          <button type="button" class="btn btn-sm btn-light" id="tambah-baris"><i class="bi bi-plus-lg me-1"></i>Tambah barang</button>
        </div>
        <div class="table-responsive">
          <table class="table">
            <thead><tr><th>Barang</th><th style="width:110px">Jumlah</th><th class="text-end" style="width:130px">Subtotal</th><th style="width:50px"></th></tr></thead>
            <tbody id="baris-barang">
            <?php foreach ($oldItems as [$oidb, $ojml]): ?>
              <tr class="item-row">
                <td>
                  <select name="item_barang[]" class="form-select pilih-barang">
                    <option value="">— pilih —</option>
                    <?php foreach ($barang as $b): ?>
                      <option value="<?= (int)$b['id_barang'] ?>" data-max="<?= (int)$b['jumlah_tersedia'] ?>" data-sewa="<?= (float)$b['biaya_sewa'] ?>" <?= sel($oidb, $b['id_barang']) ?>>
                        <?= e($b['nama_barang']) ?> (tersedia <?= (int)$b['jumlah_tersedia'] ?> <?= e($b['satuan']) ?>)
                      </option>
                    <?php endforeach; ?>
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
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="gratis" id="gratis" value="1" <?= old('gratis') ? 'checked' : '' ?>>
            <label class="form-check-label" for="gratis">Peminjam internal HIMA Ilkom (tanpa biaya sewa)</label>
          </div>
          <div class="total-box d-flex justify-content-between align-items-center">
            <span class="text-muted">Total biaya sewa</span>
            <span class="fs-5 fw-bold" id="total">Rp 0</span>
          </div>
        </div>
        <div class="card-footer bg-transparent d-flex gap-2 p-3 px-4">
          <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan peminjaman</button>
          <a href="index.php" class="btn btn-light">Batal</a>
        </div>
      </div>
    </div>
  </div>
</form>

<script>
const rp = n => 'Rp ' + Math.round(n).toLocaleString('id-ID');
const tbody = document.getElementById('baris-barang');
const gratis = document.getElementById('gratis');

function hitung() {
  let total = 0;
  tbody.querySelectorAll('tr').forEach(tr => {
    const opt = tr.querySelector('.pilih-barang').selectedOptions[0];
    const inp = tr.querySelector('.jumlah');
    const sewa = opt && opt.value ? +opt.dataset.sewa : 0;
    const max  = opt && opt.value ? +opt.dataset.max  : 0;
    if (max) inp.max = max; else inp.removeAttribute('max');
    const sub = gratis.checked ? 0 : sewa * (+inp.value || 0);
    tr.querySelector('.subtotal').textContent = rp(sub);
    tr.querySelector('.info-sewa').textContent = opt && opt.value ? (sewa > 0 ? rp(sewa) + ' / unit' : 'tidak dikenai biaya') : '';
    total += sub;
  });
  document.getElementById('total').textContent = rp(total);
}

document.getElementById('tambah-baris').addEventListener('click', () => {
  const baru = tbody.querySelector('tr').cloneNode(true);
  baru.querySelector('.pilih-barang').value = '';
  baru.querySelector('.jumlah').value = 1;
  tbody.appendChild(baru);
  hitung();
});
tbody.addEventListener('click', e => {
  if (e.target.closest('.hapus-baris') && tbody.children.length > 1) {
    e.target.closest('tr').remove(); hitung();
  }
});
tbody.addEventListener('input', hitung);
tbody.addEventListener('change', hitung);
gratis.addEventListener('change', hitung);

// peminjam lama / baru
function modePeminjam() {
  const baru = document.getElementById('m-baru').checked;
  document.getElementById('box-baru').style.display = baru ? '' : 'none';
  document.getElementById('box-lama').style.display = baru ? 'none' : '';
}
document.querySelectorAll('[name=mode_peminjam]').forEach(r => r.addEventListener('change', modePeminjam));

// jatuh tempo minimal = tanggal pinjam
const tp = document.getElementById('tgl-pinjam'), tt = document.getElementById('tgl-tempo');
tp.addEventListener('change', () => { tt.min = tp.value; if (tt.value < tp.value) tt.value = tp.value; });
tt.min = tp.value;

modePeminjam(); hitung();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
