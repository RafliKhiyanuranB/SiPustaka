<?php
// config/db_helpers.php — helper query & tampilan (hindari masalah collation)

/** Stok habis atau terbatas — pakai angka, bukan status_stok string */
function sql_stok_rendah(): string
{
    return '(stok_tersedia = 0 OR (stok_tersedia > 0 AND stok_tersedia <= 2))';
}

/** Peminjaman yang belum selesai */
function sql_peminjaman_aktif(): string
{
    return "(status = 'dipinjam' OR status = 'terlambat')";
}

function stok_badge_class(int $stok_tersedia): string
{
    if ($stok_tersedia === 0) {
        return 'badge-danger';
    }
    if ($stok_tersedia <= 2) {
        return 'badge-warning';
    }
    return 'badge-success';
}

function stok_label(int $stok_tersedia): string
{
    if ($stok_tersedia === 0) {
        return 'Habis';
    }
    if ($stok_tersedia <= 2) {
        return 'Terbatas';
    }
    return 'Tersedia';
}

/**
 * Ambil data laporan bulanan dari sp_laporan_bulanan (3 result set).
 *
 * @return array{stat_pinjam: array, buku_populer: mysqli_result|false, stat_denda: array, error: ?string}
 */
function fetch_laporan_bulanan(mysqli $db, int $bulan, int $tahun): array
{
    $defaults_pinjam = [
        'total_peminjaman' => 0,
        'sudah_kembali'    => 0,
        'masih_dipinjam'   => 0,
        'terlambat'        => 0,
    ];
    $defaults_denda = [
        'total_denda'      => 0,
        'denda_terbayar'   => 0,
        'denda_tertunggak' => 0,
    ];

    $bulan = max(1, min(12, $bulan));
    $tahun = max(2000, min(2100, $tahun));

    $first = $db->query("CALL sp_laporan_bulanan($bulan, $tahun)");
    if ($first === false) {
        return laporan_bulanan_fallback($db, $bulan, $tahun, $defaults_pinjam, $defaults_denda, $db->error);
    }

    $stat_pinjam = array_merge($defaults_pinjam, $first->fetch_assoc() ?: []);
    $first->free();

    $buku_populer = false;
    if ($db->next_result()) {
        $buku_populer = $db->store_result() ?: false;
    }

    $stat_denda = $defaults_denda;
    if ($db->next_result()) {
        $third = $db->store_result();
        if ($third) {
            $stat_denda = array_merge($defaults_denda, $third->fetch_assoc() ?: []);
            $third->free();
        }
    }

    while ($db->more_results()) {
        $db->next_result();
        if ($extra = $db->store_result()) {
            $extra->free();
        }
    }

    return [
        'stat_pinjam'  => $stat_pinjam,
        'buku_populer' => $buku_populer,
        'stat_denda'   => $stat_denda,
        'error'        => null,
    ];
}

/** @internal */
function laporan_bulanan_fallback(
    mysqli $db,
    int $bulan,
    int $tahun,
    array $defaults_pinjam,
    array $defaults_denda,
    string $sp_error
): array {
    $stat_pinjam = $defaults_pinjam;
    $stat_denda  = $defaults_denda;

    $stmt = $db->prepare("
        SELECT
            COUNT(*) AS total_peminjaman,
            SUM(CASE WHEN status = 'dikembalikan' THEN 1 ELSE 0 END) AS sudah_kembali,
            SUM(CASE WHEN status = 'dipinjam'     THEN 1 ELSE 0 END) AS masih_dipinjam,
            SUM(CASE WHEN status = 'terlambat'    THEN 1 ELSE 0 END) AS terlambat
        FROM peminjaman
        WHERE MONTH(tanggal_pinjam) = ? AND YEAR(tanggal_pinjam) = ?
    ");
    if ($stmt) {
        $stmt->bind_param('ii', $bulan, $tahun);
        $stmt->execute();
        $stat_pinjam = array_merge($defaults_pinjam, $stmt->get_result()->fetch_assoc() ?: []);
        $stmt->close();
    }

    $buku_populer = $db->prepare("
        SELECT b.kode_buku, b.judul, b.pengarang, COUNT(p.id_pinjam) AS jumlah_dipinjam
        FROM peminjaman p
        JOIN buku b ON p.id_buku = b.id_buku
        WHERE MONTH(p.tanggal_pinjam) = ? AND YEAR(p.tanggal_pinjam) = ?
        GROUP BY b.id_buku, b.kode_buku, b.judul, b.pengarang
        ORDER BY jumlah_dipinjam DESC
        LIMIT 5
    ");
    $buku_result = false;
    if ($buku_populer) {
        $buku_populer->bind_param('ii', $bulan, $tahun);
        $buku_populer->execute();
        $buku_result = $buku_populer->get_result();
        $buku_populer->close();
    }

    $stmt = $db->prepare("
        SELECT
            IFNULL(SUM(d.total_denda), 0) AS total_denda,
            IFNULL(SUM(CASE WHEN d.status_bayar = 'sudah' THEN d.total_denda END), 0) AS denda_terbayar,
            IFNULL(SUM(CASE WHEN d.status_bayar = 'belum' THEN d.total_denda END), 0) AS denda_tertunggak
        FROM denda d
        JOIN peminjaman p ON d.id_pinjam = p.id_pinjam
        WHERE MONTH(p.tanggal_pinjam) = ? AND YEAR(p.tanggal_pinjam) = ?
    ");
    if ($stmt) {
        $stmt->bind_param('ii', $bulan, $tahun);
        $stmt->execute();
        $stat_denda = array_merge($defaults_denda, $stmt->get_result()->fetch_assoc() ?: []);
        $stmt->close();
    }

    return [
        'stat_pinjam'  => $stat_pinjam,
        'buku_populer' => $buku_result,
        'stat_denda'   => $stat_denda,
        'error'        => $sp_error !== '' ? $sp_error : null,
    ];
}
