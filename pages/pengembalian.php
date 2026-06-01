<?php
// pages/pengembalian.php
session_start();
require_once '../config/database.php';

$page_title = 'Pengembalian Buku';
$base_url   = '../';
$db = getDB();

$msg = ''; $msg_type = 'success';
$detail_pinjam = null;
$kode_pinjam   = trim($_GET['kode'] ?? '');

// Cari data peminjaman jika kode diberikan
if ($kode_pinjam) {
    $stmt = $db->prepare("SELECT * FROM v_peminjaman_lengkap WHERE kode_pinjam = ?");
    $stmt->bind_param('s', $kode_pinjam);
    $stmt->execute();
    $detail_pinjam = $stmt->get_result()->fetch_assoc();
}

// Proses pengembalian via Stored Procedure sp_kembalikan_buku
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'kembalikan') {
    $kode         = trim($_POST['kode_pinjam'] ?? '');
    $tgl_aktual   = $_POST['tanggal_aktual'] ?? date('Y-m-d');

    // Panggil Stored Procedure
    $stmt = $db->prepare("CALL sp_kembalikan_buku(?, ?, @status, @denda)");
    $stmt->bind_param('ss', $kode, $tgl_aktual);
    $stmt->execute();
    $result = $db->query("SELECT @status AS status, @denda AS denda")->fetch_assoc();

    if (str_starts_with($result['status'], 'OK')) {
        $denda_rp = number_format($result['denda'], 0, ',', '.');
        $msg = "✅ {$result['status']}. " . ($result['denda'] > 0 ? "Denda: Rp{$denda_rp}" : "Tidak ada denda.");
    } else {
        $msg = $result['status']; $msg_type = 'danger';
    }
    $kode_pinjam = '';
    $detail_pinjam = null;
}

