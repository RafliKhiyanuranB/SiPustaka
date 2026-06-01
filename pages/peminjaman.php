<?php
// pages/peminjaman.php
session_start();
require_once '../config/database.php';

$page_title = 'Peminjaman';
$base_url   = '../';
$db = getDB();

$msg = ''; $msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pinjam') {
    $id_anggota = intval($_POST['id_anggota'] ?? 0);
    $id_buku    = intval($_POST['id_buku'] ?? 0);
    $tgl_pinjam = $_POST['tanggal_pinjam'] ?? date('Y-m-d');
    $durasi     = max(1, intval($_POST['durasi_hari'] ?? 7));
    $tgl_kembali = date('Y-m-d', strtotime("$tgl_pinjam +$durasi days"));
    $petugas    = trim($_POST['petugas'] ?? 'Admin');

    // Generate kode peminjaman
    $count = $db->query("SELECT COUNT(*)+1 AS n FROM peminjaman WHERE YEAR(created_at)=YEAR(NOW())")->fetch_assoc()['n'];
    $kode  = 'PJM-'.date('Y').'-'.str_pad($count, 4, '0', STR_PAD_LEFT);

    // Cek anggota aktif
    $anggota = $db->query("SELECT * FROM anggota WHERE id_anggota=$id_anggota AND status='aktif'")->fetch_assoc();
    if (!$anggota) {
        $msg = "Anggota tidak ditemukan atau nonaktif."; $msg_type = 'danger';
    } else {
        // INSERT — Trigger trg_validasi_buku_sebelum_pinjam akan cek stok
        // Trigger trg_pinjam_kurangi_stok akan kurangi stok otomatis
        $stmt = $db->prepare("
            INSERT INTO peminjaman (kode_pinjam, id_anggota, id_buku, tanggal_pinjam, tanggal_kembali, petugas)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('siisss', $kode, $id_anggota, $id_buku, $tgl_pinjam, $tgl_kembali, $petugas);

        if ($stmt->execute()) {
            $msg = "Peminjaman $kode berhasil dicatat. Stok buku dikurangi otomatis via Trigger.";
        } else {
            // Tampilkan error dari trigger jika stok habis
            $msg = "Gagal: " . $db->error;
            $msg_type = 'danger';
        }
    }
}

// Filter & query menggunakan VIEW v_peminjaman_lengkap
$status_filter = trim($_GET['status'] ?? '');
$search        = trim($_GET['q'] ?? '');
$where_parts   = [];
$status_valid = ['dipinjam', 'terlambat', 'dikembalikan'];
if ($status_filter && in_array($status_filter, $status_valid, true)) {
    $where_parts[] = "status = '" . $status_filter . "'";
}
if ($search)        $where_parts[] = "(nama_anggota LIKE '%".addslashes($search)."%' OR judul_buku LIKE '%".addslashes($search)."%' OR kode_pinjam LIKE '%".addslashes($search)."%')";
$where = $where_parts ? "WHERE ".implode(' AND ', $where_parts) : '';

$peminjaman_list = $db->query("SELECT * FROM v_peminjaman_lengkap $where ORDER BY id_pinjam DESC");

// Data untuk form dropdown
$anggota_list = $db->query("SELECT id_anggota, kode_anggota, nama FROM anggota WHERE status='aktif' ORDER BY nama");
$buku_list    = $db->query("SELECT id_buku, kode_buku, judul FROM buku WHERE stok_tersedia > 0 ORDER BY judul");

include '../includes/header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="section-title">Peminjaman Buku</h2>
        <p class="section-subtitle">Stok berkurang otomatis via <code>trg_pinjam_kurangi_stok</code> (Trigger)</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('modal-pinjam')">+ Catat Peminjaman</button>
</div>

<!-- Filter -->
<div class="card mb-4">
    <div class="card-body" style="padding:16px 24px">
        <form class="search-bar" method="GET">
            <input type="text" name="q" placeholder="Cari anggota, judul, atau kode..." value="<?= htmlspecialchars($search) ?>">
            <select name="status" style="border:1.5px solid var(--border);border-radius:8px;padding:8px 12px;font-family:'DM Sans',sans-serif;font-size:.85rem;outline:none">
                <option value="">Semua Status</option>
                <option value="dipinjam"    <?= $status_filter==='dipinjam'    ? 'selected' : '' ?>>Dipinjam</option>
                <option value="terlambat"   <?= $status_filter==='terlambat'   ? 'selected' : '' ?>>Terlambat</option>
                <option value="dikembalikan"<?= $status_filter==='dikembalikan'? 'selected' : '' ?>>Dikembalikan</option>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">🔍 Filter</button>
            <a href="peminjaman.php" class="btn btn-outline btn-sm">Reset</a>
        </form>
    </div>
</div>

