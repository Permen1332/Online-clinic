<?php
// Pastikan file ini tidak diakses langsung
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    exit('Akses Ditolak! Anda bukan admin.');
}

$pesan = '';
$status = '';

// --- LOGIKA TAMBAH DATA ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_dokter'])) {
    $nama_dokter = htmlspecialchars(trim($_POST['nama_dokter']));
    $spesialis = htmlspecialchars(trim($_POST['spesialis']));
    $no_hp = htmlspecialchars(trim($_POST['no_hp']));
    $jam_mulai   = $_POST['jam_mulai']   ?? '';
    $jam_selesai = $_POST['jam_selesai'] ?? '';
    $jam_praktek = ($jam_mulai && $jam_selesai) ? $jam_mulai . ' - ' . $jam_selesai : '';
    $kapasitas = max(1, (int)$_POST['kapasitas']);
    $hari_praktek = isset($_POST['hari_praktek']) ? implode(',', $_POST['hari_praktek']) : '';
    $username = htmlspecialchars(trim($_POST['username']));
    $password = $_POST['password'];

    try {
        // Cek apakah username sudah ada
        $cek = $pdo->prepare("SELECT id_user FROM users WHERE username = ?");
        $cek->execute([$username]);
        if ($cek->rowCount() > 0) {
            $status = 'error'; $pesan = 'Username sudah digunakan!';
        } else {
            $pdo->beginTransaction();

            // 1. Insert ke tabel users
            $hash_pass = password_hash($password, PASSWORD_DEFAULT);
            $stmtUser = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'dokter')");
            $stmtUser->execute([$username, $hash_pass]);
            $id_user_baru = $pdo->lastInsertId();

            // 2. Insert ke tabel dokter
            $stmtDokter = $pdo->prepare("INSERT INTO dokter (id_user, nama_dokter, no_hp, spesialis, jam_praktek, kapasitas, hari_praktek) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmtDokter->execute([$id_user_baru, $nama_dokter, $no_hp, $spesialis, $jam_praktek, $kapasitas, $hari_praktek]);

            $pdo->commit();
            $status = 'success'; $pesan = 'Data Dokter berhasil ditambahkan!';
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $status = 'error'; $pesan = 'Gagal menyimpan data: ' . $e->getMessage();
    }
}

// --- LOGIKA EDIT DATA ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_dokter'])) {
    $id_dokter = $_POST['id_dokter'];
    $nama_dokter = htmlspecialchars(trim($_POST['nama_dokter']));
    $spesialis = htmlspecialchars(trim($_POST['spesialis']));
    $no_hp = htmlspecialchars(trim($_POST['no_hp']));
    $jam_mulai   = $_POST['jam_mulai']   ?? '';
    $jam_selesai = $_POST['jam_selesai'] ?? '';
    $jam_praktek = ($jam_mulai && $jam_selesai) ? $jam_mulai . ' - ' . $jam_selesai : '';
    $kapasitas = max(1, (int)$_POST['kapasitas']);
    $hari_praktek = isset($_POST['hari_praktek']) ? implode(',', $_POST['hari_praktek']) : '';

    try {
        $stmtEdit = $pdo->prepare("UPDATE dokter SET nama_dokter = ?, spesialis = ?, no_hp = ?, jam_praktek = ?, kapasitas = ?, hari_praktek = ? WHERE id_dokter = ?");
        $stmtEdit->execute([$nama_dokter, $spesialis, $no_hp, $jam_praktek, $kapasitas, $hari_praktek, $id_dokter]);
        
        $status = 'success'; $pesan = 'Data Dokter berhasil diperbarui!';
    } catch (PDOException $e) {
        $status = 'error'; $pesan = 'Gagal update data: ' . $e->getMessage();
    }
}

// --- LOGIKA HAPUS DATA ---
if (isset($_GET['act']) && $_GET['act'] == 'hapus' && isset($_GET['id'])) {
    $id_user_hapus = $_GET['id']; // Yang dihapus id_user-nya agar cascade jalan
    try {
        $stmtHapus = $pdo->prepare("DELETE FROM users WHERE id_user = ? AND role = 'dokter'");
        $stmtHapus->execute([$id_user_hapus]);
        
        $status = 'success'; $pesan = 'Data Dokter berhasil dihapus!';
    } catch (PDOException $e) {
        $status = 'error'; $pesan = 'Gagal menghapus data: ' . $e->getMessage();
    }
}

// --- LOGIKA RESET PASSWORD DOKTER ---
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
            $stmtReset = $pdo->prepare("UPDATE users SET password = ? WHERE id_user = ? AND role = 'dokter'");
            $stmtReset->execute([$hash, $id_user_reset]);
            $status = 'success'; $pesan = 'Password dokter berhasil direset!';
        } catch (PDOException $e) {
            $status = 'error'; $pesan = 'Gagal reset password: ' . $e->getMessage();
        }
    }
}

