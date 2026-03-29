<?php
// Pastikan file ini tidak diakses langsung
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    exit('Akses Ditolak! Anda bukan admin.');
}

$pesan = '';
$status = '';

// --- LOGIKA TAMBAH DATA OBAT ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_obat'])) {
    $nama_obat = htmlspecialchars(trim($_POST['nama_obat']));
    $satuan = htmlspecialchars(trim($_POST['satuan']));
    $stock = (int) $_POST['stock'];

    try {
        $stmt = $pdo->prepare("INSERT INTO obat (nama_obat, stock, satuan) VALUES (?, ?, ?)");
        $stmt->execute([$nama_obat, $stock, $satuan]);
        $status = 'success'; $pesan = 'Data Obat berhasil ditambahkan!';
    } catch (PDOException $e) {
        $status = 'error'; $pesan = 'Gagal menyimpan data: ' . $e->getMessage();
    }
}

// --- LOGIKA EDIT DATA OBAT ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_obat'])) {
    $id_obat = $_POST['id_obat'];
    $nama_obat = htmlspecialchars(trim($_POST['nama_obat']));
    $satuan = htmlspecialchars(trim($_POST['satuan']));
    $stock = (int) $_POST['stock'];

    try {
        $stmt = $pdo->prepare("UPDATE obat SET nama_obat = ?, stock = ?, satuan = ? WHERE id_obat = ?");
        $stmt->execute([$nama_obat, $stock, $satuan, $id_obat]);
        $status = 'success'; $pesan = 'Data Obat berhasil diperbarui!';
    } catch (PDOException $e) {
        $status = 'error'; $pesan = 'Gagal update data: ' . $e->getMessage();
    }
}

// --- LOGIKA HAPUS DATA OBAT ---
if (isset($_GET['act']) && $_GET['act'] == 'hapus' && isset($_GET['id'])) {
    $id_obat_hapus = $_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM obat WHERE id_obat = ?");
        $stmt->execute([$id_obat_hapus]);
        $status = 'success'; $pesan = 'Data Obat berhasil dihapus!';
    } catch (PDOException $e) {
        $status = 'error'; $pesan = 'Gagal menghapus data: Obat ini mungkin sedang digunakan dalam riwayat resep.';
    }
}

// --- AMBIL DATA OBAT ---
$query = "SELECT * FROM obat ORDER BY id_obat DESC";
$data_obat = $pdo->query($query)->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
    <div>
        <h4 class="fw-bold text-dark mb-1">Master Data Obat</h4>
        <p class="text-muted small mb-0">Kelola inventaris dan stok obat Cipeng Clinic</p>
    </div>
    <button class="btn btn-primary shadow-sm rounded-pill px-4 py-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#modalTambah">
        <i class="fa-solid fa-pills me-2"></i>Tambah Obat
    </button>
</div>

<div class="table-responsive">
    <table class="table table-hover table-striped table-bordered align-middle table-datatable" style="width:100%">
        <thead class="table-light">
            <tr>
                <th class="text-center" width="5%">No</th>
                <th>Nama Obat</th>
                <th>Satuan</th>
                <th class="text-center">Stok Tersedia</th>
                <th class="text-center" width="15%">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            foreach ($data_obat as $row): 
            ?>
            <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td class="fw-semibold text-primary"><?= $row['nama_obat'] ?></td>
                <td><span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary rounded-pill px-3"><?= $row['satuan'] ?></span></td>
                <td class="text-center">
                    <?php if($row['stock'] <= 10): ?>
                        <span class="badge bg-danger rounded-pill px-3" title="Stok Menipis!"><?= $row['stock'] ?> <i class="fa-solid fa-triangle-exclamation ms-1"></i></span>
                    <?php else: ?>
                        <span class="badge bg-success rounded-pill px-3"><?= $row['stock'] ?></span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <button class="btn btn-sm btn-warning text-white rounded-3 shadow-sm me-1" data-bs-toggle="modal" data-bs-target="#modalEdit<?= $row['id_obat'] ?>" title="Edit Data">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                    <a href="?f=admin&m=data_obat&act=hapus&id=<?= $row['id_obat'] ?>" class="btn btn-sm btn-danger rounded-3 shadow-sm btn-hapus" title="Hapus Data">
                        <i class="fa-solid fa-trash-can"></i>
                    </a>
                </td>
            </tr>

            <div class="modal fade" id="modalEdit<?= $row['id_obat'] ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 rounded-4 shadow">
                        <div class="modal-header bg-light border-bottom-0">
                            <h5 class="modal-title fw-bold">Edit Data Obat</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form action="?f=admin&m=data_obat" method="POST">
                            <div class="modal-body p-4">
                                <input type="hidden" name="id_obat" value="<?= $row['id_obat'] ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Nama Obat</label>
                                    <input type="text" name="nama_obat" class="form-control" value="<?= $row['nama_obat'] ?>" required>
                                </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Satuan</label>
                                        <select name="satuan" class="form-select" required>
                                            <option value="Pcs" <?= $row['satuan'] == 'Pcs' ? 'selected' : '' ?>>Pcs</option>
                                            <option value="Strip" <?= $row['satuan'] == 'Strip' ? 'selected' : '' ?>>Strip</option>
                                            <option value="Botol" <?= $row['satuan'] == 'Botol' ? 'selected' : '' ?>>Botol</option>
                                            <option value="Tube" <?= $row['satuan'] == 'Tube' ? 'selected' : '' ?>>Tube</option>
                                            <option value="Ampul" <?= $row['satuan'] == 'Ampul' ? 'selected' : '' ?>>Ampul</option>
                                            <option value="Kapsul" <?= $row['satuan'] == 'Kapsul' ? 'selected' : '' ?>>Kapsul</option>
                                            <option value="Tablet" <?= $row['satuan'] == 'Tablet' ? 'selected' : '' ?>>Tablet</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Stok Saat Ini</label>
                                        <input type="number" name="stock" class="form-control" value="<?= $row['stock'] ?>" min="0" required>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer border-top-0 bg-light">
                                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" name="edit_obat" class="btn btn-primary rounded-pill px-4 fw-semibold">Simpan Perubahan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="modal fade" id="modalTambah" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header bg-primary text-white border-bottom-0 rounded-top-4">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-pills me-2"></i> Tambah Obat Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="?f=admin&m=data_obat" method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama Obat</label>
                        <input type="text" name="nama_obat" class="form-control" required placeholder="Contoh: Paracetamol 500mg">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Satuan</label>
                            <select name="satuan" class="form-select" required>
                                <option value="">-- Pilih Satuan --</option>
                                <option value="Pcs">Pcs</option>
                                <option value="Strip">Strip</option>
                                <option value="Botol">Botol</option>
                                <option value="Tube">Tube</option>
                                <option value="Ampul">Ampul</option>
                                <option value="Kapsul">Kapsul</option>
                                <option value="Tablet">Tablet</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Stok Awal</label>
                            <input type="number" name="stock" class="form-control" min="0" required placeholder="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah_obat" class="btn btn-primary rounded-pill px-4 fw-semibold">Simpan Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if($pesan != ''): ?>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        Swal.fire({
            title: "<?= $status == 'success' ? 'Berhasil!' : 'Gagal!' ?>",
            text: "<?= $pesan ?>",
            icon: "<?= $status ?>",
            confirmButtonColor: "#0d6efd"
        }).then(() => {
            window.location.href = "?f=admin&m=data_obat";
        });
    });
</script>
<?php endif; ?>