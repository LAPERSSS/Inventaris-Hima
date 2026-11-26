<?php
// Salin file ini menjadi db.php lalu isi kredensialnya.
// db.php tidak ikut diunggah ke GitHub (lihat .gitignore).
define('BASE_URL', '/inventaris-hima');

$host = 'localhost';
$db   = 'inventaris_hima';
$user = 'root';
$pass = '';

$pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

date_default_timezone_set('Asia/Jakarta');
