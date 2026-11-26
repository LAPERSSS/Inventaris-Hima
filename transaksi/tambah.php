<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['psdo']);

$errors = [];

if (is_post()) {
    csrf_check();
    $idBarang = (int)($_POST['id_barang'] ?? 0);
    $jenis    = $_POST['jenis'] ?? '';
    $jumlah   = (int)($_POST['jumlah'] ?? 0);
    $tanggal  = $_POST['tanggal'] ?? '';
    $ket      = trim($_POST['keterangan'] ?? '');

    if (!in_array($jenis, ['masuk', 'keluar'], true)) $errors[] = 'Pilih jenis transaksi.';
    if ($jumlah < 1)                                    $errors[] = 'Jumlah minimal 1.';
    if (!valid_date($tanggal))                          $errors[] = 'Tanggal tidak valid.';
    elseif ($tanggal > date('Y-m-d'))                   $errors[] = 'Tanggal tidak boleh di masa depan.';
    if ($ket === '')                                    $errors[] = 'Keterangan/alasan wajib diisi.';

    if (!$errors) {
        $pdo->beginTransaction();
        $s = $pdo->prepare("SELECT * FROM barang WHERE id_barang = ? FOR UPDATE");
        $s->execute([$idBarang]);
        $b = $s->fetch();

        if (!$b) {
            $errors[] = 'Barang tidak ditemukan.';
        } elseif ($jenis === 'keluar') {
            // barang yang sedang dipinjam tidak bisa dikeluarkan
            $bisaKeluar = $b['jumlah_total'] - jumlah_dipinjam($pdo, $idBarang);
            if ($jumlah > $bisaKeluar) {
                $errors[] = "Jumlah keluar melebihi barang yang ada di tempat ({$bisaKeluar} {$b['satuan']}).";
            }
        }

        if ($errors) {
            $pdo->rollBack();
        } else {
            $delta = $jenis === 'masuk' ? $jumlah : -$jumlah;
            ubah_total($pdo, $idBarang, $delta);
            catat_transaksi($pdo, $idBarang, $jenis, $jumlah, $tanggal, $ket);
            $pdo->commit();

            flash('success', "Transaksi {$jenis} {$jumlah} {$b['satuan']} {$b['nama_barang']} dicatat.");
            redirect(!empty($_POST['dari_barang']) ? 'barang/detail.php?id=' . $idBarang : 'transaksi/index.php');
        }
    }
}

$barang = $pdo->query(
    "SELECT b.id_barang, b.kode_inventaris, b.nama_barang, b.satuan, b.jumlah_total,
            b.jumlah_total - COALESCE((SELECT SUM(d.jumlah) FROM detail_peminjaman d
                JOIN peminjaman p ON p.id_peminjaman = d.id_peminjaman
                WHERE p.tanggal_kembali IS NULL AND d.id_barang = b.id_barang), 0) AS di_tempat
     FROM barang b ORDER BY b.nama_barang"
)->fetchAll();

$pilih = (int)old('id_barang', $_GET['barang'] ?? 0);

$title    = 'Catat Barang Masuk / Keluar';
$subtitle = 'Jumlah total barang akan diperbarui otomatis.';
$active   = 'transaksi';
$back     = !empty($_GET['barang']) ? 'barang/detail.php?id=' . (int)$_GET['barang'] : 'transaksi/index.php';
require __DIR__ . '/../includes/header.php';
?>

<div class="row">
  <div class="col-xl-7">
    <?= show_errors($errors) ?>
    <form method="post" class="card">
      <div class="card-body p-4">
        <?= csrf_field() ?>
        <?php if (!empty($_GET['barang']) || !empty($_POST['dari_barang'])): ?><input type="hidden" name="dari_barang" value="1"><?php endif; ?>

        <div class="mb-3">
          <label class="form-label">Jenis transaksi</label>
          <div class="d-flex gap-2">
            <?php foreach (['masuk' => ['bi-box-arrow-in-down', 'Barang masuk', 'pembelian, hibah, selesai diperbaiki'],
                            'keluar' => ['bi-box-arrow-up', 'Barang keluar', 'rusak permanen, hilang, dihapus']] as $k => [$ic, $lbl, $hint]): ?>
              <input type="radio" class="btn-check" name="jenis" id="j-<?= $k ?>" value="<?= $k ?>" <?= old('jenis', 'masuk') === $k ? 'checked' : '' ?>>
              <label class="btn btn-outline-<?= $k === 'masuk' ? 'success' : 'danger' ?> flex-fill text-start p-3" for="j-<?= $k ?>">
                <i class="bi <?= $ic ?> me-1"></i><?= $lbl ?><div class="small fw-normal opacity-75"><?= $hint ?></div>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">Barang</label>
          <select name="id_barang" id="barang" class="form-select" required>
            <option value="">— pilih barang —</option>
            <?php foreach ($barang as $b): ?>
              <option value="<?= (int)$b['id_barang'] ?>" <?= sel($pilih, $b['id_barang']) ?>
                      data-info="Total <?= (int)$b['jumlah_total'] ?> <?= e($b['satuan']) ?> · di tempat <?= (int)$b['di_tempat'] ?>">
                <?= e($b['kode_inventaris']) ?> — <?= e($b['nama_barang']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="form-text" id="info-barang"></div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label">Jumlah</label>
            <input type="number" name="jumlah" class="form-control" min="1" required value="<?= e(old('jumlah', 1)) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Tanggal</label>
            <input type="date" name="tanggal" class="form-control" required max="<?= date('Y-m-d') ?>" value="<?= e(old('tanggal', date('Y-m-d'))) ?>">
          </div>
        </div>

        <div>
          <label class="form-label">Keterangan / alasan</label>
          <input name="keterangan" class="form-control" required placeholder="mis. Pembelian untuk acara Dies Natalis" value="<?= e(old('keterangan')) ?>">
        </div>
      </div>
      <div class="card-footer bg-transparent d-flex gap-2 p-3 px-4">
        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan</button>
        <a href="<?= url($back) ?>" class="btn btn-light">Batal</a>
      </div>
    </form>
  </div>
</div>

<script>
  const sb = document.getElementById('barang'), info = document.getElementById('info-barang');
  const tampil = () => info.textContent = sb.selectedOptions[0]?.dataset.info || '';
  sb.addEventListener('change', tampil); tampil();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
