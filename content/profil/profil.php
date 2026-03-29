<?php
if (!isset($_SESSION['id_user'])) exit('Akses Ditolak!');

$id_user = $_SESSION['id_user'];
$role    = $_SESSION['role'];
$pesan   = '';
$status  = '';

// --- AMBIL DATA PROFIL SESUAI ROLE ---
if ($role === 'pasien') {
    $stmt = $pdo->prepare("SELECT p.*, u.username FROM pasien p JOIN users u ON p.id_user = u.id_user WHERE p.id_user = ?");
} elseif ($role === 'dokter') {
    $stmt = $pdo->prepare("SELECT d.*, u.username FROM dokter d JOIN users u ON d.id_user = u.id_user WHERE d.id_user = ?");
} else {
    $stmt = $pdo->prepare("SELECT *, username FROM users WHERE id_user = ?");
}
$stmt->execute([$id_user]);
$profil = $stmt->fetch();

// --- UPDATE PROFIL ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profil'])) {
    try {
        if ($role === 'pasien') {
            $nama      = htmlspecialchars(trim($_POST['nama']));
            $no_hp     = htmlspecialchars(trim($_POST['no_hp']));
            $alamat    = htmlspecialchars(trim($_POST['alamat']));
            $tgl_lahir = $_POST['tanggal_lahir'];
            $pdo->prepare("UPDATE pasien SET nama_pasien=?,no_hp=?,alamat=?,tanggal_lahir=? WHERE id_user=?")
                ->execute([$nama,$no_hp,$alamat,$tgl_lahir,$id_user]);
            $_SESSION['nama_lengkap'] = $nama;

        } elseif ($role === 'dokter') {
            $nama      = htmlspecialchars(trim($_POST['nama']));
            $no_hp     = htmlspecialchars(trim($_POST['no_hp']));
            $alamat    = htmlspecialchars(trim($_POST['alamat']));
            $tgl_lahir = $_POST['tanggal_lahir'];
            $spesialis = htmlspecialchars(trim($_POST['spesialis']));

            // Gabungkan jam mulai & selesai
            $jam_mulai   = $_POST['jam_mulai']   ?? '';
            $jam_selesai = $_POST['jam_selesai'] ?? '';
            $jam_praktek = $jam_mulai && $jam_selesai ? "$jam_mulai - $jam_selesai" : ($profil['jam_praktek'] ?? '');

            // Hari praktek (checkbox)
            $hari_list   = $_POST['hari_praktek'] ?? [];
            $hari_str    = implode(',', $hari_list);

            $pdo->prepare("UPDATE dokter SET nama_dokter=?,no_hp=?,alamat=?,tanggal_lahir=?,spesialis=?,jam_praktek=?,hari_praktek=? WHERE id_user=?")
                ->execute([$nama,$no_hp,$alamat,$tgl_lahir,$spesialis,$jam_praktek,$hari_str,$id_user]);
            $_SESSION['nama_lengkap'] = $nama;
        }
        // staf lain (resepsionis, admin, apoteker) tidak punya tabel profil terpisah

        $status = 'success';
        $pesan  = 'Profil berhasil diperbarui!';
        $stmt->execute([$id_user]);
        $profil = $stmt->fetch();

    } catch (PDOException $e) {
        $status = 'error';
        $pesan  = 'Gagal memperbarui profil.';
    }
}

// --- GANTI PASSWORD ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ganti_password'])) {
    $pass_lama  = $_POST['password_lama'];
    $pass_baru  = $_POST['password_baru'];
    $pass_ulang = $_POST['password_ulang'];

    $stmt_pw = $pdo->prepare("SELECT password FROM users WHERE id_user = ?");
    $stmt_pw->execute([$id_user]);
    $hash = $stmt_pw->fetchColumn();

    if (!password_verify($pass_lama, $hash)) {
        $status = 'error'; $pesan = 'Password lama yang Anda masukkan salah!';
    } elseif ($pass_baru !== $pass_ulang) {
        $status = 'error'; $pesan = 'Konfirmasi password baru tidak cocok!';
    } elseif (strlen($pass_baru) < 6) {
        $status = 'error'; $pesan = 'Password baru minimal 6 karakter!';
    } else {
        try {
            $pdo->prepare("UPDATE users SET password=? WHERE id_user=?")
                ->execute([password_hash($pass_baru, PASSWORD_DEFAULT), $id_user]);
            $status = 'success'; $pesan = 'Password berhasil diubah!';
        } catch (PDOException $e) {
            $status = 'error'; $pesan = 'Gagal mengubah password.';
        }
    }
}

