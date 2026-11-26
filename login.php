<?php
require_once __DIR__ . '/includes/auth.php';

if (user()) redirect('laporan/dashboard.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // akun hanya bisa login jika periode kepengurusannya sedang aktif
    $stmt = $pdo->prepare(
        "SELECT p.id_pengguna, p.nama, p.username, p.password_hash, p.peran,
                pk.nama_periode, pk.status_aktif
         FROM pengguna p
         JOIN periode_kepengurusan pk ON pk.id_periode = p.id_periode
         WHERE p.username = ?"
    );
    $stmt->execute([$username]);
    $akun = $stmt->fetch();

    if (!$akun || !password_verify($password, $akun['password_hash'])) {
        $error = 'Username atau password salah.';
    } elseif (!$akun['status_aktif']) {
        $error = 'Akun ini milik periode kepengurusan yang sudah berakhir.';
    } else {
        session_regenerate_id(true);
        unset($akun['password_hash']);
        $_SESSION['user'] = $akun;
        redirect('laporan/dashboard.php');
    }
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Masuk · SIM Inventaris HIMA Ilkom</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= url('assets/style.css') ?>" rel="stylesheet">
</head>
<body>
<div class="login-page">

  <!-- panel kiri (layar lebar) -->
  <section class="login-side">
    <div class="d-flex align-items-center gap-2 fw-semibold">
      <i class="bi bi-boxes fs-4"></i> SIM Inventaris
    </div>
    <div>
      <h2>Inventaris HIMA, tercatat rapi lintas periode.</h2>
      <p class="mb-4">Pendataan barang, peminjaman, pemeriksaan PKM Service, dan arsip serah terima dalam satu tempat.</p>
      <div class="login-feature"><i class="bi bi-check-circle"></i> Ketersediaan barang langsung terlihat</div>
      <div class="login-feature"><i class="bi bi-check-circle"></i> Pengingat peminjaman terlambat</div>
      <div class="login-feature"><i class="bi bi-check-circle"></i> Arsip inventaris per kepengurusan</div>
    </div>
    <small class="opacity-75">HIMA Ilmu Komputer · Universitas Negeri Semarang</small>
  </section>

  <!-- form -->
  <section class="login-main">
    <div class="login-card">
      <div class="brand-logo mb-3"><i class="bi bi-boxes"></i></div>
      <h1 class="h4 fw-bold mb-1">Masuk</h1>
      <p class="text-muted small mb-4">Gunakan akun pengurus HIMA Ilmu Komputer.</p>

      <?php if ($error): ?>
        <div class="alert alert-danger py-2 small d-flex align-items-center gap-2">
          <i class="bi bi-exclamation-circle"></i><?= e($error) ?>
        </div>
      <?php endif; ?>

      <form method="post">
        <div class="mb-3">
          <label class="form-label small fw-semibold">Username</label>
          <input type="text" name="username" class="form-control" required autofocus
                 placeholder="mis. psdo_demo" value="<?= e($_POST['username'] ?? '') ?>">
        </div>
        <div class="mb-4">
          <label class="form-label small fw-semibold">Password</label>
          <input type="password" name="password" class="form-control" required placeholder="••••••••">
        </div>
        <button class="btn btn-primary w-100 py-2">Masuk</button>
      </form>

      <a href="<?= url('katalog.php') ?>" class="btn btn-light w-100 mt-2"><i class="bi bi-grid me-1"></i>Mau pinjam barang? Lihat katalog</a>

      <div class="demo-box mt-4">
        <div class="fw-semibold mb-1"><i class="bi bi-info-circle me-1"></i>Akun uji coba</div>
        <code>psdo_demo</code> <code>bph_demo</code> <code>ketua_demo</code><br>
        <span class="text-muted">password: <code>hima2026</code></span>
      </div>
    </div>
  </section>
</div>
</body>
</html>
