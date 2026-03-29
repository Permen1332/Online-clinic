<?php
session_start();
require_once '../config/koneksi.php';

if (isset($_SESSION['id_user'])) {
    header("Location: ../index.php");
    exit();
}

$status  = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {

            // Cek apakah akun diblokir
            if (isset($user['is_active']) && $user['is_active'] == 0) {
                $status  = 'error';
                $message = 'Akun Anda telah diblokir. Hubungi administrator.';
            } else {
                $_SESSION['id_user']  = $user['id_user'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = $user['role'];

                switch ($user['role']) {
                    case 'pasien':
                        $s = $pdo->prepare("SELECT nama_pasien FROM pasien WHERE id_user = ?");
                        $s->execute([$user['id_user']]);
                        $d = $s->fetch();
                        $_SESSION['nama_lengkap'] = $d ? $d['nama_pasien'] : 'Pasien';
                        break;
                    case 'dokter':
                        $s = $pdo->prepare("SELECT nama_dokter FROM dokter WHERE id_user = ?");
                        $s->execute([$user['id_user']]);
                        $d = $s->fetch();
                        $_SESSION['nama_lengkap'] = $d ? $d['nama_dokter'] : 'Dokter';
                        break;
                    default:
                        $_SESSION['nama_lengkap'] = ucfirst($user['role']) . ' (' . $user['username'] . ')';
                }

                $status  = 'success';
                $message = 'Login berhasil! Mengalihkan ke Dashboard...';
            }

        } else {
            $status  = 'error';
            $message = 'Username atau password salah!';
        }
    } catch (PDOException $e) {
        $status  = 'error';
        $message = 'Terjadi kesalahan sistem.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk — Cipeng Clinic</title>

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

        /* ===== PANEL KIRI (FORM) ===== */
        .form-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 3rem 5rem;
            background: var(--cream);
            animation: slideInLeft 0.7s cubic-bezier(0.16,1,0.3,1) forwards;
        }
        @keyframes slideInLeft {
            from { opacity: 0; transform: translateX(-30px); }
            to   { opacity: 1; transform: translateX(0); }
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            color: var(--text-light);
            text-decoration: none;
            font-size: 0.82rem;
            font-weight: 500;
            margin-bottom: 3rem;
            transition: color 0.2s;
        }
        .back-link:hover { color: var(--teal-mid); }

        .form-brand {
            font-family: 'Playfair Display', serif;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--teal-deep);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
        }
        .form-brand .dot { width: 7px; height: 7px; background: var(--coral); border-radius: 50%; }

        .form-heading {
            font-family: 'Playfair Display', serif;
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--teal-deep);
            line-height: 1.2;
            margin-bottom: 0.5rem;
        }
        .form-heading em { font-style: italic; color: var(--coral); }

        .form-sub {
            font-size: 0.9rem;
            color: var(--text-mid);
            font-weight: 300;
            margin-bottom: 2.5rem;
            line-height: 1.6;
        }

        .field-group { margin-bottom: 1.25rem; }
        .field-label {
            display: block;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-mid);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }
        .field-wrap { position: relative; }
        .field-icon {
            position: absolute;
            left: 1rem; top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            font-size: 0.9rem;
        }
        .field-input {
            width: 100%;
            padding: 0.85rem 1rem 0.85rem 2.75rem;
            border: 1.5px solid var(--cream-dark);
            border-radius: 12px;
            background: var(--cream);
            font-family: 'DM Sans', sans-serif;
            font-size: 0.95rem;
            color: var(--text-dark);
            transition: all 0.2s;
            outline: none;
        }
        .field-input:focus {
            border-color: var(--teal-light);
            background: white;
            box-shadow: 0 0 0 4px rgba(42,154,176,0.1);
        }
        .field-input::placeholder { color: var(--text-light); }

        .toggle-pass {
            position: absolute;
            right: 1rem; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            color: var(--text-light);
            cursor: pointer; font-size: 0.9rem;
            transition: color 0.2s;
        }
        .toggle-pass:hover { color: var(--teal-mid); }

        .btn-submit {
            width: 100%;
            padding: 0.95rem;
            background: var(--teal-deep);
            color: white; border: none;
            border-radius: 12px;
            font-family: 'DM Sans', sans-serif;
            font-size: 1rem; font-weight: 600;
            cursor: pointer; margin-top: 0.5rem;
            display: flex; align-items: center;
            justify-content: center; gap: 0.6rem;
            box-shadow: 0 6px 20px rgba(11,61,71,0.2);
            transition: all 0.25s;
        }
        .btn-submit:hover {
            background: var(--teal-mid);
            transform: translateY(-1px);
            box-shadow: 0 10px 28px rgba(11,61,71,0.28);
        }

        .form-footer {
            text-align: center;
            margin-top: 1.75rem;
            font-size: 0.875rem;
            color: var(--text-mid);
        }
        .form-footer a { color: var(--coral); font-weight: 600; text-decoration: none; }
        .form-footer a:hover { color: var(--coral-light); }

        /* ===== PANEL KANAN ===== */
        .brand-panel {
            width: 45%;
            background: var(--teal-deep);
            position: relative; overflow: hidden;
            display: flex; flex-direction: column;
            justify-content: center; align-items: center;
            padding: 4rem 3rem; text-align: center;
            animation: slideInRight 0.7s cubic-bezier(0.16,1,0.3,1) forwards;
        }
        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(30px); }
            to   { opacity: 1; transform: translateX(0); }
        }
        .brand-panel::before {
            content: '';
            position: absolute; inset: 0;
            background:
                radial-gradient(ellipse 60% 50% at 80% 20%, rgba(42,154,176,0.3) 0%, transparent 60%),
                radial-gradient(ellipse 40% 60% at 20% 80%, rgba(224,123,90,0.15) 0%, transparent 50%);
        }
        .deco-ring { position: absolute; border-radius: 50%; border: 1px solid rgba(255,255,255,0.06); }
        .ring-1 { width: 500px; height: 500px; top: -200px; right: -180px; }
        .ring-2 { width: 320px; height: 320px; bottom: -100px; left: -80px; }

        .brand-content { position: relative; z-index: 1; }
        .brand-icon-wrap {
            width: 80px; height: 80px; border-radius: 24px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.15);
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem; color: white;
            margin: 0 auto 1.5rem;
        }
        .brand-title {
            font-family: 'Playfair Display', serif;
            font-size: 2rem; font-weight: 700;
            color: white; margin-bottom: 0.75rem;
        }
        .brand-desc {
            font-size: 0.9rem; color: rgba(255,255,255,0.55);
            line-height: 1.7; font-weight: 300;
            max-width: 300px; margin: 0 auto 2.5rem;
        }
        .brand-features { display: flex; flex-direction: column; gap: 0.75rem; text-align: left; width: 100%; max-width: 280px; }
        .brand-feat-item {
            display: flex; align-items: center; gap: 0.75rem;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 10px; padding: 0.65rem 1rem;
        }
        .brand-feat-item i { color: var(--coral-light); font-size: 0.9rem; flex-shrink: 0; }
        .brand-feat-item span { font-size: 0.82rem; color: rgba(255,255,255,0.7); }

        @media (max-width: 768px) {
            .brand-panel { display: none; }
            .form-panel  { padding: 3rem 2rem; }
        }
    </style>
