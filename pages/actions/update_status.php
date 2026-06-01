<?php
// pages/actions/update_status.php
session_start();
require_once '../../config/database.php';

$db = getDB();

// Panggil Stored Procedure yang menggunakan CURSOR
// sp_update_status_terlambat() akan iterasi semua peminjaman aktif yang terlambat
$db->query("CALL sp_update_status_terlambat()");
$result = $db->store_result();
$row    = $result ? $result->fetch_assoc() : null;

if ($row) {
    $_SESSION['msg']      = "✅ Cursor selesai dijalankan. " . htmlspecialchars($row['pesan']);
    $_SESSION['msg_type'] = 'success';
} else {
    $_SESSION['msg']      = "Stored Procedure cursor dijalankan (tidak ada data baru).";
    $_SESSION['msg_type'] = 'info';
}

// Redirect kembali ke halaman sebelumnya
$referer = $_SERVER['HTTP_REFERER'] ?? '../../index.php';
header("Location: $referer");
exit;
