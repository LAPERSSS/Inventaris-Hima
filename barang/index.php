<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['psdo', 'bph', 'ketua']);

// ---------- filter ----------
$q        = trim($_GET['q'] ?? '');
$kat      = (int)($_GET['kategori'] ?? 0);
$kondisi  = $_GET['kondisi'] ?? '';
$umurF    = $_GET['umur'] ?? '';

$where  = [];
$params = [];
if ($q !== '') {
    $where[]  = "(b.nama_barang LIKE ? OR b.kode_inventaris LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($kat) {
    $where[]  = "b.id_kategori = ?";
    $params[] = $kat;
}
if (in_array($kondisi, ['baik', 'rusak', 'hilang'], true)) {
    $where[]  = "b.kondisi = ?";
    $params[] = $kondisi;
}
if ($umurF === 'lewat') {
    $where[] = "TIMESTAMPDIFF(YEAR, b.tanggal_perolehan, CURDATE()) >= k.umur_pakai_tahun";
}

$sql = "SELECT b.*, k.nama_kategori, k.umur_pakai_tahun,
               TIMESTAMPDIFF(YEAR, b.tanggal_perolehan, CURDATE()) AS umur_tahun
        FROM barang b JOIN kategori k ON k.id_kategori = b.id_kategori"
     . ($where ? " WHERE " . implode(' AND ', $where) : '')
     . " ORDER BY b.kode_inventaris";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$barang = $stmt->fetchAll();

$kategori = $pdo->query("SELECT * FROM kategori ORDER BY nama_kategori")->fetchAll();

$title    = 'Data Barang';
$subtitle = 'Daftar seluruh inventaris HIMA Ilmu Komputer beserta kondisi dan ketersediaannya.';
$active   = 'barang';
$actions  = can_edit() ? '<a href="' . url('barang/form.php') . '" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Tambah barang</a>' : '';
require __DIR__ . '/../includes/header.php';
?>

<div class="card">
  <form class="toolbar" method="get">
    <input type="search" name="q" class="form-control search" placeholder="Cari nama atau kode inventaris…" value="<?= e($q) ?>">
    <select name="kategori" class="form-select" onchange="this.form.submit()">
      <option value="">Semua kategori</option>
      <?php foreach ($kategori as $k): ?>
        <option value="<?= (int)$k['id_kategori'] ?>" <?= sel($kat, $k['id_kategori']) ?>><?= e($k['nama_kategori']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="kondisi" class="form-select" onchange="this.form.submit()">
      <option value="">Semua kondisi</option>
      <option value="baik"   <?= sel($kondisi, 'baik') ?>>Baik</option>
      <option value="rusak"  <?= sel($kondisi, 'rusak') ?>>Rusak</option>
      <option value="hilang" <?= sel($kondisi, 'hilang') ?>>Hilang</option>
    </select>
    <select name="umur" class="form-select" onchange="this.form.submit()">
      <option value="">Semua umur</option>
      <option value="lewat" <?= sel($umurF, 'lewat') ?>>Lewat umur pakai</option>
    </select>
    <button class="btn btn-light"><i class="bi bi-search"></i></button>
    <?php if ($q || $kat || $kondisi || $umurF): ?>
      <a href="index.php" class="btn btn-link text-decoration-none">Reset</a>
    <?php endif; ?>
  </form>

  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr><th>Kode</th><th>Barang</th><th class="text-center">Tersedia</th><th>Kondisi</th><th>Umur</th>
            <th class="text-end">Sewa / unit</th><th class="text-end">Aksi</th></tr>
      </thead>
      <tbody>
      <?php foreach ($barang as $b):
          $lewat = $b['tanggal_perolehan'] && $b['umur_tahun'] >= $b['umur_pakai_tahun']; ?>
        <tr>
          <td class="mono"><?= e($b['kode_inventaris']) ?></td>
          <td>
            <div class="cell-main"><?= e($b['nama_barang']) ?></div>
            <div class="cell-sub"><?= e($b['nama_kategori']) ?> · <?= e($b['lokasi']) ?></div>
          </td>
          <td class="text-center">
            <span class="fw-semibold"><?= (int)$b['jumlah_tersedia'] ?></span>
            <span class="text-muted">/ <?= (int)$b['jumlah_total'] ?> <?= e($b['satuan']) ?></span>
          </td>
          <td><?= badge($b['kondisi']) ?></td>
          <td>
            <?php if ($b['tanggal_perolehan']): ?>
              <span class="<?= $lewat ? 'text-danger fw-semibold' : '' ?>"><?= (int)$b['umur_tahun'] ?> th</span>
              <span class="cell-sub">/ <?= (int)$b['umur_pakai_tahun'] ?> th</span>
            <?php else: ?><span class="text-muted">–</span><?php endif; ?>
          </td>
          <td class="text-end"><?= $b['biaya_sewa'] > 0 ? rupiah($b['biaya_sewa']) : '<span class="text-muted">–</span>' ?></td>
          <td class="text-end text-nowrap">
            <a href="detail.php?id=<?= (int)$b['id_barang'] ?>" class="btn btn-sm btn-light" title="Detail"><i class="bi bi-eye"></i></a>
            <?php if (can_edit()): ?>
              <a href="form.php?id=<?= (int)$b['id_barang'] ?>" class="btn btn-sm btn-light" title="Ubah"><i class="bi bi-pencil"></i></a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$barang): ?>
        <tr><td colspan="7" class="empty"><i class="bi bi-inbox d-block fs-3 mb-1"></i>Tidak ada barang yang cocok.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer bg-transparent small text-muted px-4"><?= count($barang) ?> barang ditampilkan</div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
