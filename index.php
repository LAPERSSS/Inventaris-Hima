<?php
require_once __DIR__ . '/includes/auth.php';

// pengurus yang sudah login ke dasbor, pengunjung ke katalog publik
redirect(user() ? 'laporan/dashboard.php' : 'katalog.php');