// Konfigurasi per role
$role_cfg = [
    'pasien'      => ['icon'=>'fa-user',           'color'=>'#2a9ab0', 'label'=>'Pasien',       'has_profile'=>true],
    'dokter'      => ['icon'=>'fa-stethoscope',    'color'=>'#1a6b7a', 'label'=>'Dokter',       'has_profile'=>true],
    'resepsionis' => ['icon'=>'fa-headset',        'color'=>'#f59e0b', 'label'=>'Resepsionis',  'has_profile'=>false],
    'admin'       => ['icon'=>'fa-shield-halved',  'color'=>'#dc3545', 'label'=>'Admin',        'has_profile'=>false],
    'apoteker'    => ['icon'=>'fa-mortar-pestle',  'color'=>'#198754', 'label'=>'Apoteker',     'has_profile'=>false],
];
$cfg = $role_cfg[$role];

// Parse jam praktek dokter
$jam_mulai_val = $jam_selesai_val = '';
if ($role === 'dokter' && !empty($profil['jam_praktek'])) {
    $parts = explode(' - ', $profil['jam_praktek']);
    $jam_mulai_val   = $parts[0] ?? '';
    $jam_selesai_val = $parts[1] ?? '';
}

$hari_options = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'];
$hari_aktif   = $role === 'dokter' ? explode(',', $profil['hari_praktek'] ?? '') : [];
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap');

.pf-root {
    font-family: 'DM Sans', sans-serif;
    animation: pfFade 0.45s ease forwards;
}
@keyframes pfFade { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }

/* ===== HEADER ===== */
.pf-header {
    display: flex; align-items: center;
    justify-content: space-between;
    margin-bottom: 1.75rem; flex-wrap: wrap; gap: 0.75rem;
}
.pf-header-left h4 {
    font-family: 'Playfair Display', serif;
    font-size: 1.5rem; font-weight: 700;
    color: #0b3d47; margin-bottom: 0.2rem;
}
.pf-header-left p { font-size: 0.84rem; color: #8aa5a9; margin: 0; font-weight: 300; }

/* ===== AVATAR CARD ===== */
.pf-avatar-card {
    background: white;
    border-radius: 20px;
    border: 1.5px solid #f0ece7;
    overflow: hidden;
    margin-bottom: 1.25rem;
}
.pf-avatar-top {
    height: 80px;
    position: relative;
}
.pf-avatar-top-bg {
    position: absolute; inset: 0;
    background: #0b3d47;
}
.pf-avatar-top-bg::before {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse 80% 100% at 110% 50%, rgba(42,154,176,0.4) 0%, transparent 60%);
}
.pf-avatar-circle {
    position: absolute;
    bottom: -28px; left: 50%;
    transform: translateX(-50%);
    width: 64px; height: 64px;
    border-radius: 18px;
    border: 3px solid white;
    display: flex; align-items: center; justify-content: center;
    font-family: 'Playfair Display', serif;
    font-size: 1.6rem; font-weight: 700;
    color: white;
}
.pf-avatar-body {
    padding: 2.75rem 1.5rem 1.5rem;
    text-align: center;
}
.pf-avatar-name {
    font-family: 'Playfair Display', serif;
    font-size: 1.1rem; font-weight: 700;
    color: #0b3d47; margin-bottom: 0.4rem;
}
.pf-role-badge {
    display: inline-flex; align-items: center; gap: 0.4rem;
    padding: 0.25rem 0.9rem;
    border-radius: 50px;
    font-size: 0.72rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.5px;
    margin-bottom: 1.25rem;
}
.pf-info-list { list-style: none; padding: 0; margin: 0; }
.pf-info-list li {
    display: flex; align-items: center; gap: 0.65rem;
    padding: 0.6rem 0;
    border-bottom: 1px solid #f7f3ee;
    font-size: 0.83rem; color: #4a6568;
}
.pf-info-list li:last-child { border-bottom: none; }
.pf-info-list li i {
    width: 28px; height: 28px;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.78rem; flex-shrink: 0;
}

