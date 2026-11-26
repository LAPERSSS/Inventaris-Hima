<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['psdo', 'bph', 'ketua']);

$id = (int)($_GET['id'] ?? 0);
$s = $pdo->prepare(
    "SELECT b.*, k.nama_kategori, k.umur_pakai_tahun,
            TIMESTAMPDIFF(YEAR, b.tanggal_perolehan, CURDATE()) AS umur_tahun
     FROM barang b JOIN kategori k ON k.id_kategori = b.id_kategori
     WHERE b.id_barang = ?"
);
$s->execute([$id]);
$b = $s->fetch();
if (!$b) { flash('danger', 'Barang tidak ditemukan.'); redirect('barang/index.php'); }

$dipinjam = jumlah_dipinjam($pdo, $id);

$q = function ($sql) use ($pdo, $id) {
    $st = $pdo->prepare($sql);
    $st->execute([$id]);
    return $st->fetchAll();
};

$transaksi = $q("SELECT t.*, p.nama AS petugas FROM transaksi_barang t
                 JOIN pengguna p ON p.id_pengguna = t.id_pengguna
                 WHERE t.id_barang = ? ORDER BY t.tanggal DESC, t.id_transaksi DESC");

$pinjam = $q("SELECT pm.id_peminjaman, pm.tanggal_pinjam, pm.tanggal_jatuh_tempo, pm.tanggal_kembali,
                     pj.nama AS peminjam, pj.instansi, d.jumlah, d.kondisi_kembali
              FROM detail_peminjaman d
              JOIN peminjaman pm ON pm.id_peminjaman = d.id_peminjaman
              JOIN peminjam pj   ON pj.id_peminjam   = pm.id_peminjam
              WHERE d.id_barang = ? ORDER BY pm.tanggal_pinjam DESC");

$periksa = $q("SELECT pe.id_pemeriksaan, pe.tanggal_periksa, pe.jenis, d.kondisi, d.jumlah_ditemukan, d.tindakan
               FROM detail_pemeriksaan d JOIN pemeriksaan pe ON pe.id_pemeriksaan = d.id_pemeriksaan
               WHERE d.id_barang = ? ORDER BY pe.tanggal_periksa DESC");

$arsip = $q("SELECT a.*, pk.nama_periode FROM arsip_inventaris a
             JOIN periode_kepengurusan pk ON pk.id_periode = a.id_periode
             WHERE a.id_barang = ? ORDER BY pk.tanggal_mulai DESC");

$lewat   = $b['tanggal_perolehan'] && $b['umur_tahun'] >= $b['umur_pakai_tahun'];
$hampir  = !$lewat && $b['tanggal_perolehan'] && $b['umur_tahun'] >= $b['umur_pakai_tahun'] - 1;

$title    = $b['nama_barang'];
$subtitle = $b['kode_inventaris'] . ' · ' . $b['nama_kategori'];
$active   = 'barang';
$back     = 'barang/index.php';
$actions  = can_edit()
    ? '<a href="' . url('transaksi/tambah.php?barang=' . $id) . '" class="btn btn-light"><i class="bi bi-arrow-left-right me-1"></i>Catat masuk/keluar</a>'
    . '<a href="' . url('barang/form.php?id=' . $id) . '" class="btn btn-primary"><i class="bi bi-pencil me-1"></i>Ubah</a>'
    : '';
require __DIR__ . '/../includes/header.php';

$jenisPeriksa = ['pkm_service' => 'PKM Service', 'insidental' => 'Insidental'];
?>

<div class="row g-3 mb-3">
  <?php foreach ([
      ['Jumlah total', $b['jumlah_total'] . ' ' . $b['satuan'], 'bi-box-seam', 'tone-blue'],
      ['Tersedia',     $b['jumlah_tersedia'],                  'bi-check2-circle', 'tone-green'],
      ['Sedang dipinjam', $dipinjam,                           'bi-journal-arrow-up', 'tone-cyan'],
      ['Umur barang',  $b['tanggal_perolehan'] ? $b['umur_tahun'] . ' / ' . $b['umur_pakai_tahun'] . ' th' : '–', 'bi-hourglass-split', $lewat ? 'tone-red' : ($hampir ? 'tone-amber' : 'tone-violet')],
  ] as [$l, $n, $i, $t]): ?>
    <div class="col-6 col-lg-3">
      <div class="card h-100"><div class="stat">
        <div class="stat-icon <?= $t ?>"><i class="bi <?= $i ?>"></i></div>
        <div><div class="stat-value"><?= e($n) ?></div><div class="stat-label"><?= e($l) ?></div></div>
      </div></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="row g-3 align-items-start">
  <div class="col-xl-4">
    <div class="card">
      <div class="card-header"><span>Informasi barang</span><?= badge($b['kondisi']) ?></div>
      <div class="card-body">
        <dl class="info-list">
          <dt>Kode</dt><dd class="mono"><?= e($b['kode_inventaris']) ?></dd>
          <dt>Kategori</dt><dd><?= e($b['nama_kategori']) ?></dd>
          <dt>Lokasi</dt><dd><?= e($b['lokasi']) ?></dd>
          <dt>Diperoleh</dt><dd><?= tgl($b['tanggal_perolehan']) ?></dd>
          <dt>Status umur</dt><dd><?= badge($lewat ? 'Lewat umur pakai' : ($hampir ? 'Mendekati umur pakai' : 'Layak')) ?></dd>
          <dt>Biaya sewa</dt><dd><?= $b['biaya_sewa'] > 0 ? rupiah($b['biaya_sewa']) . ' / ' . e($b['satuan']) : 'Tidak disewakan' ?></dd>
          <dt>Keterangan</dt><dd><?= $b['keterangan'] ? e($b['keterangan']) : '<span class="text-muted">–</span>' ?></dd>
        </dl>
      </div>
    </div>

    <div class="card mt-3">
      <div class="card-header"><span>Arsip antarperiode</span></div>
      <div class="table-responsive">
        <table class="table">
          <thead><tr><th>Periode</th><th class="text-center">Jumlah</th><th>Kondisi</th></tr></thead>
          <tbody>
          <?php foreach ($arsip as $a): ?>
            <tr><td><?= e($a['nama_periode']) ?></td><td class="text-center"><?= (int)$a['jumlah'] ?></td><td><?= badge($a['kondisi']) ?></td></tr>
          <?php endforeach; ?>
          <tr class="table-light"><td class="fw-semibold">Saat ini</td><td class="text-center fw-semibold"><?= (int)$b['jumlah_total'] ?></td><td><?= badge($b['kondisi']) ?></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-xl-8">
    <div class="card mb-3">
      <div class="card-header"><span>Riwayat barang masuk &amp; keluar</span><span class="hint"><?= count($transaksi) ?> catatan</span></div>
      <div class="table-responsive">
        <table class="table">
          <thead><tr><th>Tanggal</th><th>Jenis</th><th class="text-center">Jumlah</th><th>Keterangan</th><th>Petugas</th></tr></thead>
          <tbody>
          <?php foreach ($transaksi as $t): ?>
            <tr>
              <td class="text-nowrap"><?= tgl($t['tanggal']) ?></td>
              <td><?= badge($t['jenis']) ?></td>
              <td class="text-center fw-semibold <?= $t['jenis'] === 'masuk' ? 'text-success' : 'text-danger' ?>"><?= $t['jenis'] === 'masuk' ? '+' : '−' ?><?= (int)$t['jumlah'] ?></td>
              <td><?= e($t['keterangan']) ?></td>
              <td class="cell-sub"><?= e($t['petugas']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$transaksi): ?><tr><td colspan="5" class="empty">Belum ada catatan.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header"><span>Riwayat peminjaman</span><span class="hint"><?= count($pinjam) ?> kali</span></div>
      <div class="table-responsive">
        <table class="table">
          <thead><tr><th>Peminjam</th><th>Tanggal pinjam</th><th class="text-center">Jumlah</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($pinjam as $p): ?>
            <tr>
              <td><a href="<?= url('peminjaman/detail.php?id=' . $p['id_peminjaman']) ?>" class="cell-main text-decoration-none"><?= e($p['peminjam']) ?></a>
                  <div class="cell-sub"><?= e($p['instansi']) ?></div></td>
              <td><?= tgl($p['tanggal_pinjam']) ?></td>
              <td class="text-center"><?= (int)$p['jumlah'] ?></td>
              <td>
                <?php if ($p['tanggal_kembali']): ?>
                  <?= badge('kembali') ?> <span class="cell-sub">kondisi <?= e($p['kondisi_kembali'] ?? '-') ?></span>
                <?php else: ?>
                  <?= badge($p['tanggal_jatuh_tempo'] < date('Y-m-d') ? 'terlambat' : 'dipinjam') ?>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$pinjam): ?><tr><td colspan="4" class="empty">Belum pernah dipinjam.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><span>Riwayat pemeriksaan kondisi</span><span class="hint"><?= count($periksa) ?> kali</span></div>
      <div class="table-responsive">
        <table class="table">
          <thead><tr><th>Tanggal</th><th>Jenis</th><th>Kondisi</th><th class="text-center">Ditemukan</th><th>Tindakan</th></tr></thead>
          <tbody>
          <?php foreach ($periksa as $p): ?>
            <tr>
              <td><a href="<?= url('pemeriksaan/detail.php?id=' . $p['id_pemeriksaan']) ?>" class="text-decoration-none"><?= tgl($p['tanggal_periksa']) ?></a></td>
              <td><?= e($jenisPeriksa[$p['jenis']] ?? $p['jenis']) ?></td>
              <td><?= badge($p['kondisi']) ?></td>
              <td class="text-center"><?= (int)$p['jumlah_ditemukan'] ?></td>
              <td><?= $p['tindakan'] ? e($p['tindakan']) : '<span class="text-muted">–</span>' ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$periksa): ?><tr><td colspan="5" class="empty">Belum pernah diperiksa.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
