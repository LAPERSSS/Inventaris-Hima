<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['psdo', 'bph', 'ketua']);

$aktif = $pdo->query("SELECT * FROM periode_kepengurusan WHERE status_aktif = 1 ORDER BY tanggal_mulai DESC LIMIT 1")->fetch();

if (is_post()) {
    require_role(['psdo']);
    csrf_check();
    if (!$aktif) {
        flash('danger', 'Tidak ada periode yang aktif.');
        redirect('arsip/index.php');
    }
    // rekam kondisi & jumlah setiap barang saat ini; jika sudah pernah, diperbarui
    $n = $pdo->prepare(
        "INSERT INTO arsip_inventaris (id_periode, id_barang, jumlah, kondisi, tanggal_arsip)
         SELECT ?, id_barang, jumlah_total, kondisi, CURDATE() FROM barang
         ON DUPLICATE KEY UPDATE jumlah = VALUES(jumlah), kondisi = VALUES(kondisi), tanggal_arsip = VALUES(tanggal_arsip)"
    );
    $n->execute([$aktif['id_periode']]);
    flash('success', 'Arsip inventaris ' . $aktif['nama_periode'] . ' berhasil direkam (' . $pdo->query("SELECT COUNT(*) FROM barang")->fetchColumn() . ' barang).');
    redirect('arsip/detail.php?periode=' . $aktif['id_periode']);
}

$periode = $pdo->query(
    "SELECT pk.*, COALESCE(a.jml_barang,0) AS jml_barang, COALESCE(a.jml_unit,0) AS jml_unit,
            a.terakhir_arsip, COALESCE(a.jml_bermasalah,0) AS jml_bermasalah
     FROM periode_kepengurusan pk
     LEFT JOIN (
        SELECT id_periode, COUNT(*) AS jml_barang, SUM(jumlah) AS jml_unit,
               MAX(tanggal_arsip) AS terakhir_arsip, SUM(kondisi <> 'baik') AS jml_bermasalah
        FROM arsip_inventaris GROUP BY id_periode
     ) a ON a.id_periode = pk.id_periode
     ORDER BY pk.tanggal_mulai DESC"
)->fetchAll();

$title    = 'Arsip Periode';
$subtitle = 'Rekaman jumlah dan kondisi inventaris per kepengurusan, dasar serah terima ke pengurus berikutnya.';
$active   = 'arsip';
$actions  = (can_edit() && $aktif)
    ? post_button('', '<i class="bi bi-archive me-1"></i>Arsipkan ' . e($aktif['nama_periode']), 'btn btn-primary',
                  'Rekam kondisi semua barang saat ini ke arsip ' . $aktif['nama_periode'] . '? Arsip lama periode ini akan diperbarui.')
    : '';
require __DIR__ . '/../includes/header.php';
?>

<div class="alert alert-light border d-flex gap-2 small">
  <i class="bi bi-info-circle text-primary"></i>
  <div>Lakukan pengarsipan menjelang akhir kepengurusan. Pengurus periode berikutnya cukup membuka arsip ini dan
       membandingkannya dengan kondisi saat ini, tanpa harus mendata ulang dari nol.</div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table">
      <thead><tr><th>Periode</th><th>Masa jabatan</th><th>Status</th><th class="text-center">Barang diarsip</th><th class="text-center">Bermasalah</th><th>Terakhir diarsip</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($periode as $p): ?>
        <tr>
          <td class="cell-main"><?= e($p['nama_periode']) ?></td>
          <td class="text-nowrap"><?= tgl($p['tanggal_mulai']) ?> – <?= tgl($p['tanggal_selesai']) ?></td>
          <td><?= $p['status_aktif'] ? '<span class="pill pill-success">Aktif</span>' : '<span class="pill pill-secondary">Selesai</span>' ?></td>
          <td class="text-center"><?= (int)$p['jml_barang'] ?> <span class="cell-sub">(<?= (int)$p['jml_unit'] ?> unit)</span></td>
          <td class="text-center"><?= $p['jml_bermasalah'] ? '<span class="pill pill-warning">' . (int)$p['jml_bermasalah'] . '</span>' : '<span class="text-muted">–</span>' ?></td>
          <td><?= $p['terakhir_arsip'] ? tgl($p['terakhir_arsip']) : '<span class="text-muted">belum diarsip</span>' ?></td>
          <td class="text-end">
            <?php if ($p['jml_barang']): ?>
              <a href="detail.php?periode=<?= (int)$p['id_periode'] ?>" class="btn btn-sm btn-light">Lihat &amp; bandingkan</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
