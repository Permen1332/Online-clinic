<?php
// Pastikan file ini tidak diakses langsung
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    exit('Akses Ditolak! Anda bukan admin.');
}

$pesan = '';
$status = '';

// --- LOGIKA HAPUS DATA PASIEN ---
if (isset($_GET['act']) && $_GET['act'] == 'hapus' && isset($_GET['id'])) {
    $id_user_hapus = $_GET['id'];
    try {
        $stmtHapus = $pdo->prepare("DELETE FROM users WHERE id_user = ? AND role = 'pasien'");
        $stmtHapus->execute([$id_user_hapus]);
        $status = 'success'; $pesan = 'Data Pasien berhasil dihapus!';
    } catch (PDOException $e) {
        $status = 'error'; $pesan = 'Gagal menghapus data: Pasien ini mungkin masih memiliki data antrian.';
    }
}

// --- LOGIKA RESET PASSWORD PASIEN ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_password'])) {
    $id_user_reset  = (int)$_POST['id_user_reset'];
    $password_baru  = $_POST['password_baru'];
    $password_ulang = $_POST['password_ulang'];
    if ($password_baru !== $password_ulang) {
        $status = 'error'; $pesan = 'Konfirmasi password tidak cocok!';
    } elseif (strlen($password_baru) < 6) {
        $status = 'error'; $pesan = 'Password minimal 6 karakter!';
    } else {
        try {
            $hash = password_hash($password_baru, PASSWORD_DEFAULT);
            $stmtReset = $pdo->prepare("UPDATE users SET password = ? WHERE id_user = ? AND role = 'pasien'");
            $stmtReset->execute([$hash, $id_user_reset]);
            $status = 'success'; $pesan = 'Password pasien berhasil direset!';
        } catch (PDOException $e) {
            $status = 'error'; $pesan = 'Gagal reset password: ' . $e->getMessage();
        }
    }
}

// --- AMBIL DATA PASIEN ---
$query = "SELECT p.*, u.username FROM pasien p JOIN users u ON p.id_user = u.id_user ORDER BY p.id_pasien DESC";
$data_pasien = $pdo->query($query)->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
    <div>
        <h4 class="fw-bold text-dark mb-1">Data Pasien Terdaftar</h4>
        <p class="text-muted small mb-0">Daftar semua pasien yang terdaftar di Cipeng Clinic</p>
    </div>
    <span class="badge bg-primary rounded-pill px-3 py-2 fs-6">
        <i class="fa-solid fa-users me-1"></i> <?= count($data_pasien) ?> Pasien
    </span>
</div>

<div class="table-responsive">
    <table class="table table-hover table-striped table-bordered align-middle table-datatable" style="width:100%">
        <thead class="table-light">
            <tr>
                <th class="text-center" width="5%">No</th>
                <th>Nama Pasien</th>
                <th>Username</th>
                <th>No. HP</th>
                <th>Alamat</th>
                <th>Tgl. Lahir</th>
                <th class="text-center" width="10%">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($data_pasien)): ?>
            <tr>
                <td colspan="7" class="text-center py-4 text-muted">
                    <i class="fa-solid fa-users-slash fa-2x mb-2 d-block"></i>
                    Belum ada pasien terdaftar.
                </td>
            </tr>
            <?php else: ?>
            <?php $no = 1; foreach ($data_pasien as $row): ?>
            <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width:36px;height:36px;font-size:0.9rem;">
                            <?= strtoupper(substr($row['nama_pasien'], 0, 1)) ?>
                        </div>
                        <span class="fw-semibold text-dark"><?= htmlspecialchars($row['nama_pasien']) ?></span>
                    </div>
                </td>
                <td><span class="badge bg-secondary rounded-pill px-3"><?= htmlspecialchars($row['username']) ?></span></td>
                <td><?= htmlspecialchars($row['no_hp'] ?: '-') ?></td>
                <td class="small text-muted" style="max-width:180px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($row['alamat'] ?: '-') ?></td>
                <td><?= $row['tanggal_lahir'] ? date('d M Y', strtotime($row['tanggal_lahir'])) : '-' ?></td>
                <td class="text-center">
                    <button class="btn btn-sm btn-info text-white rounded-3 shadow-sm me-1" data-bs-toggle="modal" data-bs-target="#modalReset<?= $row['id_user'] ?>" title="Reset Password">
                        <i class="fa-solid fa-key"></i>
                    </button>
                    <a href="?f=admin&m=data_pasien&act=hapus&id=<?= $row['id_user'] ?>" class="btn btn-sm btn-danger rounded-3 shadow-sm btn-hapus" title="Hapus Data">
                        <i class="fa-solid fa-trash-can"></i>
                    </a>
                </td>
            </tr>

            <!-- Modal Reset Password -->
            <div class="modal fade" id="modalReset<?= $row['id_user'] ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 rounded-4 shadow">
                        <div class="modal-header bg-info text-white border-bottom-0 rounded-top-4">
                            <h5 class="modal-title fw-bold"><i class="fa-solid fa-key me-2"></i>Reset Password Pasien</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form action="?f=admin&m=data_pasien" method="POST">
                            <div class="modal-body p-4">
                                <p class="text-muted small mb-3">Reset password untuk: <strong><?= htmlspecialchars($row['nama_pasien']) ?></strong> (<?= htmlspecialchars($row['username']) ?>)</p>
                                <input type="hidden" name="id_user_reset" value="<?= $row['id_user'] ?>">
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Password Baru</label>
                                    <input type="password" name="password_baru" class="form-control" required placeholder="Min. 6 karakter">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Konfirmasi Password Baru</label>
                                    <input type="password" name="password_ulang" class="form-control" required placeholder="Ulangi password baru">
                                </div>
                            </div>
                            <div class="modal-footer border-top-0 bg-light rounded-bottom-4">
                                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" name="reset_password" class="btn btn-info text-white rounded-pill px-4 fw-semibold">Reset Password</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
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
            window.location.href = "?f=admin&m=data_pasien";
        });
    });
</script>
<?php endif; ?>
