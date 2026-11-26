-- =====================================================================
--  MIGRASI: fitur pengajuan peminjaman dari halaman publik
--  Jalankan HANYA jika database sudah ada sebelumnya (tidak perlu jika
--  baru mengimpor ulang inventaris_hima.sql versi terbaru).
--  phpMyAdmin -> pilih database inventaris_hima -> tab Import -> file ini
-- =====================================================================
SET NAMES utf8mb4;

-- 12. pengajuan ---------------------------------------------------------
-- permohonan peminjaman yang diisi sendiri oleh peminjam lewat halaman publik
CREATE TABLE pengajuan (
  id_pengajuan            INT AUTO_INCREMENT PRIMARY KEY,
  kode_pengajuan          VARCHAR(12)  NOT NULL UNIQUE,        -- contoh: AJ-7K3M9Q
  nama                    VARCHAR(100) NOT NULL,
  instansi                VARCHAR(100) NOT NULL,
  no_hp                   VARCHAR(20)  NOT NULL,
  email                   VARCHAR(100),
  tanggal_pinjam          DATE         NOT NULL,
  tanggal_kembali_rencana DATE         NOT NULL,
  keperluan               TEXT         NOT NULL,
  status                  ENUM('menunggu','disetujui','ditolak') NOT NULL DEFAULT 'menunggu',
  catatan_psdo            TEXT,
  id_pengguna             INT NULL,     -- petugas PSDO yang memproses
  id_peminjaman           INT NULL,     -- terisi jika disetujui dan menjadi peminjaman
  dibuat_pada             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diproses_pada           DATETIME     NULL,
  CONSTRAINT chk_pengajuan_tanggal CHECK (tanggal_kembali_rencana >= tanggal_pinjam),
  CONSTRAINT fk_pengajuan_pengguna FOREIGN KEY (id_pengguna)
    REFERENCES pengguna(id_pengguna) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_pengajuan_peminjaman FOREIGN KEY (id_peminjaman)
    REFERENCES peminjaman(id_peminjaman) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. detail_pengajuan --------------------------------------------------
CREATE TABLE detail_pengajuan (
  id_detail_pengajuan INT AUTO_INCREMENT PRIMARY KEY,
  id_pengajuan        INT NOT NULL,
  id_barang           INT NOT NULL,
  jumlah              INT NOT NULL,
  CONSTRAINT chk_dpengajuan_jumlah CHECK (jumlah > 0),
  CONSTRAINT fk_dpengajuan_pengajuan FOREIGN KEY (id_pengajuan)
    REFERENCES pengajuan(id_pengajuan) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_dpengajuan_barang FOREIGN KEY (id_barang)
    REFERENCES barang(id_barang) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- data contoh: 1 ditolak, 2 masih menunggu diproses PSDO
INSERT INTO pengajuan (id_pengajuan, kode_pengajuan, nama, instansi, no_hp, email, tanggal_pinjam, tanggal_kembali_rencana, keperluan, status, catatan_psdo, id_pengguna, id_peminjaman, dibuat_pada, diproses_pada) VALUES
(2, 'AJ-R4T8WX', 'Peminjam E', 'HIMA Fisika',          '0812xxxx0005', NULL,                     '2026-11-20', '2026-11-21', 'Seminar nasional', 'ditolak', 'Proyektor sudah dipinjam pada tanggal tersebut', 2, NULL, '2026-11-15 13:40:00', '2026-11-16 08:05:00'),
(3, 'AJ-H9N3VB', 'Peminjam F', 'BEM KM UNNES',         '0896xxxx0006', 'peminjam.f@example.com', '2026-12-01', '2026-12-03', 'Rapat kerja BEM di aula FMIPA', 'menunggu', NULL, NULL, NULL, '2026-11-25 20:15:00', NULL),
(4, 'AJ-D2L6YC', 'Peminjam G', 'Panitia Makrab Ilkom',  '0813xxxx0007', NULL,                     '2026-12-05', '2026-12-07', 'Malam keakraban mahasiswa baru', 'menunggu', NULL, NULL, NULL, '2026-11-26 07:48:00', NULL);

INSERT INTO detail_pengajuan (id_pengajuan, id_barang, jumlah) VALUES
(2, 1,  2),
(3, 1,  1),
(3, 2,  1),
(3, 11, 4),
(4, 10, 1),
(4, 3,  2),
(4, 12, 10);
