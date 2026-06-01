-- ============================================================
--  SISTEM PERPUSTAKAAN - DATABASE SCHEMA
--  MySQL / MariaDB
--  Includes: Tables, Views, Triggers, Stored Procedures, Cursors
-- ============================================================

CREATE DATABASE IF NOT EXISTS db_perpustakaan
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_0900_ai_ci;

USE db_perpustakaan;

-- ============================================================
-- TABLES
-- ============================================================

CREATE TABLE IF NOT EXISTS kategori (
    id_kategori   INT AUTO_INCREMENT PRIMARY KEY,
    nama_kategori VARCHAR(100) NOT NULL,
    deskripsi     TEXT,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS buku (
    id_buku       INT AUTO_INCREMENT PRIMARY KEY,
    kode_buku     VARCHAR(20)  NOT NULL UNIQUE,
    judul         VARCHAR(255) NOT NULL,
    pengarang     VARCHAR(150) NOT NULL,
    penerbit      VARCHAR(150),
    tahun_terbit  YEAR,
    id_kategori   INT,
    stok_total    INT DEFAULT 1,
    stok_tersedia INT DEFAULT 1,
    cover_url     VARCHAR(255),
    deskripsi     TEXT,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_kategori) REFERENCES kategori(id_kategori) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS anggota (
    id_anggota    INT AUTO_INCREMENT PRIMARY KEY,
    kode_anggota  VARCHAR(20)  NOT NULL UNIQUE,
    nama          VARCHAR(150) NOT NULL,
    email         VARCHAR(150) UNIQUE,
    telepon       VARCHAR(20),
    alamat        TEXT,
    tanggal_daftar DATE DEFAULT (CURRENT_DATE),
    status        ENUM('aktif','nonaktif') DEFAULT 'aktif',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS peminjaman (
    id_pinjam       INT AUTO_INCREMENT PRIMARY KEY,
    kode_pinjam     VARCHAR(20)  NOT NULL UNIQUE,
    id_anggota      INT NOT NULL,
    id_buku         INT NOT NULL,
    tanggal_pinjam  DATE NOT NULL DEFAULT (CURRENT_DATE),
    tanggal_kembali DATE NOT NULL,
    tanggal_kembali_aktual DATE,
    status          ENUM('dipinjam','dikembalikan','terlambat') DEFAULT 'dipinjam',
    petugas         VARCHAR(100),
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_anggota) REFERENCES anggota(id_anggota),
    FOREIGN KEY (id_buku)    REFERENCES buku(id_buku)
);

CREATE TABLE IF NOT EXISTS denda (
    id_denda        INT AUTO_INCREMENT PRIMARY KEY,
    id_pinjam       INT NOT NULL UNIQUE,
    jumlah_hari     INT NOT NULL DEFAULT 0,
    tarif_per_hari  DECIMAL(10,2) NOT NULL DEFAULT 1000,
    total_denda     DECIMAL(10,2) NOT NULL DEFAULT 0,
    status_bayar    ENUM('belum','sudah') DEFAULT 'belum',
    tanggal_bayar   DATE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_pinjam) REFERENCES peminjaman(id_pinjam)
);

-- ============================================================
-- VIEWS
-- ============================================================

-- View 1: Ringkasan lengkap peminjaman (dipakai di halaman daftar peminjaman)
CREATE OR REPLACE VIEW v_peminjaman_lengkap AS
SELECT
    p.id_pinjam,
    p.kode_pinjam,
    a.kode_anggota,
    a.nama        AS nama_anggota,
    a.telepon,
    b.kode_buku,
    b.judul       AS judul_buku,
    b.pengarang,
    p.tanggal_pinjam,
    p.tanggal_kembali,
    p.tanggal_kembali_aktual,
    p.status,
    p.petugas,
    DATEDIFF(IFNULL(p.tanggal_kembali_aktual, CURRENT_DATE), p.tanggal_kembali) AS keterlambatan_hari
FROM peminjaman p
JOIN anggota a ON p.id_anggota = a.id_anggota
JOIN buku b    ON p.id_buku    = b.id_buku;

-- View 2: Status stok buku (dipakai di halaman manajemen buku)
CREATE OR REPLACE VIEW v_stok_buku AS
SELECT
    b.id_buku,
    b.kode_buku,
    b.judul,
    b.pengarang,
    b.penerbit,
    k.nama_kategori,
    b.stok_total,
    b.stok_tersedia,
    (b.stok_total - b.stok_tersedia) AS sedang_dipinjam,
    CASE
        WHEN b.stok_tersedia = 0 THEN 'Habis'
        WHEN b.stok_tersedia <= 2 THEN 'Terbatas'
        ELSE 'Tersedia'
    END COLLATE utf8mb4_0900_ai_ci AS status_stok
