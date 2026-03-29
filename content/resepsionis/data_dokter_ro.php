<?php
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'resepsionis') {
    exit('Akses Ditolak! Anda bukan resepsionis.');
}

// --- AMBIL SEMUA DATA DOKTER ---
$stmt = $pdo->query("
    SELECT d.*, 
           COUNT(CASE WHEN a.tanggal_kunjungan = CURDATE() AND a.status_kedatangan != 'batal' THEN 1 END) AS antrian_hari_ini
    FROM dokter d
    LEFT JOIN antrian a ON d.id_dokter = a.id_dokter
    GROUP BY d.id_dokter
    ORDER BY d.nama_dokter ASC
");
$daftar_dokter = $stmt->fetchAll();

$total_dokter  = count($daftar_dokter);
$hari_indo     = ['Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu',
                  'Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu','Sunday'=>'Minggu'];
$hari_ini      = $hari_indo[date('l')];
$dokter_praktik_hari_ini = array_filter($daftar_dokter, function($d) use ($hari_ini) {
    $hari = array_map('trim', explode(',', $d['hari_praktek'] ?? ''));
    return in_array($hari_ini, $hari);
});
?>

<!-- ===== HEADER ===== -->
<div class="d-flex justify-content-between align-items-start mb-4 pb-3 border-bottom flex-wrap gap-3">
    <div>
        <h4 class="fw-bold text-dark mb-1">
            <i class="fa-solid fa-user-doctor text-primary me-2"></i>Jadwal Dokter
        </h4>
        <p class="text-muted small mb-0">
            Informasi jadwal dan ketersediaan dokter — 
            <strong><?= date('l, d F Y') ?></strong>
            <span class="badge bg-primary bg-opacity-10 text-primary ms-1"><?= $hari_ini ?></span>
        </p>
    </div>
</div>

<!-- ===== STAT CARDS ===== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <div class="card border-0 rounded-3 shadow-sm h-100" style="background: linear-gradient(135deg,#0d6efd,#0b5ed7);">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
                    <i class="fa-solid fa-user-doctor text-white fs-5"></i>
                </div>
                <div>
                    <div class="text-white-50 small">Total Dokter</div>
                    <div class="text-white fw-bold fs-4 lh-1"><?= $total_dokter ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card border-0 rounded-3 shadow-sm h-100" style="background: linear-gradient(135deg,#198754,#157347);">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
                    <i class="fa-solid fa-calendar-check text-white fs-5"></i>
                </div>
                <div>
                    <div class="text-white-50 small">Praktik Hari Ini</div>
                    <div class="text-white fw-bold fs-4 lh-1"><?= count($dokter_praktik_hari_ini) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card border-0 rounded-3 shadow-sm h-100" style="background: linear-gradient(135deg,#6f42c1,#5a32a3);">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
                    <i class="fa-solid fa-list-ol text-white fs-5"></i>
                </div>
                <div>
                    <div class="text-white-50 small">Antrian Hari Ini</div>
                    <div class="text-white fw-bold fs-4 lh-1">
                        <?= array_sum(array_column($daftar_dokter, 'antrian_hari_ini')) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===== KARTU DOKTER ===== -->
<div class="row g-3 mb-4">
    <?php foreach ($daftar_dokter as $d):
        $hari_list   = array_map('trim', explode(',', $d['hari_praktek'] ?? ''));
        $praktik_hari_ini = in_array($hari_ini, $hari_list);
        $sisa_slot   = max(0, ($d['kapasitas'] ?? 0) - $d['antrian_hari_ini']);
        $persen      = ($d['kapasitas'] > 0) ? round($d['antrian_hari_ini'] / $d['kapasitas'] * 100) : 0;
        $bar_color   = $persen >= 90 ? 'danger' : ($persen >= 60 ? 'warning' : 'success');

        // Parse jam praktek
        $jp = explode(' - ', $d['jam_praktek'] ?? '');
        $jam_mulai   = isset($jp[0]) ? date('H:i', strtotime(trim($jp[0]))) : '-';
        $jam_selesai = isset($jp[1]) ? date('H:i', strtotime(trim($jp[1]))) : '-';
    ?>
    <div class="col-md-6 col-xl-4">
        <div class="card border-0 shadow-sm rounded-3 h-100 <?= $praktik_hari_ini ? 'border-start border-3 border-success' : 'border-start border-3 border-secondary' ?>">
            <div class="card-body">

                <!-- Header kartu -->
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center fw-bold text-primary"
                             style="width:44px;height:44px;font-size:1.1rem;">
                            <?= strtoupper(substr($d['nama_dokter'], 0, 1)) ?>
                        </div>
                        <div>
                            <div class="fw-bold text-dark small lh-sm"><?= htmlspecialchars($d['nama_dokter']) ?></div>
                            <div class="text-muted" style="font-size:0.75rem;">
                                <?= htmlspecialchars($d['spesialis'] ?: 'Dokter Umum') ?>
                            </div>
                        </div>
                    </div>
                    <?php if ($praktik_hari_ini): ?>
                        <span class="badge bg-success-subtle text-success rounded-pill" style="font-size:0.7rem;">
                            <i class="fa-solid fa-circle-dot me-1"></i>Praktik
                        </span>
                    <?php else: ?>
                        <span class="badge bg-secondary-subtle text-secondary rounded-pill" style="font-size:0.7rem;">
                            <i class="fa-solid fa-moon me-1"></i>Libur
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Info jam & kapasitas -->
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <div class="bg-light rounded-2 p-2 text-center">
                            <div class="text-muted" style="font-size:0.7rem;">Jam Praktek</div>
                            <div class="fw-semibold text-dark small">
                                <?= $jam_mulai ?> – <?= $jam_selesai ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="bg-light rounded-2 p-2 text-center">
                            <div class="text-muted" style="font-size:0.7rem;">Sisa Slot Hari Ini</div>
                            <div class="fw-semibold <?= $sisa_slot == 0 ? 'text-danger' : 'text-success' ?> small">
                                <?= $praktik_hari_ini ? ($sisa_slot == 0 ? 'Penuh' : $sisa_slot . ' / ' . $d['kapasitas']) : '—' ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Progress bar antrian (hanya tampil jika praktik hari ini) -->
                <?php if ($praktik_hari_ini): ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1" style="font-size:0.72rem;">
                        <span class="text-muted">Antrian terisi</span>
                        <span class="fw-semibold text-<?= $bar_color ?>"><?= $persen ?>%</span>
                    </div>
                    <div class="progress rounded-pill" style="height:6px;">
                        <div class="progress-bar bg-<?= $bar_color ?>" style="width:<?= $persen ?>%"></div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Hari praktek badges -->
                <div class="d-flex flex-wrap gap-1">
                    <?php
                    $semua_hari = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'];
                    foreach ($semua_hari as $h):
                        $aktif    = in_array($h, $hari_list);
                        $is_today = ($h === $hari_ini);
                    ?>
                    <span class="badge rounded-pill"
                          style="font-size:0.65rem;
                                 background:<?= $aktif ? ($is_today ? '#0d6efd' : '#e2e8f0') : '#f1f5f9' ?>;
                                 color:<?= $aktif ? ($is_today ? '#fff' : '#334155') : '#94a3b8' ?>;
                                 font-weight:<?= $is_today ? '700' : '500' ?>;">
                        <?= $h ?>
                    </span>
                    <?php endforeach; ?>
                </div>

            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ===== TABEL RINGKAS ===== -->
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white border-bottom py-3">
        <h6 class="fw-bold mb-0 text-dark">
            <i class="fa-solid fa-table text-primary me-2"></i>Tabel Ringkas Jadwal
        </h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0 table-datatable">
                <thead class="table-light">
                    <tr>
                        <th>Nama Dokter</th>
                        <th>Spesialisasi</th>
                        <th class="text-center">Jam Praktek</th>
                        <th>Hari Praktek</th>
                        <th class="text-center">Kapasitas</th>
                        <th class="text-center">Antrian Hari Ini</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($daftar_dokter as $d):
                        $hari_list        = array_map('trim', explode(',', $d['hari_praktek'] ?? ''));
                        $praktik_hari_ini = in_array($hari_ini, $hari_list);
                        $jp               = explode(' - ', $d['jam_praktek'] ?? '');
                        $jam_mulai        = isset($jp[0]) ? date('H:i', strtotime(trim($jp[0]))) : '-';
                        $jam_selesai      = isset($jp[1]) ? date('H:i', strtotime(trim($jp[1]))) : '-';
                    ?>
                    <tr>
                        <td class="fw-semibold text-dark"><?= htmlspecialchars($d['nama_dokter']) ?></td>
                        <td class="text-muted small"><?= htmlspecialchars($d['spesialis'] ?: 'Dokter Umum') ?></td>
                        <td class="text-center small"><?= $jam_mulai ?> – <?= $jam_selesai ?></td>
                        <td>
                            <div class="d-flex flex-wrap gap-1">
                                <?php foreach ($hari_list as $h): ?>
                                <span class="badge rounded-pill <?= $h === $hari_ini ? 'bg-primary' : 'bg-light text-dark' ?>"
                                      style="font-size:0.65rem;">
                                    <?= htmlspecialchars($h) ?>
                                </span>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td class="text-center"><?= $d['kapasitas'] ?></td>
                        <td class="text-center fw-semibold <?= $d['antrian_hari_ini'] >= $d['kapasitas'] ? 'text-danger' : 'text-success' ?>">
                            <?= $d['antrian_hari_ini'] ?> / <?= $d['kapasitas'] ?>
                        </td>
                        <td class="text-center">
                            <?php if ($praktik_hari_ini): ?>
                                <span class="badge bg-success rounded-pill px-3">Praktik</span>
                            <?php else: ?>
                                <span class="badge bg-secondary rounded-pill px-3">Libur</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>