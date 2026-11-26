<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['psdo']);

$id     = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$errors = [];

$barang = null;
if ($id) {
    $s = $pdo->prepare("SELECT * FROM barang WHERE id_barang = ?");
    $s->execute([$id]);
    $barang = $s->fetch();
    if (!$barang) { flash('danger', 'Barang tidak ditemukan.'); redirect('barang/index.php'); }
}

// barang boleh dihapus hanya jika belum punya riwayat
// (riwayat transaksi pendataan awal boleh ikut terhapus)
function riwayat_barang(PDO $pdo, int $id) {
    $n = 0;
    foreach (['detail_peminjaman', 'detail_pemeriksaan', 'arsip_inventaris'] as $t) {
        $s = $pdo->prepare("SELECT COUNT(*) FROM $t WHERE id_barang = ?");
        $s->execute([$id]);
        $n += (int)$s->fetchColumn();
    }
    $s = $pdo->prepare("SELECT COUNT(*) FROM transaksi_barang WHERE id_barang = ?");
    $s->execute([$id]);
    return $n + max(0, (int)$s->fetchColumn() - 1);
}

if (is_post()) {
    csrf_check();

    // ---------- hapus ----------
    if (($_POST['aksi'] ?? '') === 'hapus' && $barang) {
        if (riwayat_barang($pdo, $id) > 0) {
            flash('danger', 'Barang sudah punya riwayat (peminjaman/pemeriksaan/arsip/transaksi) sehingga tidak bisa dihapus. Catat sebagai Barang Keluar.');
            redirect('barang/form.php?id=' . $id);
        }
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM transaksi_barang WHERE id_barang = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM barang WHERE id_barang = ?")->execute([$id]);
        $pdo->commit();
        flash('success', 'Barang ' . $barang['nama_barang'] . ' dihapus.');
        redirect('barang/index.php');
    }

    // ---------- simpan ----------
    $d = [
        'id_kategori'       => (int)($_POST['id_kategori'] ?? 0),
        'kode_inventaris'   => trim($_POST['kode_inventaris'] ?? ''),
        'nama_barang'       => trim($_POST['nama_barang'] ?? ''),
        'satuan'            => trim($_POST['satuan'] ?? ''),
        'lokasi'            => trim($_POST['lokasi'] ?? 'PKM'),
        'tanggal_perolehan' => $_POST['tanggal_perolehan'] ?? '',
        'kondisi'           => $_POST['kondisi'] ?? 'baik',
        'biaya_sewa'        => (float)($_POST['biaya_sewa'] ?? 0),
        'keterangan'        => trim($_POST['keterangan'] ?? ''),
    ];
    $jumlahAwal = (int)($_POST['jumlah_awal'] ?? 0);

    // validasi
    if (!$d['id_kategori'])                  $errors[] = 'Kategori wajib dipilih.';
    if (!preg_match('/^\d{3}\.\d{2}\.\d{4}$/', $d['kode_inventaris']))
                                             $errors[] = 'Kode inventaris harus berformat nomor.bulan.tahun, contoh 001.02.2024.';
    if ($d['nama_barang'] === '')            $errors[] = 'Nama barang wajib diisi.';
    if ($d['satuan'] === '')                 $errors[] = 'Satuan wajib diisi.';
    if ($d['lokasi'] === '')                 $errors[] = 'Lokasi wajib diisi.';
    if (!valid_date($d['tanggal_perolehan'])) $errors[] = 'Tanggal perolehan tidak valid.';
    elseif ($d['tanggal_perolehan'] > date('Y-m-d')) $errors[] = 'Tanggal perolehan tidak boleh di masa depan.';
    if (!in_array($d['kondisi'], ['baik', 'rusak', 'hilang'], true)) $errors[] = 'Kondisi tidak valid.';
    if ($d['biaya_sewa'] < 0)                $errors[] = 'Biaya sewa tidak boleh negatif.';
    if (!$barang && $jumlahAwal < 1)         $errors[] = 'Jumlah awal minimal 1.';

    $s = $pdo->prepare("SELECT COUNT(*) FROM barang WHERE kode_inventaris = ? AND id_barang <> ?");
    $s->execute([$d['kode_inventaris'], $id]);
    if ($s->fetchColumn() > 0) $errors[] = 'Kode inventaris ' . $d['kode_inventaris'] . ' sudah dipakai barang lain.';

    if (!$errors) {
        $pdo->beginTransaction();
        if ($barang) {
            $pdo->prepare(
                "UPDATE barang SET id_kategori=?, kode_inventaris=?, nama_barang=?, satuan=?, lokasi=?,
                        tanggal_perolehan=?, kondisi=?, biaya_sewa=?, keterangan=?
                 WHERE id_barang=?"
            )->execute([...array_values($d), $id]);
        } else {
            $pdo->prepare(
                "INSERT INTO barang (id_kategori, kode_inventaris, nama_barang, satuan, lokasi,
                        tanggal_perolehan, kondisi, biaya_sewa, keterangan, jumlah_total, jumlah_tersedia)
                 VALUES (?,?,?,?,?,?,?,?,?,?,0)"
            )->execute([...array_values($d), $jumlahAwal]);
            $id = (int)$pdo->lastInsertId();
            catat_transaksi($pdo, $id, 'masuk', $jumlahAwal, $d['tanggal_perolehan'], 'Pendataan awal barang');
        }
        sync_tersedia($pdo, $id);
        $pdo->commit();

        flash('success', 'Data barang ' . $d['nama_barang'] . ' disimpan.');
        redirect('barang/detail.php?id=' . $id);
    }
}

