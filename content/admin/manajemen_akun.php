<?php
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    exit('Akses Ditolak! Anda bukan admin.');
}

$pesan  = '';
$status = '';

// ============================================================
//  TAMBAH AKUN STAF BARU
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_akun'])) {
    $username = htmlspecialchars(trim($_POST['username']));
    $password = $_POST['password'];
    $role     = $_POST['role'];

    $role_valid = ['resepsionis', 'apoteker', 'admin'];

    if (!in_array($role, $role_valid)) {
        $status = 'error';
        $pesan  = 'Role tidak valid!';
    } elseif (strlen($password) < 6) {
        $status = 'error';
        $pesan  = 'Password minimal 6 karakter!';
    } else {
        try {
            $cek = $pdo->prepare("SELECT id_user FROM users WHERE username = ?");
            $cek->execute([$username]);
            if ($cek->rowCount() > 0) {
                $status = 'error';
                $pesan  = 'Username sudah digunakan!';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO users (username, password, role, is_active) VALUES (?, ?, ?, 1)")
                    ->execute([$username, $hash, $role]);
                $status = 'success';
                $pesan  = 'Akun berhasil ditambahkan!';
            }
        } catch (PDOException $e) {
            $status = 'error';
            $pesan  = 'Gagal: ' . $e->getMessage();
        }
    }
}

// ============================================================
//  RESET PASSWORD
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $id_user      = (int) $_POST['id_user'];
    $password_baru = $_POST['password_baru'];

    if (strlen($password_baru) < 6) {
        $status = 'error';
        $pesan  = 'Password baru minimal 6 karakter!';
    } else {
        try {
            $hash = password_hash($password_baru, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id_user = ?")
                ->execute([$hash, $id_user]);
            $status = 'success';
            $pesan  = 'Password berhasil direset!';
        } catch (PDOException $e) {
            $status = 'error';
            $pesan  = 'Gagal reset password: ' . $e->getMessage();
        }
    }
}

// ============================================================
//  TOGGLE BLOKIR / AKTIFKAN
// ============================================================
if (isset($_GET['act']) && in_array($_GET['act'], ['blokir', 'aktifkan']) && isset($_GET['id'])) {
    $id_target  = (int) $_GET['id'];
    $new_status = $_GET['act'] === 'blokir' ? 0 : 1;

    // Cegah admin memblokir dirinya sendiri
    if ($id_target === (int) $_SESSION['id_user']) {
        $status = 'error';
        $pesan  = 'Anda tidak dapat memblokir akun sendiri!';
    } else {
        try {
            $pdo->prepare("UPDATE users SET is_active = ? WHERE id_user = ?")
                ->execute([$new_status, $id_target]);
            $status = 'success';
            $pesan  = 'Akun berhasil ' . ($_GET['act'] === 'blokir' ? 'diblokir!' : 'diaktifkan kembali!');
        } catch (PDOException $e) {
            $status = 'error';
            $pesan  = 'Gagal: ' . $e->getMessage();
        }
    }
}

// ============================================================
//  HAPUS AKUN
// ============================================================
if (isset($_GET['act']) && $_GET['act'] === 'hapus' && isset($_GET['id'])) {
    $id_target = (int) $_GET['id'];

    if ($id_target === (int) $_SESSION['id_user']) {
        $status = 'error';
        $pesan  = 'Anda tidak dapat menghapus akun sendiri!';
    } else {
        try {
            $pdo->prepare("DELETE FROM users WHERE id_user = ? AND role IN ('resepsionis','apoteker','admin')")
                ->execute([$id_target]);
            $status = 'success';
            $pesan  = 'Akun berhasil dihapus!';
        } catch (PDOException $e) {
            $status = 'error';
            $pesan  = 'Gagal menghapus akun.';
        }
    }
}

