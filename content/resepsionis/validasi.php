<?php
// Pastikan file ini tidak diakses langsung
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'resepsionis') {
    exit('Akses Ditolak! Anda bukan resepsionis.');
}

$pesan = '';
$status = '';

// --- LOGIKA UPDATE STATUS KEDATANGAN ---
if (isset($_GET['act']) && isset($_GET['id'])) {
    $id_antrian = (int)$_GET['id'];
    $act = $_GET['act'];

    $allowed_status = ['datang', 'batal'];
    if (in_array($act, $allowed_status)) {
        try {
            $stmt = $pdo->prepare("UPDATE antrian SET status_kedatangan = ? WHERE id_antrian = ?");
            $stmt->execute([$act, $id_antrian]);
            $status_map = ['datang' => 'Pasien berhasil divalidasi kedatangannya!', 'batal' => 'Antrian berhasil dibatalkan.'];
            $status = 'success'; $pesan = $status_map[$act];
        } catch (PDOException $e) {
            $status = 'error'; $pesan = 'Gagal mengubah status: ' . $e->getMessage();
        }
    }
}

// --- AMBIL DATA ANTRIAN HARI INI (STATUS: BELUM DATANG) ---
$hari_ini = date('Y-m-d');
$query_tunggu = "SELECT a.*, p.nama_pasien, p.no_hp, d.nama_dokter, d.spesialis
                FROM antrian a
                JOIN pasien p ON a.id_pasien = p.id_pasien
                JOIN dokter d ON a.id_dokter = d.id_dokter
                WHERE a.tanggal_kunjungan = ? AND a.status_kedatangan = 'belum datang'
                ORDER BY a.no_urut ASC";
$stmt_tunggu = $pdo->prepare($query_tunggu);
$stmt_tunggu->execute([$hari_ini]);
$antrian_tunggu = $stmt_tunggu->fetchAll();

// --- AMBIL DATA ANTRIAN SUDAH DATANG HARI INI ---
$query_datang = "SELECT a.*, p.nama_pasien, d.nama_dokter
                FROM antrian a
                JOIN pasien p ON a.id_pasien = p.id_pasien
                JOIN dokter d ON a.id_dokter = d.id_dokter
                WHERE a.tanggal_kunjungan = ? AND a.status_kedatangan = 'datang'
                ORDER BY a.no_urut ASC";
$stmt_datang = $pdo->prepare($query_datang);
$stmt_datang->execute([$hari_ini]);
$antrian_datang = $stmt_datang->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
    <div>
        <h4 class="fw-bold text-dark mb-1">Validasi Kedatangan Pasien</h4>
        <p class="text-muted small mb-0">Konfirmasi kehadiran pasien untuk antrian hari ini: <strong><?= date('d F Y') ?></strong></p>
    </div>
    <div class="d-flex gap-2">
        <span class="badge bg-warning text-dark rounded-pill px-3 py-2">
            <i class="fa-solid fa-hourglass-half me-1"></i> <?= count($antrian_tunggu) ?> Menunggu
        </span>
        <span class="badge bg-success rounded-pill px-3 py-2">
            <i class="fa-solid fa-check me-1"></i> <?= count($antrian_datang) ?> Hadir
        </span>
    </div>
</div>

