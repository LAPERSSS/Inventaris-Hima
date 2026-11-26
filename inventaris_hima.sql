-- =====================================================================
--  DATABASE SISTEM INFORMASI INVENTARIS HIMA ILMU KOMPUTER UNNES
--  Kelompok 5 - Sistem Informasi Manajemen 2026
--  DBMS : MySQL / MariaDB (XAMPP)
--  Cara pakai: buat database `inventaris_hima` di phpMyAdmin,
--              pilih database itu, lalu Import file ini.
--  Akun demo (password semua: hima2026):
--      psdo_demo  -> Biro PSDO (kelola data)
--      bph_demo   -> BPH (lihat laporan)
--      ketua_demo -> Ketua HIMA (lihat dasbor)
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP VIEW  IF EXISTS v_barang_umur;
DROP VIEW  IF EXISTS v_peminjaman_aktif;
DROP VIEW  IF EXISTS v_rekap_kategori;
DROP VIEW  IF EXISTS v_pendapatan_bulanan;
DROP VIEW  IF EXISTS v_selisih_pemeriksaan;

DROP TABLE IF EXISTS detail_pemeriksaan;
DROP TABLE IF EXISTS pemeriksaan;
DROP TABLE IF EXISTS detail_peminjaman;
DROP TABLE IF EXISTS peminjaman;
DROP TABLE IF EXISTS peminjam;
DROP TABLE IF EXISTS transaksi_barang;
DROP TABLE IF EXISTS arsip_inventaris;
DROP TABLE IF EXISTS barang;
DROP TABLE IF EXISTS kategori;
DROP TABLE IF EXISTS pengguna;
DROP TABLE IF EXISTS periode_kepengurusan;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- BAGIAN 1 : STRUKTUR TABEL (urut sesuai dependensi foreign key)
-- =====================================================================

