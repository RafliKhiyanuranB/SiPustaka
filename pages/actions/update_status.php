<?php
// pages/actions/update_status.php
session_start();
require_once '../../config/database.php';

$db = getDB();

// Panggil Stored Procedure yang menggunakan CURSOR
// sp_update_status_terlambat() akan iterasi semua peminjaman aktif yang terlambat
$result = $db->query('CALL sp_update_status_terlambat()');

if ($result === false) {
    $_SESSION['msg']      = 'Gagal memperbarui status: ' . $db->error;
    $_SESSION['msg_type'] = 'danger';
} else {
    $row = $result->fetch_assoc();
    $result->free();
    while ($db->more_results()) {
        $db->next_result();
        if ($extra = $db->store_result()) {
            $extra->free();
        }
    }

    if ($row && !empty($row['pesan'])) {
        $_SESSION['msg']      = '✅ ' . htmlspecialchars($row['pesan']);
        $_SESSION['msg_type'] = 'success';
    } else {
        $_SESSION['msg']      = 'Tidak ada peminjaman yang perlu diperbarui.';
        $_SESSION['msg_type'] = 'info';
    }
}

// Redirect kembali ke halaman sebelumnya
$referer = $_SERVER['HTTP_REFERER'] ?? '../../index.php';
header("Location: $referer");
exit;
