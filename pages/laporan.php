<?php
// pages/laporan.php
session_start();
require_once '../config/database.php';

$page_title = 'Laporan Bulanan';
$base_url   = '../';
$db = getDB();

$bulan = intval($_GET['bulan'] ?? date('n'));
$tahun = intval($_GET['tahun'] ?? date('Y'));
$nama_bulan = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

// Panggil Stored Procedure sp_laporan_bulanan — mengembalikan 3 result set
$db->query("CALL sp_laporan_bulanan($bulan, $tahun)");

// Result set 1: Statistik peminjaman
$stat_pinjam = $db->store_result()->fetch_assoc();
$db->next_result();

// Result set 2: Buku terpopuler
$buku_populer = $db->store_result();
$db->next_result();

// Result set 3: Statistik denda
$stat_denda = $db->store_result()->fetch_assoc();

include '../includes/header.php';
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="section-title">Laporan Bulanan</h2>
        <p class="section-subtitle">Data dari <code>sp_laporan_bulanan()</code> (Stored Procedure) — Periode: <?= $nama_bulan[$bulan] ?> <?= $tahun ?></p>
    </div>
</div>

<!-- Filter Periode -->
<div class="card mb-6">
    <div class="card-body" style="padding:16px 24px">
        <form class="search-bar" method="GET">
            <label style="font-size:.82rem;font-weight:600;color:var(--text-muted)">Periode:</label>
            <select name="bulan" style="border:1.5px solid var(--border);border-radius:8px;padding:8px 12px;font-family:'DM Sans',sans-serif;font-size:.85rem;outline:none">
                <?php for ($i=1; $i<=12; $i++): ?>
                <option value="<?= $i ?>" <?= $i===$bulan ? 'selected' : '' ?>><?= $nama_bulan[$i] ?></option>
                <?php endfor; ?>
            </select>
            <input type="number" name="tahun" value="<?= $tahun ?>" min="2020" max="<?= date('Y') ?>" style="border:1.5px solid var(--border);border-radius:8px;padding:8px 12px;font-family:'DM Sans',sans-serif;font-size:.85rem;width:90px;outline:none">
            <button type="submit" class="btn btn-primary btn-sm">📊 Tampilkan</button>
        </form>
    </div>
</div>

<!-- Statistik Peminjaman -->
<div class="stats-grid mb-6">
    <div class="stat-card blue">
        <div class="stat-icon">🔄</div>
        <div class="stat-value"><?= $stat_pinjam['total_peminjaman'] ?? 0 ?></div>
        <div class="stat-label">Total Peminjaman</div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon">↩️</div>
        <div class="stat-value"><?= $stat_pinjam['sudah_kembali'] ?? 0 ?></div>
        <div class="stat-label">Sudah Dikembalikan</div>
    </div>
    <div class="stat-card orange">
        <div class="stat-icon">📖</div>
        <div class="stat-value"><?= $stat_pinjam['masih_dipinjam'] ?? 0 ?></div>
        <div class="stat-label">Masih Dipinjam</div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon">⚠️</div>
        <div class="stat-value"><?= $stat_pinjam['terlambat'] ?? 0 ?></div>
        <div class="stat-label">Terlambat</div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start">

    <!-- Buku Terpopuler -->
    <div class="card">
        <div class="card-header"><h3>🏆 Buku Paling Banyak Dipinjam</h3></div>
        <div class="card-body">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Kode</th>
                            <th>Judul</th>
                            <th>Pengarang</th>
                            <th>Dipinjam</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $rank = 1;
                    $medals = ['🥇','🥈','🥉','4️⃣','5️⃣'];
                    if ($buku_populer && $buku_populer->num_rows > 0):
                        while ($b = $buku_populer->fetch_assoc()):
                    ?>
                    <tr>
                        <td style="font-size:1.1rem;text-align:center"><?= $medals[$rank-1] ?? $rank ?></td>
                        <td><code style="font-size:.73rem;background:var(--bg);padding:2px 6px;border-radius:4px"><?= htmlspecialchars($b['kode_buku']) ?></code></td>
                        <td style="font-weight:500"><?= htmlspecialchars($b['judul']) ?></td>
                        <td style="color:var(--text-muted)"><?= htmlspecialchars($b['pengarang']) ?></td>
                        <td>
                            <span style="font-weight:700;color:var(--accent)"><?= $b['jumlah_dipinjam'] ?>×</span>
                        </td>
                    </tr>
                    <?php $rank++; endwhile;
                    else: ?>
                    <tr><td colspan="5">
                        <div class="empty-state" style="padding:32px"><div class="icon">📭</div><p>Tidak ada data bulan ini</p></div>
                    </td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Statistik Denda -->
    <div>
        <div class="card mb-4">
            <div class="card-header"><h3>💰 Ringkasan Denda</h3></div>
            <div class="card-body" style="padding:20px 24px">
                <div style="display:flex;flex-direction:column;gap:16px">
                    <?php
                    $items = [
                        ['label'=>'Total Denda Periode Ini', 'val'=>$stat_denda['total_denda']??0, 'color'=>'var(--primary)'],
                        ['label'=>'Sudah Dibayar',           'val'=>$stat_denda['denda_terbayar']??0, 'color'=>'var(--success)'],
                        ['label'=>'Masih Tertunggak',        'val'=>$stat_denda['denda_tertunggak']??0, 'color'=>'var(--danger)'],
                    ];
                    foreach ($items as $item): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid var(--border)">
                        <span style="font-size:.85rem;color:var(--text-muted)"><?= $item['label'] ?></span>
                        <span style="font-weight:700;font-size:1rem;color:<?= $item['color'] ?>">
                            Rp<?= number_format($item['val'],0,',','.') ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Chart batang sederhana dari data peminjaman -->
        <?php if (($stat_pinjam['total_peminjaman']??0) > 0): ?>
        <div class="card">
            <div class="card-header"><h3>📊 Komposisi Status</h3></div>
            <div class="card-body" style="padding:20px 24px">
                <?php
                $total = $stat_pinjam['total_peminjaman'];
                $bars  = [
                    ['label'=>'Dikembalikan','val'=>$stat_pinjam['sudah_kembali']??0,'color'=>'var(--success)'],
                    ['label'=>'Dipinjam',    'val'=>$stat_pinjam['masih_dipinjam']??0,'color'=>'var(--info)'],
                    ['label'=>'Terlambat',   'val'=>$stat_pinjam['terlambat']??0,     'color'=>'var(--danger)'],
                ];
                foreach ($bars as $bar):
                    $pct = $total > 0 ? round(($bar['val']/$total)*100) : 0;
                ?>
                <div style="margin-bottom:14px">
                    <div style="display:flex;justify-content:space-between;font-size:.8rem;margin-bottom:4px">
                        <span><?= $bar['label'] ?></span>
                        <span style="font-weight:600"><?= $bar['val'] ?> (<?= $pct ?>%)</span>
                    </div>
                    <div style="background:var(--border);border-radius:8px;height:10px;overflow:hidden">
                        <div style="background:<?= $bar['color'] ?>;width:<?= $pct ?>%;height:100%;border-radius:8px;transition:width .4s"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
