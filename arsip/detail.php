<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['psdo', 'bph', 'ketua']);

$id = (int)($_GET['periode'] ?? 0);
$s = $pdo->prepare("SELECT * FROM periode_kepengurusan WHERE id_periode = ?");
$s->execute([$id]);
$per = $s->fetch();
if (!$per) { flash('danger', 'Periode tidak ditemukan.'); redirect('arsip/index.php'); }

// barang yang diarsip, dibandingkan dengan kondisi sekarang
$s = $pdo->prepare(
    "SELECT b.id_barang, b.kode_inventaris, b.nama_barang, b.satuan, k.nama_kategori,
            a.jumlah AS jumlah_arsip, a.kondisi AS kondisi_arsip, a.tanggal_arsip,
            b.jumlah_total AS jumlah_kini, b.kondisi AS kondisi_kini
     FROM arsip_inventaris a
     JOIN barang b   ON b.id_barang   = a.id_barang
     JOIN kategori k ON k.id_kategori = b.id_kategori
     WHERE a.id_periode = ?
     ORDER BY k.nama_kategori, b.nama_barang"
);
$s->execute([$id]);
$rows = $s->fetchAll();

// barang yang baru ada setelah arsip dibuat
$s = $pdo->prepare(
    "SELECT b.id_barang, b.kode_inventaris, b.nama_barang, b.jumlah_total, b.satuan, b.kondisi, b.tanggal_perolehan
     FROM barang b
     WHERE b.id_barang NOT IN (SELECT id_barang FROM arsip_inventaris WHERE id_periode = ?)
     ORDER BY b.tanggal_perolehan DESC"
);
$s->execute([$id]);
$baru = $s->fetchAll();

$berubah = array_filter($rows, fn($r) => $r['jumlah_arsip'] != $r['jumlah_kini'] || $r['kondisi_arsip'] !== $r['kondisi_kini']);
$tglArsip = $rows ? max(array_column($rows, 'tanggal_arsip') ?: [null]) : null;

$title    = 'Arsip ' . $per['nama_periode'];
$subtitle = 'Direkam ' . tgl($tglArsip) . ' · dibandingkan dengan kondisi per hari ini';
$active   = 'arsip';
$back     = 'arsip/index.php';
$actions  = '<button onclick="window.print()" class="btn btn-light"><i class="bi bi-printer me-1"></i>Cetak laporan serah terima</button>';
require __DIR__ . '/../includes/header.php';
?>

<div class="row g-3 mb-3">
  <?php foreach ([['Barang diarsip', count($rows), 'bi-archive', 'tone-blue'],
                  ['Berubah sejak arsip', count($berubah), 'bi-arrow-repeat', count($berubah) ? 'tone-amber' : 'tone-green'],
                  ['Barang baru', count($baru), 'bi-plus-square', 'tone-cyan']] as [$l, $n, $i, $t]): ?>
    <div class="col-md-4"><div class="card h-100"><div class="stat">
      <div class="stat-icon <?= $t ?>"><i class="bi <?= $i ?>"></i></div>
      <div><div class="stat-value"><?= (int)$n ?></div><div class="stat-label"><?= $l ?></div></div>
    </div></div></div>
  <?php endforeach; ?>
</div>

<div class="card mb-3">
  <div class="card-header"><span>Perbandingan arsip dengan kondisi sekarang</span></div>
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr><th>Barang</th><th class="text-center">Jumlah saat arsip</th><th>Kondisi saat arsip</th>
            <th class="text-center">Jumlah kini</th><th>Kondisi kini</th><th>Perubahan</th></tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r):
          $selisih = $r['jumlah_kini'] - $r['jumlah_arsip'];
          $ubahKondisi = $r['kondisi_arsip'] !== $r['kondisi_kini']; ?>
        <tr class="<?= ($selisih || $ubahKondisi) ? 'table-warning-subtle' : '' ?>">
          <td><a href="<?= url('barang/detail.php?id=' . $r['id_barang']) ?>" class="cell-main text-decoration-none"><?= e($r['nama_barang']) ?></a>
              <div class="cell-sub"><span class="mono"><?= e($r['kode_inventaris']) ?></span> · <?= e($r['nama_kategori']) ?></div></td>
          <td class="text-center"><?= (int)$r['jumlah_arsip'] ?></td>
          <td><?= badge($r['kondisi_arsip']) ?></td>
          <td class="text-center fw-semibold"><?= (int)$r['jumlah_kini'] ?></td>
          <td><?= badge($r['kondisi_kini']) ?></td>
          <td class="small">
            <?php if (!$selisih && !$ubahKondisi): ?><span class="text-muted">Tetap</span><?php endif; ?>
            <?php if ($selisih): ?><div class="<?= $selisih < 0 ? 'text-danger' : 'text-success' ?> fw-semibold"><?= $selisih > 0 ? '+' : '' ?><?= $selisih ?> <?= e($r['satuan']) ?></div><?php endif; ?>
            <?php if ($ubahKondisi): ?><div><?= e($r['kondisi_arsip']) ?> → <strong><?= e($r['kondisi_kini']) ?></strong></div><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="6" class="empty">Periode ini belum diarsip.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($baru): ?>
<div class="card">
  <div class="card-header"><span>Barang yang belum ada di arsip ini</span><span class="hint">diperoleh/didata setelah arsip dibuat</span></div>
  <div class="table-responsive">
    <table class="table">
      <thead><tr><th>Barang</th><th>Diperoleh</th><th class="text-center">Jumlah</th><th>Kondisi</th></tr></thead>
      <tbody>
      <?php foreach ($baru as $b): ?>
        <tr>
          <td><a href="<?= url('barang/detail.php?id=' . $b['id_barang']) ?>" class="cell-main text-decoration-none"><?= e($b['nama_barang']) ?></a>
              <div class="cell-sub mono"><?= e($b['kode_inventaris']) ?></div></td>
          <td><?= tgl($b['tanggal_perolehan']) ?></td>
          <td class="text-center"><?= (int)$b['jumlah_total'] ?> <?= e($b['satuan']) ?></td>
          <td><?= badge($b['kondisi']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
