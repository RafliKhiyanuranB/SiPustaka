<?php
// index.php
require_once 'config/database.php';

$page_title = 'Dashboard';
$base_url   = './';

$db = getDB();

// Ambil statistik dari VIEW v_statistik
$stats = $db->query("SELECT * FROM v_statistik")->fetch_assoc();

// Peminjaman terbaru dari VIEW v_peminjaman_lengkap
$recent = $db->query("
    SELECT * FROM v_peminjaman_lengkap
    ORDER BY id_pinjam DESC
    LIMIT 8
");

// Buku stok terbatas dari VIEW v_stok_buku
$stok_terbatas = $db->query("
    SELECT * FROM v_stok_buku
    WHERE stok_tersedia = 0 OR (stok_tersedia > 0 AND stok_tersedia <= 2)
    ORDER BY stok_tersedia ASC
    LIMIT 5
");

include 'includes/header.php';
?>

<!-- Alert dari session -->
<?php if (!empty($_SESSION['msg'])): ?>
    <div class="alert alert-<?= $_SESSION['msg_type'] ?? 'info' ?>">
        <?= htmlspecialchars($_SESSION['msg']) ?>
    </div>
    <?php unset($_SESSION['msg'], $_SESSION['msg_type']); ?>
<?php endif; ?>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">📚</div>
        <div class="stat-value"><?= number_format($stats['total_buku'] ?? 0) ?></div>
        <div class="stat-label">Total Koleksi Buku</div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon">👥</div>
        <div class="stat-value"><?= number_format($stats['total_anggota_aktif'] ?? 0) ?></div>
        <div class="stat-label">Anggota Aktif</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon">🔄</div>
        <div class="stat-value"><?= number_format($stats['sedang_dipinjam'] ?? 0) ?></div>
        <div class="stat-label">Sedang Dipinjam</div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon">⚠️</div>
        <div class="stat-value"><?= number_format($stats['terlambat'] ?? 0) ?></div>
        <div class="stat-label">Terlambat Kembali</div>
    </div>
    <div class="stat-card orange">
        <div class="stat-icon">💰</div>
        <div class="stat-value">Rp<?= number_format($stats['total_denda_belum_bayar'] ?? 0, 0, ',', '.') ?></div>
        <div class="stat-label">Denda Belum Bayar</div>
    </div>
</div>

<div style="display:grid; grid-template-columns:1fr 320px; gap:24px; align-items:start;">

    <!-- Peminjaman Terbaru -->
    <div class="card">
        <div class="card-header">
            <h3>📋 Peminjaman Terbaru</h3>
            <a href="pages/peminjaman.php" class="btn btn-outline btn-sm">Lihat Semua</a>
        </div>
        <div class="card-body">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Anggota</th>
                            <th>Buku</th>
                            <th>Tgl Kembali</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($recent && $recent->num_rows > 0):
                        while ($row = $recent->fetch_assoc()): ?>
                        <tr>
                            <td><code style="font-size:.75rem;background:var(--bg);padding:2px 6px;border-radius:4px"><?= htmlspecialchars($row['kode_pinjam']) ?></code></td>
                            <td><?= htmlspecialchars($row['nama_anggota']) ?></td>
                            <td style="max-width:160px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($row['judul_buku']) ?></td>
                            <td style="white-space:nowrap"><?= date('d/m/Y', strtotime($row['tanggal_kembali'])) ?></td>
                            <td>
                                <?php
                                $s = $row['status'];
                                $map = ['dipinjam'=>'badge-info','dikembalikan'=>'badge-success','terlambat'=>'badge-danger'];
                                $label = ['dipinjam'=>'Dipinjam','dikembalikan'=>'Dikembalikan','terlambat'=>'Terlambat'];
                                ?>
                                <span class="badge <?= $map[$s] ?? 'badge-muted' ?>"><?= $label[$s] ?? $s ?></span>
                            </td>
                        </tr>
                        <?php endwhile;
                    else: ?>
                        <tr><td colspan="5"><div class="empty-state"><div class="icon">📭</div><p>Belum ada peminjaman</p></div></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Buku Stok Terbatas -->
    <div class="card">
        <div class="card-header">
            <h3>⚠️ Stok Terbatas</h3>
        </div>
        <div class="card-body" style="padding:0">
            <?php if ($stok_terbatas && $stok_terbatas->num_rows > 0):
                while ($bk = $stok_terbatas->fetch_assoc()): ?>
            <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;gap:8px">
                <div>
                    <div style="font-size:.85rem;font-weight:500;line-height:1.3"><?= htmlspecialchars($bk['judul']) ?></div>
                    <div style="font-size:.72rem;color:var(--text-muted)"><?= htmlspecialchars($bk['pengarang']) ?></div>
                </div>
                <span class="badge <?= $bk['status_stok']==='Habis' ? 'badge-danger' : 'badge-warning' ?>">
                    <?= $bk['stok_tersedia'] ?>/<?= $bk['stok_total'] ?>
                </span>
            </div>
            <?php endwhile;
            else: ?>
            <div class="empty-state" style="padding:30px"><div class="icon">✅</div><p>Semua stok aman</p></div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php include 'includes/footer.php'; ?>
