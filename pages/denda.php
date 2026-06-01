<?php
// pages/denda.php
session_start();
require_once '../config/database.php';

$page_title = 'Manajemen Denda';
$base_url   = '../';
$db = getDB();

$msg = ''; $msg_type = 'success';

// Tandai denda sebagai lunas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bayar') {
    $id_denda = intval($_POST['id_denda'] ?? 0);
    $db->query("UPDATE denda SET status_bayar='sudah', tanggal_bayar=CURDATE() WHERE id_denda=$id_denda");
    $msg = "Denda berhasil ditandai sebagai lunas.";
}

// Query denda detail dengan join
$filter = trim($_GET['status'] ?? '');
$search = trim($_GET['q'] ?? '');
$where_parts = [];
if ($filter) $where_parts[] = "d.status_bayar='".addslashes($filter)."'";
if ($search)  $where_parts[] = "(a.nama LIKE '%".addslashes($search)."%' OR a.kode_anggota LIKE '%".addslashes($search)."%')";
$where = $where_parts ? "WHERE ".implode(' AND ', $where_parts) : '';

$denda_list = $db->query("
    SELECT d.*, p.kode_pinjam, p.tanggal_kembali, p.tanggal_kembali_aktual,
           a.nama, a.kode_anggota, b.judul
    FROM denda d
    JOIN peminjaman p ON d.id_pinjam = p.id_pinjam
    JOIN anggota a    ON p.id_anggota = a.id_anggota
    JOIN buku b       ON p.id_buku    = b.id_buku
    $where
    ORDER BY d.status_bayar ASC, d.created_at DESC
");

// Ringkasan dari VIEW v_denda_anggota
$ringkasan = $db->query("
    SELECT SUM(total_denda) AS total, SUM(denda_belum_bayar) AS belum, SUM(denda_sudah_bayar) AS sudah
    FROM v_denda_anggota
")->fetch_assoc();

include '../includes/header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="mb-6">
    <h2 class="section-title">Manajemen Denda</h2>
    <p class="section-subtitle">Denda dihitung otomatis saat pengembalian via Stored Procedure. Ringkasan dari <code>v_denda_anggota</code></p>
</div>

<!-- Ringkasan Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px">
    <div class="stat-card orange">
        <div class="stat-icon">💰</div>
        <div class="stat-value">Rp<?= number_format($ringkasan['total'] ?? 0, 0, ',', '.') ?></div>
        <div class="stat-label">Total Denda Terkumpul</div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon">⏳</div>
        <div class="stat-value">Rp<?= number_format($ringkasan['belum'] ?? 0, 0, ',', '.') ?></div>
        <div class="stat-label">Belum Dibayar</div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon">✅</div>
        <div class="stat-value">Rp<?= number_format($ringkasan['sudah'] ?? 0, 0, ',', '.') ?></div>
        <div class="stat-label">Sudah Dibayar</div>
    </div>
</div>

<!-- Filter -->
<div class="card mb-4">
    <div class="card-body" style="padding:16px 24px">
        <form class="search-bar" method="GET">
            <input type="text" name="q" placeholder="Cari nama atau kode anggota..." value="<?= htmlspecialchars($search) ?>">
            <select name="status" style="border:1.5px solid var(--border);border-radius:8px;padding:8px 12px;font-family:'DM Sans',sans-serif;font-size:.85rem;outline:none">
                <option value="">Semua</option>
                <option value="belum" <?= $filter==='belum' ? 'selected' : '' ?>>Belum Bayar</option>
                <option value="sudah" <?= $filter==='sudah' ? 'selected' : '' ?>>Sudah Bayar</option>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">🔍 Filter</button>
            <a href="denda.php" class="btn btn-outline btn-sm">Reset</a>
        </form>
    </div>
</div>

<!-- Tabel Denda -->
<div class="card">
    <div class="card-header">
        <h3>💰 Detail Denda</h3>
        <small style="color:var(--text-muted);font-size:.75rem">Ringkasan per anggota dari <code>v_denda_anggota</code></small>
    </div>
    <div class="card-body">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Kode Pinjam</th>
                        <th>Anggota</th>
                        <th>Buku</th>
                        <th>Keterlambatan</th>
                        <th>Total Denda</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $no = 1;
                if ($denda_list && $denda_list->num_rows > 0):
                    while ($d = $denda_list->fetch_assoc()):
                ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><code style="font-size:.73rem;background:var(--bg);padding:2px 6px;border-radius:4px"><?= htmlspecialchars($d['kode_pinjam']) ?></code></td>
                    <td>
                        <div style="font-weight:500"><?= htmlspecialchars($d['nama']) ?></div>
                        <div style="font-size:.73rem;color:var(--text-muted)"><?= htmlspecialchars($d['kode_anggota']) ?></div>
                    </td>
                    <td style="max-width:160px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($d['judul']) ?></td>
                    <td>
                        <span style="font-weight:600;color:var(--danger)"><?= $d['jumlah_hari'] ?> hari</span>
                        <div style="font-size:.72rem;color:var(--text-muted)">Rp<?= number_format($d['tarif_per_hari'],0,',','.') ?>/hari</div>
                    </td>
                    <td style="font-weight:700;color:var(--primary)">Rp<?= number_format($d['total_denda'],0,',','.') ?></td>
                    <td>
                        <?php if ($d['status_bayar'] === 'sudah'): ?>
                        <span class="badge badge-success">Lunas</span>
                        <div style="font-size:.7rem;color:var(--text-muted)"><?= $d['tanggal_bayar'] ? date('d/m/Y', strtotime($d['tanggal_bayar'])) : '' ?></div>
                        <?php else: ?>
                        <span class="badge badge-danger">Belum Bayar</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($d['status_bayar'] === 'belum'): ?>
                        <form method="POST" onsubmit="return confirm('Tandai denda ini sebagai lunas?')">
                            <input type="hidden" name="action" value="bayar">
                            <input type="hidden" name="id_denda" value="<?= $d['id_denda'] ?>">
                            <button type="submit" class="btn btn-success btn-sm">💵 Lunas</button>
                        </form>
                        <?php else: ?>
                        <span style="color:var(--success);font-size:.8rem">✅ Lunas</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile;
                else: ?>
                <tr><td colspan="8">
                    <div class="empty-state"><div class="icon">🎉</div><p>Tidak ada data denda</p></div>
                </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Tabel Ringkasan Per Anggota dari VIEW v_denda_anggota -->
<div class="card" style="margin-top:24px">
    <div class="card-header">
        <h3>👥 Ringkasan Denda per Anggota</h3>
        <small style="color:var(--text-muted);font-size:.75rem">Sumber: <code>VIEW v_denda_anggota</code></small>
    </div>
    <div class="card-body">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th>Total Kasus</th>
                        <th>Total Denda</th>
                        <th>Belum Bayar</th>
                        <th>Sudah Bayar</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $ringk_list = $db->query("SELECT * FROM v_denda_anggota WHERE total_kasus_denda > 0 ORDER BY denda_belum_bayar DESC");
                if ($ringk_list && $ringk_list->num_rows > 0):
                    while ($r = $ringk_list->fetch_assoc()):
                ?>
                <tr>
                    <td><code style="font-size:.73rem;background:var(--bg);padding:2px 6px;border-radius:4px"><?= htmlspecialchars($r['kode_anggota']) ?></code></td>
                    <td style="font-weight:500"><?= htmlspecialchars($r['nama']) ?></td>
                    <td style="text-align:center"><?= $r['total_kasus_denda'] ?></td>
                    <td>Rp<?= number_format($r['total_denda'],0,',','.') ?></td>
                    <td style="color:<?= $r['denda_belum_bayar'] > 0 ? 'var(--danger)' : 'inherit' ?>;font-weight:<?= $r['denda_belum_bayar'] > 0 ? '700' : '400' ?>">
                        Rp<?= number_format($r['denda_belum_bayar'],0,',','.') ?>
                    </td>
                    <td style="color:var(--success)">Rp<?= number_format($r['denda_sudah_bayar'],0,',','.') ?></td>
                </tr>
                <?php endwhile;
                else: ?>
                <tr><td colspan="6"><div class="empty-state" style="padding:24px"><div class="icon">🎉</div><p>Tidak ada denda</p></div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
