# SIM Inventaris HIMA Ilmu Komputer UNNES

Prototipe Sistem Informasi Manajemen Inventaris Barang untuk HIMA Ilmu Komputer UNNES.
Proyek akhir mata kuliah **Sistem Informasi Manajemen 2026 – Kelompok 5**.

Fitur utama: data barang dan kategori, barang masuk/keluar, peminjaman dan pengembalian,
pemeriksaan kondisi (PKM Service), arsip inventaris per periode kepengurusan, serta dasbor untuk BPH dan Ketua HIMA.

**Teknologi:** PHP 8 (tanpa framework) · MySQL/MariaDB · Bootstrap 5 · XAMPP

---

## Daftar isi

1. [Menjalankan di laptop sendiri](#1-menjalankan-di-laptop-sendiri)
2. [Akun uji coba](#2-akun-uji-coba)
3. [Alur kerja tim dengan Git](#3-alur-kerja-tim-dengan-git)
4. [Struktur folder](#4-struktur-folder)
5. [Cara mengubah sesuatu](#5-cara-mengubah-sesuatu)
6. [Aturan penting](#6-aturan-penting)
7. [Kalau ada error](#7-kalau-ada-error)

---

## 1. Menjalankan di laptop sendiri

**Yang dibutuhkan:** [XAMPP](https://www.apachefriends.org/) (PHP 8+), [Git](https://git-scm.com/), dan VS Code.

1. **Clone repo ke folder `htdocs`.** Buka terminal, lalu jalankan:
   ```bash
   cd C:\xampp\htdocs
   git clone https://github.com/USERNAME/sim-inventaris-hima.git inventaris-hima
   ```
   Nama folder harus `inventaris-hima`, karena alamatnya dipakai di `BASE_URL`.

2. **Nyalakan XAMPP.** Klik **Start** pada **Apache** dan **MySQL**.

3. **Buat dan isi database.**
   - Buka `http://localhost/phpmyadmin`.
   - Klik **New**, beri nama `inventaris_hima`, lalu **Create**.
   - Pilih database tersebut, buka tab **Import**, pilih file `inventaris_hima.sql`, lalu klik **Go**.

4. **Buat file koneksi.** Salin `config/db.example.php` lalu ganti namanya menjadi `config/db.php`.
   Untuk XAMPP standar isinya tidak perlu diubah (user `root`, password kosong).

5. **Buka di browser.** Alamatnya `http://localhost/inventaris-hima/`.

---

## 2. Akun uji coba

Semua akun memakai password **`hima2026`**.

| Username     | Peran       | Hak akses                                         |
|--------------|-------------|---------------------------------------------------|
| `psdo_demo`  | Biro PSDO   | Mengelola semua data                              |
| `bph_demo`   | BPH         | Hanya melihat data dan laporan                    |
| `ketua_demo` | Ketua HIMA  | Hanya melihat data dan dasbor                     |
| `psdo_2025`  | PSDO lama   | **Ditolak**, karena periode 2025 sudah tidak aktif |

---

## 3. Alur kerja tim dengan Git

Ikuti urutan ini **setiap kali** mau mengubah kode, supaya pekerjaan tidak saling menimpa.

```bash
# 1. ambil perubahan terbaru dari teman (WAJIB sebelum mulai)
git pull

# 2. ... edit file, lalu tes di browser ...

# 3. simpan dan kirim
git add .
git commit -m "Jelaskan singkat apa yang diubah"
git push
```

**Contoh pesan commit yang baik:**
- `Tambah filter tanggal di halaman peminjaman`
- `Perbaiki validasi jumlah di barang keluar`

Hindari pesan seperti `update` atau `fix`, karena tidak menjelaskan apa yang berubah.

**Kalau `git push` ditolak** (pesannya *rejected… fetch first*), berarti ada teman yang push lebih dulu.
Jalankan `git pull` lalu `git push` lagi.

**Kalau muncul konflik** (*CONFLICT… Merge conflict in …*):

1. Buka file yang disebut di pesan itu. VS Code akan menandai bagian yang bentrok.
2. Pilih **Accept Current**, **Accept Incoming**, atau **Accept Both**, lalu pastikan hasilnya masuk akal.
3. Jalankan:
   ```bash
   git add .
   git commit -m "Selesaikan konflik"
   git push
   ```

Untuk mengurangi konflik, bagi pekerjaan per folder/modul. Contoh: satu orang di `peminjaman/`, satu orang di `pemeriksaan/`.

---

## 4. Struktur folder

```
inventaris-hima/
├── config/
│   ├── db.php              ← koneksi database (TIDAK ikut ke GitHub)
│   └── db.example.php      ← contoh, salin menjadi db.php
├── includes/
│   ├── auth.php            ← sesi login, hak akses, dan SEMUA fungsi bantu
│   ├── header.php          ← sidebar, topbar, judul halaman
│   └── footer.php
├── assets/style.css        ← seluruh tampilan (warna, kartu, tabel)
├── laporan/dashboard.php   ← dasbor manajer
├── barang/                 ← index (daftar), form (tambah/ubah), detail
├── kategori/               ← index (daftar + form dalam satu halaman)
├── transaksi/              ← index, tambah (barang masuk/keluar)
├── peminjaman/             ← index, tambah, detail (+ proses pengembalian)
├── pemeriksaan/            ← index, tambah (checklist), detail
├── arsip/                  ← index (buat arsip), detail (bandingkan)
├── login.php · logout.php · index.php
└── inventaris_hima.sql     ← struktur tabel + data contoh + view laporan
```

Hubungan file ke tabel database:

| Folder         | Tabel yang dipakai                                   |
|----------------|------------------------------------------------------|
| `barang/`      | `barang`, `kategori`                                 |
| `transaksi/`   | `transaksi_barang`                                   |
| `peminjaman/`  | `peminjam`, `peminjaman`, `detail_peminjaman`        |
| `pemeriksaan/` | `pemeriksaan`, `detail_pemeriksaan`                  |
| `arsip/`       | `periode_kepengurusan`, `arsip_inventaris`           |
| `laporan/`     | view `v_barang_umur`, `v_peminjaman_aktif`, `v_rekap_kategori`, `v_pendapatan_bulanan` |

---

## 5. Cara mengubah sesuatu

### 5.1 Pola setiap halaman

Semua halaman mengikuti kerangka yang sama. Salin pola ini kalau mau membuat halaman baru:

```php
<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['psdo', 'bph', 'ketua']);   // siapa yang boleh membuka halaman ini

// 1) proses form (kalau ada)
if (is_post()) {
    require_role(['psdo']);               // hanya PSDO yang boleh mengubah data
    csrf_check();                         // wajib di setiap form POST
    // ... validasi, simpan ...
    flash('success', 'Data disimpan.');
    redirect('folder/index.php');
}

// 2) ambil data untuk ditampilkan
$data = $pdo->query("SELECT ...")->fetchAll();

// 3) tampilkan
$title    = 'Judul Halaman';
$subtitle = 'Keterangan singkat';        // opsional
$active   = 'barang';                    // menu sidebar yang disorot
$back     = 'barang/index.php';          // opsional, memunculkan tombol "Kembali"
$actions  = '<a href="..." class="btn btn-primary">Tombol</a>';  // opsional
require __DIR__ . '/../includes/header.php';
?>

<div class="card"> ... isi halaman ... </div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
```

### 5.2 Fungsi bantu di `includes/auth.php`

| Fungsi                                  | Kegunaan                                                   |
|-----------------------------------------|------------------------------------------------------------|
| `require_login()` / `require_role([..])`| Mengusir pengguna yang tidak berhak                        |
| `can_edit()`                            | `true` jika yang login adalah PSDO. Dipakai untuk menyembunyikan tombol tambah/ubah |
| `e($teks)`                              | **Wajib** dipakai setiap menampilkan data (mencegah XSS)   |
| `url('barang/index.php')`               | Membuat link yang benar di lokal maupun di hosting         |
| `redirect('...')`                       | Pindah halaman                                             |
| `flash('success', 'pesan')`             | Pesan hijau/merah yang tampil sekali di halaman berikutnya |
| `csrf_field()` / `csrf_check()`         | Taruh `csrf_field()` di setiap `<form method="post">`      |
| `old('nama_field', $default)`           | Mengisi ulang input setelah validasi gagal                 |
| `show_errors($errors)`                  | Menampilkan daftar pesan error validasi                    |
| `badge('baik')`                         | Label berwarna untuk kondisi atau status                   |
| `rupiah(50000)` / `tgl('2026-11-26')`   | Format `Rp 50.000` / `26/11/2026`                          |
| `post_button(...)`                      | Tombol hapus/aksi kecil dengan konfirmasi                  |
| `sync_tersedia($pdo, $id_barang)`       | Menghitung ulang stok tersedia (lihat 6.1)                 |
| `ubah_total($pdo, $id, +n / -n)`        | Menambah/mengurangi jumlah total barang                    |
| `catat_transaksi(...)`                  | Mencatat riwayat barang masuk/keluar                       |

### 5.3 Menambah menu di sidebar

Buka `includes/header.php` dan tambahkan satu baris di array `$menu`:

```php
['kunci', 'Label Menu', 'folder/index.php', 'bi-nama-ikon', ['psdo','bph','ketua'], 'Grup'],
```

Nama ikon bisa dicari di [icons.getbootstrap.com](https://icons.getbootstrap.com/).
Lalu set `$active = 'kunci';` di halaman barunya.

### 5.4 Mengubah tampilan

- **Warna utama:** ubah `--brand` di baris atas `assets/style.css`.
- **Komponen siap pakai:** `card`, `card-header`, `table`, `toolbar` (baris filter), `stat` (kartu angka),
  `pill pill-success/warning/danger` (label), `empty` (pesan tabel kosong).
- Setelah mengubah CSS, tekan **Ctrl+F5** di browser supaya cache lama terhapus.

### 5.5 Mengubah struktur database

Kalau menambah atau mengubah kolom, misalnya:

```sql
ALTER TABLE barang ADD COLUMN merek VARCHAR(50) NULL AFTER nama_barang;
```

1. Jalankan query di tab **SQL** phpMyAdmin.
2. **Perbarui juga** `inventaris_hima.sql`, supaya teman yang mengimpor ulang mendapat struktur yang sama.
   Caranya: phpMyAdmin → **Export** → **Go**, lalu timpa file lama.
3. Beri tahu grup, supaya semua orang menjalankan `ALTER` yang sama atau mengimpor ulang.
4. **Perbarui ERD dan kamus data di laporan (Bab 4.3).** Rubrik menilai konsistensi rancangan dengan prototipe.

### 5.6 Menulis query

Selalu gunakan **prepared statement** untuk nilai yang berasal dari pengguna:

```php
// BENAR
$s = $pdo->prepare("SELECT * FROM barang WHERE id_barang = ?");
$s->execute([$id]);
$barang = $s->fetch();

// SALAH, rawan SQL injection
$pdo->query("SELECT * FROM barang WHERE id_barang = $_GET[id]");
```

Kalau satu aksi mengubah beberapa tabel sekaligus, bungkus dengan transaksi:

```php
$pdo->beginTransaction();
// ... beberapa INSERT/UPDATE ...
$pdo->commit();
```

---

## 6. Aturan penting

### 6.1 Jangan mengisi `jumlah_tersedia` secara manual

Stok tersedia **selalu dihitung ulang** oleh `sync_tersedia()` dengan aturan:

- kondisi **baik** → tersedia = jumlah total − jumlah yang sedang dipinjam
- kondisi **rusak/hilang** → tersedia = 0

Jadi kalau kodemu mengubah peminjaman, kondisi, atau jumlah barang, panggil
`sync_tersedia($pdo, $id_barang)` sesudahnya. Untuk menambah/mengurangi total, gunakan
`ubah_total()` lalu `catat_transaksi()`, supaya riwayatnya tercatat.

### 6.2 Hak akses mengikuti Tabel 4.1 laporan

Hanya **PSDO** yang boleh mengubah data. Halaman yang mengubah data wajib memakai
`require_role(['psdo'])`, dan tombol tambah/ubah dibungkus `<?php if (can_edit()): ?>`.

### 6.3 Jangan pernah commit

- `config/db.php`, karena berisi password database. File ini sudah ada di `.gitignore`.
- **Data pribadi asli** (nama atau nomor HP peminjam sungguhan). Samarkan dulu, sesuai aturan tugas.

---

## 7. Kalau ada error

| Gejala                                   | Penyebab dan solusi                                                      |
|------------------------------------------|--------------------------------------------------------------------------|
| **Not Found / 404**                      | Nama folder bukan `inventaris-hima`, atau file berada di subfolder yang salah |
| **Index of /inventaris-hima**            | Isi zip terekstrak ke dalam folder ganda. Pindahkan isinya naik satu tingkat |
| `Unknown database 'inventaris_hima'`     | Database belum dibuat atau diimpor (langkah 1.3)                         |
| `Access denied for user 'root'`          | Password MySQL di `config/db.php` tidak sesuai                           |
| `Table ... doesn't exist`                | Impor ulang `inventaris_hima.sql`                                        |
| `Failed opening required ... db.php`     | `config/db.php` belum dibuat (langkah 1.4)                               |
| Tampilan berantakan / CSS lama           | Tekan **Ctrl+F5**                                                         |
| Halaman putih kosong                     | Lihat log error di `C:\xampp\apache\logs\error.log` (baris paling bawah) |
| *Sesi formulir kedaluwarsa*              | Halaman dibuka terlalu lama atau setelah logout. Kembali lalu refresh    |

**Mengembalikan data contoh ke awal:** impor ulang `inventaris_hima.sql`. File ini otomatis menghapus
tabel lama lalu membuatnya kembali.
