<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['psdo', 'bph', 'ketua']);

$tab = $_GET['tab'] ?? 'menunggu';
$q   = trim($_GET['q'] ?? '');
if (!in_array($tab, ['menunggu', 'disetujui', 'ditolak', 'semua'], true)) $tab = 'menunggu';

$hitung = $pdo->query(
    "SELECT COALESCE(SUM(status = 'menunggu'),0)  AS menunggu,
            COALESCE(SUM(status = 'disetujui'),0) AS disetujui,
            COALESCE(SUM(status = 'ditolak'),0)   AS ditolak,
            COUNT(*) AS semua
     FROM pengajuan"
)->fetch();

$where = []; $params = [];
if ($tab !== 'semua') { $where[] = "a.status = ?"; $params[] = $tab; }
if ($q !== '') {
    $where[] = "(a.nama LIKE ? OR a.instansi LIKE ? OR a.kode_pengajuan LIKE ?)";
    array_push($params, "%$q%", "%$q%", "%$q%");
}

$stmt = $pdo->prepare(
    "SELECT a.*,
            (SELECT GROUP_CONCAT(CONCAT(d.jumlah, '× ', b.nama_barang) SEPARATOR ', ')
               FROM detail_pengajuan d JOIN barang b ON b.id_barang = d.id_barang
              WHERE d.id_pengajuan = a.id_pengajuan) AS daftar_barang
     FROM pengajuan a"
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . "
     ORDER BY (a.status = 'menunggu') DESC, a.tanggal_pinjam ASC, a.dibuat_pada DESC"
);
$stmt->execute($params);
$data = $stmt->fetchAll();

$title    = 'Pengajuan Peminjaman';
$subtitle = 'Permohonan yang dikirim peminjam melalui halaman katalog publik.';
$active   = 'pengajuan';
$actions  = '<a href="' . url('katalog.php') . '" target="_blank" class="btn btn-light"><i class="bi bi-box-arrow-up-right me-1"></i>Buka katalog publik</a>';
require __DIR__ . '/../includes/header.php';

$tabs = ['menunggu' => 'Menunggu', 'disetujui' => 'Disetujui', 'ditolak' => 'Ditolak', 'semua' => 'Semua'];
?>

<div class="card">
  <nav class="tabs">
    <?php foreach ($tabs as $k => $l): ?>
      <a href="?tab=<?= $k ?>" class="<?= $tab === $k ? 'active' : '' ?>"><?= $l ?><span class="count"><?= (int)$hitung[$k] ?></span></a>
    <?php endforeach; ?>
  </nav>
  <form class="toolbar" method="get">
    <input type="hidden" name="tab" value="<?= e($tab) ?>">
    <input type="search" name="q" class="form-control search" placeholder="Cari kode, nama, atau instansi…" value="<?= e($q) ?>">
    <button class="btn btn-light"><i class="bi bi-search"></i></button>
  </form>

  <div class="table-responsive">
    <table class="table">
      <thead><tr><th>Kode</th><th>Peminjam</th><th>Barang</th><th>Tanggal pinjam</th><th>Diajukan</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($data as $a): ?>
        <tr style="cursor:pointer" onclick="location.href='detail.php?id=<?= (int)$a['id_pengajuan'] ?>'">
          <td class="mono"><a href="detail.php?id=<?= (int)$a['id_pengajuan'] ?>" class="text-decoration-none"><?= e($a['kode_pengajuan']) ?></a></td>
          <td><div class="cell-main"><?= e($a['nama']) ?></div><div class="cell-sub"><?= e($a['instansi']) ?></div></td>
          <td class="small" style="max-width:260px"><?= e($a['daftar_barang']) ?></td>
          <td class="text-nowrap"><?= tgl($a['tanggal_pinjam']) ?>
            <div class="cell-sub">s.d. <?= tgl($a['tanggal_kembali_rencana']) ?></div></td>
          <td class="cell-sub text-nowrap"><?= date('d/m/Y H:i', strtotime($a['dibuat_pada'])) ?></td>
          <td><?= badge($a['status']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$data): ?><tr><td colspan="6" class="empty"><i class="bi bi-inbox d-block fs-3 mb-1"></i>Tidak ada pengajuan.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
