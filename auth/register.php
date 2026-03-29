<?php
session_start();
require_once '../config/koneksi.php';

$status  = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_pasien   = htmlspecialchars(trim($_POST['nama_pasien']));
    $username      = htmlspecialchars(trim($_POST['username']));
    $password      = $_POST['password'];
    $konfirmasi    = $_POST['konfirmasi_password'];
    $no_hp         = htmlspecialchars(trim($_POST['no_hp']));
    $tanggal_lahir = $_POST['tanggal_lahir'];
    $alamat        = htmlspecialchars(trim($_POST['alamat']));

    if ($password !== $konfirmasi) {
        $status  = 'error';
        $message = 'Password dan konfirmasi password tidak cocok!';
    } elseif (strlen($password) < 6) {
        $status  = 'error';
        $message = 'Password minimal 6 karakter!';
    } else {
        try {
            $cek = $pdo->prepare("SELECT id_user FROM users WHERE username = ?");
            $cek->execute([$username]);
            if ($cek->rowCount() > 0) {
                $status  = 'error';
                $message = 'Username sudah digunakan, coba yang lain.';
            } else {
                $pdo->beginTransaction();
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'pasien')")
                    ->execute([$username, $hash]);
                $id_user = $pdo->lastInsertId();
                $pdo->prepare("INSERT INTO pasien (id_user, nama_pasien, tanggal_lahir, alamat, no_hp) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$id_user, $nama_pasien, $tanggal_lahir, $alamat, $no_hp]);
                $pdo->commit();
                $status  = 'success';
                $message = 'Akun berhasil dibuat! Silakan login.';
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $status  = 'error';
            $message = 'Terjadi kesalahan sistem.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar — Cipeng Clinic</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --teal-deep:  #0b3d47;
            --teal-mid:   #1a6b7a;
            --teal-light: #2a9ab0;
            --cream:      #f7f3ee;
            --cream-dark: #ede8e1;
            --coral:      #e07b5a;
            --coral-light:#f09070;
            --text-dark:  #1a2326;
            --text-mid:   #4a6568;
            --text-light: #8aa5a9;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--cream);
            min-height: 100vh;
            display: flex;
            align-items: stretch;
            overflow-x: hidden;
        }

        /* ===== PANEL KIRI (BRANDING) ===== */
        .brand-panel {
            width: 38%;
            background: var(--teal-deep);
            position: relative; overflow: hidden;
            display: flex; flex-direction: column;
            justify-content: center; align-items: center;
            padding: 4rem 3rem; text-align: center;
            animation: slideInLeft 0.7s cubic-bezier(0.16,1,0.3,1) forwards;
        }
        @keyframes slideInLeft {
            from { opacity: 0; transform: translateX(-30px); }
            to   { opacity: 1; transform: translateX(0); }
        }
        .brand-panel::before {
            content: '';
            position: absolute; inset: 0;
            background:
                radial-gradient(ellipse 60% 50% at 20% 20%, rgba(42,154,176,0.3) 0%, transparent 60%),
                radial-gradient(ellipse 40% 60% at 80% 80%, rgba(224,123,90,0.15) 0%, transparent 50%);
        }
        .deco-ring { position: absolute; border-radius: 50%; border: 1px solid rgba(255,255,255,0.06); }
        .ring-1 { width: 500px; height: 500px; top: -200px; left: -180px; }
        .ring-2 { width: 320px; height: 320px; bottom: -100px; right: -80px; }

        .brand-content { position: relative; z-index: 1; }
        .brand-icon-wrap {
            width: 80px; height: 80px; border-radius: 24px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.15);
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem; color: white; margin: 0 auto 1.5rem;
        }
        .brand-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.9rem; font-weight: 700;
            color: white; margin-bottom: 0.75rem;
        }
        .brand-desc {
            font-size: 0.88rem; color: rgba(255,255,255,0.55);
            line-height: 1.7; font-weight: 300;
            max-width: 280px; margin: 0 auto 2rem;
        }

        /* Keuntungan daftar */
        .benefit-list { display: flex; flex-direction: column; gap: 0.6rem; text-align: left; width: 100%; max-width: 260px; }
        .benefit-item {
            display: flex; align-items: flex-start; gap: 0.65rem;
        }
        .benefit-icon {
            width: 24px; height: 24px; border-radius: 8px;
            background: rgba(224,123,90,0.25);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; margin-top: 1px;
        }
        .benefit-icon i { color: var(--coral-light); font-size: 0.7rem; }
        .benefit-item span { font-size: 0.82rem; color: rgba(255,255,255,0.65); line-height: 1.5; }

        /* Step indicator */
        .step-indicator {
            display: flex; gap: 0.5rem;
            margin-top: 2.5rem; justify-content: center;
        }
        .step-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: rgba(255,255,255,0.2);
            transition: all 0.3s;
        }
        .step-dot.active { background: var(--coral); width: 24px; border-radius: 4px; }

        /* ===== PANEL KANAN (FORM) ===== */
        .form-panel {
            flex: 1;
            display: flex; flex-direction: column;
            justify-content: center;
            padding: 3rem 4rem;
            overflow-y: auto;
            animation: slideInRight 0.7s cubic-bezier(0.16,1,0.3,1) forwards;
        }
        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(30px); }
            to   { opacity: 1; transform: translateX(0); }
        }

        .back-link {
            display: inline-flex; align-items: center; gap: 0.4rem;
            color: var(--text-light); text-decoration: none;
            font-size: 0.82rem; font-weight: 500;
            margin-bottom: 2rem; transition: color 0.2s;
        }
        .back-link:hover { color: var(--teal-mid); }

        .form-heading {
            font-family: 'Playfair Display', serif;
            font-size: 2rem; font-weight: 700;
            color: var(--teal-deep); line-height: 1.2;
            margin-bottom: 0.4rem;
        }
        .form-heading em { font-style: italic; color: var(--coral); }
        .form-sub {
            font-size: 0.875rem; color: var(--text-mid);
            font-weight: 300; margin-bottom: 2rem; line-height: 1.6;
        }

        /* Section divider */
        .form-section {
            font-size: 0.72rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 1px;
            color: var(--text-light);
            margin: 1.25rem 0 0.75rem;
            display: flex; align-items: center; gap: 0.75rem;
        }
        .form-section::after {
            content: ''; flex: 1; height: 1px; background: var(--cream-dark);
        }

        /* Grid form */
        .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .field-group { margin-bottom: 0.75rem; }
        .field-label {
            display: block; font-size: 0.78rem; font-weight: 600;
            color: var(--text-mid); text-transform: uppercase;
            letter-spacing: 0.5px; margin-bottom: 0.4rem;
        }
        .field-wrap { position: relative; }
        .field-icon {
            position: absolute; left: 0.9rem; top: 50%;
            transform: translateY(-50%);
            color: var(--text-light); font-size: 0.85rem;
        }
        .field-input {
            width: 100%;
            padding: 0.75rem 0.9rem 0.75rem 2.5rem;
            border: 1.5px solid var(--cream-dark);
            border-radius: 12px; background: var(--cream);
            font-family: 'DM Sans', sans-serif;
            font-size: 0.9rem; color: var(--text-dark);
            transition: all 0.2s; outline: none;
        }
        .field-input:focus {
            border-color: var(--teal-light); background: white;
            box-shadow: 0 0 0 4px rgba(42,154,176,0.1);
        }
        .field-input::placeholder { color: var(--text-light); }
        textarea.field-input { padding-left: 0.9rem; resize: none; }

        .toggle-pass {
            position: absolute; right: 0.9rem; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            color: var(--text-light); cursor: pointer; font-size: 0.85rem;
            transition: color 0.2s;
        }
        .toggle-pass:hover { color: var(--teal-mid); }

        .btn-submit {
            width: 100%; padding: 0.9rem;
            background: var(--coral); color: white; border: none;
            border-radius: 12px;
            font-family: 'DM Sans', sans-serif;
            font-size: 1rem; font-weight: 600; cursor: pointer;
            margin-top: 1rem;
            display: flex; align-items: center;
            justify-content: center; gap: 0.6rem;
            box-shadow: 0 6px 20px rgba(224,123,90,0.3);
            transition: all 0.25s;
        }
        .btn-submit:hover {
            background: var(--coral-light);
            transform: translateY(-1px);
            box-shadow: 0 10px 28px rgba(224,123,90,0.4);
        }

        .form-footer {
            text-align: center; margin-top: 1.5rem;
            font-size: 0.875rem; color: var(--text-mid);
        }
        .form-footer a { color: var(--teal-mid); font-weight: 600; text-decoration: none; }
        .form-footer a:hover { color: var(--teal-deep); }

        @media (max-width: 900px) {
            .brand-panel { display: none; }
            .form-panel  { padding: 3rem 2rem; }
            .field-row   { grid-template-columns: 1fr; }
        }
        @media (max-width: 480px) {
            .form-panel { padding: 2rem 1.5rem; }
        }
    </style>