// --- AMBIL DATA DOKTER UNTUK DITAMPILKAN ---
$query = "SELECT d.*, u.username FROM dokter d JOIN users u ON d.id_user = u.id_user ORDER BY d.id_dokter DESC";
$data_dokter = $pdo->query($query)->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
    <div>
        <h4 class="fw-bold text-dark mb-1">Master Data Dokter</h4>
        <p class="text-muted small mb-0">Kelola daftar dokter yang praktek di Cipeng Clinic</p>
    </div>
    <button class="btn btn-primary shadow-sm rounded-pill px-4 py-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#modalTambah">
        <i class="fa-solid fa-plus me-2"></i>Tambah Dokter
    </button>
</div>

<div class="table-responsive">
    <table class="table table-hover table-striped table-bordered align-middle table-datatable" style="width:100%">
        <thead class="table-light">
            <tr>
                <th class="text-center" width="5%">No</th>
                <th>Nama Dokter</th>
                <th>Spesialisasi</th>
                <th>Jadwal Praktek</th>
                <th>No. HP</th>
                <th class="text-center">Kapasitas/Hari</th>
                <th>Username Login</th>
                <th class="text-center" width="15%">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            foreach ($data_dokter as $row): 
            ?>
            <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td class="fw-semibold text-primary"><?= $row['nama_dokter'] ?></td>
                <td><span class="badge bg-info text-dark bg-opacity-10 border border-info rounded-pill px-3"><?= $row['spesialis'] ?: 'Umum' ?></span></td>
                <td><?= $row['jam_praktek'] ?: '-' ?></td>
                <td><?= $row['no_hp'] ?: '-' ?></td>
                <td class="text-center"><span class="badge bg-success rounded-pill px-3"><?= $row['kapasitas'] ?? 20 ?> pasien</span></td>
                <td><span class="badge bg-secondary rounded-pill px-3"><?= $row['username'] ?></span></td>
                <td class="text-center">
                    <button class="btn btn-sm btn-info text-white rounded-3 shadow-sm me-1" data-bs-toggle="modal" data-bs-target="#modalReset<?= $row['id_dokter'] ?>" title="Reset Password">
                        <i class="fa-solid fa-key"></i>
                    </button>
                    <button class="btn btn-sm btn-warning text-white rounded-3 shadow-sm me-1" data-bs-toggle="modal" data-bs-target="#modalEdit<?= $row['id_dokter'] ?>" title="Edit Data">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                    <a href="?f=admin&m=data_dokter&act=hapus&id=<?= $row['id_user'] ?>" class="btn btn-sm btn-danger rounded-3 shadow-sm btn-hapus" title="Hapus Data">
                        <i class="fa-solid fa-trash-can"></i>
                    </a>
                </td>
            </tr>

            <!-- Modal Reset Password Dokter -->
            <div class="modal fade" id="modalReset<?= $row['id_dokter'] ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 rounded-4 shadow">
                        <div class="modal-header bg-info text-white border-bottom-0 rounded-top-4">
                            <h5 class="modal-title fw-bold"><i class="fa-solid fa-key me-2"></i>Reset Password Dokter</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form action="?f=resepsionis&m=data_dokter" method="POST">
                            <div class="modal-body p-4">
                                <p class="text-muted small mb-3">Reset password untuk: <strong><?= htmlspecialchars($row['nama_dokter']) ?></strong> (<?= htmlspecialchars($row['username']) ?>)</p>
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

            <div class="modal fade" id="modalEdit<?= $row['id_dokter'] ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 rounded-4 shadow">
                        <div class="modal-header bg-light border-bottom-0">
                            <h5 class="modal-title fw-bold">Edit Data Dokter</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form action="?f=admin&m=data_dokter" method="POST">
                            <div class="modal-body p-4">
                                <input type="hidden" name="id_dokter" value="<?= $row['id_dokter'] ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Nama Lengkap Dokter</label>
                                    <input type="text" name="nama_dokter" class="form-control" value="<?= $row['nama_dokter'] ?>" required>
                                </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Spesialisasi</label>
                                        <input type="text" name="spesialis" class="form-control" value="<?= $row['spesialis'] ?>" placeholder="Contoh: Gigi / Umum">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">No. HP</label>
                                        <input type="text" name="no_hp" class="form-control" value="<?= $row['no_hp'] ?>">
                                    </div>
                                </div>
                                <div class="mb-3">
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Jam Praktek</label>
                                    <?php
                                    $jp_parts  = explode(' - ', $row['jam_praktek'] ?? '');
                                    $jp_mulai  = trim($jp_parts[0] ?? '');
                                    $jp_selesai= trim($jp_parts[1] ?? '');
                                    ?>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <label class="form-label small text-muted">Jam Mulai</label>
                                            <input type="time" name="jam_mulai" class="form-control" value="<?= $jp_mulai ?>" required>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small text-muted">Jam Selesai</label>
                                            <input type="time" name="jam_selesai" class="form-control" value="<?= $jp_selesai ?>" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Hari Praktek <span class="text-danger">*</span></label>
                                    <?php
                                    $hari_list  = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'];
                                    $hari_aktif = !empty($row['hari_praktek']) ? explode(',', $row['hari_praktek']) : [];
                                    ?>
                                    <div class="d-flex flex-wrap gap-2 mt-1">
                                        <?php foreach ($hari_list as $h): ?>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" name="hari_praktek[]"
                                                   id="edit_hari_<?= $row['id_dokter'] ?>_<?= $h ?>"
                                                   value="<?= $h ?>"
                                                   <?= in_array($h, $hari_aktif) ? 'checked' : '' ?>>
                                            <label class="form-check-label small" for="edit_hari_<?= $row['id_dokter'] ?>_<?= $h ?>"><?= $h ?></label>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="form-text">Centang hari di mana dokter ini berpraktek.</div>
                                </div>
                                    <label class="form-label small fw-semibold">Kapasitas Pasien per Hari <span class="text-danger">*</span></label>
                                    <input type="number" name="kapasitas" class="form-control" value="<?= $row['kapasitas'] ?? 20 ?>" min="1" max="200" required>
                                    <div class="form-text">Jumlah maksimal pasien yang dapat ditangani dokter ini dalam satu hari.</div>
                                </div>
                            </div>
                            <div class="modal-footer border-top-0 bg-light">
                                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" name="edit_dokter" class="btn btn-primary rounded-pill px-4 fw-semibold">Simpan Perubahan</button>
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
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header bg-primary text-white border-bottom-0 rounded-top-4">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-doctor me-2"></i> Tambah Dokter Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="?f=admin&m=data_dokter" method="POST">
                <div class="modal-body p-4">
                    <div class="row g-4">
                        <div class="col-md-6 border-end" style="max-height:70vh; overflow-y:auto;">
                            <h6 class="fw-bold text-muted mb-3">A. Data Profil Dokter</h6>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Nama Lengkap (Serta Gelar)</label>
                                <input type="text" name="nama_dokter" class="form-control" required placeholder="dr. Jhon Doe, Sp.A">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Spesialisasi</label>
                                <input type="text" name="spesialis" class="form-control" placeholder="Kosongkan jika Dokter Umum">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">No. Handphone</label>
                                <input type="text" name="no_hp" class="form-control" required placeholder="08xxxxxxxxxx">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Jam Praktek</label>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label small text-muted">Jam Mulai</label>
                                        <input type="time" name="jam_mulai" class="form-control" value="08:00" required>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small text-muted">Jam Selesai</label>
                                        <input type="time" name="jam_selesai" class="form-control" value="15:00" required>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Hari Praktek <span class="text-danger">*</span></label>
                                <div class="d-flex flex-wrap gap-2 mt-1">
                                    <?php foreach (['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'] as $h): ?>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="hari_praktek[]"
                                               id="tambah_hari_<?= $h ?>" value="<?= $h ?>"
                                               <?= in_array($h, ['Senin','Selasa','Rabu','Kamis','Jumat']) ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="tambah_hari_<?= $h ?>"><?= $h ?></label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="form-text">Centang hari di mana dokter ini berpraktek.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Kapasitas Pasien per Hari <span class="text-danger">*</span></label>
                                <input type="number" name="kapasitas" class="form-control" value="20" min="1" max="200" required>
                                <div class="form-text">Maks. pasien yang bisa ditangani dokter ini dalam satu hari.</div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <h6 class="fw-bold text-muted mb-3">B. Kredensial Login (Sistem)</h6>
                            <div class="alert alert-info py-2 small border-0 bg-info bg-opacity-10 text-info-emphasis">
                                <i class="fa-solid fa-circle-info me-1"></i> Akun ini akan digunakan oleh dokter untuk login dan memasukkan rekam medis pasien.
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Username Login</label>
                                <input type="text" name="username" class="form-control bg-light" required placeholder="Buat username unik (cth: dr.jhon)">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Password Sementara</label>
                                <input type="text" name="password" class="form-control bg-light" required placeholder="Masukkan password awal">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" name="tambah_dokter" class="btn btn-primary rounded-pill px-4 fw-semibold">Simpan Data Dokter</button>
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
            // Bersihkan URL dari parameter act=hapus agar alert tidak muncul terus saat direfresh
            window.location.href = "?f=admin&m=data_dokter";
        });
    });
</script>
<?php endif; ?>