// Daftar peminjaman aktif (belum dikembalikan) dari VIEW
$aktif_list = $db->query('
    SELECT * FROM v_peminjaman_lengkap
    WHERE ' . sql_peminjaman_aktif() . '
    ORDER BY tanggal_kembali ASC
');

include '../includes/header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="mb-6">
    <h2 class="section-title">Pengembalian Buku</h2>
    <p class="section-subtitle">Proses pengembalian via <code>sp_kembalikan_buku</code> — denda dihitung & stok ditambah otomatis</p>
</div>

<div style="display:grid;grid-template-columns:1fr 380px;gap:24px;align-items:start">

    <!-- Form Pengembalian -->
    <div>
        <!-- Cari Peminjaman -->
        <div class="card mb-4">
            <div class="card-header"><h3>🔍 Cari Peminjaman</h3></div>
            <div class="card-body" style="padding:20px 24px">
                <form class="search-bar" method="GET">
                    <input type="text" name="kode" placeholder="Masukkan kode peminjaman (PJM-...)" value="<?= htmlspecialchars($kode_pinjam) ?>" style="flex:1">
                    <button type="submit" class="btn btn-primary">🔍 Cari</button>
                </form>
            </div>
        </div>

        <!-- Detail Peminjaman -->
        <?php if ($detail_pinjam): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h3>📋 Detail Peminjaman</h3>
                <?php
                $s = $detail_pinjam['status'];
                $map = ['dipinjam'=>'badge-info','terlambat'=>'badge-danger','dikembalikan'=>'badge-success'];
                echo '<span class="badge '.($map[$s]??'badge-muted').'">'.ucfirst($s).'</span>';
                ?>
            </div>
            <div class="card-body" style="padding:20px 24px">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">
                    <div>
                        <div style="font-size:.72rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:4px">Anggota</div>
                        <div style="font-weight:500"><?= htmlspecialchars($detail_pinjam['nama_anggota']) ?></div>
                        <div style="font-size:.8rem;color:var(--text-muted)"><?= htmlspecialchars($detail_pinjam['kode_anggota']) ?> · <?= htmlspecialchars($detail_pinjam['telepon'] ?? '-') ?></div>
                    </div>
                    <div>
                        <div style="font-size:.72rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:4px">Buku</div>
                        <div style="font-weight:500"><?= htmlspecialchars($detail_pinjam['judul_buku']) ?></div>
                        <div style="font-size:.8rem;color:var(--text-muted)"><?= htmlspecialchars($detail_pinjam['pengarang']) ?></div>
                    </div>
                    <div>
                        <div style="font-size:.72rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:4px">Tanggal Pinjam</div>
                        <div><?= date('d MMMM Y', strtotime($detail_pinjam['tanggal_pinjam'])) ?></div>
                    </div>
                    <div>
                        <div style="font-size:.72rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:4px">Batas Kembali</div>
                        <div style="font-weight:600;color:<?= $detail_pinjam['status']==='terlambat' ? 'var(--danger)' : 'var(--text)' ?>">
                            <?= date('d/m/Y', strtotime($detail_pinjam['tanggal_kembali'])) ?>
                            <?php if ($detail_pinjam['keterlambatan_hari'] > 0): ?>
                            <span class="badge badge-danger" style="margin-left:8px">+<?= $detail_pinjam['keterlambatan_hari'] ?> hari</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if ($detail_pinjam['status'] !== 'dikembalikan'): ?>
                <!-- Form kembalikan -->
                <div style="border-top:1px solid var(--border);padding-top:16px">
                    <?php
                    $hari_terlambat = intval($detail_pinjam['keterlambatan_hari']);
                    $estimasi_denda = $hari_terlambat > 0 ? $hari_terlambat * 1000 : 0;
                    ?>
                    <?php if ($hari_terlambat > 0): ?>
                    <div class="alert alert-danger" style="margin-bottom:16px">
                        ⚠️ Terlambat <strong><?= $hari_terlambat ?> hari</strong>.
                        Estimasi denda: <strong>Rp<?= number_format($estimasi_denda, 0, ',', '.') ?></strong>
                    </div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="action" value="kembalikan">
                        <input type="hidden" name="kode_pinjam" value="<?= htmlspecialchars($detail_pinjam['kode_pinjam']) ?>">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Tanggal Pengembalian Aktual *</label>
                                <input type="date" name="tanggal_aktual" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                        <div style="margin-top:12px">
                            <div class="alert alert-info">
                                🔄 Stored Procedure <code>sp_kembalikan_buku</code> akan: hitung denda → update status → kembalikan stok (via trigger)
                            </div>
                            <button type="submit" class="btn btn-success" onclick="return confirm('Proses pengembalian buku ini?')">
                                ↩️ Proses Pengembalian
                            </button>
                        </div>
                    </form>
                </div>
                <?php else: ?>
                <div class="alert alert-success">✅ Buku ini sudah dikembalikan.</div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar: Daftar Belum Kembali -->
    <div class="card">
        <div class="card-header"><h3>⏳ Belum Dikembalikan</h3></div>
        <div class="card-body" style="padding:0">
            <?php if ($aktif_list && $aktif_list->num_rows > 0):
                while ($p = $aktif_list->fetch_assoc()):
                    $terlambat = $p['status'] === 'terlambat';
            ?>
            <div style="padding:12px 16px;border-bottom:1px solid var(--border);<?= $terlambat ? 'background:#fff5f5' : '' ?>">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px">
                    <div style="flex:1;min-width:0">
                        <div style="font-size:.8rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($p['nama_anggota']) ?></div>
                        <div style="font-size:.72rem;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($p['judul_buku']) ?></div>
                        <div style="font-size:.7rem;margin-top:2px;color:<?= $terlambat ? 'var(--danger)' : 'var(--text-muted)' ?>">
                            Kembali: <?= date('d/m/Y', strtotime($p['tanggal_kembali'])) ?>
                            <?php if ($terlambat): ?>(+<?= $p['keterlambatan_hari'] ?> hari)<?php endif; ?>
                        </div>
                    </div>
                    <a href="pengembalian.php?kode=<?= urlencode($p['kode_pinjam']) ?>" class="btn btn-sm <?= $terlambat ? 'btn-danger' : 'btn-outline' ?>" style="flex-shrink:0">Pilih</a>
                </div>
            </div>
            <?php endwhile;
            else: ?>
            <div class="empty-state" style="padding:30px">
                <div class="icon">✅</div><p>Tidak ada yang belum kembali</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