</head>
<body>

    <!-- PANEL KIRI: BRANDING -->
    <div class="brand-panel">
        <div class="deco-ring ring-1"></div>
        <div class="deco-ring ring-2"></div>
        <div class="brand-content">
            <div class="brand-icon-wrap">
                <i class="fa-solid fa-house-medical"></i>
            </div>
            <h2 class="brand-title">Cipeng Clinic</h2>
            <p class="brand-desc">Bergabunglah dan nikmati kemudahan layanan kesehatan digital.</p>

            <div class="benefit-list">
                <div class="benefit-item">
                    <div class="benefit-icon"><i class="fa-solid fa-check"></i></div>
                    <span>Daftar antrian kapan saja tanpa antre fisik</span>
                </div>
                <div class="benefit-item">
                    <div class="benefit-icon"><i class="fa-solid fa-check"></i></div>
                    <span>Riwayat pemeriksaan tersimpan aman di sistem</span>
                </div>
                <div class="benefit-item">
                    <div class="benefit-icon"><i class="fa-solid fa-check"></i></div>
                    <span>Pilih dokter dan jadwal sesuai kebutuhan</span>
                </div>
                <div class="benefit-item">
                    <div class="benefit-icon"><i class="fa-solid fa-check"></i></div>
                    <span>Gratis, mudah, dan tanpa biaya pendaftaran</span>
                </div>
            </div>

            <div class="step-indicator">
                <div class="step-dot active"></div>
                <div class="step-dot"></div>
                <div class="step-dot"></div>
            </div>
        </div>
    </div>

    <!-- PANEL KANAN: FORM -->
    <div class="form-panel">
        <a href="welcome.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Kembali ke Beranda
        </a>

        <h1 class="form-heading">Buat Akun <em>Baru</em></h1>
        <p class="form-sub">Lengkapi data diri Anda untuk mulai menggunakan layanan Cipeng Clinic.</p>

        <form method="POST" action="">
            <!-- Data Diri -->
            <div class="form-section">Data Diri</div>

            <div class="field-group">
                <label class="field-label">Nama Lengkap</label>
                <div class="field-wrap">
                    <i class="fa-regular fa-user field-icon"></i>
                    <input type="text" name="nama_pasien" class="field-input"
                           placeholder="Sesuai KTP" required>
                </div>
            </div>

            <div class="field-row">
                <div class="field-group">
                    <label class="field-label">Tanggal Lahir</label>
                    <div class="field-wrap">
                        <i class="fa-regular fa-calendar field-icon"></i>
                        <input type="date" name="tanggal_lahir" class="field-input" required>
                    </div>
                </div>
                <div class="field-group">
                    <label class="field-label">No. Handphone</label>
                    <div class="field-wrap">
                        <i class="fa-solid fa-phone field-icon"></i>
                        <input type="text" name="no_hp" class="field-input"
                               placeholder="08xxxxxxxxxx" required>
                    </div>
                </div>
            </div>

            <div class="field-group">
                <label class="field-label">Alamat</label>
                <div class="field-wrap">
                    <textarea name="alamat" rows="2" class="field-input"
                              placeholder="Alamat domisili lengkap" required></textarea>
                </div>
            </div>

            <!-- Kredensial -->
            <div class="form-section">Kredensial Login</div>

            <div class="field-group">
                <label class="field-label">Username</label>
                <div class="field-wrap">
                    <i class="fa-solid fa-at field-icon"></i>
                    <input type="text" name="username" class="field-input"
                           placeholder="Buat username unik" required autocomplete="username">
                </div>
            </div>

            <div class="field-row">
                <div class="field-group">
                    <label class="field-label">Password</label>
                    <div class="field-wrap">
                        <i class="fa-solid fa-lock field-icon"></i>
                        <input type="password" name="password" id="pass1" class="field-input"
                               placeholder="Min. 6 karakter" required>
                        <button type="button" class="toggle-pass" onclick="togglePass('pass1','icon1')">
                            <i class="fa-solid fa-eye" id="icon1"></i>
                        </button>
                    </div>
                </div>
                <div class="field-group">
                    <label class="field-label">Ulangi Password</label>
                    <div class="field-wrap">
                        <i class="fa-solid fa-lock field-icon"></i>
                        <input type="password" name="konfirmasi_password" id="pass2" class="field-input"
                               placeholder="Ulangi password" required>
                        <button type="button" class="toggle-pass" onclick="togglePass('pass2','icon2')">
                            <i class="fa-solid fa-eye" id="icon2"></i>
                        </button>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-submit">
                <i class="fa-solid fa-user-plus"></i> Buat Akun Sekarang
            </button>
        </form>

        <div class="form-footer">
            Sudah punya akun?
            <a href="login.php">Masuk di sini</a>
        </div>
    </div>

    <script>
        function togglePass(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon  = document.getElementById(iconId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        <?php if ($status === 'success'): ?>
        Swal.fire({
            title: 'Akun Berhasil Dibuat!',
            text : '<?= $message ?>',
            icon : 'success',
            confirmButtonColor: '#0b3d47',
            confirmButtonText: 'Lanjut Login',
            background: '#f7f3ee', color: '#1a2326'
        }).then(() => { window.location.href = 'login.php'; });
        <?php elseif ($status === 'error'): ?>
        Swal.fire({
            title: 'Pendaftaran Gagal',
            text : '<?= addslashes($message) ?>',
            icon : 'error',
            confirmButtonColor: '#0b3d47',
            background: '#f7f3ee', color: '#1a2326'
        });
        <?php endif; ?>
    </script>
</body>
</html>