-- 1. periode_kepengurusan --------------------------------------------
CREATE TABLE periode_kepengurusan (
  id_periode      INT AUTO_INCREMENT PRIMARY KEY,
  nama_periode    VARCHAR(50)  NOT NULL,
  tanggal_mulai   DATE         NOT NULL,
  tanggal_selesai DATE         NOT NULL,
  status_aktif    BOOLEAN      NOT NULL DEFAULT FALSE,
  CONSTRAINT chk_periode_tanggal CHECK (tanggal_selesai > tanggal_mulai)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. kategori ----------------------------------------------------------
CREATE TABLE kategori (
  id_kategori      INT AUTO_INCREMENT PRIMARY KEY,
  nama_kategori    VARCHAR(50) NOT NULL UNIQUE,
  umur_pakai_tahun INT         NOT NULL DEFAULT 3,
  CONSTRAINT chk_umur_pakai CHECK (umur_pakai_tahun > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. peminjam ----------------------------------------------------------
CREATE TABLE peminjam (
  id_peminjam INT AUTO_INCREMENT PRIMARY KEY,
  nama        VARCHAR(100) NOT NULL,
  instansi    VARCHAR(100) NOT NULL,
  no_hp       VARCHAR(20)  NOT NULL,
  email       VARCHAR(100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. pengguna ----------------------------------------------------------
CREATE TABLE pengguna (
  id_pengguna   INT AUTO_INCREMENT PRIMARY KEY,
  id_periode    INT          NOT NULL,
  nama          VARCHAR(100) NOT NULL,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  peran         ENUM('psdo','bph','ketua') NOT NULL,
  CONSTRAINT fk_pengguna_periode FOREIGN KEY (id_periode)
    REFERENCES periode_kepengurusan(id_periode)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. barang ------------------------------------------------------------
CREATE TABLE barang (
  id_barang         INT AUTO_INCREMENT PRIMARY KEY,
  id_kategori       INT           NOT NULL,
  kode_inventaris   VARCHAR(20)   NOT NULL UNIQUE,   -- format 001.02.2024
  nama_barang       VARCHAR(100)  NOT NULL,
  satuan            VARCHAR(20)   NOT NULL,
  jumlah_total      INT           NOT NULL DEFAULT 0,
  jumlah_tersedia   INT           NOT NULL DEFAULT 0,
  lokasi            VARCHAR(50)   NOT NULL DEFAULT 'PKM',
  tanggal_perolehan DATE,
  kondisi           ENUM('baik','rusak','hilang') NOT NULL DEFAULT 'baik',
  biaya_sewa        DECIMAL(10,2) NOT NULL DEFAULT 0,
  keterangan        TEXT,
  CONSTRAINT chk_jumlah_total    CHECK (jumlah_total >= 0),
  CONSTRAINT chk_jumlah_tersedia CHECK (jumlah_tersedia >= 0 AND jumlah_tersedia <= jumlah_total),
  CONSTRAINT chk_biaya_sewa      CHECK (biaya_sewa >= 0),
  CONSTRAINT fk_barang_kategori FOREIGN KEY (id_kategori)
    REFERENCES kategori(id_kategori)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. transaksi_barang --------------------------------------------------
CREATE TABLE transaksi_barang (
  id_transaksi INT AUTO_INCREMENT PRIMARY KEY,
  id_barang    INT  NOT NULL,
  id_pengguna  INT  NOT NULL,
  jenis        ENUM('masuk','keluar') NOT NULL,
  jumlah       INT  NOT NULL,
  tanggal      DATE NOT NULL,
  keterangan   TEXT,
  CONSTRAINT chk_transaksi_jumlah CHECK (jumlah > 0),
  CONSTRAINT fk_transaksi_barang FOREIGN KEY (id_barang)
    REFERENCES barang(id_barang) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_transaksi_pengguna FOREIGN KEY (id_pengguna)
    REFERENCES pengguna(id_pengguna) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. peminjaman --------------------------------------------------------
CREATE TABLE peminjaman (
  id_peminjaman       INT AUTO_INCREMENT PRIMARY KEY,
  id_peminjam         INT  NOT NULL,
  id_pengguna         INT  NOT NULL,
  tanggal_pinjam      DATE NOT NULL,
  tanggal_jatuh_tempo DATE NOT NULL,
  tanggal_kembali     DATE NULL,
  status              ENUM('dipinjam','kembali','terlambat') NOT NULL DEFAULT 'dipinjam',
  total_biaya         DECIMAL(10,2) NOT NULL DEFAULT 0,
  CONSTRAINT chk_jatuh_tempo CHECK (tanggal_jatuh_tempo >= tanggal_pinjam),
  CONSTRAINT fk_peminjaman_peminjam FOREIGN KEY (id_peminjam)
    REFERENCES peminjam(id_peminjam) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_peminjaman_pengguna FOREIGN KEY (id_pengguna)
    REFERENCES pengguna(id_pengguna) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. detail_peminjaman -------------------------------------------------
CREATE TABLE detail_peminjaman (
  id_detail       INT AUTO_INCREMENT PRIMARY KEY,
  id_peminjaman   INT NOT NULL,
  id_barang       INT NOT NULL,
  jumlah          INT NOT NULL,
  kondisi_keluar  ENUM('baik','rusak','hilang') NOT NULL DEFAULT 'baik',
  kondisi_kembali ENUM('baik','rusak','hilang') NULL,
  subtotal_biaya  DECIMAL(10,2) NOT NULL DEFAULT 0,
  CONSTRAINT chk_detail_jumlah CHECK (jumlah > 0),
  CONSTRAINT fk_detail_peminjaman FOREIGN KEY (id_peminjaman)
    REFERENCES peminjaman(id_peminjaman) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_detail_barang FOREIGN KEY (id_barang)
    REFERENCES barang(id_barang) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. pemeriksaan -------------------------------------------------------
CREATE TABLE pemeriksaan (
  id_pemeriksaan  INT AUTO_INCREMENT PRIMARY KEY,
  id_pengguna     INT  NOT NULL,
  tanggal_periksa DATE NOT NULL,
  jenis           ENUM('pkm_service','insidental') NOT NULL,
  catatan         TEXT,
  CONSTRAINT fk_pemeriksaan_pengguna FOREIGN KEY (id_pengguna)
    REFERENCES pengguna(id_pengguna) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. detail_pemeriksaan -----------------------------------------------
CREATE TABLE detail_pemeriksaan (
  id_detail_periksa INT AUTO_INCREMENT PRIMARY KEY,
  id_pemeriksaan    INT NOT NULL,
  id_barang         INT NOT NULL,
  kondisi           ENUM('baik','rusak','hilang') NOT NULL,
  jumlah_ditemukan  INT NOT NULL,
  tindakan          TEXT,
  CONSTRAINT chk_jumlah_ditemukan CHECK (jumlah_ditemukan >= 0),
  CONSTRAINT fk_dperiksa_pemeriksaan FOREIGN KEY (id_pemeriksaan)
    REFERENCES pemeriksaan(id_pemeriksaan) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_dperiksa_barang FOREIGN KEY (id_barang)
    REFERENCES barang(id_barang) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. arsip_inventaris -------------------------------------------------
CREATE TABLE arsip_inventaris (
  id_arsip      INT AUTO_INCREMENT PRIMARY KEY,
  id_periode    INT  NOT NULL,
  id_barang     INT  NOT NULL,
  jumlah        INT  NOT NULL,
  kondisi       ENUM('baik','rusak','hilang') NOT NULL,
  tanggal_arsip DATE NOT NULL,
  CONSTRAINT uq_arsip_periode_barang UNIQUE (id_periode, id_barang),
  CONSTRAINT fk_arsip_periode FOREIGN KEY (id_periode)
    REFERENCES periode_kepengurusan(id_periode) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_arsip_barang FOREIGN KEY (id_barang)
    REFERENCES barang(id_barang) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =====================================================================
-- BAGIAN 2 : DATA CONTOH
-- (ganti dengan data asli dari Excel inventaris HIMA 2026;
--  identitas peminjam sudah disamarkan)
-- =====================================================================

INSERT INTO periode_kepengurusan (id_periode, nama_periode, tanggal_mulai, tanggal_selesai, status_aktif) VALUES
(1, 'Kepengurusan 2025', '2025-01-01', '2025-12-31', FALSE),
(2, 'Kepengurusan 2026', '2026-01-01', '2026-12-31', TRUE);

-- password semua akun: hima2026
INSERT INTO pengguna (id_pengguna, id_periode, nama, username, password_hash, peran) VALUES
(1, 1, 'PSDO Periode 2025', 'psdo_2025',  '$2y$10$9KNsMFVmnqDN/m/Cai4JReNmIxd0Qnn2xVgrzJSeRyl5T5PhmGfoa', 'psdo'),
(2, 2, 'Biro PSDO (Demo)',  'psdo_demo',  '$2y$10$9KNsMFVmnqDN/m/Cai4JReNmIxd0Qnn2xVgrzJSeRyl5T5PhmGfoa', 'psdo'),
(3, 2, 'BPH (Demo)',        'bph_demo',   '$2y$10$9KNsMFVmnqDN/m/Cai4JReNmIxd0Qnn2xVgrzJSeRyl5T5PhmGfoa', 'bph'),
(4, 2, 'Ketua HIMA (Demo)', 'ketua_demo', '$2y$10$9KNsMFVmnqDN/m/Cai4JReNmIxd0Qnn2xVgrzJSeRyl5T5PhmGfoa', 'ketua');

INSERT INTO kategori (id_kategori, nama_kategori, umur_pakai_tahun) VALUES
(1, 'Elektronik',          4),
(2, 'Audio',               3),
(3, 'Perlengkapan Acara',  5),
(4, 'Furnitur',            5),
(5, 'Alat Tulis Kantor',   2);

-- jumlah_tersedia sudah dikurangi barang yang sedang dipinjam
INSERT INTO barang (id_barang, id_kategori, kode_inventaris, nama_barang, satuan, jumlah_total, jumlah_tersedia, lokasi, tanggal_perolehan, kondisi, biaya_sewa, keterangan) VALUES
(1,  1, '001.03.2022', 'Proyektor Epson',            'unit', 2,  1,  'PKM', '2022-03-10', 'baik',  50000, '1 unit sedang dipinjam'),
(2,  1, '002.08.2024', 'Kabel HDMI 10 m',            'buah', 3,  3,  'PKM', '2024-08-05', 'baik',  10000, NULL),
(3,  1, '003.01.2023', 'Terminal Listrik 5 Lubang',  'buah', 6,  6,  'PKM', '2023-01-15', 'baik',   5000, NULL),
(4,  1, '004.07.2021', 'Printer Canon',              'unit', 1,  0,  'PKM', '2021-07-20', 'rusak',     0, 'Head printer bermasalah, tidak disewakan'),
(5,  2, '005.02.2022', 'Mikrofon Wireless',          'set',  2,  0,  'PKM', '2022-02-14', 'rusak',     0, 'Ditemukan bermasalah saat persiapan agenda'),
(6,  2, '006.09.2024', 'Speaker Portable',           'unit', 2,  0,  'PKM', '2024-09-01', 'baik',  75000, 'Semua unit sedang dipinjam'),
(7,  2, '007.10.2025', 'Mikrofon Kabel',             'buah', 4,  4,  'PKM', '2025-10-12', 'baik',  15000, 'Pengganti mikrofon wireless'),
(8,  3, '008.05.2020', 'X-Banner Stand',             'buah', 4,  4,  'PKM', '2020-05-18', 'baik',  10000, 'Sudah melewati umur pakai'),
(9,  3, '009.02.2024', 'Jas Almamater Panitia',      'buah', 10, 10, 'PKM', '2024-02-02', 'baik',      0, 'Khusus internal'),
(10, 3, '010.11.2023', 'Tenda Lipat 3x3 m',          'unit', 1,  1,  'PKM', '2023-11-25', 'baik', 100000, NULL),
(11, 4, '011.01.2022', 'Meja Lipat',                 'buah', 6,  6,  'PKM', '2022-01-08', 'baik',  15000, NULL),
(12, 4, '012.06.2021', 'Kursi Plastik',              'buah', 20, 16, 'PKM', '2021-06-11', 'baik',   3000, '4 buah sedang dipinjam'),
(13, 5, '013.08.2026', 'Spidol Whiteboard',          'buah', 12, 12, 'PKM', '2026-08-03', 'baik',      0, 'Barang habis pakai'),
(14, 5, '014.08.2026', 'Kertas HVS A4',              'rim',  3,  3,  'PKM', '2026-08-03', 'baik',      0, 'Barang habis pakai'),
(15, 1, '015.04.2019', 'Kamera Digital',             'unit', 1,  0,  'PKM', '2019-04-22', 'hilang',    0, 'Tidak ditemukan saat pendataan ulang 2026');

INSERT INTO transaksi_barang (id_barang, id_pengguna, jenis, jumlah, tanggal, keterangan) VALUES
(7,  1, 'masuk',  4,  '2025-10-12', 'Pembelian mikrofon kabel'),
(13, 2, 'masuk',  12, '2026-08-03', 'Pembelian ATK semester ganjil'),
(14, 2, 'masuk',  3,  '2026-08-03', 'Pembelian ATK semester ganjil'),
(2,  2, 'masuk',  1,  '2026-09-15', 'Hibah dari alumni'),
(12, 2, 'keluar', 2,  '2026-10-05', 'Rusak permanen (kaki patah), dihapus dari inventaris'),
(3,  2, 'masuk',  2,  '2026-10-20', 'Pembelian tambahan untuk acara Dies Natalis');

INSERT INTO peminjam (id_peminjam, nama, instansi, no_hp, email) VALUES
(1, 'Peminjam A', 'HIMA Matematika',            '0812xxxx0001', 'peminjam.a@example.com'),
(2, 'Peminjam B', 'BEM FMIPA',                  '0813xxxx0002', 'peminjam.b@example.com'),
(3, 'Peminjam C', 'UKM Fotografi',              '0857xxxx0003', 'peminjam.c@example.com'),
(4, 'Peminjam D', 'Panitia Dies Natalis Ilkom', '0821xxxx0004', NULL);

-- 1 & 2 sudah kembali, 3 masih dipinjam (belum jatuh tempo),
-- 4 masih dipinjam dan SUDAH lewat jatuh tempo (terlambat)
INSERT INTO peminjaman (id_peminjaman, id_peminjam, id_pengguna, tanggal_pinjam, tanggal_jatuh_tempo, tanggal_kembali, status, total_biaya) VALUES
(1, 1, 2, '2026-10-03', '2026-10-05', '2026-10-05', 'kembali',   145000),
(2, 2, 2, '2026-11-02', '2026-11-04', '2026-11-05', 'kembali',   100000),
(3, 3, 2, '2026-11-24', '2026-11-30', NULL,         'dipinjam',  150000),
(4, 4, 2, '2026-11-18', '2026-11-22', NULL,         'dipinjam',   62000);

INSERT INTO detail_peminjaman (id_peminjaman, id_barang, jumlah, kondisi_keluar, kondisi_kembali, subtotal_biaya) VALUES
(1, 1,  1, 'baik', 'baik', 50000),
(1, 6,  1, 'baik', 'baik', 75000),
(1, 2,  2, 'baik', 'baik', 20000),
(2, 10, 1, 'baik', 'baik', 100000),
(3, 6,  2, 'baik', NULL,  150000),
(4, 1,  1, 'baik', NULL,   50000),
(4, 12, 4, 'baik', NULL,   12000);

INSERT INTO pemeriksaan (id_pemeriksaan, id_pengguna, tanggal_periksa, jenis, catatan) VALUES
(1, 2, '2026-10-28', 'pkm_service', 'PKM Service bulan Oktober'),
(2, 2, '2026-11-10', 'insidental',  'Pengecekan sebelum agenda seminar, mikrofon wireless bermasalah'),
(3, 2, '2026-11-25', 'pkm_service', 'PKM Service bulan November');

INSERT INTO detail_pemeriksaan (id_pemeriksaan, id_barang, kondisi, jumlah_ditemukan, tindakan) VALUES
(1, 1,  'baik',   2,  NULL),
(1, 4,  'rusak',  1,  'Perlu servis head printer'),
(1, 8,  'baik',   4,  'Sudah lewat umur pakai, usulkan penggantian'),
(1, 15, 'hilang', 0,  'Tidak ditemukan, laporkan ke Ketua HIMA'),
(2, 5,  'rusak',  2,  'Beli pengganti / servis'),
(3, 1,  'baik',   1,  '1 unit sedang dipinjam'),
(3, 3,  'baik',   6,  NULL),
(3, 11, 'baik',   6,  NULL),
(3, 12, 'baik',   16, '4 buah sedang dipinjam');

-- snapshot serah terima akhir periode 2025
INSERT INTO arsip_inventaris (id_periode, id_barang, jumlah, kondisi, tanggal_arsip) VALUES
(1, 1,  2,  'baik', '2025-12-31'),
(1, 4,  1,  'baik', '2025-12-31'),
(1, 5,  2,  'baik', '2025-12-31'),
(1, 7,  4,  'baik', '2025-12-31'),
(1, 8,  4,  'baik', '2025-12-31'),
(1, 11, 6,  'baik', '2025-12-31'),
(1, 12, 22, 'baik', '2025-12-31'),
(1, 15, 1,  'baik', '2025-12-31');


-- =====================================================================
-- BAGIAN 3 : VIEW UNTUK LAPORAN & DASBOR MANAJER
-- =====================================================================

-- Umur barang dan penanda melewati umur pakai
CREATE VIEW v_barang_umur AS
SELECT b.id_barang, b.kode_inventaris, b.nama_barang, k.nama_kategori,
       b.tanggal_perolehan, b.kondisi,
       TIMESTAMPDIFF(YEAR, b.tanggal_perolehan, CURDATE()) AS umur_tahun,
       k.umur_pakai_tahun,
       CASE
         WHEN TIMESTAMPDIFF(YEAR, b.tanggal_perolehan, CURDATE()) >= k.umur_pakai_tahun THEN 'Lewat umur pakai'
         WHEN TIMESTAMPDIFF(YEAR, b.tanggal_perolehan, CURDATE()) >= k.umur_pakai_tahun - 1 THEN 'Mendekati umur pakai'
         ELSE 'Layak'
       END AS status_umur
FROM barang b
JOIN kategori k ON k.id_kategori = b.id_kategori;

-- Peminjaman yang belum kembali + status terlambat dihitung otomatis
CREATE VIEW v_peminjaman_aktif AS
SELECT p.id_peminjaman, pj.nama AS peminjam, pj.instansi, pj.no_hp,
       p.tanggal_pinjam, p.tanggal_jatuh_tempo, p.total_biaya,
       CASE WHEN p.tanggal_jatuh_tempo < CURDATE() THEN 'terlambat' ELSE 'dipinjam' END AS status_terkini,
       GREATEST(DATEDIFF(CURDATE(), p.tanggal_jatuh_tempo), 0) AS hari_terlambat
FROM peminjaman p
JOIN peminjam pj ON pj.id_peminjam = p.id_peminjam
WHERE p.tanggal_kembali IS NULL;

-- Rekap jumlah barang per kategori dan kondisi
CREATE VIEW v_rekap_kategori AS
SELECT k.nama_kategori,
       COUNT(b.id_barang)                                         AS jenis_barang,
       COALESCE(SUM(b.jumlah_total), 0)                           AS total_unit,
       COALESCE(SUM(b.jumlah_tersedia), 0)                        AS unit_tersedia,
       COALESCE(SUM(CASE WHEN b.kondisi = 'baik'   THEN 1 ELSE 0 END), 0) AS jenis_baik,
       COALESCE(SUM(CASE WHEN b.kondisi = 'rusak'  THEN 1 ELSE 0 END), 0) AS jenis_rusak,
       COALESCE(SUM(CASE WHEN b.kondisi = 'hilang' THEN 1 ELSE 0 END), 0) AS jenis_hilang
FROM kategori k
LEFT JOIN barang b ON b.id_kategori = k.id_kategori
GROUP BY k.id_kategori, k.nama_kategori;

-- Pendapatan sewa per bulan
CREATE VIEW v_pendapatan_bulanan AS
SELECT DATE_FORMAT(tanggal_pinjam, '%Y-%m') AS bulan,
       COUNT(*)          AS jumlah_peminjaman,
       SUM(total_biaya)  AS total_pendapatan
FROM peminjaman
GROUP BY DATE_FORMAT(tanggal_pinjam, '%Y-%m');

-- Selisih jumlah fisik vs seharusnya ada di PKM (total - sedang dipinjam)
-- pada pemeriksaan terakhir tiap barang; selisih negatif = barang kurang
CREATE VIEW v_selisih_pemeriksaan AS
SELECT b.kode_inventaris, b.nama_barang, pm.tanggal_periksa, pm.jenis,
       b.jumlah_total - COALESCE(pinjam.jumlah_dipinjam, 0) AS seharusnya_ada,
       dp.jumlah_ditemukan,
       dp.jumlah_ditemukan - (b.jumlah_total - COALESCE(pinjam.jumlah_dipinjam, 0)) AS selisih,
       dp.kondisi, dp.tindakan
FROM detail_pemeriksaan dp
JOIN pemeriksaan pm ON pm.id_pemeriksaan = dp.id_pemeriksaan
JOIN barang b       ON b.id_barang       = dp.id_barang
LEFT JOIN (
  SELECT d.id_barang, SUM(d.jumlah) AS jumlah_dipinjam
  FROM detail_peminjaman d
  JOIN peminjaman p ON p.id_peminjaman = d.id_peminjaman
  WHERE p.tanggal_kembali IS NULL
  GROUP BY d.id_barang
) pinjam ON pinjam.id_barang = b.id_barang
WHERE pm.tanggal_periksa = (
  SELECT MAX(pm2.tanggal_periksa)
  FROM detail_pemeriksaan dp2
  JOIN pemeriksaan pm2 ON pm2.id_pemeriksaan = dp2.id_pemeriksaan
  WHERE dp2.id_barang = dp.id_barang
);
