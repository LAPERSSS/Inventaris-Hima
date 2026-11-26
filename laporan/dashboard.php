<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

// ---------- angka ringkasan ----------
$ringkas = $pdo->query(
    "SELECT COUNT(*) AS jenis,
            COALESCE(SUM(jumlah_total),0)    AS unit,
            COALESCE(SUM(jumlah_tersedia),0) AS tersedia,
            COALESCE(SUM(kondisi = 'rusak'),0)  AS rusak,
            COALESCE(SUM(kondisi = 'hilang'),0) AS hilang
     FROM barang"
)->fetch();

$pinjamAktif  = $pdo->query("SELECT * FROM v_peminjaman_aktif ORDER BY hari_terlambat DESC, tanggal_jatuh_tempo")->fetchAll();
$jmlTerlambat = count(array_filter($pinjamAktif, fn($p) => $p['status_terkini'] === 'terlambat'));

// barang yang perlu perhatian karena umur (barang hilang tidak dihitung)
$umur = $pdo->query(
    "SELECT * FROM v_barang_umur
     WHERE status_umur <> 'Layak' AND kondisi <> 'hilang'
     ORDER BY (umur_tahun / umur_pakai_tahun) DESC, umur_tahun DESC"
)->fetchAll();
$jmlLewat = count(array_filter($umur, fn($b) => $b['status_umur'] === 'Lewat umur pakai'));

$rekap = $pdo->query("SELECT * FROM v_rekap_kategori ORDER BY nama_kategori")->fetchAll();
$pendapatan = $pdo->query("SELECT * FROM v_pendapatan_bulanan ORDER BY bulan DESC LIMIT 6")->fetchAll();

$title    = 'Dasbor Inventaris';
$subtitle = 'Ringkasan kondisi inventaris HIMA Ilmu Komputer per hari ini.';
$active   = 'dashboard';
require __DIR__ . '/../includes/header.php';

$kartu = [
    ['Jenis barang',      $ringkas['jenis'],  '',                         'bi-box-seam',             'tone-blue'],
    ['Unit tersedia',     $ringkas['tersedia'], '/ ' . $ringkas['unit'],   'bi-check2-circle',        'tone-green'],
    ['Sedang dipinjam',   count($pinjamAktif), 'transaksi',               'bi-journal-arrow-up',     'tone-cyan'],
    ['Terlambat kembali', $jmlTerlambat,      'transaksi',                'bi-alarm',                'tone-red'],
    ['Rusak / hilang',    $ringkas['rusak'],  '/ ' . $ringkas['hilang'],  'bi-exclamation-triangle', 'tone-amber'],
    ['Lewat umur pakai',  $jmlLewat,          'jenis',                    'bi-hourglass-split',      'tone-violet'],
];
?>

<?php $nAju = jumlah_pengajuan_menunggu($pdo); if ($nAju): ?>
  <a href="<?= url('pengajuan/index.php') ?>" class="alert alert-warning d-flex align-items-center gap-2 text-decoration-none">
    <i class="bi bi-inbox fs-5"></i>
    <span><strong><?= $nAju ?> pengajuan peminjaman</strong> dari katalog publik menunggu diproses.</span>
    <span class="ms-auto fw-semibold">Lihat <i class="bi bi-arrow-right"></i></span>
  </a>
<?php endif; ?>