// usulan kode: nomor urut berikutnya + bulan & tahun perolehan
$noBerikut = (int)$pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(kode_inventaris,'.',1) AS UNSIGNED)),0)+1 FROM barang")->fetchColumn();
$noBerikut = str_pad($noBerikut, 3, '0', STR_PAD_LEFT);

$v = fn($k, $def = '') => old($k, $barang[$k] ?? $def);
$kategori = $pdo->query("SELECT * FROM kategori ORDER BY nama_kategori")->fetchAll();

$title    = $barang ? 'Ubah Barang' : 'Tambah Barang';
$subtitle = $barang ? $barang['kode_inventaris'] . ' · ' . $barang['nama_barang'] : 'Data barang inventaris baru.';
$active   = 'barang';
$back     = $barang ? 'barang/detail.php?id=' . $id : 'barang/index.php';
require __DIR__ . '/../includes/header.php';
?>

<div class="row">
  <div class="col-xl-8">
    <?= show_errors($errors) ?>
    <form method="post" class="card">
      <div class="card-body p-4">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$id ?>">

        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label">Nama barang</label>
            <input name="nama_barang" class="form-control" maxlength="100" required value="<?= e($v('nama_barang')) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Kategori</label>
            <select name="id_kategori" class="form-select" required>
              <option value="">— pilih —</option>
              <?php foreach ($kategori as $k): ?>
                <option value="<?= (int)$k['id_kategori'] ?>" <?= sel($v('id_kategori'), $k['id_kategori']) ?>>
                  <?= e($k['nama_kategori']) ?> (<?= (int)$k['umur_pakai_tahun'] ?> th)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label">Tanggal perolehan</label>
            <input type="date" name="tanggal_perolehan" id="tgl" class="form-control" required max="<?= date('Y-m-d') ?>"
                   value="<?= e($v('tanggal_perolehan', date('Y-m-d'))) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Kode inventaris</label>
            <input name="kode_inventaris" id="kode" class="form-control mono" required placeholder="001.02.2024"
                   pattern="\d{3}\.\d{2}\.\d{4}" value="<?= e($v('kode_inventaris')) ?>">
            <div class="form-text">Format nomor.bulan.tahun<?= $barang ? '' : ', terisi otomatis' ?>.</div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Kondisi</label>
            <select name="kondisi" class="form-select">
              <?php foreach (['baik' => 'Baik', 'rusak' => 'Rusak', 'hilang' => 'Hilang'] as $k => $l): ?>
                <option value="<?= $k ?>" <?= sel($v('kondisi', 'baik'), $k) ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label">Jumlah</label>
            <?php if ($barang): ?>
              <input class="form-control" disabled value="<?= (int)$barang['jumlah_total'] ?>">
              <div class="form-text">Ubah lewat menu Masuk &amp; Keluar.</div>
            <?php else: ?>
              <input type="number" name="jumlah_awal" class="form-control" min="1" required value="<?= e(old('jumlah_awal', 1)) ?>">
            <?php endif; ?>
          </div>
          <div class="col-md-4">
            <label class="form-label">Satuan</label>
            <input name="satuan" class="form-control" list="satuan-list" required maxlength="20" value="<?= e($v('satuan', 'unit')) ?>">
            <datalist id="satuan-list"><option>unit</option><option>buah</option><option>set</option><option>rim</option><option>pak</option></datalist>
          </div>
          <div class="col-md-4">
            <label class="form-label">Lokasi</label>
            <input name="lokasi" class="form-control" required maxlength="50" value="<?= e($v('lokasi', 'PKM')) ?>">
          </div>

          <div class="col-md-4">
            <label class="form-label">Biaya sewa per unit</label>
            <div class="input-group">
              <span class="input-group-text">Rp</span>
              <input type="number" name="biaya_sewa" class="form-control" min="0" step="500" value="<?= e((int)$v('biaya_sewa', 0)) ?>">
            </div>
            <div class="form-text">Isi 0 jika tidak disewakan.</div>
          </div>
          <div class="col-md-8">
            <label class="form-label">Keterangan</label>
            <input name="keterangan" class="form-control" value="<?= e($v('keterangan')) ?>">
          </div>
        </div>
      </div>
      <div class="card-footer bg-transparent d-flex gap-2 p-3 px-4">
        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan</button>
        <a href="<?= url($back) ?>" class="btn btn-light">Batal</a>
      </div>
    </form>

    <?php if ($barang): ?>
      <div class="card mt-3">
        <div class="card-body d-flex justify-content-between align-items-center gap-3 flex-wrap">
          <div>
            <div class="fw-semibold">Hapus barang</div>
            <div class="small text-muted">Hanya untuk barang yang salah input dan belum punya riwayat.</div>
          </div>
          <?= post_button('', '<i class="bi bi-trash me-1"></i>Hapus', 'btn btn-outline-danger',
                          'Hapus ' . $barang['nama_barang'] . '? Tindakan ini tidak bisa dibatalkan.', ['aksi' => 'hapus', 'id' => $id]) ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if (!$barang): ?>
<script>
  // isi kode otomatis: nomor urut berikutnya + bulan + tahun dari tanggal perolehan
  const tgl = document.getElementById('tgl'), kode = document.getElementById('kode');
  let manual = kode.value !== '';
  kode.addEventListener('input', () => manual = true);
  function isiKode() {
    if (manual || !tgl.value) return;
    const [y, m] = tgl.value.split('-');
    kode.value = '<?= $noBerikut ?>.' + m + '.' + y;
  }
  tgl.addEventListener('change', isiKode);
  isiKode();
</script>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
