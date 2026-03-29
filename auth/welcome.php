<?php
require_once '../config/koneksi.php';

// --- DATA REALTIME UNTUK HERO ---
// Total pasien terdaftar
$total_pasien = $pdo->query("SELECT COUNT(*) FROM pasien")->fetchColumn();

// Dokter yang praktik hari ini
$hari_indo = ['Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu',
              'Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu','Sunday'=>'Minggu'];
$hari_ini  = $hari_indo[date('l')];
$stmt = $pdo->prepare("SELECT COUNT(*) FROM dokter WHERE FIND_IN_SET(:hari, REPLACE(hari_praktek, ' ', '')) > 0");
$stmt->execute([':hari' => $hari_ini]);
$dokter_aktif = $stmt->fetchColumn();

// Total item obat tersedia (stock > 0)
$total_obat = $pdo->query("SELECT COUNT(*) FROM obat WHERE stock > 0")->fetchColumn();

// Total antrian hari ini
$antrian_hari_ini = $pdo->query("SELECT COUNT(*) FROM antrian WHERE tanggal_kunjungan = CURDATE()")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cipeng Clinic — Kesehatan Digital Terpercaya</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --teal-deep:   #0b3d47;
            --teal-mid:    #1a6b7a;
            --teal-light:  #2a9ab0;
            --cream:       #f7f3ee;
            --cream-dark:  #ede8e1;
            --coral:       #e07b5a;
            --coral-light: #f09070;
            --text-dark:   #1a2326;
            --text-mid:    #4a6568;
            --text-light:  #8aa5a9;
            --white:       #ffffff;
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'DM Sans', sans-serif;
            background-color: var(--cream);
            color: var(--text-dark);
            overflow-x: hidden;
        }

        /* ============ NAVBAR ============ */
        nav {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 1000;
            padding: 1.25rem 3rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.4s ease;
        }
        nav.scrolled {
            background: rgba(247, 243, 238, 0.95);
            backdrop-filter: blur(12px);
            padding: 0.85rem 3rem;
            box-shadow: 0 2px 24px rgba(11, 61, 71, 0.08);
        }
        .nav-brand {
            font-family: 'Playfair Display', serif;
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--teal-deep);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .nav-brand .brand-dot {
            width: 8px; height: 8px;
            background: var(--coral);
            border-radius: 50%;
            display: inline-block;
        }
        .nav-actions { display: flex; gap: 0.75rem; align-items: center; }
        .btn-nav-outline {
            padding: 0.5rem 1.4rem;
            border: 1.5px solid var(--teal-mid);
            color: var(--teal-mid);
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-nav-outline:hover {
            background: var(--teal-mid);
            color: var(--white);
        }
        .btn-nav-fill {
            padding: 0.5rem 1.4rem;
            background: var(--coral);
            color: var(--white);
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.25s;
            box-shadow: 0 4px 14px rgba(224, 123, 90, 0.35);
        }
        .btn-nav-fill:hover {
            background: var(--coral-light);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(224, 123, 90, 0.45);
        }

        /* ============ HERO ============ */
        #hero {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1fr 1fr;
            position: relative;
            overflow: hidden;
        }

        /* Kiri: konten */
        .hero-left {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 8rem 4rem 4rem 6rem;
            position: relative;
            z-index: 2;
        }
        .hero-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(42, 154, 176, 0.1);
            color: var(--teal-mid);
            border-radius: 50px;
            padding: 0.4rem 1rem;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 1.5rem;
            width: fit-content;
            animation: fadeUp 0.8s ease forwards;
            opacity: 0;
        }
        .hero-tag .pulse-dot {
            width: 7px; height: 7px;
            background: var(--teal-light);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.4); }
        }

        .hero-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(2.8rem, 5vw, 4.2rem);
            font-weight: 700;
            line-height: 1.12;
            color: var(--teal-deep);
            margin-bottom: 1.5rem;
            animation: fadeUp 0.8s 0.15s ease forwards;
            opacity: 0;
        }
        .hero-title em {
            font-style: italic;
            color: var(--coral);
        }

        .hero-desc {
            font-size: 1.05rem;
            color: var(--text-mid);
            line-height: 1.75;
            max-width: 440px;
            margin-bottom: 2.5rem;
            font-weight: 300;
            animation: fadeUp 0.8s 0.3s ease forwards;
            opacity: 0;
        }

        .hero-cta {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            animation: fadeUp 0.8s 0.45s ease forwards;
            opacity: 0;
        }
        .btn-hero-primary {
            padding: 0.85rem 2rem;
            background: var(--teal-deep);
            color: var(--white);
            border-radius: 50px;
            font-size: 0.95rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            box-shadow: 0 6px 24px rgba(11, 61, 71, 0.25);
            transition: all 0.25s;
        }
        .btn-hero-primary:hover {
            background: var(--teal-mid);
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(11, 61, 71, 0.3);
        }
        .btn-hero-secondary {
            padding: 0.85rem 2rem;
            border: 1.5px solid var(--teal-deep);
            color: var(--teal-deep);
            border-radius: 50px;
            font-size: 0.95rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            transition: all 0.25s;
        }
        .btn-hero-secondary:hover {
            background: var(--teal-deep);
            color: var(--white);
            transform: translateY(-2px);
        }

        /* Stats bawah hero */
        .hero-stats {
            display: flex;
            gap: 2.5rem;
            margin-top: 3.5rem;
            padding-top: 2rem;
            border-top: 1px solid var(--cream-dark);
            animation: fadeUp 0.8s 0.6s ease forwards;
            opacity: 0;
        }
        .stat-item .stat-num {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--teal-deep);
            line-height: 1;
        }
        .stat-item .stat-label {
            font-size: 0.78rem;
            color: var(--text-light);
            font-weight: 500;
            margin-top: 0.2rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Kanan: visual */
        .hero-right {
            position: relative;
            background: var(--teal-deep);
            overflow: hidden;
        }
        .hero-right::before {
            content: '';
            position: absolute;
            inset: 0;
            background: 
                radial-gradient(ellipse 60% 50% at 80% 20%, rgba(42,154,176,0.35) 0%, transparent 60%),
                radial-gradient(ellipse 40% 60% at 20% 80%, rgba(224,123,90,0.2) 0%, transparent 50%);
        }

        /* Grid visual cards */
        .hero-visual {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 5rem 3rem;
        }
        .visual-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            width: 100%;
            max-width: 380px;
        }
        .vcard {
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 16px;
            padding: 1.4rem;
            backdrop-filter: blur(8px);
            color: white;
            transition: transform 0.3s ease;
        }
        .vcard:hover { transform: translateY(-4px); }
        .vcard.tall { grid-row: span 2; }
        .vcard.accent {
            background: var(--coral);
            border-color: transparent;
        }
        .vcard-icon {
            width: 36px; height: 36px;
            border-radius: 10px;
            background: rgba(255,255,255,0.15);
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem;
            margin-bottom: 0.75rem;
        }
        .vcard.accent .vcard-icon { background: rgba(255,255,255,0.25); }
        .vcard-title {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            opacity: 0.7;
            margin-bottom: 0.3rem;
        }
        .vcard-value {
            font-family: 'Playfair Display', serif;
            font-size: 1.6rem;
            font-weight: 700;
        }
        .vcard-sub {
            font-size: 0.75rem;
            opacity: 0.65;
            margin-top: 0.2rem;
        }

        /* Heartbeat SVG line */
        .heartbeat-wrap {
            padding: 1rem 0;
        }
        .heartbeat-wrap svg {
            width: 100%;
            overflow: visible;
        }
        .heartbeat-path {
            fill: none;
            stroke: rgba(255,255,255,0.5);
            stroke-width: 2;
            stroke-dasharray: 300;
            stroke-dashoffset: 300;
            animation: draw 2s ease forwards 0.5s, heartLoop 3s ease-in-out 2.5s infinite;
        }
        @keyframes draw {
            to { stroke-dashoffset: 0; }
        }
        @keyframes heartLoop {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        /* Dekorasi besar di kanan */
        .deco-circle {
            position: absolute;
            border-radius: 50%;
            border: 1px solid rgba(255,255,255,0.06);
        }
        .deco-circle-1 { width: 500px; height: 500px; top: -150px; right: -150px; }
        .deco-circle-2 { width: 300px; height: 300px; bottom: -80px; left: -60px; }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ============ SECTION: FITUR ============ */
        #fitur {
            padding: 6rem 6rem;
            background: var(--white);
        }
        .section-label {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--coral);
            margin-bottom: 0.75rem;
        }
        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(1.8rem, 3vw, 2.6rem);
            font-weight: 700;
            color: var(--teal-deep);
            line-height: 1.2;
            max-width: 480px;
            margin-bottom: 1rem;
        }
        .section-desc {
            font-size: 1rem;
            color: var(--text-mid);
            max-width: 520px;
            line-height: 1.7;
            font-weight: 300;
            margin-bottom: 3.5rem;
        }

        .fitur-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
        }
        .fitur-card {
            padding: 2rem;
            border-radius: 20px;
            border: 1px solid var(--cream-dark);
            background: var(--cream);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .fitur-card::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 3px;
            background: var(--teal-light);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        .fitur-card:hover {
            border-color: var(--teal-light);
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(26, 107, 122, 0.1);
        }
        .fitur-card:hover::after { transform: scaleX(1); }
        .fitur-icon {
            width: 52px; height: 52px;
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem;
            margin-bottom: 1.25rem;
        }
        .fitur-card h3 {
            font-family: 'Playfair Display', serif;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--teal-deep);
            margin-bottom: 0.6rem;
        }
        .fitur-card p {
            font-size: 0.875rem;
            color: var(--text-mid);
            line-height: 1.65;
            font-weight: 300;
        }

        /* ============ SECTION: CARA KERJA ============ */
        #cara-kerja {
            padding: 6rem;
            background: var(--cream);
        }
        .steps-wrap {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0;
            position: relative;
            margin-top: 3rem;
        }
        .steps-wrap::before {
            content: '';
            position: absolute;
            top: 28px;
            left: 12.5%;
            right: 12.5%;
            height: 1px;
            background: linear-gradient(to right, transparent, var(--teal-light), transparent);
        }
        .step-item {
            text-align: center;
            padding: 0 1.5rem;
            position: relative;
        }
        .step-num {
            width: 56px; height: 56px;
            border-radius: 50%;
            background: var(--teal-deep);
            color: white;
            font-family: 'Playfair Display', serif;
            font-size: 1.3rem;
            font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.25rem;
            position: relative;
            z-index: 1;
            box-shadow: 0 4px 16px rgba(11, 61, 71, 0.25);
        }
        .step-item:nth-child(2) .step-num { background: var(--teal-mid); }
        .step-item:nth-child(3) .step-num { background: var(--teal-light); }
        .step-item:nth-child(4) .step-num { background: var(--coral); }
        .step-item h4 {
            font-family: 'Playfair Display', serif;
            font-size: 1rem;
            font-weight: 600;
            color: var(--teal-deep);
            margin-bottom: 0.5rem;
        }
        .step-item p {
            font-size: 0.84rem;
            color: var(--text-mid);
            line-height: 1.6;
            font-weight: 300;
        }

        /* ============ SECTION: PERAN ============ */
        #peran {
            padding: 6rem;
            background: var(--teal-deep);
            position: relative;
            overflow: hidden;
        }
        #peran::before {
            content: '';
            position: absolute;
            inset: 0;
            background: 
                radial-gradient(ellipse 50% 80% at 90% 50%, rgba(42,154,176,0.2) 0%, transparent 60%),
                radial-gradient(ellipse 30% 50% at 10% 80%, rgba(224,123,90,0.15) 0%, transparent 50%);
        }
        #peran .section-label { color: var(--coral-light); }
        #peran .section-title { color: var(--white); }
        #peran .section-desc { color: rgba(255,255,255,0.6); }

        .peran-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1rem;
            position: relative;
            z-index: 1;
        }
        .peran-card {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px;
            padding: 1.75rem 1.25rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: default;
        }
        .peran-card:hover {
            background: rgba(255,255,255,0.12);
            border-color: rgba(255,255,255,0.25);
            transform: translateY(-6px);
        }
        .peran-icon {
            width: 56px; height: 56px;
            border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem;
            margin: 0 auto 1rem;
        }
        .peran-card h4 {
            font-family: 'Playfair Display', serif;
            font-size: 1rem;
            font-weight: 600;
            color: white;
            margin-bottom: 0.4rem;
        }
        .peran-card p {
            font-size: 0.78rem;
            color: rgba(255,255,255,0.5);
            line-height: 1.5;
        }

        /* ============ CTA BOTTOM ============ */
        #cta {
            padding: 6rem;
            background: var(--white);
            text-align: center;
        }
        .cta-inner {
            max-width: 560px;
            margin: 0 auto;
        }
        .cta-inner .section-title { margin: 0 auto 1rem; }
        .cta-inner .section-desc { margin: 0 auto 2.5rem; }
        .cta-btns {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn-cta-primary {
            padding: 1rem 2.5rem;
            background: var(--teal-deep);
            color: white;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            box-shadow: 0 6px 24px rgba(11,61,71,0.25);
            transition: all 0.25s;
        }
        .btn-cta-primary:hover {
            background: var(--teal-mid);
            transform: translateY(-2px);
        }
        .btn-cta-secondary {
            padding: 1rem 2.5rem;
            border: 1.5px solid var(--cream-dark);
            color: var(--text-mid);
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.25s;
        }
        .btn-cta-secondary:hover {
            border-color: var(--teal-mid);
            color: var(--teal-mid);
        }

        /* ============ FOOTER ============ */
        footer {
            background: var(--teal-deep);
            color: rgba(255,255,255,0.5);
            text-align: center;
            padding: 1.75rem;
            font-size: 0.82rem;
        }
        footer span { color: var(--coral-light); }

        /* ============ SCROLL REVEAL ============ */
        .reveal {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.7s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .reveal.visible {
            opacity: 1;
            transform: translateY(0);
        }
        .reveal-delay-1 { transition-delay: 0.1s; }
        .reveal-delay-2 { transition-delay: 0.2s; }
        .reveal-delay-3 { transition-delay: 0.3s; }
        .reveal-delay-4 { transition-delay: 0.4s; }

        /* ============ RESPONSIVE ============ */
        @media (max-width: 1024px) {
            #hero { grid-template-columns: 1fr; }
            .hero-left { padding: 7rem 2.5rem 3rem; }
            .hero-right { min-height: 320px; }
            #fitur, #cara-kerja, #peran, #cta { padding: 4rem 2.5rem; }
            .fitur-grid { grid-template-columns: 1fr 1fr; }
            .steps-wrap { grid-template-columns: 1fr 1fr; gap: 2rem; }
            .steps-wrap::before { display: none; }
            .peran-grid { grid-template-columns: repeat(3, 1fr); }
            nav { padding: 1rem 2rem; }
            nav.scrolled { padding: 0.75rem 2rem; }
        }
        @media (max-width: 640px) {
            .hero-left { padding: 6rem 1.5rem 2.5rem; }
            .hero-stats { gap: 1.5rem; }
            .fitur-grid { grid-template-columns: 1fr; }
            .peran-grid { grid-template-columns: 1fr 1fr; }
            .nav-actions .btn-nav-outline { display: none; }
            #fitur, #cara-kerja, #peran, #cta { padding: 3.5rem 1.5rem; }
        }
    </style>
</head>
<body>

<!-- ============ NAVBAR ============ -->
<nav id="navbar">
    <a href="#" class="nav-brand">
        <i class="fa-solid fa-house-medical" style="color:var(--teal-light);font-size:1.2rem;"></i>
        Cipeng Clinic
        <span class="brand-dot"></span>
    </a>
    <div class="nav-actions">
        <a href="#fitur" class="btn-nav-outline">Fitur</a>
        <a href="login.php" class="btn-nav-outline">Masuk</a>
        <a href="register.php" class="btn-nav-fill">Daftar Gratis</a>
    </div>
</nav>

<!-- ============ HERO ============ -->
<section id="hero">
    <!-- KIRI -->
    <div class="hero-left">
        <div class="hero-tag">
            <span class="pulse-dot"></span>
            Sistem Klinik Digital
        </div>

        <h1 class="hero-title">
            Kesehatan Anda,<br>
            <em>Prioritas</em> Kami
        </h1>

        <p class="hero-desc">
            Cipeng Clinic menghadirkan layanan antrean digital, rekam medis terstruktur, 
            dan pengelolaan farmasi yang terintegrasi — semuanya dalam satu platform.
        </p>

        <div class="hero-cta">
            <a href="register.php" class="btn-hero-primary">
                <i class="fa-solid fa-calendar-plus"></i>
                Buat Antrian Sekarang
            </a>
            <a href="login.php" class="btn-hero-secondary">
                <i class="fa-solid fa-right-to-bracket"></i>
                Masuk ke Sistem
            </a>
        </div>

        <div class="hero-stats">
            <div class="stat-item">
                <div class="stat-num"><?= $total_pasien ?></div>
                <div class="stat-label">Pasien Terdaftar</div>
            </div>
            <div class="stat-item">
                <div class="stat-num">24/7</div>
                <div class="stat-label">Akses Digital</div>
            </div>
            <div class="stat-item">
                <div class="stat-num">100%</div>
                <div class="stat-label">Terdigitalisasi</div>
            </div>
        </div>
    </div>

    <!-- KANAN -->
    <div class="hero-right">
        <div class="deco-circle deco-circle-1"></div>
        <div class="deco-circle deco-circle-2"></div>

        <div class="hero-visual">
            <div class="visual-grid">
                <!-- Kartu antrian hari ini -->
                <div class="vcard tall">
                    <div class="vcard-icon"><i class="fa-solid fa-list-ol"></i></div>
                    <div class="vcard-title">Antrian Hari Ini</div>
                    <div class="vcard-value"><?= $antrian_hari_ini ?></div>
                    <div class="vcard-sub">antrian hari ini</div>
                    <div class="heartbeat-wrap" style="margin-top:1.5rem;">
                        <svg viewBox="0 0 120 40" height="40">
                            <path class="heartbeat-path"
                                  d="M0,20 L20,20 L28,5 L35,35 L42,10 L48,28 L55,20 L75,20 L83,5 L90,35 L97,10 L103,28 L110,20 L120,20"/>
                        </svg>
                    </div>
                </div>

                <!-- Kartu dokter aktif -->
                <div class="vcard accent">
                    <div class="vcard-icon"><i class="fa-solid fa-user-doctor"></i></div>
                    <div class="vcard-title">Dokter Aktif</div>
                    <div class="vcard-value"><?= $dokter_aktif ?></div>
                    <div class="vcard-sub">praktik hari ini</div>
                </div>

                <!-- Kartu stok obat -->
                <div class="vcard">
                    <div class="vcard-icon"><i class="fa-solid fa-pills"></i></div>
                    <div class="vcard-title">Stok Obat</div>
                    <div class="vcard-value"><?= $total_obat ?></div>
                    <div class="vcard-sub">jenis obat tersedia</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============ FITUR ============ -->
<section id="fitur">
    <div class="reveal">
        <div class="section-label">Apa yang kami tawarkan</div>
        <h2 class="section-title">Semua yang dibutuhkan klinik modern</h2>
        <p class="section-desc">
            Dari pendaftaran pasien hingga pengeluaran obat, seluruh alur pelayanan 
            klinik dikelola dalam satu sistem yang terintegrasi.
        </p>
    </div>

    <div class="fitur-grid">
        <div class="fitur-card reveal reveal-delay-1">
            <div class="fitur-icon" style="background:rgba(42,154,176,0.12);color:var(--teal-mid);">
                <i class="fa-solid fa-calendar-check"></i>
            </div>
            <h3>Antrian Digital</h3>
            <p>Pasien bisa mendaftar secara mandiri dari rumah. Tidak perlu datang lebih awal hanya untuk mengambil nomor antrian.</p>
        </div>
        <div class="fitur-card reveal reveal-delay-2">
            <div class="fitur-icon" style="background:rgba(224,123,90,0.12);color:var(--coral);">
                <i class="fa-solid fa-notes-medical"></i>
            </div>
            <h3>Rekam Medis Terstruktur</h3>
            <p>Dokter mencatat diagnosis dan resep langsung di sistem. Riwayat medis pasien tersimpan aman dan mudah diakses kapanpun.</p>
        </div>
        <div class="fitur-card reveal reveal-delay-3">
            <div class="fitur-icon" style="background:rgba(11,61,71,0.1);color:var(--teal-deep);">
                <i class="fa-solid fa-mortar-pestle"></i>
            </div>
            <h3>Manajemen Farmasi</h3>
            <p>Apoteker menerima resep digital langsung dari dokter. Stok obat diperbarui otomatis saat pengeluaran dikonfirmasi.</p>
        </div>
        <div class="fitur-card reveal reveal-delay-1">
            <div class="fitur-icon" style="background:rgba(42,154,176,0.12);color:var(--teal-mid);">
                <i class="fa-solid fa-clipboard-check"></i>
            </div>
            <h3>Validasi Kedatangan</h3>
            <p>Resepsionis memverifikasi kedatangan pasien dengan mudah. Status antrian diperbarui secara real-time untuk semua pihak.</p>
        </div>
        <div class="fitur-card reveal reveal-delay-2">
            <div class="fitur-icon" style="background:rgba(224,123,90,0.12);color:var(--coral);">
                <i class="fa-solid fa-file-medical"></i>
            </div>
            <h3>Rekap & Laporan</h3>
            <p>Admin memiliki akses ke laporan pemeriksaan lengkap. Data bisa dicetak atau diekspor ke Excel sesuai kebutuhan.</p>
        </div>
        <div class="fitur-card reveal reveal-delay-3">
            <div class="fitur-icon" style="background:rgba(11,61,71,0.1);color:var(--teal-deep);">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <h3>Keamanan Berlapis</h3>
            <p>Setiap peran hanya dapat mengakses menu yang relevan. Data sensitif pasien terlindungi dari akses tidak sah.</p>
        </div>
    </div>
</section>

<!-- ============ CARA KERJA ============ -->
<section id="cara-kerja">
    <div class="reveal" style="text-align:center;">
        <div class="section-label">Alur Pelayanan</div>
        <h2 class="section-title" style="margin:0 auto 1rem;">Dari daftar hingga obat di tangan</h2>
        <p class="section-desc" style="margin:0 auto 0;">Empat langkah sederhana yang menghubungkan pasien, dokter, resepsionis, dan apoteker.</p>
    </div>

    <div class="steps-wrap">
        <div class="step-item reveal reveal-delay-1">
            <div class="step-num">1</div>
            <h4>Pasien Mendaftar</h4>
            <p>Pasien membuat antrian secara mandiri melalui aplikasi, memilih dokter dan jadwal yang tersedia.</p>
        </div>
        <div class="step-item reveal reveal-delay-2">
            <div class="step-num">2</div>
            <h4>Resepsionis Validasi</h4>
            <p>Saat pasien datang, resepsionis memverifikasi kedatangan dan mengonfirmasi antrian.</p>
        </div>
        <div class="step-item reveal reveal-delay-3">
            <div class="step-num">3</div>
            <h4>Dokter Periksa</h4>
            <p>Dokter mencatat diagnosis dan menulis resep digital langsung di sistem.</p>
        </div>
        <div class="step-item reveal reveal-delay-4">
            <div class="step-num">4</div>
            <h4>Apoteker Siapkan Obat</h4>
            <p>Apoteker menerima resep, menyiapkan obat, dan mengonfirmasi pengeluaran stok.</p>
        </div>
    </div>
</section>

<!-- ============ PERAN ============ -->
<section id="peran">
    <div class="reveal" style="position:relative;z-index:1;">
        <div class="section-label">5 Aktor Sistem</div>
        <h2 class="section-title">Dirancang untuk semua peran</h2>
        <p class="section-desc">Setiap pengguna mendapat tampilan dan akses yang disesuaikan dengan tanggung jawabnya.</p>
    </div>

    <div class="peran-grid">
        <div class="peran-card reveal reveal-delay-1">
            <div class="peran-icon" style="background:rgba(42,154,176,0.2);">
                <i class="fa-solid fa-user" style="color:#7dd3e0;"></i>
            </div>
            <h4>Pasien</h4>
            <p>Daftar antrian & lihat riwayat medis</p>
        </div>
        <div class="peran-card reveal reveal-delay-2">
            <div class="peran-icon" style="background:rgba(224,123,90,0.2);">
                <i class="fa-solid fa-headset" style="color:var(--coral-light);"></i>
            </div>
            <h4>Resepsionis</h4>
            <p>Kelola antrian & validasi kedatangan</p>
        </div>
        <div class="peran-card reveal reveal-delay-3">
            <div class="peran-icon" style="background:rgba(255,255,255,0.1);">
                <i class="fa-solid fa-stethoscope" style="color:rgba(255,255,255,0.8);"></i>
            </div>
            <h4>Dokter</h4>
            <p>Periksa pasien & tulis resep digital</p>
        </div>
        <div class="peran-card reveal reveal-delay-4">
            <div class="peran-icon" style="background:rgba(42,154,176,0.2);">
                <i class="fa-solid fa-mortar-pestle" style="color:#7dd3e0;"></i>
            </div>
            <h4>Apoteker</h4>
            <p>Proses resep & kelola stok obat</p>
        </div>
        <div class="peran-card reveal reveal-delay-4">
            <div class="peran-icon" style="background:rgba(224,123,90,0.2);">
                <i class="fa-solid fa-shield-halved" style="color:var(--coral-light);"></i>
            </div>
            <h4>Admin</h4>
            <p>Kelola data master & laporan sistem</p>
        </div>
    </div>
</section>

<!-- ============ CTA ============ -->
<section id="cta">
    <div class="cta-inner reveal">
        <div class="section-label" style="text-align:center;">Mulai Sekarang</div>
        <h2 class="section-title" style="text-align:center;">
            Siap menggunakan Cipeng Clinic?
        </h2>
        <p class="section-desc" style="text-align:center;">
            Daftarkan diri Anda sebagai pasien, atau masuk ke sistem jika Anda adalah bagian dari tim klinik.
        </p>
        <div class="cta-btns">
            <a href="register.php" class="btn-cta-primary">
                <i class="fa-solid fa-user-plus"></i>
                Daftar sebagai Pasien
            </a>
            <a href="login.php" class="btn-cta-secondary">
                <i class="fa-solid fa-right-to-bracket"></i>
                Login Staf / Dokter
            </a>
        </div>
    </div>
</section>

<!-- ============ FOOTER ============ -->
<footer>
    &copy; <?= date('Y') ?> <span>Cipeng Clinic</span> — Dibuat dengan ❤️ oleh Kelompok Cipeng. Semua hak dilindungi.
</footer>

<script>
    // Navbar scroll effect
    const navbar = document.getElementById('navbar');
    window.addEventListener('scroll', () => {
        navbar.classList.toggle('scrolled', window.scrollY > 40);
    });

    // Scroll reveal
    const reveals = document.querySelectorAll('.reveal');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
            }
        });
    }, { threshold: 0.12 });
    reveals.forEach(el => observer.observe(el));

    // Smooth scroll untuk nav links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
</script>
</body>
</html>