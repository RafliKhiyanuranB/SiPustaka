-- Jalankan sekali di HeidiSQL / phpMyAdmin (Laragon) jika error collation masih muncul
USE db_perpustakaan;

SET NAMES utf8mb4 COLLATE utf8mb4_0900_ai_ci;

-- Perbarui view stok buku agar kolom status_stok punya collation konsisten
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
