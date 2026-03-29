<?php
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'apoteker') {
    exit('Akses Ditolak! Anda bukan apoteker.');
}

$pesan  = '';
$status = '';

// --- LOGIKA TAMBAH ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_obat'])) {
    $nama_obat = htmlspecialchars(trim($_POST['nama_obat']));
    $satuan    = htmlspecialchars(trim($_POST['satuan']));
    $stock     = max(0, (int) $_POST['stock']);

    try {
        $pdo->prepare("INSERT INTO obat (nama_obat, stock, satuan) VALUES (?, ?, ?)")
            ->execute([$nama_obat, $stock, $satuan]);
        $status = 'success';
        $pesan  = 'Data obat berhasil ditambahkan!';
    } catch (PDOException $e) {
        $status = 'error';
        $pesan  = 'Gagal menyimpan data: ' . $e->getMessage();
    }
}

// --- LOGIKA EDIT ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_obat'])) {
    $id_obat   = (int) $_POST['id_obat'];
    $nama_obat = htmlspecialchars(trim($_POST['nama_obat']));
    $satuan    = htmlspecialchars(trim($_POST['satuan']));
    $stock     = max(0, (int) $_POST['stock']);

    try {
        $pdo->prepare("UPDATE obat SET nama_obat = ?, stock = ?, satuan = ? WHERE id_obat = ?")
            ->execute([$nama_obat, $stock, $satuan, $id_obat]);
        $status = 'success';
        $pesan  = 'Data obat berhasil diperbarui!';
    } catch (PDOException $e) {
        $status = 'error';
        $pesan  = 'Gagal update data: ' . $e->getMessage();
    }
}

// --- LOGIKA HAPUS ---
if (isset($_GET['act']) && $_GET['act'] === 'hapus' && isset($_GET['id'])) {
    try {
        $pdo->prepare("DELETE FROM obat WHERE id_obat = ?")
            ->execute([(int) $_GET['id']]);
        $status = 'success';
        $pesan  = 'Data obat berhasil dihapus!';
    } catch (PDOException $e) {
        $status = 'error';
        $pesan  = 'Gagal menghapus: obat ini masih digunakan dalam riwayat resep.';
    }
}

// --- AMBIL DATA ---
$data_obat = $pdo->query("SELECT * FROM obat ORDER BY nama_obat ASC")->fetchAll();

// Hitung statistik
$total_obat   = count($data_obat);
$stok_aman    = count(array_filter($data_obat, fn($o) => $o['stock'] > 10));
$stok_menipis = count(array_filter($data_obat, fn($o) => $o['stock'] > 0 && $o['stock'] <= 10));
$stok_habis   = count(array_filter($data_obat, fn($o) => $o['stock'] == 0));
?>

<!-- ===== HEADER ===== -->
<div class="d-flex justify-content-between align-items-start mb-4 pb-3 border-bottom flex-wrap gap-3">
    <div>
        <h4 class="fw-bold text-dark mb-1">
            <i class="fa-solid fa-pills text-success me-2"></i>Stok Obat
        </h4>
        <p class="text-muted small mb-0">Kelola inventaris dan stok obat Cipeng Clinic</p>
    </div>
    <button class="btn btn-success shadow-sm rounded-pill px-4 py-2 fw-semibold"
            data-bs-toggle="modal" data-bs-target="#modalTambah">
        <i class="fa-solid fa-plus me-2"></i>Tambah Obat
    </button>
</div>

<!-- ===== STAT CARDS ===== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 rounded-3 shadow-sm h-100"
             style="background:linear-gradient(135deg,#0d6efd,#0b5ed7);">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center"
                     style="width:44px;height:44px;">
                    <i class="fa-solid fa-pills text-white fs-5"></i>
                </div>
                <div>
                    <div class="text-white-50 small">Total Obat</div>
                    <div class="text-white fw-bold fs-4 lh-1"><?= $total_obat ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 rounded-3 shadow-sm h-100"
             style="background:linear-gradient(135deg,#198754,#157347);">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center"
                     style="width:44px;height:44px;">
                    <i class="fa-solid fa-circle-check text-white fs-5"></i>
                </div>
                <div>
                    <div class="text-white-50 small">Stok Aman</div>
                    <div class="text-white fw-bold fs-4 lh-1"><?= $stok_aman ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 rounded-3 shadow-sm h-100"
             style="background:linear-gradient(135deg,#ffc107,#e0a800);">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center"
                     style="width:44px;height:44px;">
                    <i class="fa-solid fa-triangle-exclamation text-white fs-5"></i>
                </div>
                <div>
                    <div class="text-dark text-opacity-75 small">Stok Menipis</div>
                    <div class="text-dark fw-bold fs-4 lh-1"><?= $stok_menipis ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 rounded-3 shadow-sm h-100"
             style="background:linear-gradient(135deg,#dc3545,#b02a37);">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center"
                     style="width:44px;height:44px;">
                    <i class="fa-solid fa-ban text-white fs-5"></i>
                </div>
                <div>
                    <div class="text-white-50 small">Stok Habis</div>
                    <div class="text-white fw-bold fs-4 lh-1"><?= $stok_habis ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($stok_menipis > 0 || $stok_habis > 0): ?>