</head>
<body>
    <div class="form-panel">
        <a href="welcome.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Kembali ke Beranda
        </a>
        <div class="form-brand">
            <i class="fa-solid fa-house-medical" style="color:var(--teal-light);"></i>
            Cipeng Clinic <span class="dot"></span>
        </div>
        <h1 class="form-heading">Selamat Datang<br><em>Kembali</em></h1>
        <p class="form-sub">Masukkan kredensial Anda untuk mengakses sistem klinik.</p>

        <form method="POST" action="">
            <div class="field-group">
                <label class="field-label">Username</label>
                <div class="field-wrap">
                    <i class="fa-solid fa-at field-icon"></i>
                    <input type="text" name="username" class="field-input"
                           placeholder="Masukkan username" required autocomplete="username">
                </div>
            </div>
            <div class="field-group">
                <label class="field-label">Password</label>
                <div class="field-wrap">
                    <i class="fa-solid fa-lock field-icon"></i>
                    <input type="password" name="password" class="field-input"
                           placeholder="Masukkan password" required id="passInput">
                    <button type="button" class="toggle-pass" onclick="togglePass()">
                        <i class="fa-solid fa-eye" id="passIcon"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn-submit">
                <i class="fa-solid fa-right-to-bracket"></i> Masuk ke Sistem
            </button>
        </form>

        <div class="form-footer">
            Belum punya akun?
            <a href="register.php">Daftar sebagai Pasien</a>
        </div>
    </div>

    <div class="brand-panel">
        <div class="deco-ring ring-1"></div>
        <div class="deco-ring ring-2"></div>
        <div class="brand-content">
            <div class="brand-icon-wrap"><i class="fa-solid fa-heart-pulse"></i></div>
            <h2 class="brand-title">Cipeng Clinic</h2>
            <p class="brand-desc">Platform manajemen klinik digital yang menghubungkan semua pihak dalam satu ekosistem terintegrasi.</p>
            <div class="brand-features">
                <div class="brand-feat-item">
                    <i class="fa-solid fa-calendar-check"></i>
                    <span>Antrian digital tanpa perlu datang lebih awal</span>
                </div>
                <div class="brand-feat-item">
                    <i class="fa-solid fa-notes-medical"></i>
                    <span>Rekam medis & resep terdigitalisasi penuh</span>
                </div>
                <div class="brand-feat-item">
                    <i class="fa-solid fa-shield-halved"></i>
                    <span>Akses berlapis sesuai peran pengguna</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePass() {
            const input = document.getElementById('passInput');
            const icon  = document.getElementById('passIcon');
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
            title: 'Berhasil!', text: '<?= $message ?>',
            icon: 'success', timer: 1500, showConfirmButton: false,
            background: '#f7f3ee', color: '#1a2326'
        }).then(() => { window.location.href = '../index.php'; });
        <?php elseif ($status === 'error'): ?>
        Swal.fire({
            title: 'Gagal Masuk', text: '<?= addslashes($message) ?>',
            icon: 'error', confirmButtonColor: '#0b3d47',
            background: '#f7f3ee', color: '#1a2326'
        });
        <?php endif; ?>
    </script>
</body>
</html>