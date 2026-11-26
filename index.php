<?php
require_once __DIR__ . '/includes/auth.php';

redirect(user() ? 'laporan/dashboard.php' : 'login.php');