FROM buku b
LEFT JOIN kategori k ON b.id_kategori = k.id_kategori;

-- View 3: Ringkasan denda per anggota (dipakai di laporan denda)
CREATE OR REPLACE VIEW v_denda_anggota AS
SELECT
    a.id_anggota,
    a.kode_anggota,
    a.nama,
    COUNT(d.id_denda)          AS total_kasus_denda,
    SUM(d.total_denda)         AS total_denda,
    SUM(CASE WHEN d.status_bayar = 'belum' THEN d.total_denda ELSE 0 END) AS denda_belum_bayar,
    SUM(CASE WHEN d.status_bayar = 'sudah' THEN d.total_denda ELSE 0 END) AS denda_sudah_bayar
FROM anggota a
LEFT JOIN peminjaman p ON a.id_anggota = p.id_anggota
LEFT JOIN denda d      ON p.id_pinjam  = d.id_pinjam
GROUP BY a.id_anggota, a.kode_anggota, a.nama;

-- View 4: Statistik laporan (dipakai di dashboard)
CREATE OR REPLACE VIEW v_statistik AS
SELECT
    (SELECT COUNT(*) FROM buku)                                  AS total_buku,
    (SELECT COUNT(*) FROM anggota WHERE status = 'aktif')        AS total_anggota_aktif,
    (SELECT COUNT(*) FROM peminjaman WHERE status = 'dipinjam')  AS sedang_dipinjam,
    (SELECT COUNT(*) FROM peminjaman WHERE status = 'terlambat') AS terlambat,
    (SELECT IFNULL(SUM(total_denda),0) FROM denda WHERE status_bayar = 'belum') AS total_denda_belum_bayar;

-- ============================================================
-- TRIGGERS
-- ============================================================

-- Trigger 1: Kurangi stok buku otomatis saat peminjaman baru dibuat
DELIMITER $$
CREATE TRIGGER trg_pinjam_kurangi_stok
AFTER INSERT ON peminjaman
FOR EACH ROW
BEGIN
    UPDATE buku
    SET stok_tersedia = stok_tersedia - 1
    WHERE id_buku = NEW.id_buku;
END$$
DELIMITER ;

-- Trigger 2: Tambah stok kembali dan buat denda otomatis saat buku dikembalikan
DELIMITER $$
CREATE TRIGGER trg_kembali_update
AFTER UPDATE ON peminjaman
FOR EACH ROW
BEGIN
    DECLARE v_hari_terlambat INT;

    -- Hanya proses jika status berubah menjadi 'dikembalikan'
    IF NEW.status = 'dikembalikan' AND OLD.status != 'dikembalikan' THEN

        -- Tambah stok buku
        UPDATE buku
        SET stok_tersedia = stok_tersedia + 1
        WHERE id_buku = NEW.id_buku;

        -- Hitung keterlambatan
        SET v_hari_terlambat = DATEDIFF(NEW.tanggal_kembali_aktual, NEW.tanggal_kembali);

        -- Buat record denda jika terlambat dan belum ada denda
        IF v_hari_terlambat > 0 THEN
            INSERT INTO denda (id_pinjam, jumlah_hari, tarif_per_hari, total_denda, status_bayar)
            VALUES (
                NEW.id_pinjam,
                v_hari_terlambat,
                1000,
                v_hari_terlambat * 1000,
                'belum'
            )
            ON DUPLICATE KEY UPDATE
                jumlah_hari   = v_hari_terlambat,
                total_denda   = v_hari_terlambat * 1000;
        END IF;
    END IF;
END$$
DELIMITER ;

-- Trigger 3: Update status peminjaman otomatis menjadi 'terlambat' saat tanggal kembali terlewat
--            (dipanggil manual via EVENT atau di-cek tiap akses halaman)
DELIMITER $$
CREATE TRIGGER trg_validasi_buku_sebelum_pinjam
BEFORE INSERT ON peminjaman
FOR EACH ROW
BEGIN
    DECLARE v_stok INT;
    SELECT stok_tersedia INTO v_stok FROM buku WHERE id_buku = NEW.id_buku;
    IF v_stok <= 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Buku tidak tersedia, stok habis.';
    END IF;
END$$
DELIMITER ;

-- ============================================================
-- STORED PROCEDURES
-- ============================================================

