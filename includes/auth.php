<?php
// Dipanggil di baris pertama setiap halaman.
// Berisi: sesi login, cek hak akses, dan fungsi bantu.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';

// nomor WhatsApp CP Biro PSDO untuk halaman publik (format 62xxxxxxxxxx)
// boleh juga didefinisikan ulang di config/db.php
if (!defined('CP_WHATSAPP')) define('CP_WHATSAPP', '6281234567890');
if (!defined('CP_NAMA'))     define('CP_NAMA', 'CP Biro PSDO');

// ---------- fungsi bantu tampilan ----------
function e($s)        { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function url($path='') { return BASE_URL . '/' . ltrim($path, '/'); }
function rupiah($n)   { return 'Rp ' . number_format((float)$n, 0, ',', '.'); }
function tgl($d)      { return $d ? date('d/m/Y', strtotime($d)) : '-'; }

function redirect($path) {
    header('Location: ' . url($path));
    exit;
}

// ---------- login & hak akses (Tabel 4.1) ----------
function user() { return $_SESSION['user'] ?? null; }

function require_login() {
    if (!user()) redirect('login.php');
}

// contoh: require_role(['psdo']) untuk halaman tambah/edit/hapus
function require_role(array $roles) {
    require_login();
    if (!in_array(user()['peran'], $roles, true)) {
        http_response_code(403);
        flash('danger', 'Anda tidak punya hak akses ke halaman tersebut.');
        redirect('laporan/dashboard.php');
    }
}

// hanya Biro PSDO yang boleh mengubah data
function can_edit() { return user() && user()['peran'] === 'psdo'; }

function nama_peran($p) {
    return ['psdo' => 'Biro PSDO', 'bph' => 'BPH', 'ketua' => 'Ketua HIMA'][$p] ?? $p;
}

// ---------- pesan sukses/gagal satu kali tampil ----------
function flash($type = null, $msg = null) {
    if ($type !== null) {
        $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

// ---------- formulir ----------
function is_post() { return $_SERVER['REQUEST_METHOD'] === 'POST'; }

// token CSRF: mencegah formulir dikirim dari situs lain
function csrf_token() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field() { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
function csrf_check() {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Sesi formulir kedaluwarsa. Kembali dan muat ulang halaman.');
    }
}

// isi ulang nilai input setelah gagal validasi
function old($key, $default = '') { return $_POST[$key] ?? $default; }
function sel($a, $b) { return (string)$a === (string)$b ? 'selected' : ''; }

function show_errors(array $errors) {
    if (!$errors) return '';
    $li = implode('', array_map(fn($x) => '<li>' . e($x) . '</li>', $errors));
    return '<div class="alert alert-danger"><div class="fw-semibold mb-1">Periksa kembali isian:</div><ul class="mb-0 ps-3">' . $li . '</ul></div>';
}

// tombol hapus/aksi kecil yang dikirim lewat POST + konfirmasi
function post_button($action, $label, $class = 'btn btn-sm btn-outline-danger', $confirm = 'Yakin?', array $fields = []) {
    $h = '<form method="post" action="' . e($action) . '" class="d-inline" onsubmit="return confirm(\'' . e($confirm) . '\')">' . csrf_field();
    foreach ($fields as $k => $v) $h .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    return $h . '<button class="' . e($class) . '">' . $label . '</button></form>';
}

// ---------- aturan stok (dipakai semua modul) ----------
// jumlah unit yang sedang dipinjam (belum kembali)
function jumlah_dipinjam(PDO $pdo, int $id_barang) {
    $s = $pdo->prepare(
        "SELECT COALESCE(SUM(d.jumlah),0) FROM detail_peminjaman d
         JOIN peminjaman p ON p.id_peminjaman = d.id_peminjaman
         WHERE p.tanggal_kembali IS NULL AND d.id_barang = ?"
    );
    $s->execute([$id_barang]);
    return (int)$s->fetchColumn();
}

// hitung ulang jumlah_tersedia:
//   kondisi baik  -> total - sedang dipinjam
//   rusak/hilang  -> 0 (tidak bisa dipinjam)
function sync_tersedia(PDO $pdo, int $id_barang) {
    $dipinjam = jumlah_dipinjam($pdo, $id_barang);
    $s = $pdo->prepare(
        "UPDATE barang
         SET jumlah_tersedia = IF(kondisi = 'baik', GREATEST(jumlah_total - ?, 0), 0)
         WHERE id_barang = ?"
    );
    $s->execute([$dipinjam, $id_barang]);
}

// tambah/kurangi jumlah_total, lalu hitung ulang tersedia
// (tersedia ikut dipotong di statement yang sama agar CHECK tersedia <= total tidak gagal)
function ubah_total(PDO $pdo, int $id_barang, int $delta) {
    $pdo->prepare(
        "UPDATE barang
         SET jumlah_total = jumlah_total + ?,
             jumlah_tersedia = LEAST(jumlah_tersedia, jumlah_total)
         WHERE id_barang = ?"
    )->execute([$delta, $id_barang]);
    sync_tersedia($pdo, $id_barang);
}

function catat_transaksi(PDO $pdo, int $id_barang, string $jenis, int $jumlah, string $tanggal, string $ket) {
    $s = $pdo->prepare(
        "INSERT INTO transaksi_barang (id_barang, id_pengguna, jenis, jumlah, tanggal, keterangan)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $s->execute([$id_barang, user()['id_pengguna'], $jenis, $jumlah, $tanggal, $ket]);
}

function valid_date($d) {
    $t = DateTime::createFromFormat('Y-m-d', (string)$d);
    return $t && $t->format('Y-m-d') === $d;
}

// ---------- pengajuan peminjaman (halaman publik) ----------
function buat_kode_pengajuan(PDO $pdo) {
    $huruf = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';   // tanpa O/0/I/1 agar tidak tertukar
    do {
        $kode = 'AJ-';
        for ($i = 0; $i < 6; $i++) $kode .= $huruf[random_int(0, strlen($huruf) - 1)];
        $s = $pdo->prepare("SELECT COUNT(*) FROM pengajuan WHERE kode_pengajuan = ?");
        $s->execute([$kode]);
    } while ($s->fetchColumn() > 0);
    return $kode;
}

function hanya_angka($s) { return preg_replace('/\D/', '', (string)$s); }

function wa_link($pesan) {
    return 'https://wa.me/' . CP_WHATSAPP . '?text=' . rawurlencode($pesan);
}

function jumlah_pengajuan_menunggu(PDO $pdo) {
    try {
        return (int)$pdo->query("SELECT COUNT(*) FROM pengajuan WHERE status = 'menunggu'")->fetchColumn();
    } catch (PDOException $e) {
        return 0;   // tabel belum dibuat (migrasi belum dijalankan)
    }
}

// ---------- badge warna untuk kondisi/status ----------
function badge($val) {
    $warna = [
        'baik' => 'success', 'rusak' => 'warning', 'hilang' => 'danger',
        'dipinjam' => 'primary', 'kembali' => 'success', 'terlambat' => 'danger',
        'Layak' => 'success', 'Mendekati umur pakai' => 'warning', 'Lewat umur pakai' => 'danger',
        'masuk' => 'success', 'keluar' => 'secondary',
        'menunggu' => 'warning', 'disetujui' => 'success', 'ditolak' => 'danger',
    ][$val] ?? 'secondary';
    $label = [
        'Lewat umur pakai' => 'Lewat umur', 'Mendekati umur pakai' => 'Hampir habis',
    ][$val] ?? ucfirst($val);
    return '<span class="pill pill-' . $warna . '">' . e($label) . '</span>';
}
