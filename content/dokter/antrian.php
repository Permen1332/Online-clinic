<?php
// Pastikan file ini tidak diakses langsung
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'dokter') {
    exit('Akses Ditolak! Anda bukan dokter.');
}

// Ambil id_dokter berdasarkan id_user yang login
$stmt_dokter = $pdo->prepare("SELECT id_dokter, nama_dokter, spesialis FROM dokter WHERE id_user = ?");
$stmt_dokter->execute([$_SESSION['id_user']]);
$profil_dokter = $stmt_dokter->fetch();

if (!$profil_dokter) {
    echo "<div class='alert alert-danger'>Data dokter tidak ditemukan. Hubungi administrator.</div>";
    return;
}
$id_dokter = $profil_dokter['id_dokter'];

// Ambil antrian hari ini untuk dokter ini yang sudah hadir dan belum diperiksa
$hari_ini = date('Y-m-d');
$query = "SELECT a.*, p.nama_pasien, p.no_hp, p.tanggal_lahir, p.alamat,
                 CASE WHEN pm.id_pemeriksaan IS NOT NULL THEN 1 ELSE 0 END AS sudah_diperiksa
          FROM antrian a
          JOIN pasien p ON a.id_pasien = p.id_pasien
          LEFT JOIN pemeriksaan pm ON pm.id_antrian = a.id_antrian
          WHERE a.id_dokter = ? AND a.tanggal_kunjungan = ? AND a.status_kedatangan = 'datang'
          ORDER BY a.no_urut ASC";
$stmt = $pdo->prepare($query);
$stmt->execute([$id_dokter, $hari_ini]);
$daftar_antrian = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
    <div>
        <h4 class="fw-bold text-dark mb-1">Daftar Pasien Hari Ini</h4>
        <p class="text-muted small mb-0">
            <i class="fa-solid fa-stethoscope text-primary me-1"></i>
            Dr. <?= htmlspecialchars($profil_dokter['nama_dokter']) ?> &mdash; 
            <span class="badge bg-info text-dark"><?= htmlspecialchars($profil_dokter['spesialis'] ?: 'Umum') ?></span>
            &mdash; <?= date('d F Y') ?>
        </p>
    </div>
    <span class="badge bg-primary rounded-pill px-3 py-2 fs-6">
        <i class="fa-solid fa-users me-1"></i> <?= count($daftar_antrian) ?> Pasien
    </span>
</div>

<?php if (empty($daftar_antrian)): ?>
<div class="text-center py-5">
    <i class="fa-solid fa-calendar-check fa-4x text-muted mb-3 d-block opacity-50"></i>
    <h5 class="text-muted fw-semibold">Tidak ada pasien hari ini</h5>
    <p class="text-muted small">Belum ada pasien yang hadir untuk jadwal Anda hari ini.</p>
</div>
<?php else: ?>

<div class="row g-3">
    <?php foreach ($daftar_antrian as $row): ?>
    <div class="col-md-6 col-xl-4">
        <div class="card border-0 rounded-4 shadow-sm h-100 <?= $row['sudah_diperiksa'] ? 'border-start border-success border-4' : 'border-start border-primary border-4' ?>">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-3 px-3 py-2 fw-bold fs-4">
                        #<?= $row['no_urut'] ?>
                    </div>
                    <?php if ($row['sudah_diperiksa']): ?>
                        <span class="badge bg-success rounded-pill">Sudah Diperiksa</span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark rounded-pill">Menunggu</span>
                    <?php endif; ?>
                </div>

                <h6 class="fw-bold text-dark mb-1 mt-3"><?= htmlspecialchars($row['nama_pasien']) ?></h6>
                <p class="small text-muted mb-1"><i class="fa-solid fa-phone me-1"></i><?= htmlspecialchars($row['no_hp'] ?: '-') ?></p>
                <?php if($row['tanggal_lahir']): ?>
                <p class="small text-muted mb-1">
                    <i class="fa-solid fa-cake-candles me-1"></i>
                    <?php
                        $tgl = new DateTime($row['tanggal_lahir']);
                        $now = new DateTime();
                        $umur = $tgl->diff($now)->y;
                        echo date('d M Y', strtotime($row['tanggal_lahir'])) . " ({$umur} tahun)";
                    ?>
                </p>
                <?php endif; ?>
                
                <hr class="my-2">
                <p class="small mb-2"><strong>Keluhan:</strong> <?= htmlspecialchars($row['keluhan_awal'] ?: 'Tidak ada keluhan dicatat') ?></p>

                <?php if (!$row['sudah_diperiksa']): ?>
                <a href="?f=dokter&m=periksa&id_antrian=<?= $row['id_antrian'] ?>" class="btn btn-primary btn-sm w-100 rounded-3 fw-semibold mt-1">
                    <i class="fa-solid fa-stethoscope me-2"></i>Mulai Periksa
                </a>
                <?php else: ?>
                <a href="?f=dokter&m=periksa&id_antrian=<?= $row['id_antrian'] ?>" class="btn btn-outline-success btn-sm w-100 rounded-3 fw-semibold mt-1">
                    <i class="fa-solid fa-eye me-2"></i>Lihat Hasil Periksa
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php endif; ?>
