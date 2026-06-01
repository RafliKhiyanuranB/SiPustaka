<?php
// includes/header.php
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($page_title ?? 'Perpustakaan') ?> — SiPustaka</title>
<link rel="stylesheet" href="<?= $base_url ?? '../' ?>assets/css/style.css">
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="logo-icon">📚</div>
        <h2>SiPustaka</h2>
        <small>Sistem Informasi Perpustakaan</small>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-label">Menu Utama</div>
        <a href="<?= $base_url ?? '../' ?>index.php" class="<?= $current_page === 'index' ? 'active' : '' ?>">
            <span class="icon">🏠</span> Dashboard
        </a>

        <div class="nav-label">Manajemen</div>
        <a href="<?= $base_url ?? '../' ?>pages/buku.php" class="<?= $current_page === 'buku' ? 'active' : '' ?>">
            <span class="icon">📖</span> Manajemen Buku
        </a>
        <a href="<?= $base_url ?? '../' ?>pages/anggota.php" class="<?= $current_page === 'anggota' ? 'active' : '' ?>">
            <span class="icon">👥</span> Manajemen Anggota
        </a>
        <a href="<?= $base_url ?? '../' ?>pages/peminjaman.php" class="<?= $current_page === 'peminjaman' ? 'active' : '' ?>">
            <span class="icon">🔄</span> Peminjaman
        </a>
        <a href="<?= $base_url ?? '../' ?>pages/pengembalian.php" class="<?= $current_page === 'pengembalian' ? 'active' : '' ?>">
            <span class="icon">↩️</span> Pengembalian
        </a>

        <div class="nav-label">Keuangan</div>
        <a href="<?= $base_url ?? '../' ?>pages/denda.php" class="<?= $current_page === 'denda' ? 'active' : '' ?>">
            <span class="icon">💰</span> Denda
        </a>

        <div class="nav-label">Laporan</div>
        <a href="<?= $base_url ?? '../' ?>pages/laporan.php" class="<?= $current_page === 'laporan' ? 'active' : '' ?>">
            <span class="icon">📊</span> Laporan Bulanan
        </a>
    </nav>

    <div class="sidebar-footer">
        &copy; <?= date('Y') ?> SiPustaka v1.0
    </div>
</aside>

<!-- MAIN -->
<div class="main">
    <div class="topbar">
        <div>
            <h1><?= htmlspecialchars($page_title ?? 'Dashboard') ?></h1>
            <div class="breadcrumb">SiPustaka &rsaquo; <?= htmlspecialchars($page_title ?? 'Dashboard') ?></div>
        </div>
        <div class="topbar-right">
            <form method="POST" action="<?= $base_url ?? '../' ?>pages/actions/update_status.php" style="display:inline">
                <button type="submit" class="btn-update-status">
                    🔄 Update Status Terlambat
                </button>
            </form>
        </div>
    </div>
    <div class="content">
