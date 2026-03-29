<?php
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'resepsionis') {
    exit('Akses Ditolak! Anda bukan resepsionis.');
}

// --- FILTER TANGGAL (default: hari ini) ---
$filter_tgl = isset($_GET['tgl']) && !empty($_GET['tgl']) ? $_GET['tgl'] : date('Y-m-d');

// --- AMBIL DATA ANTRIAN BERDASARKAN FILTER ---
$stmt = $pdo->prepare("
    SELECT a.*, p.nama_pasien, d.nama_dokter, d.spesialis
    FROM antrian a
    JOIN pasien p ON a.id_pasien = p.id_pasien
    JOIN dokter d ON a.id_dokter = d.id_dokter
    WHERE a.tanggal_kunjungan = :tgl
    ORDER BY a.no_urut ASC
");
$stmt->execute([':tgl' => $filter_tgl]);
$data_antrian = $stmt->fetchAll();

// --- STATISTIK RINGKAS UNTUK HARI YANG DIPILIH ---
$total       = count($data_antrian);
$sudah_datang = count(array_filter($data_antrian, fn($r) => $r['status_kedatangan'] === 'datang'));
$belum_datang = count(array_filter($data_antrian, fn($r) => $r['status_kedatangan'] === 'belum datang'));
$dibatalkan   = count(array_filter($data_antrian, fn($r) => $r['status_kedatangan'] === 'batal'));

$badge_status = [
    'belum datang' => '<span class="badge bg-warning text-dark rounded-pill px-3"><i class="fa-solid fa-clock me-1"></i>Belum Datang</span>',
    'datang'       => '<span class="badge bg-success rounded-pill px-3"><i class="fa-solid fa-circle-check me-1"></i>Sudah Datang</span>',
    'batal'        => '<span class="badge bg-danger rounded-pill px-3"><i class="fa-solid fa-ban me-1"></i>Dibatalkan</span>',
];

$is_today = ($filter_tgl === date('Y-m-d'));
?>

<!-- ===== HEADER ===== -->
<div class="d-flex justify-content-between align-items-start mb-4 pb-3 border-bottom flex-wrap gap-3">
    <div>
        <h4 class="fw-bold text-dark mb-1">
            <i class="fa-solid fa-list-ol text-primary me-2"></i>Data Antrian
        </h4>
        <p class="text-muted small mb-0">
            <?= $is_today ? 'Antrian hari ini — ' : 'Antrian tanggal — ' ?>
            <strong><?= date('d F Y', strtotime($filter_tgl)) ?></strong>
        </p>
    </div>
    <a href="?f=resepsionis&m=buat_antrian" class="btn btn-primary shadow-sm rounded-pill px-4 py-2 fw-semibold">
        <i class="fa-solid fa-user-plus me-2"></i>Daftar Walk-in
    </a>
</div>

<!-- ===== STAT CARDS ===== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 rounded-3 shadow-sm h-100" style="background: linear-gradient(135deg,#0d6efd,#0b5ed7);">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
                    <i class="fa-solid fa-calendar-day text-white fs-5"></i>
                </div>
                <div>
                    <div class="text-white-50 small">Total Antrian</div>
                    <div class="text-white fw-bold fs-4 lh-1"><?= $total ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 rounded-3 shadow-sm h-100" style="background: linear-gradient(135deg,#198754,#157347);">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
                    <i class="fa-solid fa-circle-check text-white fs-5"></i>
                </div>
                <div>
                    <div class="text-white-50 small">Sudah Datang</div>
                    <div class="text-white fw-bold fs-4 lh-1"><?= $sudah_datang ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 rounded-3 shadow-sm h-100" style="background: linear-gradient(135deg,#ffc107,#e0a800);">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
                    <i class="fa-solid fa-clock text-white fs-5"></i>
                </div>
                <div>
                    <div class="text-dark text-opacity-75 small">Belum Datang</div>
                    <div class="text-dark fw-bold fs-4 lh-1"><?= $belum_datang ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 rounded-3 shadow-sm h-100" style="background: linear-gradient(135deg,#dc3545,#b02a37);">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
                    <i class="fa-solid fa-ban text-white fs-5"></i>
                </div>
                <div>
                    <div class="text-white-50 small">Dibatalkan</div>
                    <div class="text-white fw-bold fs-4 lh-1"><?= $dibatalkan ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===== FILTER TANGGAL ===== -->
<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-body py-3">
        <form method="GET" class="d-flex align-items-center gap-2 flex-wrap">
            <input type="hidden" name="f" value="resepsionis">
            <input type="hidden" name="m" value="data_antrian">
            <label class="form-label small fw-semibold text-muted mb-0 me-1">
                <i class="fa-solid fa-calendar-days me-1"></i>Tampilkan Tanggal:
            </label>
            <input type="date" name="tgl" value="<?= $filter_tgl ?>"
                   class="form-control form-control-sm rounded-pill" style="width:180px;">
            <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3">
                <i class="fa-solid fa-filter me-1"></i>Tampilkan
            </button>
            <?php if (!$is_today): ?>
            <a href="?f=resepsionis&m=data_antrian" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                <i class="fa-solid fa-rotate-left me-1"></i>Hari Ini
            </a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- ===== TABEL ANTRIAN ===== -->
<div class="table-responsive">
    <table class="table table-hover table-striped table-bordered align-middle table-datatable" style="width:100%">
        <thead class="table-light">
            <tr>
                <th class="text-center">No</th>
                <th>Nama Pasien</th>
                <th>Dokter Tujuan</th>
                <th class="text-center">No. Urut</th>
                <th>Keluhan Awal</th>
                <th class="text-center">Status</th>
                <th class="text-center">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($data_antrian)): ?>
            <tr>
                <td colspan="7" class="text-center py-5 text-muted">
                    <i class="fa-solid fa-calendar-xmark fa-3x mb-3 d-block text-secondary opacity-50"></i>
                    <span class="fw-semibold">Tidak ada antrian</span><br>
                    <small>pada tanggal <?= date('d F Y', strtotime($filter_tgl)) ?></small>
                </td>
            </tr>
            <?php else: ?>
            <?php $no = 1; foreach ($data_antrian as $row): ?>
            <tr>
                <td class="text-center text-muted small"><?= $no++ ?></td>
                <td>
                    <span class="fw-semibold text-dark"><?= htmlspecialchars($row['nama_pasien']) ?></span>
                </td>
                <td>
                    <span class="fw-semibold text-primary"><?= htmlspecialchars($row['nama_dokter']) ?></span><br>
                    <small class="text-muted"><?= htmlspecialchars($row['spesialis'] ?: 'Dokter Umum') ?></small>
                </td>
                <td class="text-center">
                    <span class="badge bg-primary rounded-circle d-inline-flex align-items-center justify-content-center fw-bold"
                          style="width:36px;height:36px;font-size:1rem;">
                        <?= $row['no_urut'] ?>
                    </span>
                </td>
                <td class="small text-muted">
                    <?php
                    $keluhan = $row['keluhan_awal'] ?: '-';
                    echo htmlspecialchars(mb_substr($keluhan, 0, 60)) . (mb_strlen($keluhan) > 60 ? '…' : '');
                    ?>
                </td>
                <td class="text-center">
                    <?= $badge_status[$row['status_kedatangan']] ?? '<span class="badge bg-secondary">-</span>' ?>
                </td>
                <td class="text-center">
                    <?php if ($row['status_kedatangan'] === 'belum datang' && $is_today): ?>
                    <a href="?f=resepsionis&m=validasi" 
                       class="btn btn-sm btn-outline-success rounded-pill px-3"
                       title="Pergi ke halaman validasi">
                        <i class="fa-solid fa-clipboard-check me-1"></i>Validasi
                    </a>
                    <?php else: ?>
                    <span class="text-muted small">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>