-- Stored Procedure 1: Proses pengembalian buku
DELIMITER $$
CREATE PROCEDURE sp_kembalikan_buku(
    IN  p_kode_pinjam VARCHAR(20),
    IN  p_tanggal_aktual DATE,
    OUT p_status      VARCHAR(100),
    OUT p_denda       DECIMAL(10,2)
)
BEGIN
    DECLARE v_id_pinjam INT;
    DECLARE v_tgl_kembali DATE;
    DECLARE v_hari_terlambat INT;
    DECLARE v_status_saat_ini VARCHAR(20);

    -- Cari data peminjaman
    SELECT id_pinjam, tanggal_kembali, status
    INTO v_id_pinjam, v_tgl_kembali, v_status_saat_ini
    FROM peminjaman
    WHERE kode_pinjam = p_kode_pinjam;

    IF v_id_pinjam IS NULL THEN
        SET p_status = 'ERROR: Kode peminjaman tidak ditemukan';
        SET p_denda  = 0;
    ELSEIF v_status_saat_ini = 'dikembalikan' THEN
        SET p_status = 'ERROR: Buku sudah dikembalikan sebelumnya';
        SET p_denda  = 0;
    ELSE
        SET v_hari_terlambat = DATEDIFF(p_tanggal_aktual, v_tgl_kembali);
        SET p_denda = IF(v_hari_terlambat > 0, v_hari_terlambat * 1000, 0);

        -- Update status peminjaman (trigger akan handle stok & denda)
        UPDATE peminjaman
        SET tanggal_kembali_aktual = p_tanggal_aktual,
            status = 'dikembalikan'
        WHERE id_pinjam = v_id_pinjam;

        IF v_hari_terlambat > 0 THEN
            SET p_status = CONCAT('OK: Terlambat ', v_hari_terlambat, ' hari');
        ELSE
            SET p_status = 'OK: Tepat waktu';
        END IF;
    END IF;
END$$
DELIMITER ;

-- Stored Procedure 2: Generate laporan statistik bulanan
DELIMITER $$
CREATE PROCEDURE sp_laporan_bulanan(
    IN p_bulan INT,
    IN p_tahun INT
)
BEGIN
    -- Total peminjaman bulan ini
    SELECT
        COUNT(*)                                                  AS total_peminjaman,
        SUM(CASE WHEN status = 'dikembalikan' THEN 1 ELSE 0 END) AS sudah_kembali,
        SUM(CASE WHEN status = 'dipinjam'     THEN 1 ELSE 0 END) AS masih_dipinjam,
        SUM(CASE WHEN status = 'terlambat'    THEN 1 ELSE 0 END) AS terlambat
    FROM peminjaman
    WHERE MONTH(tanggal_pinjam) = p_bulan
      AND YEAR(tanggal_pinjam)  = p_tahun;

    -- Buku paling banyak dipinjam bulan ini
    SELECT
        b.kode_buku,
        b.judul,
        b.pengarang,
        COUNT(p.id_pinjam) AS jumlah_dipinjam
    FROM peminjaman p
    JOIN buku b ON p.id_buku = b.id_buku
    WHERE MONTH(p.tanggal_pinjam) = p_bulan
      AND YEAR(p.tanggal_pinjam)  = p_tahun
    GROUP BY b.id_buku, b.kode_buku, b.judul, b.pengarang
    ORDER BY jumlah_dipinjam DESC
    LIMIT 5;

    -- Total denda bulan ini
    SELECT
        IFNULL(SUM(d.total_denda), 0)                                          AS total_denda,
        IFNULL(SUM(CASE WHEN d.status_bayar='sudah' THEN d.total_denda END),0) AS denda_terbayar,
        IFNULL(SUM(CASE WHEN d.status_bayar='belum' THEN d.total_denda END),0) AS denda_tertunggak
    FROM denda d
    JOIN peminjaman p ON d.id_pinjam = p.id_pinjam
    WHERE MONTH(p.tanggal_pinjam) = p_bulan
      AND YEAR(p.tanggal_pinjam)  = p_tahun;
END$$
DELIMITER ;

-- Stored Procedure 3: Daftarkan anggota baru dengan generate kode otomatis
DELIMITER $$
CREATE PROCEDURE sp_daftar_anggota(
    IN  p_nama    VARCHAR(150),
    IN  p_email   VARCHAR(150),
    IN  p_telepon VARCHAR(20),
    IN  p_alamat  TEXT,
    OUT p_kode    VARCHAR(20),
    OUT p_pesan   VARCHAR(255)
)
BEGIN
    DECLARE v_count INT;
    DECLARE v_tahun CHAR(4);

    SET v_tahun = YEAR(CURRENT_DATE);

    -- Generate kode anggota: AGT-YYYY-XXXX
    SELECT COUNT(*) + 1 INTO v_count FROM anggota
    WHERE YEAR(created_at) = v_tahun;

    SET p_kode = CONCAT('AGT-', v_tahun, '-', LPAD(v_count, 4, '0'));

    INSERT INTO anggota (kode_anggota, nama, email, telepon, alamat)
    VALUES (p_kode, p_nama, p_email, p_telepon, p_alamat);

    SET p_pesan = CONCAT('Anggota berhasil didaftarkan dengan kode: ', p_kode);