<div class="alert alert-warning border-0 shadow-sm rounded-3 d-flex align-items-center gap-2 mb-4">
    <i class="fa-solid fa-triangle-exclamation fa-lg text-warning"></i>
    <div class="small">
        Terdapat <strong><?= $stok_menipis ?> obat menipis</strong>
        <?= $stok_habis > 0 ? "dan <strong>{$stok_habis} obat habis</strong>" : '' ?>.
        Segera lakukan pengadaan stok.
    </div>
</div>
<?php endif; ?>

<!-- ===== TABEL ===== -->
<div class="table-responsive">
    <table class="table table-hover table-striped table-bordered align-middle table-datatable" style="width:100%">
        <thead class="table-light">
            <tr>
                <th class="text-center">No</th>
                <th>Nama Obat</th>
                <th class="text-center">Satuan</th>
                <th class="text-center">Stok Tersedia</th>
                <th class="text-center">Kondisi</th>
                <th class="text-center">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php $no = 1; foreach ($data_obat as $row): ?>
            <tr>
                <td class="text-center text-muted small"><?= $no++ ?></td>
                <td class="fw-semibold text-dark"><?= htmlspecialchars($row['nama_obat']) ?></td>
                <td class="text-center">
                    <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary rounded-pill px-3">
                        <?= htmlspecialchars($row['satuan']) ?>
                    </span>
                </td>
                <td class="text-center fw-bold fs-6">
                    <?php if ($row['stock'] == 0): ?>
                        <span class="text-danger"><?= $row['stock'] ?></span>
                    <?php elseif ($row['stock'] <= 10): ?>
                        <span class="text-warning"><?= $row['stock'] ?></span>
                    <?php else: ?>
                        <span class="text-success"><?= $row['stock'] ?></span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <?php if ($row['stock'] == 0): ?>
                        <span class="badge bg-danger rounded-pill px-3">
                            <i class="fa-solid fa-ban me-1"></i>Habis
                        </span>
                    <?php elseif ($row['stock'] <= 10): ?>
                        <span class="badge bg-warning text-dark rounded-pill px-3">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i>Menipis
                        </span>
                    <?php else: ?>
                        <span class="badge bg-success rounded-pill px-3">
                            <i class="fa-solid fa-circle-check me-1"></i>Aman
                        </span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <button class="btn btn-sm btn-warning text-white rounded-3 shadow-sm me-1"
                            data-bs-toggle="modal"
                            data-bs-target="#modalEdit<?= $row['id_obat'] ?>"
                            title="Edit">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                    <a href="?f=apoteker&m=data_obat&act=hapus&id=<?= $row['id_obat'] ?>"
                       class="btn btn-sm btn-danger rounded-3 shadow-sm btn-hapus"
                       title="Hapus">
                        <i class="fa-solid fa-trash-can"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- ===== MODAL TAMBAH ===== -->
<div class="modal fade" id="modalTambah" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header bg-success text-white border-bottom-0 rounded-top-4">
                <h5 class="modal-title fw-bold">
                    <i class="fa-solid fa-plus me-2"></i>Tambah Obat Baru
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="?f=apoteker&m=data_obat" method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama Obat</label>
                        <input type="text" name="nama_obat" class="form-control" required
                               placeholder="Contoh: Paracetamol 500mg">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Satuan</label>
                            <select name="satuan" class="form-select" required>
                                <option value="">-- Pilih Satuan --</option>
                                <option value="Tablet">Tablet</option>
                                <option value="Kapsul">Kapsul</option>
                                <option value="Strip">Strip</option>
                                <option value="Botol">Botol</option>
                                <option value="Tube">Tube</option>
                                <option value="Ampul">Ampul</option>
                                <option value="Pcs">Pcs</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Stok Awal</label>
                            <input type="number" name="stock" class="form-control"
                                   min="0" required placeholder="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4"
                            data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah_obat"
                            class="btn btn-success rounded-pill px-4 fw-semibold">
                        <i class="fa-solid fa-floppy-disk me-1"></i>Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== MODAL EDIT (di luar tabel) ===== -->
<?php foreach ($data_obat as $row): ?>
<div class="modal fade" id="modalEdit<?= $row['id_obat'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header bg-warning text-dark border-bottom-0 rounded-top-4">
                <h5 class="modal-title fw-bold">
                    <i class="fa-solid fa-pen-to-square me-2"></i>Edit Data Obat
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="?f=apoteker&m=data_obat" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="id_obat" value="<?= $row['id_obat'] ?>">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama Obat</label>
                        <input type="text" name="nama_obat" class="form-control"
                               value="<?= htmlspecialchars($row['nama_obat']) ?>" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Satuan</label>
                            <select name="satuan" class="form-select" required>
                                <?php foreach (['Tablet','Kapsul','Strip','Botol','Tube','Ampul','Pcs'] as $s): ?>
                                <option value="<?= $s ?>" <?= $row['satuan'] === $s ? 'selected' : '' ?>>
                                    <?= $s ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Stok Saat Ini</label>
                            <input type="number" name="stock" class="form-control"
                                   value="<?= $row['stock'] ?>" min="0" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4"
                            data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="edit_obat"
                            class="btn btn-warning rounded-pill px-4 fw-semibold">
                        <i class="fa-solid fa-floppy-disk me-1"></i>Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php if ($pesan): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        title: '<?= $status === "success" ? "Berhasil!" : "Gagal!" ?>',
        text : '<?= addslashes($pesan) ?>',
        icon : '<?= $status ?>',
        confirmButtonColor: '#0d6efd'
    }).then(() => {
        window.location.href = '?f=apoteker&m=data_obat';
    });
});
</script>
<?php endif; ?>