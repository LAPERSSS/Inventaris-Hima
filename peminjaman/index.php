<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['psdo', 'bph', 'ketua']);

$tab = $_GET['tab'] ?? 'aktif';
$q   = trim($_GET['q'] ?? '');

// status terkini dihitung dari tanggal, bukan dari kolom status
$statusSql = "CASE WHEN p.tanggal_kembali IS NOT NULL THEN 'kembali'
                   WHEN p.tanggal_jatuh_tempo < CURDATE() THEN 'terlambat'
                   ELSE 'dipinjam' END";

$filter = [
    'aktif'     => "p.tanggal_kembali IS NULL",
    'terlambat' => "p.tanggal_kembali IS NULL AND p.tanggal_jatuh_tempo < CURDATE()",
    'kembali'   => "p.tanggal_kembali IS NOT NULL",
    'semua'     => "1=1",
];
if (!isset($filter[$tab])) $tab = 'aktif';

$hitung = $pdo->query(
    "SELECT SUM(tanggal_kembali IS NULL) AS aktif,
            SUM(tanggal_kembali IS NULL AND tanggal_jatuh_tempo < CURDATE()) AS terlambat,
            SUM(tanggal_kembali IS NOT NULL) AS kembali,
            COUNT(*) AS semua
     FROM peminjaman"
)->fetch();

$where  = [$filter[$tab]];
$params = [];
if ($q !== '') {
    $where[] = "(pj.nama LIKE ? OR pj.instansi LIKE ?)";
    array_push($params, "%$q%", "%$q%");
}

$stmt = $pdo->prepare(
    "SELECT p.*, pj.nama AS peminjam, pj.instansi, $statusSql AS status_terkini,
            GREATEST(DATEDIFF(COALESCE(p.tanggal_kembali, CURDATE()), p.tanggal_jatuh_tempo), 0) AS hari_telat,
            (SELECT GROUP_CONCAT(CONCAT(d.jumlah, '× ', b.nama_barang) SEPARATOR ', ')
               FROM detail_peminjaman d JOIN barang b ON b.id_barang = d.id_barang
              WHERE d.id_peminjaman = p.id_peminjaman) AS daftar_barang
     FROM peminjaman p JOIN peminjam pj ON pj.id_peminjam = p.id_peminjam
     WHERE " . implode(' AND ', $where) . "
     ORDER BY (p.tanggal_kembali IS NULL) DESC, p.tanggal_jatuh_tempo ASC, p.id_peminjaman DESC"
);
$stmt->execute($params);
$data = $stmt->fetchAll();

$title    = 'Peminjaman';
$subtitle = 'Pencatatan peminjaman dan pengembalian barang dalam satu alur.';
$active   = 'peminjaman';
$actions  = can_edit() ? '<a href="' . url('peminjaman/tambah.php') . '" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Peminjaman baru</a>' : '';
require __DIR__ . '/../includes/header.php';

$tabs = ['aktif' => 'Belum kembali', 'terlambat' => 'Terlambat', 'kembali' => 'Sudah kembali', 'semua' => 'Semua'];
?>

<div class="card">
  <nav class="tabs">
    <?php foreach ($tabs as $k => $l): ?>
      <a href="?tab=<?= $k ?>" class="<?= $tab === $k ? 'active' : '' ?>"><?= $l ?><span class="count"><?= (int)$hitung[$k] ?></span></a>
    <?php endforeach; ?>
  </nav>
  <form class="toolbar" method="get">
    <input type="hidden" name="tab" value="<?= e($tab) ?>">
    <input type="search" name="q" class="form-control search" placeholder="Cari nama peminjam atau instansi…" value="<?= e($q) ?>">
    <button class="btn btn-light"><i class="bi bi-search"></i></button>
  </form>

  <div class="table-responsive">
    <table class="table">
      <thead><tr><th>No.</th><th>Peminjam</th><th>Barang</th><th>Pinjam</th><th>Jatuh tempo</th><th>Status</th><th class="text-end">Biaya</th></tr></thead>
      <tbody>
      <?php foreach ($data as $p): ?>
        <tr style="cursor:pointer" onclick="location.href='detail.php?id=<?= (int)$p['id_peminjaman'] ?>'">
          <td class="mono"><a href="detail.php?id=<?= (int)$p['id_peminjaman'] ?>" class="text-decoration-none">PJ-<?= str_pad($p['id_peminjaman'], 4, '0', STR_PAD_LEFT) ?></a></td>
          <td><div class="cell-main"><?= e($p['peminjam']) ?></div><div class="cell-sub"><?= e($p['instansi']) ?></div></td>
          <td class="small" style="max-width:260px"><?= e($p['daftar_barang']) ?></td>
          <td class="text-nowrap"><?= tgl($p['tanggal_pinjam']) ?></td>
          <td class="text-nowrap">
            <?= tgl($p['tanggal_jatuh_tempo']) ?>
            <?php if ($p['hari_telat'] > 0): ?><div class="cell-sub text-danger">telat <?= (int)$p['hari_telat'] ?> hari</div><?php endif; ?>
          </td>
          <td><?= badge($p['status_terkini']) ?></td>
          <td class="text-end fw-semibold"><?= $p['total_biaya'] > 0 ? rupiah($p['total_biaya']) : '<span class="text-muted fw-normal">Gratis</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$data): ?><tr><td colspan="7" class="empty"><i class="bi bi-inbox d-block fs-3 mb-1"></i>Tidak ada data.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
