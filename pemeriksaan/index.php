<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['psdo', 'bph', 'ketua']);

$jenis = $_GET['jenis'] ?? '';
$where = []; $params = [];
if (in_array($jenis, ['pkm_service', 'insidental'], true)) { $where[] = "pe.jenis = ?"; $params[] = $jenis; }

$stmt = $pdo->prepare(
    "SELECT pe.*, u.nama AS petugas,
            COALESCE(h.jml_barang, 0) AS jml_barang, COALESCE(h.jml_baik, 0) AS jml_baik,
            COALESCE(h.jml_rusak, 0)  AS jml_rusak,  COALESCE(h.jml_hilang, 0) AS jml_hilang
     FROM pemeriksaan pe
     JOIN pengguna u ON u.id_pengguna = pe.id_pengguna
     LEFT JOIN (
        SELECT id_pemeriksaan, COUNT(*) AS jml_barang,
               SUM(kondisi = 'baik') AS jml_baik, SUM(kondisi = 'rusak') AS jml_rusak, SUM(kondisi = 'hilang') AS jml_hilang
        FROM detail_pemeriksaan GROUP BY id_pemeriksaan
     ) h ON h.id_pemeriksaan = pe.id_pemeriksaan"
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . "
     ORDER BY pe.tanggal_periksa DESC, pe.id_pemeriksaan DESC"
);
$stmt->execute($params);
$data = $stmt->fetchAll();

$terakhir = $pdo->query("SELECT MAX(tanggal_periksa) FROM pemeriksaan WHERE jenis = 'pkm_service'")->fetchColumn();
$hariSejak = $terakhir ? (int)((time() - strtotime($terakhir)) / 86400) : null;

$title    = 'Pemeriksaan Kondisi';
$subtitle = 'Hasil PKM Service bulanan dan pemeriksaan insidental sebelum barang dipakai.';
$active   = 'pemeriksaan';
$actions  = can_edit() ? '<a href="' . url('pemeriksaan/tambah.php') . '" class="btn btn-primary"><i class="bi bi-clipboard-plus me-1"></i>Mulai pemeriksaan</a>' : '';
require __DIR__ . '/../includes/header.php';

$namaJenis = ['pkm_service' => 'PKM Service', 'insidental' => 'Insidental'];
?>

<?php if ($hariSejak === null || $hariSejak > 30): ?>
  <div class="alert alert-warning d-flex align-items-center gap-2">
    <i class="bi bi-alarm"></i>
    <?= $hariSejak === null ? 'Belum pernah ada PKM Service yang tercatat.' : "PKM Service terakhir {$hariSejak} hari lalu. Sudah waktunya pemeriksaan bulanan." ?>
  </div>
<?php endif; ?>

<div class="card">
  <form class="toolbar" method="get">
    <select name="jenis" class="form-select" onchange="this.form.submit()">
      <option value="">Semua jenis</option>
      <option value="pkm_service" <?= sel($jenis, 'pkm_service') ?>>PKM Service</option>
      <option value="insidental"  <?= sel($jenis, 'insidental') ?>>Insidental</option>
    </select>
  </form>
  <div class="table-responsive">
    <table class="table">
      <thead><tr><th>Tanggal</th><th>Jenis</th><th>Catatan</th><th class="text-center">Diperiksa</th><th>Hasil</th><th>Petugas</th></tr></thead>
      <tbody>
      <?php foreach ($data as $r): ?>
        <tr style="cursor:pointer" onclick="location.href='detail.php?id=<?= (int)$r['id_pemeriksaan'] ?>'">
          <td class="text-nowrap"><a href="detail.php?id=<?= (int)$r['id_pemeriksaan'] ?>" class="fw-semibold text-decoration-none"><?= tgl($r['tanggal_periksa']) ?></a></td>
          <td><span class="pill <?= $r['jenis'] === 'pkm_service' ? 'pill-primary' : 'pill-secondary' ?>"><?= e($namaJenis[$r['jenis']]) ?></span></td>
          <td class="small"><?= e($r['catatan']) ?></td>
          <td class="text-center"><?= (int)$r['jml_barang'] ?></td>
          <td class="text-nowrap">
            <?php if ($r['jml_baik'])   echo '<span class="pill pill-success me-1">' . (int)$r['jml_baik'] . ' baik</span>'; ?>
            <?php if ($r['jml_rusak'])  echo '<span class="pill pill-warning me-1">' . (int)$r['jml_rusak'] . ' rusak</span>'; ?>
            <?php if ($r['jml_hilang']) echo '<span class="pill pill-danger">' . (int)$r['jml_hilang'] . ' hilang</span>'; ?>
          </td>
          <td class="cell-sub"><?= e($r['petugas']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$data): ?><tr><td colspan="6" class="empty"><i class="bi bi-clipboard d-block fs-3 mb-1"></i>Belum ada pemeriksaan.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