<!-- ============ KARTU RINGKASAN ============ -->
<div class="row g-3 mb-4">
  <?php foreach ($kartu as [$label, $nilai, $kecil, $icon, $tone]): ?>
    <div class="col-6 col-md-4 col-xxl-2">
      <div class="card h-100">
        <div class="stat">
          <div class="stat-icon <?= $tone ?>"><i class="bi <?= $icon ?>"></i></div>
          <div>
            <div class="stat-value"><?= e($nilai) ?> <?php if ($kecil): ?><small><?= e($kecil) ?></small><?php endif; ?></div>
            <div class="stat-label"><?= e($label) ?></div>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="row g-3 mb-4 align-items-start">
  <!-- ============ PEMINJAMAN AKTIF ============ -->
  <div class="col-xl-7">
    <div class="card">
      <div class="card-header">
        <span>Peminjaman belum kembali</span>
        <a href="<?= url('peminjaman/index.php') ?>">Lihat semua <i class="bi bi-arrow-right"></i></a>
      </div>
      <div class="table-responsive">
        <table class="table">
          <thead><tr><th>Peminjam</th><th>Jatuh tempo</th><th>Status</th><th class="text-end">Biaya</th></tr></thead>
          <tbody>
          <?php foreach ($pinjamAktif as $p): ?>
            <tr>
              <td>
                <div class="cell-main"><?= e($p['peminjam']) ?></div>
                <div class="cell-sub"><?= e($p['instansi']) ?></div>
              </td>
              <td>
                <?= tgl($p['tanggal_jatuh_tempo']) ?>
                <?php if ($p['hari_terlambat'] > 0): ?>
                  <div class="cell-sub text-danger">lewat <?= (int)$p['hari_terlambat'] ?> hari</div>
                <?php endif; ?>
              </td>
              <td><?= badge($p['status_terkini']) ?></td>
              <td class="text-end fw-semibold"><?= rupiah($p['total_biaya']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$pinjamAktif): ?>
            <tr><td colspan="4" class="empty"><i class="bi bi-check2-all d-block fs-3 mb-1"></i>Semua barang sudah kembali.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ============ UMUR BARANG ============ -->
  <div class="col-xl-5">
    <div class="card">
      <div class="card-header">
        <span>Perlu dipertimbangkan diganti</span>
        <span class="hint"><?= count($umur) ?> barang</span>
      </div>
      <div class="table-responsive">
        <table class="table">
          <thead><tr><th>Barang</th><th>Umur pakai</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach (array_slice($umur, 0, 6) as $b):
              $persen = min(100, round($b['umur_tahun'] / max(1, $b['umur_pakai_tahun']) * 100));
              $warna  = $b['status_umur'] === 'Lewat umur pakai' ? '#e03131' : '#f59f00'; ?>
            <tr>
              <td>
                <div class="cell-main"><?= e($b['nama_barang']) ?></div>
                <div class="cell-sub"><?= e($b['kode_inventaris']) ?></div>
              </td>
              <td>
                <div class="umur-bar mb-1"><span style="width: <?= $persen ?>%; background: <?= $warna ?>"></span></div>
                <div class="cell-sub"><?= (int)$b['umur_tahun'] ?> dari <?= (int)$b['umur_pakai_tahun'] ?> tahun</div>
              </td>
              <td><?= badge($b['status_umur']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$umur): ?>
            <tr><td colspan="3" class="empty">Semua barang masih dalam umur pakai.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php if (count($umur) > 6): ?>
        <div class="card-footer bg-transparent text-center small py-2">
          <a href="<?= url('barang/index.php') ?>" class="text-decoration-none">+<?= count($umur) - 6 ?> barang lainnya</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="row g-3 align-items-start">
<!-- ============ REKAP KATEGORI ============ -->
<div class="col-xl-8">
<div class="card">
  <div class="card-header">
    <span>Rekap per kategori</span>
    <span class="hint">jumlah jenis barang per kondisi</span>
  </div>
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>Kategori</th><th class="text-end">Jenis</th><th style="min-width:180px">Ketersediaan unit</th>
          <th class="text-center">Baik</th><th class="text-center">Rusak</th><th class="text-center">Hilang</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rekap as $r):
          $pct = $r['total_unit'] > 0 ? round($r['unit_tersedia'] / $r['total_unit'] * 100) : 0; ?>
        <tr>
          <td class="cell-main"><?= e($r['nama_kategori']) ?></td>
          <td class="text-end"><?= (int)$r['jenis_barang'] ?></td>
          <td>
            <div class="d-flex align-items-center gap-2">
              <div class="umur-bar flex-grow-1"><span style="width: <?= $pct ?>%; background: var(--brand)"></span></div>
              <span class="cell-sub text-nowrap"><?= (int)$r['unit_tersedia'] ?> / <?= (int)$r['total_unit'] ?></span>
            </div>
          </td>
          <td class="text-center"><?= $r['jenis_baik']   ? '<span class="pill pill-success">'.(int)$r['jenis_baik'].'</span>'   : '<span class="text-muted">–</span>' ?></td>
          <td class="text-center"><?= $r['jenis_rusak']  ? '<span class="pill pill-warning">'.(int)$r['jenis_rusak'].'</span>'  : '<span class="text-muted">–</span>' ?></td>
          <td class="text-center"><?= $r['jenis_hilang'] ? '<span class="pill pill-danger">'.(int)$r['jenis_hilang'].'</span>' : '<span class="text-muted">–</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</div>

<!-- ============ PENDAPATAN SEWA ============ -->
<div class="col-xl-4">
  <div class="card">
    <div class="card-header"><span>Pendapatan sewa per bulan</span></div>
    <div class="table-responsive">
      <table class="table">
        <thead><tr><th>Bulan</th><th class="text-center">Transaksi</th><th class="text-end">Pendapatan</th></tr></thead>
        <tbody>
        <?php $namaBln = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        foreach ($pendapatan as $pd): [$th, $bl] = explode('-', $pd['bulan']); ?>
          <tr>
            <td><?= $namaBln[(int)$bl] . ' ' . $th ?></td>
            <td class="text-center"><?= (int)$pd['jumlah_peminjaman'] ?></td>
            <td class="text-end fw-semibold"><?= rupiah($pd['total_pendapatan']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$pendapatan): ?><tr><td colspan="3" class="empty">Belum ada peminjaman.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