<div class="mb-4">
    <h5 class="fw-semibold text-warning mb-3"><i class="fa-solid fa-hourglass-half me-2"></i>Menunggu Kedatangan</h5>
    <?php if (empty($antrian_tunggu)): ?>
        <div class="alert alert-success border-0 rounded-4">
            <i class="fa-solid fa-circle-check me-2"></i> Semua antrian hari ini sudah divalidasi.
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover table-bordered align-middle" style="width:100%">
            <thead class="table-warning">
                <tr>
                    <th class="text-center" width="5%">No. Urut</th>
                    <th>Nama Pasien</th>
                    <th>No. HP</th>
                    <th>Dokter Tujuan</th>
                    <th>Keluhan</th>
                    <th class="text-center" width="15%">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($antrian_tunggu as $row): ?>
                <tr>
                    <td class="text-center fw-bold fs-5 text-warning"><?= $row['no_urut'] ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="bg-warning bg-opacity-20 text-warning rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width:36px;height:36px;">
                                <?= strtoupper(substr($row['nama_pasien'], 0, 1)) ?>
                            </div>
                            <span class="fw-semibold"><?= htmlspecialchars($row['nama_pasien']) ?></span>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($row['no_hp'] ?: '-') ?></td>
                    <td>
                        <span class="fw-semibold text-primary"><?= htmlspecialchars($row['nama_dokter']) ?></span><br>
                        <small class="text-muted"><?= htmlspecialchars($row['spesialis'] ?: 'Umum') ?></small>
                    </td>
                    <td class="small text-muted" style="max-width:180px"><?= htmlspecialchars($row['keluhan_awal'] ?: '-') ?></td>
                    <td class="text-center">
                        <a href="?f=resepsionis&m=validasi&act=datang&id=<?= $row['id_antrian'] ?>" 
                           class="btn btn-sm btn-success rounded-3 shadow-sm me-1 btn-hadir"
                           data-nama="<?= htmlspecialchars($row['nama_pasien']) ?>"
                           title="Konfirmasi Hadir">
                            <i class="fa-solid fa-check me-1"></i>Hadir
                        </a>
                        <a href="?f=resepsionis&m=validasi&act=batal&id=<?= $row['id_antrian'] ?>" 
                           class="btn btn-sm btn-danger rounded-3 shadow-sm btn-hapus"
                           title="Batalkan Antrian">
                            <i class="fa-solid fa-xmark"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($antrian_datang)): ?>
<div>
    <h5 class="fw-semibold text-success mb-3"><i class="fa-solid fa-circle-check me-2"></i>Sudah Hadir (Diproses Dokter)</h5>
    <div class="table-responsive">
        <table class="table table-hover table-bordered align-middle" style="width:100%">
            <thead class="table-success">
                <tr>
                    <th class="text-center" width="5%">No. Urut</th>
                    <th>Nama Pasien</th>
                    <th>Dokter Tujuan</th>
                    <th>Keluhan</th>
                    <th class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($antrian_datang as $row): ?>
                <tr>
                    <td class="text-center fw-bold fs-5 text-success"><?= $row['no_urut'] ?></td>
                    <td class="fw-semibold"><?= htmlspecialchars($row['nama_pasien']) ?></td>
                    <td><?= htmlspecialchars($row['nama_dokter']) ?></td>
                    <td class="small text-muted"><?= htmlspecialchars($row['keluhan_awal'] ?: '-') ?></td>
                    <td class="text-center"><span class="badge bg-success rounded-pill px-3">Sudah Hadir</span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
    // Notifikasi berhasil/gagal dari PHP
    <?php if($pesan != ''): ?>
    document.addEventListener("DOMContentLoaded", function() {
        Swal.fire({
            title: "<?= $status == 'success' ? 'Berhasil!' : 'Gagal!' ?>",
            text: "<?= $pesan ?>",
            icon: "<?= $status ?>",
            confirmButtonColor: "#0d6efd"
        }).then(() => {
            window.location.href = "?f=resepsionis&m=validasi";
        });
    });
    <?php endif; ?>

    // --- SweetAlert2: Konfirmasi Kehadiran (Tombol Hadir) ---
    document.body.addEventListener('click', function(e) {
        if(e.target.closest('.btn-hadir')) {
            e.preventDefault();
            const btn = e.target.closest('.btn-hadir');
            const href = btn.getAttribute('href');
            const namaPasien = btn.getAttribute('data-nama');

            Swal.fire({
                title: 'Konfirmasi Kedatangan',
                text: "Apakah pasien atas nama " + namaPasien + " sudah berada di klinik?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#198754', // Warna hijau success Bootstrap
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Sudah Hadir!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = href;
                }
            });
        }
    });
</script>