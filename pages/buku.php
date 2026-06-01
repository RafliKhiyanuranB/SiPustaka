<?php
// pages/buku.php
session_start();
require_once '../config/database.php';

$page_title = 'Manajemen Buku';
$base_url   = '../';
$db = getDB();

// ── PROSES FORM ────────────────────────────────────────
$msg = ''; $msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'tambah' || $action === 'edit') {
        $kode       = trim($_POST['kode_buku'] ?? '');
        $judul      = trim($_POST['judul'] ?? '');
        $pengarang  = trim($_POST['pengarang'] ?? '');
        $penerbit   = trim($_POST['penerbit'] ?? '');
        $tahun      = intval($_POST['tahun_terbit'] ?? 0);
        $kategori   = intval($_POST['id_kategori'] ?? 0);
        $stok       = max(1, intval($_POST['stok_total'] ?? 1));
        $deskripsi  = trim($_POST['deskripsi'] ?? '');

        if ($action === 'tambah') {
            // Cek kode unik
            $cek = $db->prepare("SELECT id_buku FROM buku WHERE kode_buku = ?");
            $cek->bind_param('s', $kode);
            $cek->execute();
            if ($cek->get_result()->num_rows > 0) {
                $msg = "Kode buku '$kode' sudah digunakan.";
                $msg_type = 'danger';
            } else {
                $stmt = $db->prepare("
                    INSERT INTO buku (kode_buku,judul,pengarang,penerbit,tahun_terbit,id_kategori,stok_total,stok_tersedia,deskripsi)
                    VALUES (?,?,?,?,?,?,?,?,?)
                ");
                $stmt->bind_param('ssssiiiis', $kode, $judul, $pengarang, $penerbit, $tahun, $kategori, $stok, $stok, $deskripsi);
                if ($stmt->execute()) {
                    $msg = "Buku '$judul' berhasil ditambahkan.";
                } else {
                    $msg = "Gagal menambahkan buku."; $msg_type = 'danger';
                }
            }
        } else {
            $id = intval($_POST['id_buku'] ?? 0);
            // Hitung selisih stok
            $old = $db->query("SELECT stok_total, stok_tersedia FROM buku WHERE id_buku=$id")->fetch_assoc();
            $selisih = $stok - $old['stok_total'];
            $stok_tersedia_baru = max(0, $old['stok_tersedia'] + $selisih);

            $stmt = $db->prepare("
                UPDATE buku SET kode_buku=?,judul=?,pengarang=?,penerbit=?,tahun_terbit=?,
                id_kategori=?,stok_total=?,stok_tersedia=?,deskripsi=?
                WHERE id_buku=?
            ");
            $stmt->bind_param('ssssiiissi', $kode, $judul, $pengarang, $penerbit, $tahun, $kategori, $stok, $stok_tersedia_baru, $deskripsi, $id);
            if ($stmt->execute()) {
                $msg = "Buku berhasil diperbarui.";
            } else {
                $msg = "Gagal memperbarui buku."; $msg_type = 'danger';
            }
        }
    }

    if ($action === 'hapus') {
        $id = intval($_POST['id_buku'] ?? 0);
        // Cek apakah sedang dipinjam
        $cek = $db->query("SELECT COUNT(*) AS c FROM peminjaman WHERE id_buku=$id AND " . sql_peminjaman_aktif());
        $row = $cek->fetch_assoc();
        if ($row['c'] > 0) {
            $msg = "Tidak dapat menghapus: buku sedang dipinjam."; $msg_type = 'danger';
        } else {
            $db->query("DELETE FROM buku WHERE id_buku=$id");
            $msg = "Buku berhasil dihapus.";
        }
    }
}

// ── DATA ────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$where  = $search ? "WHERE judul LIKE '%".addslashes($search)."%' OR pengarang LIKE '%".addslashes($search)."%' OR kode_buku LIKE '%".addslashes($search)."%'" : '';

// Pakai VIEW v_stok_buku
$buku_list = $db->query("SELECT * FROM v_stok_buku $where ORDER BY id_buku DESC");
$kategori_list = $db->query("SELECT * FROM kategori ORDER BY nama_kategori");

include '../includes/header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="section-title">Manajemen Buku</h2>
        <p class="section-subtitle">Kelola koleksi buku perpustakaan</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('modal-tambah')">+ Tambah Buku</button>
</div>

<!-- Search & Filter -->
<div class="card mb-4">
    <div class="card-body" style="padding:16px 24px">
        <form class="search-bar" method="GET">
            <input type="text" name="q" placeholder="Cari judul, pengarang, atau kode buku..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-primary btn-sm">🔍 Cari</button>
            <?php if ($search): ?><a href="buku.php" class="btn btn-outline btn-sm">✕ Reset</a><?php endif; ?>
        </form>
    </div>
</div>

<!-- Tabel Buku — menggunakan data dari VIEW v_stok_buku -->
<div class="card">
    <div class="card-header">
        <h3>📚 Daftar Koleksi Buku</h3>
        <small style="color:var(--text-muted);font-size:.75rem">Data dari <code>v_stok_buku</code></small>
    </div>
    <div class="card-body">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Kode</th>
                        <th>Judul</th>
                        <th>Pengarang</th>
                        <th>Kategori</th>
                        <th>Stok</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $no = 1;
                if ($buku_list && $buku_list->num_rows > 0):
                    while ($b = $buku_list->fetch_assoc()): ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><code style="font-size:.75rem;background:var(--bg);padding:2px 6px;border-radius:4px"><?= htmlspecialchars($b['kode_buku']) ?></code></td>
                    <td>
                        <div style="font-weight:500"><?= htmlspecialchars($b['judul']) ?></div>
                        <div style="font-size:.75rem;color:var(--text-muted)"><?= $b['penerbit'] ? htmlspecialchars($b['penerbit']) : '-' ?></div>
                    </td>
                    <td><?= htmlspecialchars($b['pengarang']) ?></td>
                    <td><?= htmlspecialchars($b['nama_kategori'] ?? '-') ?></td>
                    <td>
                        <span style="font-weight:600"><?= $b['stok_tersedia'] ?></span>
                        <span style="color:var(--text-muted)"> / <?= $b['stok_total'] ?></span>
                        <div style="font-size:.72rem;color:var(--text-muted)"><?= $b['sedang_dipinjam'] ?> dipinjam</div>
                    </td>
                    <td>
                        <?php
                        $stok = (int) $b['stok_tersedia'];
                        echo '<span class="badge ' . stok_badge_class($stok) . '">' . htmlspecialchars(stok_label($stok)) . '</span>';
                        ?>
                    </td>
                    <td>
                        <div style="display:flex;gap:6px">
                            <button class="btn btn-outline btn-sm" onclick="editBuku(<?= htmlspecialchars(json_encode($b)) ?>)">✏️ Edit</button>
                            <form method="POST" onsubmit="return confirm('Hapus buku ini?')" style="display:inline">
                                <input type="hidden" name="action" value="hapus">
                                <input type="hidden" name="id_buku" value="<?= $b['id_buku'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">🗑</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endwhile;
                else: ?>
                <tr><td colspan="8">
                    <div class="empty-state">
                        <div class="icon">📭</div>
                        <p>Tidak ada buku ditemukan</p>
                    </div>
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
            <h4>➕ Tambah Buku Baru</h4>
            <button class="modal-close" onclick="closeModal('modal-tambah')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="tambah">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Kode Buku *</label>
                        <input type="text" name="kode_buku" required placeholder="BK-009">
                    </div>
                    <div class="form-group">
                        <label>Tahun Terbit</label>
                        <input type="number" name="tahun_terbit" min="1900" max="<?= date('Y') ?>" placeholder="<?= date('Y') ?>">
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label>Judul Buku *</label>
                        <input type="text" name="judul" required placeholder="Masukkan judul buku">
                    </div>
                    <div class="form-group">
                        <label>Pengarang *</label>
                        <input type="text" name="pengarang" required placeholder="Nama pengarang">
                    </div>
                    <div class="form-group">
                        <label>Penerbit</label>
                        <input type="text" name="penerbit" placeholder="Nama penerbit">
                    </div>
                    <div class="form-group">
                        <label>Kategori</label>
                        <select name="id_kategori">
                            <option value="">-- Pilih Kategori --</option>
                            <?php
                            $kategori_list->data_seek(0);
                            while ($k = $kategori_list->fetch_assoc()):
                            ?>
                            <option value="<?= $k['id_kategori'] ?>"><?= htmlspecialchars($k['nama_kategori']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Stok Total *</label>
                        <input type="number" name="stok_total" value="1" min="1" required>
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label>Deskripsi</label>
                        <textarea name="deskripsi" rows="3" placeholder="Deskripsi singkat buku..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-tambah')">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Buku</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal-overlay" id="modal-edit">
    <div class="modal">
        <div class="modal-header">
            <h4>✏️ Edit Buku</h4>
            <button class="modal-close" onclick="closeModal('modal-edit')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id_buku" id="edit_id">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Kode Buku *</label>
                        <input type="text" name="kode_buku" id="edit_kode" required>
                    </div>
                    <div class="form-group">
                        <label>Tahun Terbit</label>
                        <input type="number" name="tahun_terbit" id="edit_tahun" min="1900" max="<?= date('Y') ?>">
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label>Judul Buku *</label>
                        <input type="text" name="judul" id="edit_judul" required>
                    </div>
                    <div class="form-group">
                        <label>Pengarang *</label>
                        <input type="text" name="pengarang" id="edit_pengarang" required>
                    </div>
                    <div class="form-group">
                        <label>Penerbit</label>
                        <input type="text" name="penerbit" id="edit_penerbit">
                    </div>
                    <div class="form-group">
                        <label>Kategori</label>
                        <select name="id_kategori" id="edit_kategori">
                            <option value="">-- Pilih Kategori --</option>
                            <?php
                            $kategori_list->data_seek(0);
                            while ($k = $kategori_list->fetch_assoc()):
                            ?>
                            <option value="<?= $k['id_kategori'] ?>"><?= htmlspecialchars($k['nama_kategori']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Stok Total *</label>
                        <input type="number" name="stok_total" id="edit_stok" min="1" required>
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label>Deskripsi</label>
                        <textarea name="deskripsi" id="edit_deskripsi" rows="3"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-edit')">Batal</button>
                <button type="submit" class="btn btn-primary">Perbarui</button>
            </div>
        </form>
    </div>
</div>

<script>
function editBuku(data) {
    document.getElementById('edit_id').value       = data.id_buku;
    document.getElementById('edit_kode').value     = data.kode_buku;
    document.getElementById('edit_judul').value    = data.judul;
    document.getElementById('edit_pengarang').value= data.pengarang;
    document.getElementById('edit_penerbit').value = data.penerbit || '';
    document.getElementById('edit_tahun').value    = data.tahun_terbit || '';
    document.getElementById('edit_stok').value     = data.stok_total;
    document.getElementById('edit_deskripsi').value= data.deskripsi || '';
    // Set kategori (need name_kategori → by id, simplified: re-select doesn't apply here without id; use id from data)
    openModal('modal-edit');
}
</script>

<?php include '../includes/footer.php'; ?>
