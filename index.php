<?php
session_start();
require_once "config/koneksi.php";

// Tampilkan error saat mode development
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Cek Keamanan: Apakah User Sudah Login?
if (!isset($_SESSION['id_user'])) {
    header("Location: auth/welcome.php");
    exit();
}

// Ambil data session
$id_user      = $_SESSION['id_user'];
$role         = $_SESSION['role']; // pasien | dokter | resepsionis | admin | apoteker
$nama_lengkap = $_SESSION['nama_lengkap'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cipeng Clinic - <?= ucfirst($role) ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --bs-primary: #1a6b7a;
            --bs-primary-rgb: 26, 107, 122;
        }

        body { 
            font-family: 'DM Sans', sans-serif !important; 
            background-color: #f4f6f9; 
            overflow-x: hidden;
        }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #0d6efd; }

        #wrapper {
            display: flex;
            width: 100%;
            height: 100vh;
            overflow: hidden;
        }

        /* --- Sidebar --- */
        #sidebar {
            width: 260px;
            background: #1e293b;
            color: #fff;
            display: flex;
            flex-direction: column;
            transition: all 0.3s;
            z-index: 1050;
            flex-shrink: 0;
        }
        .sidebar-brand {
            height: 70px;
            display: flex;
            align-items: center;
            padding: 0 1.5rem;
            background: #0f172a;
            font-weight: 700;
            font-size: 1.25rem;
            letter-spacing: 1px;
            white-space: nowrap;
        }

        /* Badge role di bawah brand */
        .sidebar-role-badge {
            padding: 0.4rem 1.5rem 0.6rem;
            background: #0f172a;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .sidebar-role-badge .badge {
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .sidebar-nav {
            padding: 1rem 0;
            overflow-y: auto;
            flex-grow: 1;
        }
        .nav-title {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #64748b;
            padding: 1rem 1.5rem 0.4rem;
            font-weight: 600;
        }
        .sidebar-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: #cbd5e1;
            text-decoration: none;
            transition: all 0.2s;
            gap: 0.75rem;
        }
        .sidebar-link i { width: 20px; font-size: 1rem; flex-shrink: 0; }
        .sidebar-link:hover  { color: #fff; background: rgba(255,255,255,0.05); }
        .sidebar-link.active { color: #fff; background: #0d6efd; border-left: 4px solid #fff; }

        #sidebar-overlay {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background: rgba(0,0,0,0.5); z-index: 1040; display: none;
        }

        /* --- Main Content --- */
        #main-content {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            min-height: 0;
            background-color: #f4f7f6;
        }
        .topbar {
            height: 70px;
            background: #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
            z-index: 1000;
        }
        .btn-toggle { background: none; border: none; font-size: 1.5rem; color: #6c757d; }
        .content-area { padding: 1.5rem; overflow-y: auto; flex-grow: 1; }
        .card-custom {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            background: #fff;
        }

        @media (max-width: 768px) {
            #sidebar { position: fixed; height: 100%; left: -260px; }
            #sidebar.show { left: 0; }
        }
    </style>
</head>
<body>
<div id="wrapper">
    <div id="sidebar-overlay" onclick="toggleSidebar()"></div>

    <!-- ===================== SIDEBAR ===================== -->
    <nav id="sidebar">
        <div class="sidebar-brand">
            <i class="fa-solid fa-house-medical text-primary me-2"></i>
            <span>Cipeng Clinic</span>
        </div>

        <!-- Badge role aktif -->
        <div class="sidebar-role-badge">
            <?php
            $badgeColor = match($role) {
                'admin'       => 'danger',
                'apoteker'    => 'success',
                'resepsionis' => 'warning',
                'dokter'      => 'info',
                'pasien'      => 'secondary',
                default       => 'secondary'
            };
            $roleIcon = match($role) {
                'admin'       => 'fa-shield-halved',
                'apoteker'    => 'fa-mortar-pestle',
                'resepsionis' => 'fa-headset',
                'dokter'      => 'fa-stethoscope',
                'pasien'      => 'fa-user',
                default       => 'fa-user'
            };
            ?>
            <span class="badge bg-<?= $badgeColor ?> bg-opacity-25 text-<?= $badgeColor ?>">
                <i class="fa-solid <?= $roleIcon ?> me-1"></i><?= ucfirst($role) ?>
            </span>
        </div>

        <div class="sidebar-nav">
            <!-- Dashboard (semua role) -->
            <a href="index.php" class="sidebar-link <?= (!isset($_GET['m'])) ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-pie"></i> <span>Dashboard</span>
            </a>

            <!-- ===== PASIEN ===== -->
            <?php if ($role == 'pasien'): ?>
                <div class="nav-title">Layanan Medis</div>
                <a href="?f=pasien&m=pengajuan" class="sidebar-link <?= (@$_GET['m'] == 'pengajuan') ? 'active' : '' ?>">
                    <i class="fa-solid fa-calendar-plus"></i> <span>Buat Antrian</span>
                </a>
                <a href="?f=pasien&m=riwayat" class="sidebar-link <?= (@$_GET['m'] == 'riwayat') ? 'active' : '' ?>">
                    <i class="fa-solid fa-notes-medical"></i> <span>Riwayat Medis</span>
                </a>
            <?php endif; ?>

            <!-- ===== DOKTER ===== -->
            <?php if ($role == 'dokter'): ?>
                <div class="nav-title">Pemeriksaan</div>
                <a href="?f=dokter&m=antrian" class="sidebar-link <?= (@$_GET['m'] == 'antrian') ? 'active' : '' ?>">
                    <i class="fa-solid fa-stethoscope"></i> <span>Daftar Pasien</span>
                </a>
            <?php endif; ?>

            <!-- ===== RESEPSIONIS ===== -->
            <?php if ($role == 'resepsionis'): ?>
                <div class="nav-title">Operasional Harian</div>
                <a href="?f=resepsionis&m=validasi" class="sidebar-link <?= (@$_GET['m'] == 'validasi') ? 'active' : '' ?>">
                    <i class="fa-solid fa-clipboard-check"></i> <span>Validasi Antrian</span>
                </a>
                <a href="?f=resepsionis&m=buat_antrian" class="sidebar-link <?= (@$_GET['m'] == 'buat_antrian') ? 'active' : '' ?>">
                    <i class="fa-solid fa-user-plus"></i> <span>Daftar Walk-in</span>
                </a>
                <a href="?f=resepsionis&m=data_antrian" class="sidebar-link <?= (@$_GET['m'] == 'data_antrian') ? 'active' : '' ?>">
                    <i class="fa-solid fa-list-ol"></i> <span>Data Antrian</span>
                </a>

                <div class="nav-title">Referensi</div>
                <a href="?f=resepsionis&m=data_dokter_ro" class="sidebar-link <?= (@$_GET['m'] == 'data_dokter_ro') ? 'active' : '' ?>">
                    <i class="fa-solid fa-user-doctor"></i> <span>Jadwal Dokter</span>
                </a>
            <?php endif; ?>

            <!-- ===== ADMIN ===== -->
            <?php if ($role == 'admin'): ?>
                <div class="nav-title">Master Data</div>
                <a href="?f=admin&m=data_dokter" class="sidebar-link <?= (@$_GET['m'] == 'data_dokter') ? 'active' : '' ?>">
                    <i class="fa-solid fa-user-doctor"></i> <span>Data Dokter</span>
                </a>
                <a href="?f=admin&m=data_pasien" class="sidebar-link <?= (@$_GET['m'] == 'data_pasien') ? 'active' : '' ?>">
                    <i class="fa-solid fa-users"></i> <span>Data Pasien</span>
                </a>
                <a href="?f=admin&m=data_obat" class="sidebar-link <?= (@$_GET['m'] == 'data_obat') ? 'active' : '' ?>">
                    <i class="fa-solid fa-pills"></i> <span>Data Obat</span>
                </a>

                <div class="nav-title">Laporan</div>
                <a href="?f=admin&m=rekap_pemeriksaan" class="sidebar-link <?= (@$_GET['m'] == 'rekap_pemeriksaan') ? 'active' : '' ?>">
                    <i class="fa-solid fa-file-medical"></i> <span>Rekap Pemeriksaan</span>
                </a>

                <div class="nav-title">Sistem</div>
                <a href="?f=admin&m=manajemen_akun" class="sidebar-link <?= (@$_GET['m'] == 'manajemen_akun') ? 'active' : '' ?>">
                    <i class="fa-solid fa-users-gear"></i> <span>Manajemen Akun</span>
                </a>
            <?php endif; ?>

            <!-- ===== APOTEKER ===== -->
            <?php if ($role == 'apoteker'): ?>
                <div class="nav-title">Farmasi</div>
                <a href="?f=apoteker&m=resep_masuk" class="sidebar-link <?= (@$_GET['m'] == 'resep_masuk') ? 'active' : '' ?>">
                    <i class="fa-solid fa-prescription-bottle-medical"></i> <span>Resep Masuk</span>
                </a>
                <a href="?f=apoteker&m=data_obat" class="sidebar-link <?= (@$_GET['m'] == 'data_obat') ? 'active' : '' ?>">
                    <i class="fa-solid fa-pills"></i> <span>Stok Obat</span>
                </a>
                <a href="?f=apoteker&m=riwayat_obat" class="sidebar-link <?= (@$_GET['m'] == 'riwayat_obat') ? 'active' : '' ?>">
                    <i class="fa-solid fa-clock-rotate-left"></i> <span>Riwayat Pengeluaran</span>
                </a>
            <?php endif; ?>

            <!-- Akun (semua role) -->
            <div class="nav-title">Akun Saya</div>
            <a href="?f=profil&m=profil" class="sidebar-link <?= (@$_GET['m'] == 'profil') ? 'active' : '' ?>">
                <i class="fa-solid fa-circle-user"></i> <span>Profil & Keamanan</span>
            </a>
        </div>
    </nav>
    <!-- =================== END SIDEBAR =================== -->

    <div id="main-content">
        <header class="topbar">
            <div class="d-flex align-items-center">
                <button class="btn-toggle d-md-none me-3" onclick="toggleSidebar()">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <h5 class="mb-0 fw-bold text-dark d-none d-sm-block">
                    <?= isset($_GET['m']) ? ucwords(str_replace("_", " ", $_GET['m'])) : "Dashboard" ?>
                </h5>
            </div>

            <div class="dropdown">
                <button class="btn btn-light rounded-pill dropdown-toggle d-flex align-items-center gap-2 border-0 shadow-sm"
                        type="button" data-bs-toggle="dropdown">
                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center"
                         style="width:35px;height:35px;font-weight:bold;">
                        <?= strtoupper(substr($nama_lengkap, 0, 1)) ?>
                    </div>
                    <span class="d-none d-md-block fw-medium text-dark"><?= $nama_lengkap ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                    <li>
                        <h6 class="dropdown-header">
                            Login sebagai: <span class="text-primary text-capitalize"><?= $role ?></span>
                        </h6>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item text-danger d-flex align-items-center gap-2 py-2"
                           href="auth/logout.php" id="btn-logout">
                            <i class="fa-solid fa-right-from-bracket"></i> Keluar Aplikasi
                        </a>
                    </li>
                </ul>
            </div>
        </header>

        <main class="content-area">
            <div class="card card-custom p-4">
                <?php
                // ============================================================
                //  ROUTING AMAN + ROLE GUARD
                // ============================================================
                if (isset($_GET['f']) && isset($_GET['m'])) {
                    $f = $_GET['f'];
                    $m = $_GET['m'];

                    // 1. Validasi karakter (cegah LFI / directory traversal)
                    if (!preg_match('/^[a-zA-Z0-9_]+$/', $f) || !preg_match('/^[a-zA-Z0-9_]+$/', $m)) {
                        echo "<div class='text-center py-5'>
                                <i class='fa-solid fa-triangle-exclamation display-1 text-danger mb-4'></i>
                                <h2 class='fw-bold text-dark'>Akses Ditolak!</h2>
                                <p class='text-muted'>URL mengandung karakter tidak valid.</p>
                              </div>";

                    // 2. Role guard: folder harus sesuai role, kecuali 'profil' (boleh semua)
                    } elseif ($f !== $role && $f !== 'profil') {
                        echo "<div class='text-center py-5'>
                                <i class='fa-solid fa-ban display-1 text-danger mb-4'></i>
                                <h2 class='fw-bold text-dark'>Akses Ditolak!</h2>
                                <p class='text-muted mt-2'>Anda tidak memiliki izin untuk mengakses halaman ini.</p>
                                <a href='index.php' class='btn btn-primary rounded-pill px-4 mt-3'>
                                    <i class='fa-solid fa-arrow-left me-2'></i>Kembali ke Dashboard
                                </a>
                              </div>";

                    // 3. Load file konten
                    } else {
                        $file = 'content/' . $f . '/' . $m . '.php';
                        if (file_exists($file)) {
                            require_once($file);
                        } else {
                            echo "<div class='text-center py-5'>
                                    <h1 class='display-1 fw-bold text-secondary'>404</h1>
                                    <h3 class='fw-bold text-dark mt-3'>Fitur Belum Tersedia</h3>
                                    <p class='text-muted'>Halaman <b>{$m}.php</b> tidak ditemukan di folder <b>{$f}</b>.</p>
                                  </div>";
                        }
                    }

                } else {
                    // Dashboard default
                    $dashFile = "content/dashboard_{$role}.php";
                    if (file_exists($dashFile)) {
                        include $dashFile;
                    } elseif (file_exists("content/dashboard.php")) {
                        include "content/dashboard.php";
                    } else {
                        echo "<p class='text-muted text-center py-5'>File dashboard belum dibuat.</p>";
                    }
                }
                ?>
            </div>

            <footer class="text-center text-muted mt-4 pb-2" style="font-size:0.85rem;">
                &copy; <?= date('Y') ?> Cipeng Clinic by [Kelompok Cipeng]. All rights reserved.
            </footer>
        </main>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<script>
    const sidebar  = document.getElementById('sidebar');
    const overlay  = document.getElementById('sidebar-overlay');

    function toggleSidebar() {
        sidebar.classList.toggle('show');
        overlay.style.display = sidebar.classList.contains('show') ? 'block' : 'none';
    }

    // Konfirmasi Logout
    document.getElementById('btn-logout').addEventListener('click', function(e) {
        e.preventDefault();
        const href = this.getAttribute('href');
        Swal.fire({
            title: 'Keluar Aplikasi?',
            text: "Sesi Anda akan diakhiri.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Logout!',
            cancelButtonText: 'Batal'
        }).then(r => { if (r.isConfirmed) window.location.href = href; });
    });

    // Global Hapus Data (gunakan class 'btn-hapus')
    document.body.addEventListener('click', function(e) {
        if (e.target.closest('.btn-hapus')) {
            e.preventDefault();
            const btn  = e.target.closest('.btn-hapus');
            const href = btn.getAttribute('href');
            Swal.fire({
                title: 'Hapus data ini?',
                text: "Data yang dihapus tidak dapat dikembalikan!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then(r => { if (r.isConfirmed) window.location.href = href; });
        }
    });

    // Auto DataTables (gunakan class 'table-datatable')
    $(document).ready(function() {
        $('.table-datatable').each(function() {
            // Cegah inisialisasi ganda pada tabel yang sama
            if ($.fn.DataTable.isDataTable(this)) {
                $(this).DataTable().destroy();
            }

            var theadCols = $(this).find('thead tr th').length;
            var tbodyCols = $(this).find('tbody tr:first td').length;

            // Hanya init jika jumlah kolom thead & tbody cocok, atau tbody kosong
            if (tbodyCols === 0 || theadCols === tbodyCols) {
                $(this).DataTable({
                    autoWidth   : false,
                    destroy     : true,
                    language: {
                        search      : "Cari:",
                        lengthMenu  : "Tampilkan _MENU_ data",
                        info        : "Menampilkan _START_ s/d _END_ dari _TOTAL_ data",
                        infoEmpty   : "Tidak ada data",
                        zeroRecords : "Data tidak ditemukan",
                        paginate    : { first:"Awal", last:"Akhir", next:"Lanjut", previous:"Kembali" }
                    },
                    ordering   : true,
                    pageLength : 10
                });
            }
        });
    });
</script>
</body>
</html>