// ============================================================
//  AMBIL DATA STAF
// ============================================================
$daftar_akun = $pdo->query("
    SELECT id_user, username, role, is_active
    FROM users
    WHERE role IN ('resepsionis', 'apoteker', 'admin')
    ORDER BY role ASC, username ASC
")->fetchAll();

// Statistik
$total_akun    = count($daftar_akun);
$total_aktif   = count(array_filter($daftar_akun, fn($u) => $u['is_active'] == 1));
$total_blokir  = count(array_filter($daftar_akun, fn($u) => $u['is_active'] == 0));
$per_role      = array_count_values(array_column($daftar_akun, 'role'));

$badge_role = [
    'admin'       => '<span class="badge bg-danger rounded-pill px-3"><i class="fa-solid fa-shield-halved me-1"></i>Admin</span>',
    'resepsionis' => '<span class="badge bg-warning text-dark rounded-pill px-3"><i class="fa-solid fa-headset me-1"></i>Resepsionis</span>',
    'apoteker'    => '<span class="badge bg-success rounded-pill px-3"><i class="fa-solid fa-mortar-pestle me-1"></i>Apoteker</span>',
];
?>

<!-- ===== HEADER ===== -->
<div class="d-flex justify-content-between align-items-start mb-4 pb-3 border-bottom flex-wrap gap-3">
    <div>
        <h4 class="fw-bold text-dark mb-1">
            <i class="fa-solid fa-users-gear text-danger me-2"></i>Manajemen Akun Staf
        </h4>
        <p class="text-muted small mb-0">Kelola akun login resepsionis, apoteker, dan admin</p>
    </div>
    <button class="btn btn-danger shadow-sm rounded-pill px-4 py-2 fw-semibold"
            data-bs-toggle="modal" data-bs-target="#modalTambah">
        <i class="fa-solid fa-user-plus me-2"></i>Tambah Akun
    </button>
</div>

<!-- ===== STAT CARDS ===== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 rounded-3 shadow-sm h-100"
             style="background:linear-gradient(135deg,#1e293b,#334155);">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center"
                     style="width:44px;height:44px;">
                    <i class="fa-solid fa-users text-white fs-5"></i>
                </div>
                <div>
                    <div class="text-white-50 small">Total Akun</div>
                    <div class="text-white fw-bold fs-4 lh-1"><?= $total_akun ?></div>
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
                    <div class="text-white-50 small">Akun Aktif</div>
                    <div class="text-white fw-bold fs-4 lh-1"><?= $total_aktif ?></div>
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
                    <div class="text-white-50 small">Diblokir</div>
                    <div class="text-white fw-bold fs-4 lh-1"><?= $total_blokir ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 rounded-3 shadow-sm h-100"
             style="background:linear-gradient(135deg,#6f42c1,#5a32a3);">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center"
                     style="width:44px;height:44px;">
                    <i class="fa-solid fa-shield-halved text-white fs-5"></i>
                </div>
                <div>
                    <div class="text-white-50 small">Admin</div>
                    <div class="text-white fw-bold fs-4 lh-1"><?= $per_role['admin'] ?? 0 ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===== TABEL ===== -->
<div class="table-responsive">
    <table class="table table-hover table-striped table-bordered align-middle table-datatable" style="width:100%">
        <thead class="table-light">
            <tr>
                <th class="text-center">No</th>
                <th>Username</th>
                <th class="text-center">Role</th>
                <th class="text-center">Status</th>
                <th class="text-center">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php $no = 1; foreach ($daftar_akun as $akun): 
                $is_self = ($akun['id_user'] == $_SESSION['id_user']);
            ?>
            <tr class="<?= $akun['is_active'] == 0 ? 'table-secondary text-decoration-line-through opacity-75' : '' ?>">
                <td class="text-center text-muted small"><?= $no++ ?></td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center
                                    justify-content-center fw-bold text-primary"
                             style="width:34px;height:34px;font-size:0.9rem;flex-shrink:0;">
                            <?= strtoupper(substr($akun['username'], 0, 1)) ?>
                        </div>
                        <span class="fw-semibold text-dark"><?= htmlspecialchars($akun['username']) ?></span>
                        <?php if ($is_self): ?>
                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill"
                              style="font-size:0.65rem;">Anda</span>
                        <?php endif; ?>
                    </div>
                </td>
                <td class="text-center">
                    <?= $badge_role[$akun['role']] ?? '-' ?>
                </td>
                <td class="text-center">
                    <?php if ($akun['is_active'] == 1): ?>
                        <span class="badge bg-success rounded-pill px-3">
                            <i class="fa-solid fa-circle-dot me-1"></i>Aktif
                        </span>
                    <?php else: ?>
                        <span class="badge bg-danger rounded-pill px-3">
                            <i class="fa-solid fa-ban me-1"></i>Diblokir
                        </span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <div class="d-flex justify-content-center gap-1">
                        <!-- Reset Password -->
                        <button class="btn btn-sm btn-outline-primary rounded-3"
                                data-bs-toggle="modal"
                                data-bs-target="#modalReset<?= $akun['id_user'] ?>"
                                title="Reset Password">
                            <i class="fa-solid fa-key"></i>
                        </button>

                        <!-- Blokir / Aktifkan -->
                        <?php if (!$is_self): ?>
                            <?php if ($akun['is_active'] == 1): ?>
                            <a href="?f=admin&m=manajemen_akun&act=blokir&id=<?= $akun['id_user'] ?>"
                               class="btn btn-sm btn-outline-warning rounded-3 btn-hapus"
                               title="Blokir Akun"
                               data-swal-title="Blokir akun ini?"
                               data-swal-text="Akun tidak akan bisa login sampai diaktifkan kembali.">
                                <i class="fa-solid fa-ban"></i>
                            </a>
                            <?php else: ?>
                            <a href="?f=admin&m=manajemen_akun&act=aktifkan&id=<?= $akun['id_user'] ?>"
                               class="btn btn-sm btn-outline-success rounded-3"
                               title="Aktifkan Kembali">
                                <i class="fa-solid fa-circle-check"></i>
                            </a>
                            <?php endif; ?>

                            <!-- Hapus -->
                            <a href="?f=admin&m=manajemen_akun&act=hapus&id=<?= $akun['id_user'] ?>"
                               class="btn btn-sm btn-outline-danger rounded-3 btn-hapus"
                               title="Hapus Akun">
                                <i class="fa-solid fa-trash-can"></i>
                            </a>
                        <?php else: ?>
                            <span class="text-muted small fst-italic">—</span>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- ===== MODAL TAMBAH AKUN ===== -->
<div class="modal fade" id="modalTambah" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header bg-danger text-white border-bottom-0 rounded-top-4">
                <h5 class="modal-title fw-bold">
                    <i class="fa-solid fa-user-plus me-2"></i>Tambah Akun Staf
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="?f=admin&m=manajemen_akun" method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-at text-muted"></i></span>
                            <input type="text" name="username" class="form-control" required
                                   placeholder="Buat username unik">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-lock text-muted"></i></span>
                            <input type="password" name="password" class="form-control" required
                                   placeholder="Min. 6 karakter" id="inputPasswordTambah">
                            <button type="button" class="btn btn-outline-secondary"
                                    onclick="togglePassword('inputPasswordTambah', this)">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Role</label>
                        <select name="role" class="form-select" required>
                            <option value="">-- Pilih Role --</option>
                            <option value="resepsionis">Resepsionis</option>
                            <option value="apoteker">Apoteker</option>
                            <option value="admin">Admin</option>
                        </select>
                        <div class="form-text text-warning">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i>
                            Role Admin memiliki akses penuh ke sistem.
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4"
                            data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah_akun"
                            class="btn btn-danger rounded-pill px-4 fw-semibold">
                        <i class="fa-solid fa-floppy-disk me-1"></i>Buat Akun
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== MODAL RESET PASSWORD (di luar tabel) ===== -->
<?php foreach ($daftar_akun as $akun): ?>
<div class="modal fade" id="modalReset<?= $akun['id_user'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header bg-primary text-white border-bottom-0 rounded-top-4">
                <h5 class="modal-title fw-bold">
                    <i class="fa-solid fa-key me-2"></i>Reset Password
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="?f=admin&m=manajemen_akun" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="id_user" value="<?= $akun['id_user'] ?>">
                    <div class="alert alert-info py-2 small border-0 bg-info bg-opacity-10 mb-3">
                        <i class="fa-solid fa-circle-info me-1"></i>
                        Reset password untuk akun
                        <strong><?= htmlspecialchars($akun['username']) ?></strong>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Password Baru</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-lock text-muted"></i></span>
                            <input type="password" name="password_baru" class="form-control" required
                                   placeholder="Min. 6 karakter"
                                   id="inputReset<?= $akun['id_user'] ?>">
                            <button type="button" class="btn btn-outline-secondary"
                                    onclick="togglePassword('inputReset<?= $akun['id_user'] ?>', this)">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4"
                            data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="reset_password"
                            class="btn btn-primary rounded-pill px-4 fw-semibold">
                        <i class="fa-solid fa-rotate me-1"></i>Reset Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- SweetAlert feedback -->
<?php if ($pesan): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        title: '<?= $status === "success" ? "Berhasil!" : "Gagal!" ?>',
        text : '<?= addslashes($pesan) ?>',
        icon : '<?= $status ?>',
        confirmButtonColor: '#0d6efd',
        timer: <?= $status === 'success' ? 2000 : 0 ?>,
        showConfirmButton: <?= $status === 'success' ? 'false' : 'true' ?>
    }).then(() => {
        window.location.href = '?f=admin&m=manajemen_akun';
    });
});
</script>
<?php endif; ?>

<script>
// Toggle show/hide password
function togglePassword(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}
</script>