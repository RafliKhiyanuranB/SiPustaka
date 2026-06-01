<?php
// pages/anggota.php
session_start();
require_once '../config/database.php';

$page_title = 'Manajemen Anggota';
$base_url   = '../';
$db = getDB();

$msg = ''; $msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // TAMBAH — pakai Stored Procedure sp_daftar_anggota
    if ($action === 'tambah') {
        $nama    = trim($_POST['nama'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $telepon = trim($_POST['telepon'] ?? '');
        $alamat  = trim($_POST['alamat'] ?? '');

        // Panggil Stored Procedure
        $stmt = $db->prepare("CALL sp_daftar_anggota(?, ?, ?, ?, @kode, @pesan)");
        $stmt->bind_param('ssss', $nama, $email, $telepon, $alamat);
        $stmt->execute();
        $result = $db->query("SELECT @kode AS kode, @pesan AS pesan")->fetch_assoc();
        $msg = $result['pesan'];
    }

    // EDIT
    if ($action === 'edit') {
        $id      = intval($_POST['id_anggota'] ?? 0);
        $nama    = trim($_POST['nama'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $telepon = trim($_POST['telepon'] ?? '');
        $alamat  = trim($_POST['alamat'] ?? '');
        $status  = $_POST['status'] ?? 'aktif';

        $stmt = $db->prepare("UPDATE anggota SET nama=?,email=?,telepon=?,alamat=?,status=? WHERE id_anggota=?");
        $stmt->bind_param('sssssi', $nama, $email, $telepon, $alamat, $status, $id);
        if ($stmt->execute()) {
            $msg = 'Data anggota berhasil diperbarui.';
        } else {
            $msg = 'Gagal memperbarui data.';
            $msg_type = 'danger';
        }
    }

    // NONAKTIF / AKTIF
    if ($action === 'toggle_status') {
        $id     = intval($_POST['id_anggota'] ?? 0);
        $status = $_POST['current_status'] === 'aktif' ? 'nonaktif' : 'aktif';
        $db->query("UPDATE anggota SET status='$status' WHERE id_anggota=$id");
        $msg = "Status anggota diubah menjadi $status.";
    }

    // HAPUS
    if ($action === 'hapus') {
        $id = intval($_POST['id_anggota'] ?? 0);
        $cek = $db->query("SELECT COUNT(*) AS c FROM peminjaman WHERE id_anggota=$id AND " . sql_peminjaman_aktif())->fetch_assoc();
        if ($cek['c'] > 0) {
            $msg = "Tidak dapat menghapus: anggota masih memiliki buku yang dipinjam.";
            $msg_type = 'danger';
        } else {
            $db->query("DELETE FROM anggota WHERE id_anggota=$id");
            $msg = "Anggota berhasil dihapus.";
        }
    }
}

// Query anggota
$search = trim($_GET['q'] ?? '');
$filter = trim($_GET['status'] ?? '');
$where_parts = [];
if ($search) $where_parts[] = "(nama LIKE '%".addslashes($search)."%' OR kode_anggota LIKE '%".addslashes($search)."%' OR email LIKE '%".addslashes($search)."%')";
if (in_array($filter, ['aktif', 'nonaktif'], true)) {
    $where_parts[] = "status = '" . $filter . "'";
}
$where = $where_parts ? "WHERE ".implode(' AND ', $where_parts) : '';

$anggota_list = $db->query("SELECT * FROM anggota $where ORDER BY id_anggota DESC");

include '../includes/header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="section-title">Manajemen Anggota</h2>
        <p class="section-subtitle">Pendaftaran baru menggunakan <code>sp_daftar_anggota</code> (Stored Procedure)</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('modal-tambah')">+ Daftar Anggota</button>
</div>

<!-- Filter -->
<div class="card mb-4">
    <div class="card-body" style="padding:16px 24px">
        <form class="search-bar" method="GET">
            <input type="text" name="q" placeholder="Cari nama, kode, atau email..." value="<?= htmlspecialchars($search) ?>">
            <select name="status" style="border:1.5px solid var(--border);border-radius:8px;padding:8px 12px;font-family:'DM Sans',sans-serif;font-size:.85rem;outline:none">
                <option value="">Semua Status</option>
                <option value="aktif"    <?= $filter==='aktif'     ? 'selected' : '' ?>>Aktif</option>
                <option value="nonaktif" <?= $filter==='nonaktif'  ? 'selected' : '' ?>>Nonaktif</option>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">🔍 Filter</button>
            <a href="anggota.php" class="btn btn-outline btn-sm">Reset</a>
        </form>
    </div>
</div>

<!-- Tabel -->
<div class="card">
    <div class="card-header">
        <h3>👥 Daftar Anggota</h3>
    </div>
    <div class="card-body">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Telepon</th>
                        <th>Tgl Daftar</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $no = 1;
                if ($anggota_list && $anggota_list->num_rows > 0):
                    while ($a = $anggota_list->fetch_assoc()):
                ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><code style="font-size:.75rem;background:var(--bg);padding:2px 6px;border-radius:4px"><?= htmlspecialchars($a['kode_anggota']) ?></code></td>
                    <td style="font-weight:500"><?= htmlspecialchars($a['nama']) ?></td>
                    <td style="color:var(--text-muted)"><?= htmlspecialchars($a['email'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($a['telepon'] ?? '-') ?></td>
                    <td><?= $a['tanggal_daftar'] ? date('d/m/Y', strtotime($a['tanggal_daftar'])) : '-' ?></td>
                    <td>
                        <span class="badge <?= $a['status']==='aktif' ? 'badge-success' : 'badge-muted' ?>">
                            <?= ucfirst($a['status']) ?>
                        </span>
                    </td>
                    <td>
                        <div style="display:flex;gap:6px;flex-wrap:wrap">
                            <button class="btn btn-outline btn-sm" onclick='editAnggota(<?= json_encode($a) ?>)'>✏️</button>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="id_anggota" value="<?= $a['id_anggota'] ?>">
                                <input type="hidden" name="current_status" value="<?= $a['status'] ?>">
                                <button type="submit" class="btn btn-sm <?= $a['status']==='aktif' ? 'btn-outline' : 'btn-success' ?>">
                                    <?= $a['status']==='aktif' ? '🔒' : '🔓' ?>
                                </button>
                            </form>
                            <form method="POST" onsubmit="return confirm('Hapus anggota ini?')" style="display:inline">
                                <input type="hidden" name="action" value="hapus">
                                <input type="hidden" name="id_anggota" value="<?= $a['id_anggota'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">🗑</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endwhile;
                else: ?>
                <tr><td colspan="8">
                    <div class="empty-state"><div class="icon">👤</div><p>Belum ada anggota</p></div>
                </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah -->
<div class="modal-overlay" id="modal-tambah">
    <div class="modal">
        <div class="modal-header">
            <h4>👤 Daftar Anggota Baru</h4>
            <button class="modal-close" onclick="closeModal('modal-tambah')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="tambah">
            <div class="modal-body">
                <div class="alert alert-info" style="margin-bottom:16px">
                    💡 Kode anggota akan di-generate otomatis via <strong>Stored Procedure</strong>
                </div>
                <div class="form-grid">
                    <div class="form-group" style="grid-column:1/-1">
                        <label>Nama Lengkap *</label>
                        <input type="text" name="nama" required placeholder="Nama lengkap anggota">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" placeholder="email@contoh.com">
                    </div>
                    <div class="form-group">
                        <label>No. Telepon</label>
                        <input type="text" name="telepon" placeholder="081234567890">
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label>Alamat</label>
                        <textarea name="alamat" rows="2" placeholder="Alamat lengkap..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-tambah')">Batal</button>
                <button type="submit" class="btn btn-primary">Daftarkan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal-overlay" id="modal-edit">
    <div class="modal">
        <div class="modal-header">
            <h4>✏️ Edit Anggota</h4>
            <button class="modal-close" onclick="closeModal('modal-edit')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id_anggota" id="edit_id">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group" style="grid-column:1/-1">
                        <label>Nama Lengkap *</label>
                        <input type="text" name="nama" id="edit_nama" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" id="edit_email">
                    </div>
                    <div class="form-group">
                        <label>No. Telepon</label>
                        <input type="text" name="telepon" id="edit_telepon">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="edit_status">
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Nonaktif</option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label>Alamat</label>
                        <textarea name="alamat" id="edit_alamat" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-edit')">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
function editAnggota(data) {
    document.getElementById('edit_id').value      = data.id_anggota;
    document.getElementById('edit_nama').value    = data.nama;
    document.getElementById('edit_email').value   = data.email || '';
    document.getElementById('edit_telepon').value = data.telepon || '';
    document.getElementById('edit_alamat').value  = data.alamat || '';
    document.getElementById('edit_status').value  = data.status;
    openModal('modal-edit');
}
</script>

<?php include '../includes/footer.php'; ?>