END$$
DELIMITER ;

-- ============================================================
-- CURSOR — Digunakan di dalam Stored Procedure
-- Fungsi: Update status peminjaman yang melewati tanggal kembali
-- ============================================================
DELIMITER $$
CREATE PROCEDURE sp_update_status_terlambat()
BEGIN
    DECLARE v_selesai      INT DEFAULT 0;
    DECLARE v_id_pinjam    INT;
    DECLARE v_kode_pinjam  VARCHAR(20);
    DECLARE v_jumlah_update INT DEFAULT 0;

    -- Cursor: ambil semua peminjaman aktif yang sudah melewati tanggal kembali
    DECLARE cur_terlambat CURSOR FOR
        SELECT id_pinjam, kode_pinjam
        FROM peminjaman
        WHERE status = 'dipinjam'
          AND tanggal_kembali < CURRENT_DATE;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_selesai = 1;

    OPEN cur_terlambat;

    loop_cursor: LOOP
        FETCH cur_terlambat INTO v_id_pinjam, v_kode_pinjam;

        IF v_selesai = 1 THEN
            LEAVE loop_cursor;
        END IF;

        -- Update status menjadi 'terlambat'
        UPDATE peminjaman
        SET status = 'terlambat'
        WHERE id_pinjam = v_id_pinjam;

        SET v_jumlah_update = v_jumlah_update + 1;
    END LOOP;

    CLOSE cur_terlambat;

    -- Kembalikan info hasil update
    SELECT v_jumlah_update AS jumlah_diupdate,
           CONCAT(v_jumlah_update, ' peminjaman diperbarui menjadi terlambat') AS pesan;
END$$
DELIMITER ;

-- ============================================================
-- SAMPLE DATA
-- ============================================================

INSERT INTO kategori (nama_kategori, deskripsi) VALUES
('Fiksi',           'Novel, cerpen, dan karya fiksi lainnya'),
('Non-Fiksi',       'Biografi, ensiklopedia, referensi'),
('Sains & Teknologi','Buku ilmu pengetahuan dan teknologi'),
('Pendidikan',      'Buku pelajaran dan akademik'),
('Sejarah',         'Buku sejarah lokal maupun internasional');

INSERT INTO buku (kode_buku, judul, pengarang, penerbit, tahun_terbit, id_kategori, stok_total, stok_tersedia) VALUES
('BK-001','Laskar Pelangi',        'Andrea Hirata',      'Bentang Pustaka',  2005, 1, 3, 3),
('BK-002','Bumi Manusia',          'Pramoedya A. Toer',  'Hasta Mitra',      1980, 1, 2, 2),
('BK-003','Sapiens',               'Yuval Noah Harari',  'KPG',              2011, 5, 2, 2),
('BK-004','Clean Code',            'Robert C. Martin',   'Prentice Hall',    2008, 3, 3, 3),
('BK-005','Atomic Habits',         'James Clear',        'Avery',            2018, 2, 4, 4),
('BK-006','Harry Potter & Batu Bertuah','J.K. Rowling',  'Gramedia',         1997, 1, 2, 2),
('BK-007','Sejarah Indonesia Modern','M.C. Ricklefs',    'Gadjah Mada UP',   2001, 5, 2, 2),
('BK-008','Algoritma & Pemrograman','Rinaldi Munir',     'Informatika',      2016, 3, 3, 3);

INSERT INTO anggota (kode_anggota, nama, email, telepon, alamat) VALUES
('AGT-2024-0001','Budi Santoso',    'budi@email.com',   '081234567890', 'Jl. Merdeka No.1, Surabaya'),
('AGT-2024-0002','Siti Rahayu',     'siti@email.com',   '081234567891', 'Jl. Pahlawan No.5, Sidoarjo'),
('AGT-2024-0003','Ahmad Fauzi',     'ahmad@email.com',  '081234567892', 'Jl. Diponegoro No.10, Surabaya'),
('AGT-2024-0004','Dewi Lestari',    'dewi@email.com',   '081234567893', 'Jl. Sudirman No.3, Malang'),
('AGT-2024-0005','Rizky Pratama',   'rizky@email.com',  '081234567894', 'Jl. Gatot Subroto No.7, Surabaya');