/* ===== PASSWORD CARD ===== */
.pf-pass-card {
    background: white;
    border-radius: 20px;
    border: 1.5px solid #f0ece7;
    overflow: hidden;
}
.pf-card-head {
    padding: 1rem 1.4rem;
    display: flex; align-items: center; gap: 0.65rem;
    border-bottom: 1px solid #f7f3ee;
}
.pf-card-head-icon {
    width: 34px; height: 34px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.85rem;
}
.pf-card-head h6 {
    font-family: 'Playfair Display', serif;
    font-size: 1rem; font-weight: 600;
    color: #0b3d47; margin: 0;
}
.pf-card-body { padding: 1.25rem 1.4rem; }

/* ===== FORM FIELDS ===== */
.pf-field { margin-bottom: 1rem; }
.pf-label {
    display: block;
    font-size: 0.76rem; font-weight: 600;
    color: #4a6568; text-transform: uppercase;
    letter-spacing: 0.5px; margin-bottom: 0.4rem;
}
.pf-input {
    width: 100%;
    padding: 0.7rem 1rem;
    border: 1.5px solid #ede8e1;
    border-radius: 10px;
    background: #faf8f5;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.9rem; color: #1a2326;
    transition: all 0.2s; outline: none;
}
.pf-input:focus {
    border-color: #2a9ab0;
    background: white;
    box-shadow: 0 0 0 3px rgba(42,154,176,0.1);
}
.pf-input::placeholder { color: #b0c4c7; }
.pf-input-wrap { position: relative; }
.pf-input-wrap .pf-toggle {
    position: absolute; right: 0.75rem; top: 50%;
    transform: translateY(-50%);
    background: none; border: none;
    color: #8aa5a9; cursor: pointer;
    font-size: 0.85rem; transition: color 0.2s;
}
.pf-input-wrap .pf-toggle:hover { color: #2a9ab0; }

/* Hari checkbox pills */
.hari-pills { display: flex; flex-wrap: wrap; gap: 0.4rem; }
.hari-pill input { display: none; }
.hari-pill label {
    display: inline-block;
    padding: 0.3rem 0.85rem;
    border-radius: 50px;
    border: 1.5px solid #ede8e1;
    font-size: 0.8rem; font-weight: 500;
    color: #4a6568;
    cursor: pointer;
    transition: all 0.2s;
    background: #faf8f5;
}
.hari-pill input:checked + label {
    background: #0b3d47;
    border-color: #0b3d47;
    color: white;
}

/* Jam praktek grid */
.jam-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }

/* Staf info panel */
.pf-staf-info {
    background: #f0faf9;
    border: 1.5px dashed #a8d8df;
    border-radius: 14px;
    padding: 1.25rem 1.4rem;
    display: flex; align-items: flex-start; gap: 0.85rem;
    margin-bottom: 1rem;
}
.pf-staf-info i { color: #2a9ab0; margin-top: 2px; flex-shrink: 0; }
.pf-staf-info p { font-size: 0.84rem; color: #4a6568; line-height: 1.6; margin: 0; font-weight: 300; }

.pf-detail-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 0.65rem 0;
    border-bottom: 1px solid #f7f3ee;
    font-size: 0.85rem;
}
.pf-detail-row:last-child { border-bottom: none; }
.pf-detail-row span:first-child { color: #8aa5a9; font-weight: 400; }
.pf-detail-row span:last-child  { color: #1a2326; font-weight: 600; }

/* ===== MAIN CARD ===== */
.pf-main-card {
    background: white;
    border-radius: 20px;
    border: 1.5px solid #f0ece7;
    overflow: hidden;
}

/* ===== BUTTON ===== */
.pf-btn {
    display: inline-flex; align-items: center; gap: 0.5rem;
    padding: 0.65rem 1.6rem;
    border-radius: 50px; border: none;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.9rem; font-weight: 600;
    cursor: pointer; transition: all 0.2s;
}
.pf-btn-primary {
    background: #0b3d47; color: white;
    box-shadow: 0 4px 14px rgba(11,61,71,0.2);
}
.pf-btn-primary:hover { background: #1a6b7a; transform: translateY(-1px); }
.pf-btn-danger {
    background: #dc3545; color: white; width: 100%;
    justify-content: center;
    box-shadow: 0 4px 14px rgba(220,53,69,0.2);
}
.pf-btn-danger:hover { background: #c82333; transform: translateY(-1px); }

@media (max-width: 768px) {
    .pf-grid { grid-template-columns: 1fr !important; }
    .jam-grid { grid-template-columns: 1fr; }
}
</style>

<div class="pf-root">

<!-- HEADER -->
<div class="pf-header">
    <div class="pf-header-left">
        <h4><i class="fa-solid fa-id-card me-2" style="color:#2a9ab0;font-size:1.2rem;"></i>Profil & Keamanan</h4>
        <p>Kelola informasi akun dan kata sandi Anda</p>
    </div>
</div>

<div class="row g-4">

    <!-- ===== KOLOM KIRI ===== -->
    <div class="col-lg-4">

        <!-- Avatar Card -->
        <div class="pf-avatar-card">
            <div class="pf-avatar-top">
                <div class="pf-avatar-top-bg"></div>
                <div class="pf-avatar-circle" style="background:<?= $cfg['color'] ?>;">
                    <?= strtoupper(substr($_SESSION['nama_lengkap'], 0, 1)) ?>
                </div>
            </div>
            <div class="pf-avatar-body">
                <div class="pf-avatar-name"><?= htmlspecialchars($_SESSION['nama_lengkap']) ?></div>
                <div class="pf-role-badge" style="background:<?= $cfg['color'] ?>18;color:<?= $cfg['color'] ?>;">
                    <i class="fa-solid <?= $cfg['icon'] ?>"></i>
                    <?= $cfg['label'] ?>
                </div>
                <ul class="pf-info-list">
                    <li>
                        <i class="fa-solid fa-at" style="background:#2a9ab018;color:#2a9ab0;"></i>
                        <?= htmlspecialchars($profil['username']) ?>
                    </li>
                    <?php if ($role === 'pasien' && !empty($profil['no_hp'])): ?>
                    <li>
                        <i class="fa-solid fa-phone" style="background:#19875418;color:#198754;"></i>
                        <?= htmlspecialchars($profil['no_hp']) ?>
                    </li>
                    <?php endif; ?>
                    <?php if ($role === 'pasien' && !empty($profil['tanggal_lahir'])): ?>
                    <li>
                        <i class="fa-solid fa-cake-candles" style="background:#e07b5a18;color:#e07b5a;"></i>
                        <?= date('d M Y', strtotime($profil['tanggal_lahir'])) ?>
                    </li>
                    <?php endif; ?>
                    <?php if ($role === 'dokter' && !empty($profil['spesialis'])): ?>
                    <li>
                        <i class="fa-solid fa-stethoscope" style="background:#1a6b7a18;color:#1a6b7a;"></i>
                        <?= htmlspecialchars($profil['spesialis']) ?>
                    </li>
                    <?php endif; ?>
                    <?php if ($role === 'dokter' && !empty($profil['jam_praktek'])): ?>
                    <li>
                        <i class="fa-solid fa-clock" style="background:#f59e0b18;color:#f59e0b;"></i>
                        <?= htmlspecialchars($profil['jam_praktek']) ?>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- Ganti Password -->
        <div class="pf-pass-card">
            <div class="pf-card-head">
                <div class="pf-card-head-icon" style="background:#dc354518;color:#dc3545;">
                    <i class="fa-solid fa-lock"></i>
                </div>
                <h6>Ganti Password</h6>
            </div>
            <div class="pf-card-body">
                <form method="POST" action="?f=profil&m=profil">
                    <div class="pf-field">
                        <label class="pf-label">Password Lama</label>
                        <div class="pf-input-wrap">
                            <input type="password" name="password_lama" id="pl" class="pf-input"
                                   placeholder="Masukkan password lama" required>
                            <button type="button" class="pf-toggle" onclick="togglePf('pl','ipl')">
                                <i class="fa-solid fa-eye" id="ipl"></i>
                            </button>
                        </div>
                    </div>
                    <div class="pf-field">
                        <label class="pf-label">Password Baru</label>
                        <div class="pf-input-wrap">
                            <input type="password" name="password_baru" id="pb" class="pf-input"
                                   placeholder="Min. 6 karakter" required>
                            <button type="button" class="pf-toggle" onclick="togglePf('pb','ipb')">
                                <i class="fa-solid fa-eye" id="ipb"></i>
                            </button>
                        </div>
                    </div>
                    <div class="pf-field">
                        <label class="pf-label">Ulangi Password Baru</label>
                        <div class="pf-input-wrap">
                            <input type="password" name="password_ulang" id="pu" class="pf-input"
                                   placeholder="Ulangi password baru" required>
                            <button type="button" class="pf-toggle" onclick="togglePf('pu','ipu')">
                                <i class="fa-solid fa-eye" id="ipu"></i>
                            </button>
                        </div>
                    </div>
                    <button type="submit" name="ganti_password" class="pf-btn pf-btn-danger">
                        <i class="fa-solid fa-key"></i> Ubah Password
                    </button>
                </form>
            </div>
        </div>

    </div><!-- end kolom kiri -->

    <!-- ===== KOLOM KANAN ===== -->
    <div class="col-lg-8">
        <div class="pf-main-card">
            <div class="pf-card-head" style="padding:1.1rem 1.5rem;">
                <div class="pf-card-head-icon" style="background:<?= $cfg['color'] ?>18;color:<?= $cfg['color'] ?>;">
                    <i class="fa-solid fa-pen-to-square"></i>
                </div>
                <h6>Edit Informasi Profil</h6>
            </div>
            <div class="pf-card-body" style="padding:1.5rem;">

                <?php if (!$cfg['has_profile']): ?>
                <!-- STAF: tidak ada tabel profil terpisah -->
                <div class="pf-staf-info">
                    <i class="fa-solid fa-circle-info"></i>
                    <p>Akun <strong><?= $cfg['label'] ?></strong> tidak memiliki data profil tambahan di luar sistem. Anda hanya dapat mengganti password melalui form di sebelah kiri.</p>
                </div>
                <div class="pf-detail-row">
                    <span>Username</span>
                    <span><?= htmlspecialchars($profil['username']) ?></span>
                </div>
                <div class="pf-detail-row">
                    <span>Role</span>
                    <span>
                        <span class="pf-role-badge" style="background:<?= $cfg['color'] ?>18;color:<?= $cfg['color'] ?>;padding:0.2rem 0.75rem;font-size:0.72rem;">
                            <i class="fa-solid <?= $cfg['icon'] ?>"></i>
                            <?= $cfg['label'] ?>
                        </span>
                    </span>
                </div>
                <div class="pf-detail-row">
                    <span>Status Akun</span>
                    <span><span style="background:#e8f5e9;color:#198754;padding:0.2rem 0.75rem;border-radius:50px;font-size:0.75rem;font-weight:600;">Aktif</span></span>
                </div>

                <?php else: ?>
                <!-- PASIEN / DOKTER: ada form edit -->
                <form method="POST" action="?f=profil&m=profil">
                    <div class="row g-3">

                        <!-- Nama -->
                        <div class="col-md-8">
                            <div class="pf-field mb-0">
                                <label class="pf-label">
                                    <?= $role === 'dokter' ? 'Nama Lengkap (Beserta Gelar)' : 'Nama Lengkap' ?> <span style="color:#dc3545">*</span>
                                </label>
                                <input type="text" name="nama" class="pf-input"
                                       value="<?= htmlspecialchars($role === 'dokter' ? ($profil['nama_dokter']??'') : ($profil['nama_pasien']??'')) ?>"
                                       required placeholder="Nama lengkap">
                            </div>
                        </div>

                        <!-- Tanggal Lahir -->
                        <div class="col-md-4">
                            <div class="pf-field mb-0">
                                <label class="pf-label">Tanggal Lahir</label>
                                <input type="date" name="tanggal_lahir" class="pf-input"
                                       value="<?= htmlspecialchars($profil['tanggal_lahir']??'') ?>">
                            </div>
                        </div>

                        <!-- No HP -->
                        <div class="col-md-<?= $role === 'dokter' ? '6' : '6' ?>">
                            <div class="pf-field mb-0">
                                <label class="pf-label">No. Handphone</label>
                                <input type="text" name="no_hp" class="pf-input"
                                       value="<?= htmlspecialchars($profil['no_hp']??'') ?>"
                                       placeholder="08xxxxxxxxxx">
                            </div>
                        </div>

                        <?php if ($role === 'dokter'): ?>
                        <!-- Spesialisasi -->
                        <div class="col-md-6">
                            <div class="pf-field mb-0">
                                <label class="pf-label">Spesialisasi</label>
                                <input type="text" name="spesialis" class="pf-input"
                                       value="<?= htmlspecialchars($profil['spesialis']??'') ?>"
                                       placeholder="Kosongkan jika Dokter Umum">
                            </div>
                        </div>

                        <!-- Jam Praktek -->
                        <div class="col-12">
                            <label class="pf-label">Jam Praktek</label>
                            <div class="jam-grid">
                                <div class="pf-field mb-0">
                                    <label class="pf-label" style="font-size:0.7rem;">Mulai</label>
                                    <input type="time" name="jam_mulai" class="pf-input"
                                           value="<?= htmlspecialchars($jam_mulai_val) ?>">
                                </div>
                                <div class="pf-field mb-0">
                                    <label class="pf-label" style="font-size:0.7rem;">Selesai</label>
                                    <input type="time" name="jam_selesai" class="pf-input"
                                           value="<?= htmlspecialchars($jam_selesai_val) ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Hari Praktek -->
                        <div class="col-12">
                            <label class="pf-label">Hari Praktek</label>
                            <div class="hari-pills">
                                <?php foreach ($hari_options as $hari): ?>
                                <div class="hari-pill">
                                    <input type="checkbox" name="hari_praktek[]"
                                           id="hari_<?= $hari ?>"
                                           value="<?= $hari ?>"
                                           <?= in_array($hari, $hari_aktif) ? 'checked' : '' ?>>
                                    <label for="hari_<?= $hari ?>"><?= $hari ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Alamat -->
                        <div class="col-12">
                            <div class="pf-field mb-0">
                                <label class="pf-label">Alamat</label>
                                <textarea name="alamat" rows="3" class="pf-input"
                                          placeholder="Alamat domisili lengkap"><?= htmlspecialchars($profil['alamat']??'') ?></textarea>
                            </div>
                        </div>

                    </div><!-- end row -->

                    <div style="display:flex;justify-content:flex-end;margin-top:1.5rem;">
                        <button type="submit" name="update_profil" class="pf-btn pf-btn-primary">
                            <i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
                <?php endif; ?>

            </div>
        </div>
    </div><!-- end kolom kanan -->

</div><!-- end row -->
</div><!-- end pf-root -->

<?php if ($pesan): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    Swal.fire({
        title: '<?= $status === "success" ? "Berhasil!" : "Gagal!" ?>',
        text : '<?= addslashes($pesan) ?>',
        icon : '<?= $status ?>',
        confirmButtonColor: '#0b3d47',
        background: '#f7f3ee', color: '#1a2326',
        timer: <?= $status === 'success' ? 2000 : 0 ?>,
        showConfirmButton: <?= $status === 'success' ? 'false' : 'true' ?>
    }).then(() => {
        window.location.href = '?f=profil&m=profil';
    });
});
</script>
<?php endif; ?>

<script>
function togglePf(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    input.type  = input.type === 'password' ? 'text' : 'password';
    icon.classList.toggle('fa-eye');
    icon.classList.toggle('fa-eye-slash');
}
</script>