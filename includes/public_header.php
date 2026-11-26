<?php
// Kerangka halaman publik (tanpa login) untuk peminjam.
// Set $title dan $pubActive ('katalog' | 'ajukan' | 'status') sebelum include.
$title     = $title     ?? 'Katalog Inventaris';
$pubActive = $pubActive ?? '';
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> · Inventaris HIMA Ilmu Komputer UNNES</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= url('assets/style.css') ?>" rel="stylesheet">
</head>
<body class="public">

<header class="pub-nav">
  <div class="pub-wrap d-flex align-items-center gap-3">
    <a href="<?= url('katalog.php') ?>" class="d-flex align-items-center gap-2 text-decoration-none text-body">
      <span class="brand-logo"><i class="bi bi-boxes"></i></span>
      <span class="d-none d-sm-block">
        <span class="brand-name d-block">Inventaris HIMA Ilkom</span>
        <span class="brand-sub d-block">Universitas Negeri Semarang</span>
      </span>
    </a>
    <nav class="pub-links ms-auto">
      <a href="<?= url('katalog.php') ?>" class="<?= $pubActive === 'katalog' ? 'active' : '' ?>"><i class="bi bi-grid me-1"></i><span>Katalog</span></a>
      <a href="<?= url('ajukan.php') ?>"  class="<?= $pubActive === 'ajukan'  ? 'active' : '' ?>"><i class="bi bi-send me-1"></i><span>Ajukan</span></a>
      <a href="<?= url('status.php') ?>"  class="<?= $pubActive === 'status'  ? 'active' : '' ?>"><i class="bi bi-search me-1"></i><span>Cek status</span></a>
    </nav>
    <a href="<?= url('login.php') ?>" class="btn btn-sm btn-light"><i class="bi bi-person-lock"></i><span class="d-none d-md-inline ms-1">Pengurus</span></a>
  </div>
</header>

<main class="pub-wrap py-4">
<?php if ($f = flash()): ?>
  <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show">
    <?= e($f['msg']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>
