<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['psdo', 'bph', 'ketua']);

$id = (int)($_GET['id'] ?? 0);
$s = $pdo->prepare(
    "SELECT pe.*, u.nama AS petugas FROM pemeriksaan pe
     JOIN pengguna u ON u.id_pengguna = pe.id_pengguna WHERE pe.id_pemeriksaan = ?"
);
$s->execute([$id]);
$pe = $s->fetch();
if (!$pe) { flash('danger', 'Pemeriksaan tidak ditemukan.'); redirect('pemeriksaan/index.php'); }

$d = $pdo->prepare(
    "SELECT d.*, b.nama_barang, b.kode_inventaris, b.satuan, b.jumlah_total, k.nama_kategori
     FROM detail_pemeriksaan d
     JOIN barang b   ON b.id_barang   = d.id_barang
     JOIN kategori k ON k.id_kategori = b.id_kategori
     WHERE d.id_pemeriksaan = ?
     ORDER BY FIELD(d.kondisi, 'hilang', 'rusak', 'baik'), k.nama_kategori, b.nama_barang"
);
$d->execute([$id]);
$rows = $d->fetchAll();

$hitung = ['baik' => 0, 'rusak' => 0, 'hilang' => 0];
foreach ($rows as $r) $hitung[$r['kondisi']]++;
$adaTindakan = array_filter($rows, fn($r) => $r['kondisi'] !== 'baik' || $r['tindakan']);

$namaJenis = ['pkm_service' => 'PKM Service', 'insidental' => 'Pemeriksaan Insidental'];
$title    = $namaJenis[$pe['jenis']] . ' ' . tgl($pe['tanggal_periksa']);
$subtitle = ($pe['catatan'] ?: 'Tanpa catatan') . ' · oleh ' . $pe['petugas'];
$active   = 'pemeriksaan';
$back     = 'pemeriksaan/index.php';
$actions  = '<button onclick="window.print()" class="btn btn-light"><i class="bi bi-printer me-1"></i>Cetak laporan</button>';
require __DIR__ . '/../includes/header.php';
?>

<div class="row g-3 mb-3">
  <?php foreach ([['Diperiksa', count($rows), 'bi-clipboard-check', 'tone-blue'],
                  ['Baik', $hitung['baik'], 'bi-check2-circle', 'tone-green'],
                  ['Rusak', $hitung['rusak'], 'bi-tools', 'tone-amber'],
                  ['Hilang', $hitung['hilang'], 'bi-question-circle', 'tone-red']] as [$l, $n, $i, $t]): ?>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="stat">
      <div class="stat-icon <?= $t ?>"><i class="bi <?= $i ?>"></i></div>
      <div><div class="stat-value"><?= (int)$n ?></div><div class="stat-label"><?= $l ?></div></div>
    </div></div></div>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="card-header"><span>Hasil per barang</span><span class="hint"><?= count($adaTindakan) ?> perlu perhatian</span></div>
  <div class="table-responsive">
    <table class="table">
      <thead><tr><th>Barang</th><th>Kategori</th><th>Kondisi</th><th class="text-center">Ditemukan</th><th>Tindakan</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><a href="<?= url('barang/detail.php?id=' . $r['id_barang']) ?>" class="cell-main text-decoration-none"><?= e($r['nama_barang']) ?></a>
              <div class="cell-sub mono"><?= e($r['kode_inventaris']) ?></div></td>
          <td class="cell-sub"><?= e($r['nama_kategori']) ?></td>
          <td><?= badge($r['kondisi']) ?></td>
          <td class="text-center"><?= (int)$r['jumlah_ditemukan'] ?> <span class="cell-sub"><?= e($r['satuan']) ?></span></td>
          <td><?= $r['tindakan'] ? e($r['tindakan']) : '<span class="text-muted">–</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
