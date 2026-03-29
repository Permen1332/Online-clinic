<?php
// Pastikan file ini tidak diakses langsung
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'pasien') {
    exit('Akses Ditolak! Anda bukan pasien.');
}

// Ambil id_pasien
$stmt_p = $pdo->prepare("SELECT id_pasien, nama_pasien FROM pasien WHERE id_user = ?");
$stmt_p->execute([$_SESSION['id_user']]);
$profil_pasien = $stmt_p->fetch();
if (!$profil_pasien) { echo "<div class='alert alert-danger'>Profil pasien tidak ditemukan.</div>"; return; }
$id_pasien = $profil_pasien['id_pasien'];

// Ambil riwayat antrian beserta pemeriksaan
$query = "SELECT a.*, d.nama_dokter, d.spesialis,
                 pm.id_pemeriksaan, pm.catatan, pm.hasil_diagnosis
          FROM antrian a
          JOIN dokter d ON a.id_dokter = d.id_dokter
          LEFT JOIN pemeriksaan pm ON pm.id_antrian = a.id_antrian
          WHERE a.id_pasien = ?
          ORDER BY a.tanggal_kunjungan DESC";
$stmt = $pdo->prepare($query);
$stmt->execute([$id_pasien]);
$riwayat = $stmt->fetchAll();

// Preload resep per id_pemeriksaan
$resep_per_periksa = [];
foreach ($riwayat as $r) {
    if ($r['id_pemeriksaan']) {
        $stmt_ro = $pdo->prepare("SELECT ro.*, o.nama_obat, o.satuan FROM resep_obat ro JOIN obat o ON ro.id_obat = o.id_obat WHERE ro.id_pemeriksaan = ?");
        $stmt_ro->execute([$r['id_pemeriksaan']]);
        $resep_per_periksa[$r['id_pemeriksaan']] = $stmt_ro->fetchAll();
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
    <div>
        <h4 class="fw-bold text-dark mb-1">Riwayat Medis Saya</h4>
        <p class="text-muted small mb-0">Rekam jejak kunjungan dan pemeriksaan di Cipeng Clinic</p>
    </div>
    <span class="badge bg-primary rounded-pill px-3 py-2 fs-6">
        <i class="fa-solid fa-notes-medical me-1"></i><?= count($riwayat) ?> Kunjungan
    </span>
</div>

<?php if (empty($riwayat)): ?>
<div class="text-center py-5">
    <i class="fa-solid fa-notes-medical fa-4x text-muted mb-3 d-block opacity-50"></i>
    <h5 class="text-muted fw-semibold">Belum ada riwayat kunjungan</h5>
    <p class="text-muted small">Anda belum pernah melakukan kunjungan ke Cipeng Clinic.</p>
    <a href="?f=pasien&m=pengajuan" class="btn btn-primary rounded-pill px-4 mt-2">
        <i class="fa-solid fa-calendar-plus me-2"></i>Buat Antrian Sekarang
    </a>
</div>
<?php else: ?>

<?php
$badge_status = [
    'belum datang' => '<span class="badge bg-warning text-dark rounded-pill">Belum Datang</span>',
    'datang'       => '<span class="badge bg-success rounded-pill">Sudah Hadir</span>',
    'batal'        => '<span class="badge bg-danger rounded-pill">Dibatalkan</span>',
];

foreach ($riwayat as $r):
?>
<div class="card border-0 rounded-4 shadow-sm mb-3 overflow-hidden">
    <div class="card-header py-3 px-4 d-flex justify-content-between align-items-center
        <?= $r['id_pemeriksaan'] ? 'bg-success bg-opacity-10' : ($r['status_kedatangan'] == 'batal' ? 'bg-danger bg-opacity-10' : 'bg-light') ?>">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-primary bg-opacity-10 text-primary rounded-3 px-3 py-2 fw-bold">
                No. <?= $r['no_urut'] ?>
            </div>
            <div>
                <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($r['nama_dokter']) ?></h6>
                <small class="text-muted"><?= htmlspecialchars($r['spesialis'] ?: 'Dokter Umum') ?></small>
            </div>
        </div>
        <div class="text-end">
            <div class="mb-1"><?= $badge_status[$r['status_kedatangan']] ?? '-' ?></div>
            <small class="text-muted"><i class="fa-solid fa-calendar me-1"></i><?= date('d M Y', strtotime($r['tanggal_kunjungan'])) ?></small>
        </div>
    </div>

    <div class="card-body p-4">
        <?php if ($r['keluhan_awal']): ?>
        <div class="mb-3">
            <span class="fw-semibold small text-muted text-uppercase letter-spacing-1">Keluhan:</span>
            <p class="mb-0 mt-1 text-dark"><?= htmlspecialchars($r['keluhan_awal']) ?></p>
        </div>
        <?php endif; ?>

        <?php if ($r['id_pemeriksaan']): ?>
        <hr>
        <div class="row g-3">
            <div class="col-md-6">
                <div class="p-3 bg-light rounded-3">
                    <h6 class="fw-semibold text-primary mb-2"><i class="fa-solid fa-stethoscope me-1"></i>Hasil Diagnosis</h6>
                    <p class="mb-0 small"><?= nl2br(htmlspecialchars($r['hasil_diagnosis'] ?: '-')) ?></p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="p-3 bg-light rounded-3">
                    <h6 class="fw-semibold text-success mb-2"><i class="fa-solid fa-notes-medical me-1"></i>Catatan Dokter</h6>
                    <p class="mb-0 small"><?= nl2br(htmlspecialchars($r['catatan'] ?: '-')) ?></p>
                </div>
            </div>
        </div>

        <?php $resep = $resep_per_periksa[$r['id_pemeriksaan']] ?? []; ?>
        <?php if (!empty($resep)): ?>
        <hr>
        <h6 class="fw-semibold text-dark mb-3"><i class="fa-solid fa-pills text-success me-2"></i>Resep Obat</h6>
        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle rounded-3 overflow-hidden mb-0">
                <thead class="table-success">
                    <tr>
                        <th>Nama Obat</th>
                        <th>Satuan</th>
                        <th>Jumlah</th>
                        <th>Aturan Pakai</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resep as $ro): ?>
                    <tr>
                        <td class="fw-semibold"><?= htmlspecialchars($ro['nama_obat']) ?></td>
                        <td><?= htmlspecialchars($ro['satuan']) ?></td>
                        <td class="text-center"><?= $ro['jumlah_obat_keluar'] ?></td>
                        <td><?= htmlspecialchars($ro['dosis']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="alert alert-light border rounded-3 py-2 mb-0 text-muted small">
            <?php if ($r['status_kedatangan'] == 'batal'): ?>
                <i class="fa-solid fa-circle-xmark text-danger me-1"></i> Antrian ini telah dibatalkan.
            <?php else: ?>
                <i class="fa-solid fa-clock text-warning me-1"></i> Pemeriksaan belum dilakukan oleh dokter.
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<?php endif; ?>
