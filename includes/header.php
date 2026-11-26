<?php
// Sebelum include file ini, set:
//   $title    = judul halaman
//   $subtitle = (opsional) keterangan singkat di bawah judul
//   $active   = kunci menu yang aktif (lihat $menu di bawah)
$title    = $title    ?? 'SIM Inventaris';
$subtitle = $subtitle ?? '';
$active   = $active   ?? '';
$u = user();

// [kunci, label, path, ikon, siapa yang lihat, grup]
$menu = [
    ['dashboard',   'Dasbor',           'laporan/dashboard.php', 'bi-grid-1x2',         ['psdo','bph','ketua'], 'Ringkasan'],
    ['barang',      'Data Barang',      'barang/index.php',      'bi-box-seam',         ['psdo','bph','ketua'], 'Inventaris'],
    ['kategori',    'Kategori',         'kategori/index.php',    'bi-tags',             ['psdo'],               'Inventaris'],
    ['transaksi',   'Masuk & Keluar',   'transaksi/index.php',   'bi-arrow-left-right', ['psdo','bph','ketua'], 'Inventaris'],
    ['pengajuan',   'Pengajuan',        'pengajuan/index.php',   'bi-inbox',            ['psdo','bph','ketua'], 'Layanan'],
    ['peminjaman',  'Peminjaman',       'peminjaman/index.php',  'bi-journal-arrow-up', ['psdo','bph','ketua'], 'Layanan'],
    ['pemeriksaan', 'Pemeriksaan',      'pemeriksaan/index.php', 'bi-clipboard-check',  ['psdo','bph','ketua'], 'Layanan'],
    ['arsip',       'Arsip Periode',    'arsip/index.php',       'bi-archive',          ['psdo','bph','ketua'], 'Layanan'],
];

$namaBulan = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$hari  = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$hariIni = $hari[date('w')] . ', ' . date('j') . ' ' . $namaBulan[(int)date('n')] . ' ' . date('Y');
$inisial = strtoupper(mb_substr($u['nama'], 0, 1));
$jmlMenunggu = jumlah_pengajuan_menunggu($pdo);
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> · SIM Inventaris HIMA Ilkom</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= url('assets/style.css') ?>" rel="stylesheet">
</head>
<body>

<div class="app">
  <!-- ============ SIDEBAR ============ -->
  <aside class="offcanvas-md offcanvas-start sidebar" id="sidebar" tabindex="-1">
    <div class="brand">
      <div class="brand-logo"><i class="bi bi-boxes"></i></div>
      <div>
        <div class="brand-name">SIM Inventaris</div>
        <div class="brand-sub">HIMA Ilkom UNNES</div>
      </div>
      <button type="button" class="btn-close d-md-none ms-auto" data-bs-dismiss="offcanvas" data-bs-target="#sidebar"></button>
    </div>

    <nav class="menu">
      <?php $grupSebelum = null;
      foreach ($menu as [$key, $label, $path, $icon, $roles, $grup]):
          if (!in_array($u['peran'], $roles, true)) continue;
          if ($grup !== $grupSebelum): $grupSebelum = $grup; ?>
            <div class="menu-group"><?= e($grup) ?></div>
          <?php endif; ?>
        <a class="menu-link <?= $active === $key ? 'active' : '' ?>" href="<?= url($path) ?>">
          <i class="bi <?= $icon ?>"></i><span><?= e($label) ?></span>
          <?php if ($key === 'pengajuan' && $jmlMenunggu): ?><span class="menu-badge"><?= $jmlMenunggu ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="sidebar-foot">
      <span class="periode-chip"><i class="bi bi-calendar3"></i><?= e($u['nama_periode']) ?></span>
    </div>
  </aside>

  <!-- ============ AREA KONTEN ============ -->
  <div class="main">
    <header class="topbar">
      <button class="btn btn-icon d-md-none" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-label="Menu">
        <i class="bi bi-list"></i>
      </button>
      <div class="topbar-date d-none d-sm-block"><i class="bi bi-calendar-event me-1"></i><?= e($hariIni) ?></div>

      <div class="dropdown ms-auto">
        <button class="user-btn" data-bs-toggle="dropdown">
          <span class="avatar"><?= e($inisial) ?></span>
          <span class="d-none d-sm-block text-start">
            <span class="user-name"><?= e($u['nama']) ?></span>
            <span class="user-role"><?= e(nama_peran($u['peran'])) ?></span>
          </span>
          <i class="bi bi-chevron-down small text-muted"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
          <li><span class="dropdown-item-text small text-muted">Masuk sebagai <strong><?= e($u['username']) ?></strong></span></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="<?= url('logout.php') ?>"><i class="bi bi-box-arrow-right me-2"></i>Keluar</a></li>
        </ul>
      </div>
    </header>

    <main class="content">
      <div class="page-head">
        <div>
          <?php if (!empty($back)): ?>
            <a href="<?= url($back) ?>" class="back-link"><i class="bi bi-arrow-left"></i> Kembali</a>
          <?php endif; ?>
          <h1 class="page-title"><?= e($title) ?></h1>
          <?php if ($subtitle): ?><p class="page-sub"><?= e($subtitle) ?></p><?php endif; ?>
        </div>
        <?php if (!empty($actions)): ?><div class="page-actions"><?= $actions ?></div><?php endif; ?>
      </div>

      <?php if ($f = flash()): ?>
        <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show">
          <?= e($f['msg']) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>
