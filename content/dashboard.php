<?php
if (!isset($_SESSION['id_user'])) exit('Akses Ditolak!');

$role         = $_SESSION['role'];
$nama_lengkap = $_SESSION['nama_lengkap'];
$id_user      = $_SESSION['id_user'];
$hari_ini     = date('Y-m-d');

// Salam berdasarkan waktu
$jam = (int) date('H');
if ($jam >= 5  && $jam < 12) $salam = 'Selamat Pagi';
elseif ($jam >= 12 && $jam < 15) $salam = 'Selamat Siang';
elseif ($jam >= 15 && $jam < 19) $salam = 'Selamat Sore';
else $salam = 'Selamat Malam';

// Hari dalam Bahasa Indonesia
$hari_map = ['Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu',
             'Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu','Sunday'=>'Minggu'];
$bulan_map = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
              7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
$tanggal_lengkap = $hari_map[date('l')] . ', ' . date('j') . ' ' . $bulan_map[(int)date('n')] . ' ' . date('Y');

// ==============================================================
//  QUERY PER ROLE
// ==============================================================
$stats   = [];
$recents = [];

try {
    // ---- PASIEN ----
    if ($role === 'pasien') {
        $sp = $pdo->prepare("SELECT id_pasien FROM pasien WHERE id_user = ?");
        $sp->execute([$id_user]);
        $id_pasien = $sp->fetchColumn();

        if ($id_pasien) {
            $stats['antrian_aktif'] = $pdo->prepare("SELECT COUNT(*) FROM antrian WHERE id_pasien = ? AND status_kedatangan = 'belum datang'");
            $stats['antrian_aktif']->execute([$id_pasien]);
            $stats['antrian_aktif'] = $stats['antrian_aktif']->fetchColumn();

            $stats['riwayat'] = $pdo->prepare("SELECT COUNT(*) FROM antrian a JOIN pemeriksaan pm ON pm.id_antrian = a.id_antrian WHERE a.id_pasien = ?");
            $stats['riwayat']->execute([$id_pasien]);
            $stats['riwayat'] = $stats['riwayat']->fetchColumn();

            $stats['total_antrian'] = $pdo->prepare("SELECT COUNT(*) FROM antrian WHERE id_pasien = ?");
            $stats['total_antrian']->execute([$id_pasien]);
            $stats['total_antrian'] = $stats['total_antrian']->fetchColumn();

            // Antrian mendatang
            $sq = $pdo->prepare("
                SELECT a.tanggal_kunjungan, d.nama_dokter, d.spesialis,
                       a.id_antrian, a.status_kedatangan
                FROM antrian a
                JOIN dokter d ON d.id_dokter = a.id_dokter
                WHERE a.id_pasien = ? AND a.status_kedatangan = 'belum datang'
                ORDER BY a.tanggal_kunjungan ASC LIMIT 3
            ");
            $sq->execute([$id_pasien]);
            $recents = $sq->fetchAll();
        }
    }

    // ---- DOKTER ----
    elseif ($role === 'dokter') {
        $sd = $pdo->prepare("SELECT id_dokter FROM dokter WHERE id_user = ?");
        $sd->execute([$id_user]);
        $id_dokter = $sd->fetchColumn();

        if ($id_dokter) {
            $sq = $pdo->prepare("SELECT COUNT(*) FROM antrian WHERE id_dokter = ? AND tanggal_kunjungan = ? AND status_kedatangan = 'datang'");
            $sq->execute([$id_dokter, $hari_ini]);
            $stats['antrian_hari_ini'] = $sq->fetchColumn();

            $sq2 = $pdo->prepare("SELECT COUNT(*) FROM pemeriksaan pm JOIN antrian a ON a.id_antrian = pm.id_antrian WHERE a.id_dokter = ?");
            $sq2->execute([$id_dokter]);
            $stats['total_diperiksa'] = $sq2->fetchColumn();

            $sq3 = $pdo->prepare("SELECT COUNT(*) FROM antrian WHERE id_dokter = ? AND tanggal_kunjungan = ? AND status_kedatangan = 'datang' AND id_antrian NOT IN (SELECT id_antrian FROM pemeriksaan)");
            $sq3->execute([$id_dokter, $hari_ini]);
            $stats['belum_diperiksa'] = $sq3->fetchColumn();

            $sq4 = $pdo->prepare("SELECT COUNT(*) FROM resep_obat ro JOIN pemeriksaan pm ON pm.id_pemeriksaan = ro.id_pemeriksaan JOIN antrian a ON a.id_antrian = pm.id_antrian WHERE a.id_dokter = ? AND ro.status_resep = 'menunggu'");
            $sq4->execute([$id_dokter]);
            $stats['resep_menunggu'] = $sq4->fetchColumn();

            // Antrian hari ini (yang sudah hadir, belum diperiksa)
            $sq5 = $pdo->prepare("
                SELECT p.nama_pasien, a.id_antrian, a.tanggal_kunjungan,
                       TIMESTAMPDIFF(YEAR, p.tanggal_lahir, CURDATE()) AS usia
                FROM antrian a
                JOIN pasien p ON p.id_pasien = a.id_pasien
                WHERE a.id_dokter = ? AND a.tanggal_kunjungan = ?
                      AND a.status_kedatangan = 'datang'
                      AND a.id_antrian NOT IN (SELECT id_antrian FROM pemeriksaan)
                ORDER BY a.id_antrian ASC LIMIT 5
            ");
            $sq5->execute([$id_dokter, $hari_ini]);
            $recents = $sq5->fetchAll();
        }
    }

    // ---- RESEPSIONIS ----
    elseif ($role === 'resepsionis') {
        $sq = $pdo->prepare("SELECT COUNT(*) FROM antrian WHERE tanggal_kunjungan = ? AND status_kedatangan = 'datang'");
        $sq->execute([$hari_ini]);
        $stats['hadir'] = $sq->fetchColumn();

        $sq2 = $pdo->prepare("SELECT COUNT(*) FROM antrian WHERE tanggal_kunjungan = ? AND status_kedatangan = 'belum datang'");
        $sq2->execute([$hari_ini]);
        $stats['menunggu'] = $sq2->fetchColumn();

        $sq3 = $pdo->prepare("SELECT COUNT(*) FROM antrian WHERE tanggal_kunjungan = ? AND status_kedatangan = 'batal'");
        $sq3->execute([$hari_ini]);
        $stats['batal'] = $sq3->fetchColumn();

        $stats['total_dokter'] = $pdo->query("SELECT COUNT(*) FROM dokter")->fetchColumn();

        // Antrian belum datang hari ini
        $sq4 = $pdo->prepare("
            SELECT p.nama_pasien, d.nama_dokter, a.id_antrian,
                   TIMESTAMPDIFF(YEAR, p.tanggal_lahir, CURDATE()) AS usia,
                   a.keluhan
            FROM antrian a
            JOIN pasien p ON p.id_pasien = a.id_pasien
            JOIN dokter  d ON d.id_dokter = a.id_dokter
            WHERE a.tanggal_kunjungan = ? AND a.status_kedatangan = 'belum datang'
            ORDER BY a.id_antrian ASC LIMIT 5
        ");
        $sq4->execute([$hari_ini]);
        $recents = $sq4->fetchAll();
    }

    // ---- ADMIN ----
    elseif ($role === 'admin') {
        $stats['total_pasien']      = $pdo->query("SELECT COUNT(*) FROM pasien")->fetchColumn();
        $stats['total_dokter']      = $pdo->query("SELECT COUNT(*) FROM dokter")->fetchColumn();
        $stats['total_obat']        = $pdo->query("SELECT COUNT(*) FROM obat WHERE stock > 0")->fetchColumn();
        $stats['obat_menipis']      = $pdo->query("SELECT COUNT(*) FROM obat WHERE stock BETWEEN 1 AND 10")->fetchColumn();
        $stats['periksa_bulan_ini'] = $pdo->prepare("SELECT COUNT(*) FROM pemeriksaan WHERE MONTH(tanggal_periksa) = MONTH(CURDATE()) AND YEAR(tanggal_periksa) = YEAR(CURDATE())");
        $stats['periksa_bulan_ini']->execute();
        $stats['periksa_bulan_ini'] = $stats['periksa_bulan_ini']->fetchColumn();

        $stats['antrian_hari_ini']  = $pdo->prepare("SELECT COUNT(*) FROM antrian WHERE tanggal_kunjungan = ?");
        $stats['antrian_hari_ini']->execute([$hari_ini]);
        $stats['antrian_hari_ini']  = $stats['antrian_hari_ini']->fetchColumn();

        // 5 akun staf terakhir dibuat
        $sq = $pdo->query("SELECT username, role FROM users WHERE role IN ('resepsionis','apoteker','admin') ORDER BY id_user DESC LIMIT 5");
        $recents = $sq->fetchAll();
    }

    // ---- APOTEKER ----
    elseif ($role === 'apoteker') {
        $stats['resep_menunggu']   = $pdo->query("SELECT COUNT(DISTINCT id_pemeriksaan) FROM resep_obat WHERE status_resep = 'menunggu'")->fetchColumn();
        $stats['selesai_hari_ini'] = $pdo->prepare("SELECT COUNT(DISTINCT ro.id_pemeriksaan) FROM resep_obat ro JOIN pemeriksaan pm ON pm.id_pemeriksaan = ro.id_pemeriksaan WHERE ro.status_resep = 'selesai' AND DATE(pm.tanggal_periksa) = ?");
        $stats['selesai_hari_ini']->execute([$hari_ini]);
        $stats['selesai_hari_ini'] = $stats['selesai_hari_ini']->fetchColumn();

        $stats['stok_aman']    = $pdo->query("SELECT COUNT(*) FROM obat WHERE stock > 10")->fetchColumn();
        $stats['stok_menipis'] = $pdo->query("SELECT COUNT(*) FROM obat WHERE stock BETWEEN 1 AND 10")->fetchColumn();
        $stats['stok_habis']   = $pdo->query("SELECT COUNT(*) FROM obat WHERE stock = 0")->fetchColumn();

        // Resep menunggu terbaru
        $sq = $pdo->query("
            SELECT p.nama_pasien, d.nama_dokter, pm.tanggal_periksa,
                   COUNT(ro.id_resep) AS jumlah_item
            FROM resep_obat ro
            JOIN pemeriksaan pm ON pm.id_pemeriksaan = ro.id_pemeriksaan
            JOIN antrian a ON a.id_antrian = pm.id_antrian
            JOIN pasien p ON p.id_pasien = a.id_pasien
            JOIN dokter d ON d.id_dokter = a.id_dokter
            WHERE ro.status_resep = 'menunggu'
            GROUP BY ro.id_pemeriksaan
            ORDER BY pm.tanggal_periksa DESC LIMIT 5
        ");
        $recents = $sq->fetchAll();
    }
} catch (PDOException $e) {
    // Biarkan stats = 0
}

// Konfigurasi warna & ikon per role
$role_config = [
    'pasien'      => ['color'=>'#1a6b7a', 'accent'=>'#e07b5a', 'icon'=>'fa-user',           'label'=>'Pasien'],
    'dokter'      => ['color'=>'#1a6b7a', 'accent'=>'#2a9ab0', 'icon'=>'fa-stethoscope',    'label'=>'Dokter'],
    'resepsionis' => ['color'=>'#1a6b7a', 'accent'=>'#f59e0b', 'icon'=>'fa-headset',        'label'=>'Resepsionis'],
    'admin'       => ['color'=>'#1a6b7a', 'accent'=>'#dc3545', 'icon'=>'fa-shield-halved',  'label'=>'Admin'],
    'apoteker'    => ['color'=>'#1a6b7a', 'accent'=>'#198754', 'icon'=>'fa-mortar-pestle',  'label'=>'Apoteker'],
];
$cfg = $role_config[$role];
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap');

.db-root {
    font-family: 'DM Sans', sans-serif;
    animation: dbFadeIn 0.5s ease forwards;
}
@keyframes dbFadeIn { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }

/* ===== HERO BANNER ===== */
.db-hero {
    border-radius: 20px;
    overflow: hidden;
    position: relative;
    margin-bottom: 1.75rem;
    background: #0b3d47;
    padding: 2.25rem 2.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    animation: dbFadeIn 0.5s ease forwards;
}
.db-hero::before {
    content: '';
    position: absolute; inset: 0;
    background:
        radial-gradient(ellipse 60% 80% at 90% 50%, rgba(42,154,176,0.25) 0%, transparent 60%),
        radial-gradient(ellipse 30% 50% at 5%  80%, rgba(224,123,90,0.15) 0%, transparent 50%);
}
.db-hero-deco {
    position: absolute;
    right: -40px; top: -60px;
    font-size: 13rem;
    opacity: 0.045;
    color: white;
    line-height: 1;
}
.db-hero-left { position: relative; z-index: 1; }
.db-hero-date {
    display: inline-flex; align-items: center; gap: 0.5rem;
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 50px;
    padding: 0.3rem 1rem;
    font-size: 0.78rem;
    color: rgba(255,255,255,0.65);
    font-weight: 500;
    margin-bottom: 1rem;
}
.db-hero-date i { color: #2a9ab0; }
.db-hero-salam {
    font-family: 'Playfair Display', serif;
    font-size: clamp(1.5rem, 3vw, 2.1rem);
    font-weight: 700;
    color: white;
    line-height: 1.15;
    margin-bottom: 0.35rem;
}
.db-hero-salam em { font-style: italic; color: #f09070; }
.db-hero-sub {
    font-size: 0.875rem;
    color: rgba(255,255,255,0.5);
    font-weight: 300;
    margin-bottom: 1.5rem;
}
.db-hero-actions { display: flex; gap: 0.75rem; flex-wrap: wrap; }
.db-btn-primary {
    display: inline-flex; align-items: center; gap: 0.5rem;
    padding: 0.6rem 1.4rem;
    background: #e07b5a; color: white;
    border-radius: 50px; font-size: 0.875rem; font-weight: 600;
    text-decoration: none;
    box-shadow: 0 4px 14px rgba(224,123,90,0.35);
    transition: all 0.2s;
}
.db-btn-primary:hover { background: #f09070; transform: translateY(-1px); color:white; }
.db-btn-outline {
    display: inline-flex; align-items: center; gap: 0.5rem;
    padding: 0.6rem 1.4rem;
    border: 1.5px solid rgba(255,255,255,0.25);
    color: rgba(255,255,255,0.8);
    border-radius: 50px; font-size: 0.875rem; font-weight: 500;
    text-decoration: none; transition: all 0.2s;
}
.db-btn-outline:hover { border-color: white; color: white; background: rgba(255,255,255,0.07); }

/* Badge role di hero kanan */
.db-hero-badge {
    position: relative; z-index: 1;
    display: flex; flex-direction: column;
    align-items: center; gap: 0.5rem;
    flex-shrink: 0;
}
.db-hero-icon {
    width: 72px; height: 72px;
    border-radius: 20px;
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.15);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.8rem; color: white;
}
.db-role-pill {
    padding: 0.25rem 0.85rem;
    border-radius: 50px;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
}

/* ===== STAT CARDS ===== */
.db-stats { display: grid; gap: 1rem; margin-bottom: 1.75rem; }
.db-stats-4 { grid-template-columns: repeat(4, 1fr); }
.db-stats-3 { grid-template-columns: repeat(3, 1fr); }
.db-stats-2 { grid-template-columns: repeat(2, 1fr); }

.db-stat {
    background: white;
    border-radius: 16px;
    padding: 1.4rem 1.5rem;
    border: 1.5px solid #f0ece7;
    position: relative; overflow: hidden;
    transition: all 0.25s ease;
    animation: dbFadeIn 0.5s ease forwards;
    opacity: 0;
}
.db-stat:hover {
    border-color: var(--stat-color);
    transform: translateY(-3px);
    box-shadow: 0 8px 28px rgba(0,0,0,0.06);
}
.db-stat::after {
    content: '';
    position: absolute; bottom: 0; left: 0; right: 0;
    height: 3px;
    background: var(--stat-color);
    transform: scaleX(0);
    transition: transform 0.3s;
}
.db-stat:hover::after { transform: scaleX(1); }
.db-stat-icon {
    width: 44px; height: 44px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem;
    margin-bottom: 1rem;
    background: color-mix(in srgb, var(--stat-color) 12%, white);
    color: var(--stat-color);
}
.db-stat-val {
    font-family: 'Playfair Display', serif;
    font-size: 2rem; font-weight: 700;
    color: #0b3d47; line-height: 1;
    margin-bottom: 0.2rem;
}
.db-stat-label {
    font-size: 0.78rem; color: #8aa5a9;
    font-weight: 500; text-transform: uppercase;
    letter-spacing: 0.4px;
}
.db-stat-deco {
    position: absolute; right: -8px; bottom: -8px;
    font-size: 4rem; opacity: 0.05;
    color: var(--stat-color);
}

/* Delay animasi */
.db-stat:nth-child(1){animation-delay:0.05s}
.db-stat:nth-child(2){animation-delay:0.12s}
.db-stat:nth-child(3){animation-delay:0.19s}
.db-stat:nth-child(4){animation-delay:0.26s}
.db-stat:nth-child(5){animation-delay:0.33s}
.db-stat:nth-child(6){animation-delay:0.40s}

/* ===== BOTTOM SECTION (2 kolom) ===== */
.db-bottom { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }

.db-panel {
    background: white;
    border-radius: 16px;
    border: 1.5px solid #f0ece7;
    overflow: hidden;
    animation: dbFadeIn 0.5s 0.3s ease forwards;
    opacity: 0;
}
.db-panel-head {
    padding: 1.1rem 1.4rem;
    border-bottom: 1px solid #f7f3ee;
    display: flex; align-items: center;
    justify-content: space-between;
}
.db-panel-title {
    font-family: 'Playfair Display', serif;
    font-size: 1rem; font-weight: 600;
    color: #0b3d47;
    display: flex; align-items: center; gap: 0.5rem;
}
.db-panel-title i { color: #2a9ab0; font-size: 0.9rem; }
.db-panel-link {
    font-size: 0.78rem; color: #2a9ab0;
    text-decoration: none; font-weight: 600;
}
.db-panel-link:hover { color: #0b3d47; }
.db-panel-body { padding: 0.5rem 0; }

/* Row item dalam panel */
.db-row-item {
    padding: 0.75rem 1.4rem;
    display: flex; align-items: center; gap: 0.85rem;
    border-bottom: 1px solid #f7f3ee;
    transition: background 0.15s;
}
.db-row-item:last-child { border-bottom: none; }
.db-row-item:hover { background: #faf8f5; }
.db-row-avatar {
    width: 36px; height: 36px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.95rem; font-weight: 700; flex-shrink: 0;
    background: #f7f3ee; color: #0b3d47;
}
.db-row-main { flex: 1; min-width: 0; }
.db-row-name { font-size: 0.875rem; font-weight: 600; color: #1a2326; }
.db-row-sub  { font-size: 0.76rem; color: #8aa5a9; margin-top: 0.1rem; }
.db-row-meta { font-size: 0.75rem; color: #4a6568; font-weight: 500; white-space: nowrap; }

/* Empty state */
.db-empty {
    padding: 2rem 1.4rem; text-align: center;
    color: #8aa5a9; font-size: 0.85rem;
}
.db-empty i { font-size: 2rem; margin-bottom: 0.5rem; display: block; opacity: 0.4; }

/* Info box */
.db-info {
    background: white;
    border-radius: 16px;
    border: 1.5px solid #f0ece7;
    padding: 1.25rem 1.4rem;
    display: flex; align-items: center; gap: 1rem;
    animation: dbFadeIn 0.5s 0.4s ease forwards;
    opacity: 0;
    margin-top: 1.25rem;
}
.db-info i { color: #2a9ab0; font-size: 1.1rem; flex-shrink: 0; }
.db-info p { font-size: 0.84rem; color: #4a6568; line-height: 1.6; margin: 0; font-weight: 300; }

/* Alert stok menipis */
.db-alert {
    background: #fff8f6; border: 1.5px solid #fbd5c9;
    border-radius: 16px; padding: 1rem 1.4rem;
    display: flex; align-items: center; gap: 0.85rem;
    margin-bottom: 1.25rem;
    animation: dbFadeIn 0.5s 0.1s ease forwards; opacity: 0;
}
.db-alert i { color: #e07b5a; font-size: 1.1rem; flex-shrink: 0; }
.db-alert-text { font-size: 0.84rem; color: #4a6568; line-height: 1.5; }
.db-alert-text strong { color: #c0532a; }

@media (max-width: 900px) {
    .db-stats-4, .db-stats-3 { grid-template-columns: repeat(2, 1fr); }
    .db-bottom { grid-template-columns: 1fr; }
    .db-hero { flex-direction: column; align-items: flex-start; }
    .db-hero-badge { flex-direction: row; }
}
@media (max-width: 560px) {
    .db-stats-4, .db-stats-3, .db-stats-2 { grid-template-columns: 1fr 1fr; }
    .db-hero { padding: 1.5rem; }
}
</style>

<div class="db-root">

<!-- ============================================================
     HERO BANNER
============================================================ -->
<div class="db-hero">
    <i class="fa-solid <?= $cfg['icon'] ?> db-hero-deco"></i>

    <div class="db-hero-left">
        <div class="db-hero-date">
            <i class="fa-regular fa-calendar"></i>
            <?= $tanggal_lengkap ?>
        </div>

        <div class="db-hero-salam">
            <?= $salam ?>,<br>
            <em><?= htmlspecialchars(explode(' ', $nama_lengkap)[0]) ?></em>!
        </div>
        <div class="db-hero-sub">
            Panel <?= ucfirst($role) ?> — Cipeng Clinic. Selamat bekerja dengan penuh semangat.
        </div>

        <div class="db-hero-actions">
            <?php if ($role === 'pasien'): ?>
                <a href="?f=pasien&m=pengajuan" class="db-btn-primary">
                    <i class="fa-solid fa-calendar-plus"></i> Buat Antrian
                </a>
                <a href="?f=pasien&m=riwayat" class="db-btn-outline">
                    <i class="fa-solid fa-notes-medical"></i> Riwayat Saya
                </a>

            <?php elseif ($role === 'dokter'): ?>
                <a href="?f=dokter&m=antrian" class="db-btn-primary">
                    <i class="fa-solid fa-stethoscope"></i> Lihat Antrian Hari Ini
                </a>

            <?php elseif ($role === 'resepsionis'): ?>
                <a href="?f=resepsionis&m=data_antrian" class="db-btn-primary">
                    <i class="fa-solid fa-clipboard-list"></i> Antrian Hari Ini
                </a>
                <a href="?f=resepsionis&m=buat_antrian" class="db-btn-outline">
                    <i class="fa-solid fa-user-plus"></i> Daftar Walk-in
                </a>

            <?php elseif ($role === 'admin'): ?>
                <a href="?f=admin&m=data_dokter" class="db-btn-primary">
                    <i class="fa-solid fa-user-doctor"></i> Kelola Dokter
                </a>
                <a href="?f=admin&m=rekap_pemeriksaan" class="db-btn-outline">
                    <i class="fa-solid fa-file-medical"></i> Rekap Pemeriksaan
                </a>

            <?php elseif ($role === 'apoteker'): ?>
                <a href="?f=apoteker&m=resep_masuk" class="db-btn-primary">
                    <i class="fa-solid fa-prescription-bottle-medical"></i> Resep Masuk
                    <?php if(!empty($stats['resep_menunggu']) && $stats['resep_menunggu'] > 0): ?>
                        <span style="background:white;color:#e07b5a;border-radius:50px;padding:0.1rem 0.5rem;font-size:0.72rem;"><?= $stats['resep_menunggu'] ?></span>
                    <?php endif; ?>
                </a>
                <a href="?f=apoteker&m=data_obat" class="db-btn-outline">
                    <i class="fa-solid fa-pills"></i> Stok Obat
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="db-hero-badge">
        <div class="db-hero-icon">
            <i class="fa-solid <?= $cfg['icon'] ?>"></i>
        </div>
        <span class="db-role-pill" style="background:<?= $cfg['accent'] ?>22;color:<?= $cfg['accent'] ?>;">
            <?= ucfirst($role) ?>
        </span>
    </div>
</div>


<!-- ============================================================
     ALERT KHUSUS (apoteker: stok menipis/habis)
============================================================ -->
<?php if ($role === 'apoteker' && (($stats['stok_menipis'] ?? 0) > 0 || ($stats['stok_habis'] ?? 0) > 0)): ?>
<div class="db-alert">
    <i class="fa-solid fa-triangle-exclamation"></i>
    <div class="db-alert-text">
        Perhatian stok obat:
        <?php if ($stats['stok_habis'] > 0): ?>
            <strong><?= $stats['stok_habis'] ?> item habis</strong><?= $stats['stok_menipis'] > 0 ? ' dan ' : '.' ?>
        <?php endif; ?>
        <?php if ($stats['stok_menipis'] > 0): ?>
            <strong><?= $stats['stok_menipis'] ?> item menipis</strong> (stok ≤ 10).
        <?php endif; ?>
        <a href="?f=apoteker&m=data_obat" style="color:#e07b5a;font-weight:600;">Cek sekarang →</a>
    </div>
</div>
<?php endif; ?>

<?php if ($role === 'admin' && ($stats['obat_menipis'] ?? 0) > 0): ?>
<div class="db-alert">
    <i class="fa-solid fa-triangle-exclamation"></i>
    <div class="db-alert-text">
        <strong><?= $stats['obat_menipis'] ?> item obat</strong> stoknya menipis (≤ 10). 
        <a href="?f=admin&m=data_obat" style="color:#e07b5a;font-weight:600;">Lihat detail →</a>
    </div>
</div>
<?php endif; ?>


<!-- ============================================================
     STAT CARDS
============================================================ -->
<?php if ($role === 'pasien'): ?>
<div class="db-stats db-stats-3">
    <div class="db-stat" style="--stat-color:#2a9ab0">
        <div class="db-stat-icon"><i class="fa-solid fa-ticket"></i></div>
        <div class="db-stat-val"><?= $stats['antrian_aktif'] ?? 0 ?></div>
        <div class="db-stat-label">Antrian Aktif</div>
        <i class="fa-solid fa-ticket db-stat-deco"></i>
    </div>
    <div class="db-stat" style="--stat-color:#198754">
        <div class="db-stat-icon"><i class="fa-solid fa-notes-medical"></i></div>
        <div class="db-stat-val"><?= $stats['riwayat'] ?? 0 ?></div>
        <div class="db-stat-label">Riwayat Berobat</div>
        <i class="fa-solid fa-notes-medical db-stat-deco"></i>
    </div>
    <div class="db-stat" style="--stat-color:#e07b5a">
        <div class="db-stat-icon"><i class="fa-solid fa-calendar-days"></i></div>
        <div class="db-stat-val"><?= $stats['total_antrian'] ?? 0 ?></div>
        <div class="db-stat-label">Total Kunjungan</div>
        <i class="fa-solid fa-calendar-days db-stat-deco"></i>
    </div>
</div>

<?php elseif ($role === 'dokter'): ?>
<div class="db-stats db-stats-4">
    <div class="db-stat" style="--stat-color:#2a9ab0">
        <div class="db-stat-icon"><i class="fa-solid fa-clipboard-list"></i></div>
        <div class="db-stat-val"><?= $stats['antrian_hari_ini'] ?? 0 ?></div>
        <div class="db-stat-label">Antrian Hari Ini</div>
        <i class="fa-solid fa-clipboard-list db-stat-deco"></i>
    </div>
    <div class="db-stat" style="--stat-color:#e07b5a">
        <div class="db-stat-icon"><i class="fa-solid fa-user-clock"></i></div>
        <div class="db-stat-val"><?= $stats['belum_diperiksa'] ?? 0 ?></div>
        <div class="db-stat-label">Belum Diperiksa</div>
        <i class="fa-solid fa-user-clock db-stat-deco"></i>
    </div>
    <div class="db-stat" style="--stat-color:#198754">
        <div class="db-stat-icon"><i class="fa-solid fa-bed-pulse"></i></div>
        <div class="db-stat-val"><?= $stats['total_diperiksa'] ?? 0 ?></div>
        <div class="db-stat-label">Total Ditangani</div>
        <i class="fa-solid fa-bed-pulse db-stat-deco"></i>
    </div>
    <div class="db-stat" style="--stat-color:#f59e0b">
        <div class="db-stat-icon"><i class="fa-solid fa-prescription-bottle"></i></div>
        <div class="db-stat-val"><?= $stats['resep_menunggu'] ?? 0 ?></div>
        <div class="db-stat-label">Resep Menunggu</div>
        <i class="fa-solid fa-prescription-bottle db-stat-deco"></i>
    </div>
</div>

<?php elseif ($role === 'resepsionis'): ?>
<div class="db-stats db-stats-4">
    <div class="db-stat" style="--stat-color:#198754">
        <div class="db-stat-icon"><i class="fa-solid fa-circle-check"></i></div>
        <div class="db-stat-val"><?= $stats['hadir'] ?? 0 ?></div>
        <div class="db-stat-label">Sudah Hadir</div>
        <i class="fa-solid fa-circle-check db-stat-deco"></i>
    </div>
    <div class="db-stat" style="--stat-color:#f59e0b">
        <div class="db-stat-icon"><i class="fa-solid fa-hourglass-half"></i></div>
        <div class="db-stat-val"><?= $stats['menunggu'] ?? 0 ?></div>
        <div class="db-stat-label">Belum Datang</div>
        <i class="fa-solid fa-hourglass-half db-stat-deco"></i>
    </div>
    <div class="db-stat" style="--stat-color:#dc3545">
        <div class="db-stat-icon"><i class="fa-solid fa-ban"></i></div>
        <div class="db-stat-val"><?= $stats['batal'] ?? 0 ?></div>
        <div class="db-stat-label">Dibatalkan</div>
        <i class="fa-solid fa-ban db-stat-deco"></i>
    </div>
    <div class="db-stat" style="--stat-color:#2a9ab0">
        <div class="db-stat-icon"><i class="fa-solid fa-user-doctor"></i></div>
        <div class="db-stat-val"><?= $stats['total_dokter'] ?? 0 ?></div>
        <div class="db-stat-label">Total Dokter</div>
        <i class="fa-solid fa-user-doctor db-stat-deco"></i>
    </div>
</div>

<?php elseif ($role === 'admin'): ?>
<div class="db-stats db-stats-3" style="margin-bottom:1rem;">
    <div class="db-stat" style="--stat-color:#2a9ab0">
        <div class="db-stat-icon"><i class="fa-solid fa-users"></i></div>
        <div class="db-stat-val"><?= $stats['total_pasien'] ?? 0 ?></div>
        <div class="db-stat-label">Total Pasien</div>
        <i class="fa-solid fa-users db-stat-deco"></i>
    </div>
    <div class="db-stat" style="--stat-color:#1a6b7a">
        <div class="db-stat-icon"><i class="fa-solid fa-user-doctor"></i></div>
        <div class="db-stat-val"><?= $stats['total_dokter'] ?? 0 ?></div>
        <div class="db-stat-label">Total Dokter</div>
        <i class="fa-solid fa-user-doctor db-stat-deco"></i>
    </div>
    <div class="db-stat" style="--stat-color:#198754">
        <div class="db-stat-icon"><i class="fa-solid fa-pills"></i></div>
        <div class="db-stat-val"><?= $stats['total_obat'] ?? 0 ?></div>
        <div class="db-stat-label">Jenis Obat Tersedia</div>
        <i class="fa-solid fa-pills db-stat-deco"></i>
    </div>
</div>
<div class="db-stats db-stats-2" style="margin-bottom:1.75rem;">
    <div class="db-stat" style="--stat-color:#e07b5a">
        <div class="db-stat-icon"><i class="fa-solid fa-calendar-day"></i></div>
        <div class="db-stat-val"><?= $stats['antrian_hari_ini'] ?? 0 ?></div>
        <div class="db-stat-label">Antrian Hari Ini</div>
        <i class="fa-solid fa-calendar-day db-stat-deco"></i>
    </div>
    <div class="db-stat" style="--stat-color:#6f42c1">
        <div class="db-stat-icon"><i class="fa-solid fa-file-medical"></i></div>
        <div class="db-stat-val"><?= $stats['periksa_bulan_ini'] ?? 0 ?></div>
        <div class="db-stat-label">Pemeriksaan Bulan Ini</div>
        <i class="fa-solid fa-file-medical db-stat-deco"></i>
    </div>
</div>

<?php elseif ($role === 'apoteker'): ?>
<div class="db-stats db-stats-4">
    <div class="db-stat" style="--stat-color:#e07b5a">
        <div class="db-stat-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
        <div class="db-stat-val"><?= $stats['resep_menunggu'] ?? 0 ?></div>
        <div class="db-stat-label">Resep Menunggu</div>
        <i class="fa-solid fa-clock-rotate-left db-stat-deco"></i>
    </div>
    <div class="db-stat" style="--stat-color:#198754">
        <div class="db-stat-icon"><i class="fa-solid fa-circle-check"></i></div>
        <div class="db-stat-val"><?= $stats['selesai_hari_ini'] ?? 0 ?></div>
        <div class="db-stat-label">Selesai Hari Ini</div>
        <i class="fa-solid fa-circle-check db-stat-deco"></i>
    </div>
    <div class="db-stat" style="--stat-color:#2a9ab0">
        <div class="db-stat-icon"><i class="fa-solid fa-box-open"></i></div>
        <div class="db-stat-val"><?= $stats['stok_aman'] ?? 0 ?></div>
        <div class="db-stat-label">Stok Aman</div>
        <i class="fa-solid fa-box-open db-stat-deco"></i>
    </div>
    <div class="db-stat" style="--stat-color:#dc3545">
        <div class="db-stat-icon"><i class="fa-solid fa-box-xmark"></i></div>
        <div class="db-stat-val"><?= $stats['stok_habis'] ?? 0 ?></div>
        <div class="db-stat-label">Stok Habis</div>
        <i class="fa-solid fa-box-xmark db-stat-deco"></i>
    </div>
</div>
<?php endif; ?>


<!-- ============================================================
     BOTTOM PANELS
============================================================ -->
<div class="db-bottom">

    <!-- PANEL KIRI: Data aktif / terkini -->
    <div class="db-panel">
        <div class="db-panel-head">
            <div class="db-panel-title">
                <?php if ($role === 'pasien'): ?>
                    <i class="fa-solid fa-calendar-check"></i> Antrian Mendatang
                <?php elseif ($role === 'dokter'): ?>
                    <i class="fa-solid fa-user-clock"></i> Menunggu Diperiksa
                <?php elseif ($role === 'resepsionis'): ?>
                    <i class="fa-solid fa-hourglass-half"></i> Belum Divalidasi
                <?php elseif ($role === 'admin'): ?>
                    <i class="fa-solid fa-users-gear"></i> Akun Staf Terbaru
                <?php elseif ($role === 'apoteker'): ?>
                    <i class="fa-solid fa-prescription-bottle-medical"></i> Resep Menunggu
                <?php endif; ?>
            </div>
            <?php if ($role === 'pasien'): ?>
                <a href="?f=pasien&m=riwayat" class="db-panel-link">Lihat semua →</a>
            <?php elseif ($role === 'dokter'): ?>
                <a href="?f=dokter&m=antrian" class="db-panel-link">Lihat semua →</a>
            <?php elseif ($role === 'resepsionis'): ?>
                <a href="?f=resepsionis&m=data_antrian" class="db-panel-link">Lihat semua →</a>
            <?php elseif ($role === 'admin'): ?>
                <a href="?f=admin&m=manajemen_akun" class="db-panel-link">Kelola →</a>
            <?php elseif ($role === 'apoteker'): ?>
                <a href="?f=apoteker&m=resep_masuk" class="db-panel-link">Proses →</a>
            <?php endif; ?>
        </div>
        <div class="db-panel-body">
            <?php if (empty($recents)): ?>
                <div class="db-empty">
                    <i class="fa-solid fa-inbox"></i>
                    Tidak ada data untuk ditampilkan.
                </div>
            <?php else: ?>
                <?php foreach ($recents as $r): ?>
                <div class="db-row-item">
                    <div class="db-row-avatar">
                        <?php
                        if ($role === 'pasien')
                            echo strtoupper(substr($r['nama_dokter'], 0, 1));
                        elseif ($role === 'admin')
                            echo strtoupper(substr($r['username'], 0, 1));
                        else
                            echo strtoupper(substr($r['nama_pasien'], 0, 1));
                        ?>
                    </div>
                    <div class="db-row-main">
                        <?php if ($role === 'pasien'): ?>
                            <div class="db-row-name">dr. <?= htmlspecialchars($r['nama_dokter']) ?></div>
                            <div class="db-row-sub"><?= htmlspecialchars($r['spesialis'] ?? '') ?></div>
                        <?php elseif ($role === 'dokter'): ?>
                            <div class="db-row-name"><?= htmlspecialchars($r['nama_pasien']) ?></div>
                            <div class="db-row-sub"><?= $r['usia'] ?> tahun · #<?= $r['id_antrian'] ?></div>
                        <?php elseif ($role === 'resepsionis'): ?>
                            <div class="db-row-name"><?= htmlspecialchars($r['nama_pasien']) ?></div>
                            <div class="db-row-sub">→ dr. <?= htmlspecialchars($r['nama_dokter']) ?></div>
                        <?php elseif ($role === 'admin'): ?>
                            <div class="db-row-name"><?= htmlspecialchars($r['username']) ?></div>
                            <div class="db-row-sub"><?= ucfirst($r['role']) ?></div>
                        <?php elseif ($role === 'apoteker'): ?>
                            <div class="db-row-name"><?= htmlspecialchars($r['nama_pasien']) ?></div>
                            <div class="db-row-sub">dr. <?= htmlspecialchars($r['nama_dokter']) ?> · <?= $r['jumlah_item'] ?> item</div>
                        <?php endif; ?>
                    </div>
                    <div class="db-row-meta">
                        <?php if ($role === 'pasien'): ?>
                            <?= date('d M', strtotime($r['tanggal_kunjungan'])) ?>
                        <?php elseif ($role === 'dokter'): ?>
                            <span style="background:#fff3e0;color:#e07b5a;padding:0.15rem 0.5rem;border-radius:6px;font-size:0.72rem;">Menunggu</span>
                        <?php elseif ($role === 'resepsionis'): ?>
                            <span style="background:#fff8e1;color:#f59e0b;padding:0.15rem 0.5rem;border-radius:6px;font-size:0.72rem;">Belum Datang</span>
                        <?php elseif ($role === 'admin'): ?>
                            <span style="background:#e8f5e9;color:#198754;padding:0.15rem 0.5rem;border-radius:6px;font-size:0.72rem;">Aktif</span>
                        <?php elseif ($role === 'apoteker'): ?>
                            <?= date('d M', strtotime($r['tanggal_periksa'])) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- PANEL KANAN: Aksi cepat & info -->
    <div class="db-panel">
        <div class="db-panel-head">
            <div class="db-panel-title">
                <i class="fa-solid fa-bolt"></i> Aksi Cepat
            </div>
        </div>
        <div class="db-panel-body" style="padding:0.75rem 1rem;">
            <?php
            $actions = [];
            if ($role === 'pasien') {
                $actions = [
                    ['icon'=>'fa-calendar-plus',   'color'=>'#2a9ab0', 'label'=>'Buat Antrian Baru',    'url'=>'?f=pasien&m=pengajuan'],
                    ['icon'=>'fa-notes-medical',    'color'=>'#198754', 'label'=>'Riwayat Berobat',      'url'=>'?f=pasien&m=riwayat'],
                    ['icon'=>'fa-user-pen',         'color'=>'#e07b5a', 'label'=>'Edit Profil Saya',     'url'=>'?f=profil&m=edit'],
                ];
            } elseif ($role === 'dokter') {
                $actions = [
                    ['icon'=>'fa-stethoscope',       'color'=>'#2a9ab0', 'label'=>'Antrian Hari Ini',     'url'=>'?f=dokter&m=antrian'],
                    ['icon'=>'fa-user-pen',          'color'=>'#e07b5a', 'label'=>'Edit Profil Dokter',   'url'=>'?f=profil&m=edit'],
                ];
            } elseif ($role === 'resepsionis') {
                $actions = [
                    ['icon'=>'fa-user-plus',         'color'=>'#2a9ab0', 'label'=>'Daftarkan Pasien Walk-in', 'url'=>'?f=resepsionis&m=buat_antrian'],
                    ['icon'=>'fa-clipboard-list',    'color'=>'#198754', 'label'=>'Data Antrian Hari Ini',    'url'=>'?f=resepsionis&m=data_antrian'],
                    ['icon'=>'fa-user-doctor',       'color'=>'#f59e0b', 'label'=>'Jadwal Dokter',            'url'=>'?f=resepsionis&m=data_dokter'],
                ];
            } elseif ($role === 'admin') {
                $actions = [
                    ['icon'=>'fa-user-doctor',       'color'=>'#2a9ab0', 'label'=>'Data Dokter',           'url'=>'?f=admin&m=data_dokter'],
                    ['icon'=>'fa-users',             'color'=>'#1a6b7a', 'label'=>'Data Pasien',           'url'=>'?f=admin&m=data_pasien'],
                    ['icon'=>'fa-pills',             'color'=>'#198754', 'label'=>'Data Obat',             'url'=>'?f=admin&m=data_obat'],
                    ['icon'=>'fa-file-medical',      'color'=>'#e07b5a', 'label'=>'Rekap Pemeriksaan',     'url'=>'?f=admin&m=rekap_pemeriksaan'],
                    ['icon'=>'fa-users-gear',        'color'=>'#6f42c1', 'label'=>'Manajemen Akun Staf',   'url'=>'?f=admin&m=manajemen_akun'],
                ];
            } elseif ($role === 'apoteker') {
                $actions = [
                    ['icon'=>'fa-prescription-bottle-medical', 'color'=>'#e07b5a', 'label'=>'Resep Masuk',          'url'=>'?f=apoteker&m=resep_masuk'],
                    ['icon'=>'fa-pills',                       'color'=>'#198754', 'label'=>'Stok Obat',            'url'=>'?f=apoteker&m=data_obat'],
                    ['icon'=>'fa-clock-rotate-left',           'color'=>'#2a9ab0', 'label'=>'Riwayat Pengeluaran',  'url'=>'?f=apoteker&m=riwayat_obat'],
                ];
            }
            foreach ($actions as $act): ?>
            <a href="<?= $act['url'] ?>" style="
                display:flex; align-items:center; gap:0.85rem;
                padding:0.7rem 0.75rem;
                border-radius:12px;
                text-decoration:none;
                margin-bottom:0.4rem;
                border:1.5px solid #f0ece7;
                background:white;
                transition:all 0.2s;
            " onmouseover="this.style.borderColor='<?= $act['color'] ?>';this.style.background='<?= $act['color'] ?>0d'"
               onmouseout="this.style.borderColor='#f0ece7';this.style.background='white'">
                <div style="width:36px;height:36px;border-radius:10px;
                            background:<?= $act['color'] ?>18;
                            color:<?= $act['color'] ?>;
                            display:flex;align-items:center;justify-content:center;
                            flex-shrink:0;font-size:0.95rem;">
                    <i class="fa-solid <?= $act['icon'] ?>"></i>
                </div>
                <span style="font-size:0.875rem;font-weight:500;color:#1a2326;">
                    <?= $act['label'] ?>
                </span>
                <i class="fa-solid fa-chevron-right" style="color:#ccc;font-size:0.65rem;margin-left:auto;"></i>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

</div><!-- end db-bottom -->

<!-- Info sistem -->
<div class="db-info">
    <i class="fa-solid fa-circle-info"></i>
    <p>Sistem Cipeng Clinic berjalan normal. Selalu lakukan <strong>logout</strong> setelah selesai menggunakan sistem untuk menjaga keamanan data medis pasien.</p>
</div>

</div><!-- end db-root -->