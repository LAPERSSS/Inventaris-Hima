<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['psdo', 'bph', 'ketua']);

$jenis = $_GET['jenis'] ?? '';
$bulan = $_GET['bulan'] ?? '';
$q     = trim($_GET['q'] ?? '');

$where = []; $params = [];
if (in_array($jenis, ['masuk', 'keluar'], true)) { $where[] = "t.jenis = ?"; $params[] = $jenis; }
if (preg_match('/^\d{4}-\d{2}$/', $bulan))       { $where[] = "DATE_FORMAT(t.tanggal, '%Y-%m') = ?"; $params[] = $bulan; }
if ($q !== '') {
    $where[] = "(b.nama_barang LIKE ? OR b.kode_inventaris LIKE ? OR t.keterangan LIKE ?)";
    array_push($params, "%$q%", "%$q%", "%$q%");
}

$stmt = $pdo->prepare(
    "SELECT t.*, b.nama_barang, b.kode_inventaris, b.satuan, p.nama AS petugas
     FROM transaksi_barang t
     JOIN barang b   ON b.id_barang   = t.id_barang
     JOIN pengguna p ON p.id_pengguna = t.id_pengguna"
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
    . " ORDER BY t.tanggal DESC, t.id_transaksi DESC"
);
$stmt->execute($params);
$data = $stmt->fetchAll();

$totMasuk  = array_sum(array_map(fn($t) => $t['jenis'] === 'masuk'  ? $t['jumlah'] : 0, $data));
$totKeluar = array_sum(array_map(fn($t) => $t['jenis'] === 'keluar' ? $t['jumlah'] : 0, $data));

$title    = 'Barang Masuk & Keluar';
$subtitle = 'Riwayat perubahan jumlah inventaris: pembelian, hibah, barang rusak permanen, atau dihapus.';
$active   = 'transaksi';
$actions  = can_edit() ? '<a href="' . url('transaksi/tambah.php') . '" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Catat transaksi</a>' : '';
require __DIR__ . '/../includes/header.php';
?>

<div class="row g-3 mb-3">
  <div class="col-6 col-md-3"><div class="card"><div class="stat">
    <div class="stat-icon tone-green"><i class="bi bi-box-arrow-in-down"></i></div>
    <div><div class="stat-value">+<?= (int)$totMasuk ?></div><div class="stat-label">Unit masuk</div></div>
  </div></div></div>
  <div class="col-6 col-md-3"><div class="card"><div class="stat">
    <div class="stat-icon tone-red"><i class="bi bi-box-arrow-up"></i></div>
    <div><div class="stat-value">−<?= (int)$totKeluar ?></div><div class="stat-label">Unit keluar</div></div>
  </div></div></div>
</div>

<div class="card">
  <form class="toolbar" method="get">
    <input type="search" name="q" class="form-control search" placeholder="Cari barang atau keterangan…" value="<?= e($q) ?>">
    <select name="jenis" class="form-select" onchange="this.form.submit()">
      <option value="">Masuk &amp; keluar</option>
      <option value="masuk"  <?= sel($jenis, 'masuk') ?>>Masuk saja</option>
      <option value="keluar" <?= sel($jenis, 'keluar') ?>>Keluar saja</option>
    </select>
    <input type="month" name="bulan" class="form-control" value="<?= e($bulan) ?>" onchange="this.form.submit()">
    <button class="btn btn-light"><i class="bi bi-search"></i></button>
    <?php if ($jenis || $bulan || $q): ?><a href="index.php" class="btn btn-link text-decoration-none">Reset</a><?php endif; ?>
  </form>
  <div class="table-responsive">
    <table class="table">
      <thead><tr><th>Tanggal</th><th>Barang</th><th>Jenis</th><th class="text-center">Jumlah</th><th>Keterangan</th><th>Petugas</th></tr></thead>
      <tbody>
      <?php foreach ($data as $t): ?>
        <tr>
          <td class="text-nowrap"><?= tgl($t['tanggal']) ?></td>
          <td>
            <a href="<?= url('barang/detail.php?id=' . $t['id_barang']) ?>" class="cell-main text-decoration-none"><?= e($t['nama_barang']) ?></a>
            <div class="cell-sub mono"><?= e($t['kode_inventaris']) ?></div>
          </td>
          <td><?= badge($t['jenis']) ?></td>
          <td class="text-center fw-semibold <?= $t['jenis'] === 'masuk' ? 'text-success' : 'text-danger' ?>">
            <?= $t['jenis'] === 'masuk' ? '+' : '−' ?><?= (int)$t['jumlah'] ?> <span class="cell-sub"><?= e($t['satuan']) ?></span>
          </td>
          <td><?= e($t['keterangan']) ?></td>
          <td class="cell-sub"><?= e($t['petugas']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$data): ?><tr><td colspan="6" class="empty"><i class="bi bi-inbox d-block fs-3 mb-1"></i>Belum ada transaksi.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