<!-- Tabel menggunakan VIEW v_peminjaman_lengkap -->
<div class="card">
    <div class="card-header">
        <h3>🔄 Daftar Peminjaman</h3>
        <small style="color:var(--text-muted);font-size:.75rem">Data dari <code>v_peminjaman_lengkap</code></small>
    </div>
    <div class="card-body">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Anggota</th>
                        <th>Buku</th>
                        <th>Tgl Pinjam</th>
                        <th>Tgl Kembali</th>
                        <th>Keterlambatan</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                if ($peminjaman_list && $peminjaman_list->num_rows > 0):
                    while ($p = $peminjaman_list->fetch_assoc()):
                ?>
                <tr>
                    <td><code style="font-size:.73rem;background:var(--bg);padding:2px 6px;border-radius:4px"><?= htmlspecialchars($p['kode_pinjam']) ?></code></td>
                    <td>
                        <div style="font-weight:500"><?= htmlspecialchars($p['nama_anggota']) ?></div>
                        <div style="font-size:.73rem;color:var(--text-muted)"><?= htmlspecialchars($p['kode_anggota']) ?></div>
                    </td>
                    <td style="max-width:180px">
                        <div style="font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($p['judul_buku']) ?></div>
                        <div style="font-size:.73rem;color:var(--text-muted)"><?= htmlspecialchars($p['pengarang']) ?></div>
                    </td>
                    <td style="white-space:nowrap"><?= date('d/m/Y', strtotime($p['tanggal_pinjam'])) ?></td>
                    <td style="white-space:nowrap"><?= date('d/m/Y', strtotime($p['tanggal_kembali'])) ?></td>
                    <td>
                        <?php
                        $hari = intval($p['keterlambatan_hari']);
                        if ($p['status'] === 'dikembalikan') {
                            echo $hari > 0
                                ? "<span style='color:var(--danger);font-weight:600'>$hari hari</span>"
                                : "<span style='color:var(--success)'>Tepat waktu</span>";
                        } elseif ($p['status'] === 'terlambat') {
                            echo "<span style='color:var(--danger);font-weight:600'>$hari hari</span>";
                        } else {
                            $sisa = intval(ceil((strtotime($p['tanggal_kembali']) - time()) / 86400));
                            echo $sisa >= 0
                                ? "<span style='color:var(--info)'>{$sisa}h lagi</span>"
                                : "<span style='color:var(--danger);font-weight:600'>".abs($sisa)." hari</span>";
                        }
                        ?>
                    </td>
                    <td>
                        <?php
                        $map = ['dipinjam'=>'badge-info','dikembalikan'=>'badge-success','terlambat'=>'badge-danger'];
                        $label=['dipinjam'=>'Dipinjam','dikembalikan'=>'Dikembalikan','terlambat'=>'Terlambat'];
                        $s = $p['status'];
                        echo '<span class="badge '.($map[$s]??'badge-muted').'">'.($label[$s]??$s).'</span>';
                        ?>
                    </td>
                    <td>
                        <?php if ($p['status'] !== 'dikembalikan'): ?>
                        <a href="pengembalian.php?kode=<?= urlencode($p['kode_pinjam']) ?>" class="btn btn-success btn-sm">↩️ Kembalikan</a>
                        <?php else: ?>
                        <span style="color:var(--text-muted);font-size:.8rem">Selesai</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile;
                else: ?>
                <tr><td colspan="8">
                    <div class="empty-state"><div class="icon">📭</div><p>Tidak ada data peminjaman</p></div>
                </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Catat Peminjaman -->
<div class="modal-overlay" id="modal-pinjam">
    <div class="modal">
        <div class="modal-header">
            <h4>📋 Catat Peminjaman Baru</h4>
            <button class="modal-close" onclick="closeModal('modal-pinjam')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="pinjam">
            <div class="modal-body">
                <div class="alert alert-info" style="margin-bottom:16px">
                    📌 Stok buku akan <strong>berkurang otomatis</strong> melalui <strong>Trigger</strong> setelah data disimpan
                </div>
                <div class="form-grid">
                    <div class="form-group" style="grid-column:1/-1">
                        <label>Anggota *</label>
                        <select name="id_anggota" required>
                            <option value="">-- Pilih Anggota Aktif --</option>
                            <?php while ($a = $anggota_list->fetch_assoc()): ?>
                            <option value="<?= $a['id_anggota'] ?>"><?= htmlspecialchars($a['kode_anggota'].' — '.$a['nama']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label>Buku *</label>
                        <select name="id_buku" required>
                            <option value="">-- Pilih Buku (stok tersedia) --</option>
                            <?php while ($bk = $buku_list->fetch_assoc()): ?>
                            <option value="<?= $bk['id_buku'] ?>"><?= htmlspecialchars($bk['kode_buku'].' — '.$bk['judul']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tanggal Pinjam *</label>
                        <input type="date" name="tanggal_pinjam" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Durasi Pinjam (hari)</label>
                        <input type="number" name="durasi_hari" value="7" min="1" max="30">
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label>Petugas</label>
                        <input type="text" name="petugas" placeholder="Nama petugas" value="Admin">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-pinjam')">Batal</button>
                <button type="submit" class="btn btn-primary">Catat Peminjaman</button>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
