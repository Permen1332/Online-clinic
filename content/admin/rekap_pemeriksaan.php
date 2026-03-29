<?php
// Pastikan file ini tidak diakses langsung
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'admin') {
    exit('Akses Ditolak! Anda bukan admin.');
}

// --- FILTER ---
$filter_dokter = isset($_GET['id_dokter']) && $_GET['id_dokter'] !== '' ? (int)$_GET['id_dokter'] : '';
$filter_dari   = isset($_GET['dari'])    && $_GET['dari']    !== '' ? $_GET['dari']    : date('Y-m-01');
$filter_sampai = isset($_GET['sampai'])  && $_GET['sampai']  !== '' ? $_GET['sampai']  : date('Y-m-d');
$filter_cari   = isset($_GET['cari'])    ? htmlspecialchars(trim($_GET['cari'])) : '';

// --- QUERY REKAP ---
$sql = "
    SELECT
        p.id_pemeriksaan,
        pa.nama_pasien,
        d.nama_dokter,
        d.spesialis,
        a.tanggal_kunjungan,
        a.no_urut,
        a.keluhan_awal,
        p.hasil_diagnosis,
        p.catatan,
        a.status_kedatangan,
        GROUP_CONCAT(ob.nama_obat ORDER BY ob.nama_obat SEPARATOR ', ') AS daftar_obat,
        COUNT(r.id_resep) AS jumlah_obat
    FROM pemeriksaan p
    JOIN antrian a         ON p.id_antrian      = a.id_antrian
    JOIN pasien pa         ON a.id_pasien        = pa.id_pasien
    JOIN dokter d          ON a.id_dokter        = d.id_dokter
    LEFT JOIN resep_obat r ON r.id_pemeriksaan   = p.id_pemeriksaan
    LEFT JOIN obat ob      ON r.id_obat          = ob.id_obat
    WHERE a.tanggal_kunjungan BETWEEN :dari AND :sampai
";
$params = [':dari' => $filter_dari, ':sampai' => $filter_sampai];

if ($filter_dokter !== '') {
    $sql .= " AND d.id_dokter = :id_dokter";
    $params[':id_dokter'] = $filter_dokter;
}
if ($filter_cari !== '') {
    $sql .= " AND (pa.nama_pasien LIKE :cari OR p.hasil_diagnosis LIKE :cari2)";
    $params[':cari']  = "%{$filter_cari}%";
    $params[':cari2'] = "%{$filter_cari}%";
}
$sql .= " GROUP BY p.id_pemeriksaan ORDER BY a.tanggal_kunjungan DESC, a.no_urut ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data_rekap = $stmt->fetchAll();

// Statistik
$total_pemeriksaan     = count($data_rekap);
$total_obat_diresepkan = array_sum(array_column($data_rekap, 'jumlah_obat'));
$dokter_aktif          = count(array_unique(array_column($data_rekap, 'nama_dokter')));

// Dropdown dokter
$daftar_dokter = $pdo->query("SELECT id_dokter, nama_dokter, spesialis FROM dokter ORDER BY nama_dokter ASC")->fetchAll();

// URL export mengarah langsung ke file export_rekap.php (tidak melalui index.php)
$export_qs = 'export_rekap.php?' . http_build_query([
    'dari'      => $filter_dari,
    'sampai'    => $filter_sampai,
    'id_dokter' => $filter_dokter,
    'cari'      => $filter_cari,
]);
?>

<style>
@media print {
    .no-print   { display: none !important; }
    .print-only { display: block !important; }
    .card { box-shadow: none !important; border: 1px solid #ccc !important; border-radius: 0 !important; }
    body, table { font-size: 10px !important; }
    thead.table-dark { background-color: #333 !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .badge { border: 1px solid #999; background: transparent !important; color: #000 !important; }
}
</style>

<!-- HEADER HALAMAN -->
<div class="d-flex justify-content-between align-items-start mb-4 pb-3 border-bottom no-print">
    <div>
        <h4 class="fw-bold text-dark mb-1">Rekap Riwayat Pemeriksaan</h4>
        <p class="text-muted small mb-0">Tracking seluruh riwayat pemeriksaan pasien di klinik</p>
    </div>
    <div class="d-flex gap-2">
        <a href="content/resepsionis/<?= $export_qs ?>" class="btn btn-success rounded-pill px-4 fw-semibold shadow-sm">
            <i class="fa-solid fa-file-excel me-2"></i>Export Excel
        </a>
        <button onclick="cetakHalaman()" class="btn btn-secondary rounded-pill px-4 fw-semibold shadow-sm">
            <i class="fa-solid fa-print me-2"></i>Cetak
        </button>
    </div>
</div>

<!-- FORM FILTER -->
<div class="card border-0 rounded-4 shadow-sm mb-4 no-print">
    <div class="card-body p-4">
        <form method="GET" class="row g-3 align-items-end">
            <input type="hidden" name="f" value="resepsionis">
            <input type="hidden" name="m" value="rekap_pemeriksaan">
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Dari Tanggal</label>
                <input type="date" name="dari" class="form-control" value="<?= $filter_dari ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Sampai Tanggal</label>
                <input type="date" name="sampai" class="form-control" value="<?= $filter_sampai ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Filter Dokter</label>
                <select name="id_dokter" class="form-select">
                    <option value="">-- Semua Dokter --</option>
                    <?php foreach ($daftar_dokter as $d): ?>
                    <option value="<?= $d['id_dokter'] ?>" <?= $filter_dokter == $d['id_dokter'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['nama_dokter']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Cari Pasien / Diagnosis</label>
                <input type="text" name="cari" class="form-control"
                       placeholder="Nama pasien atau kata kunci diagnosis..."
                       value="<?= $filter_cari ?>">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary rounded-3 flex-fill fw-semibold">
                    <i class="fa-solid fa-magnifying-glass me-1"></i>Filter
                </button>
                <a href="?f=resepsionis&m=rekap_pemeriksaan" class="btn btn-outline-secondary rounded-3" title="Reset">
                    <i class="fa-solid fa-arrows-rotate"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- STATISTIK -->
<div class="row g-3 mb-4 no-print">
    <div class="col-md-4">
        <div class="card border-0 rounded-4 shadow-sm bg-primary text-white">
            <div class="card-body py-3 px-4 d-flex justify-content-between align-items-center">
                <div>
                    <div class="small opacity-75">Total Pemeriksaan</div>
                    <div class="fs-3 fw-bold"><?= $total_pemeriksaan ?></div>
                    <div class="small opacity-75"><?= date('d M', strtotime($filter_dari)) ?> – <?= date('d M Y', strtotime($filter_sampai)) ?></div>
                </div>
                <i class="fa-solid fa-stethoscope fa-2x opacity-50"></i>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 rounded-4 shadow-sm bg-success text-white">
            <div class="card-body py-3 px-4 d-flex justify-content-between align-items-center">
                <div>
                    <div class="small opacity-75">Total Item Obat Diresepkan</div>
                    <div class="fs-3 fw-bold"><?= $total_obat_diresepkan ?></div>
                    <div class="small opacity-75">dari seluruh pemeriksaan</div>
                </div>
                <i class="fa-solid fa-pills fa-2x opacity-50"></i>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 rounded-4 shadow-sm bg-info text-white">
            <div class="card-body py-3 px-4 d-flex justify-content-between align-items-center">
                <div>
                    <div class="small opacity-75">Dokter Bertugas</div>
                    <div class="fs-3 fw-bold"><?= $dokter_aktif ?></div>
                    <div class="small opacity-75">pada periode ini</div>
                </div>
                <i class="fa-solid fa-user-doctor fa-2x opacity-50"></i>
            </div>
        </div>
    </div>
</div>

<!-- HEADER PRINT (hanya tampil saat print) -->
<div class="print-only" style="display:none; margin-bottom:14px;">
    <h3 style="margin:0 0 3px; font-weight:bold;">Cipeng Clinic</h3>
    <h5 style="margin:0 0 3px;">Rekap Riwayat Pemeriksaan Pasien</h5>
    <p style="margin:0; font-size:11px; color:#444;">
        Periode: <strong><?= date('d M Y', strtotime($filter_dari)) ?> s/d <?= date('d M Y', strtotime($filter_sampai)) ?></strong>
        <?php if ($filter_dokter !== ''): ?>
        &nbsp;&bull;&nbsp; Dokter: <strong><?php
            foreach ($daftar_dokter as $dd) {
                if ($dd['id_dokter'] == $filter_dokter) echo htmlspecialchars($dd['nama_dokter']);
            }
        ?></strong>
        <?php endif; ?>
        &nbsp;&bull;&nbsp; Dicetak: <strong><?= date('d M Y H:i') ?></strong>
        &nbsp;&bull;&nbsp; Total: <strong><?= $total_pemeriksaan ?> pemeriksaan</strong>
    </p>
    <hr style="margin:8px 0; border-color:#555;">
</div>

<!-- TABEL REKAP -->
<div class="card border-0 rounded-4 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($data_rekap)): ?>
        <div class="text-center py-5 text-muted">
            <i class="fa-solid fa-folder-open fa-3x mb-3 d-block opacity-40"></i>
            <p class="mb-1">Tidak ada data pemeriksaan untuk filter yang dipilih.</p>
            <small>Coba ubah rentang tanggal atau hapus filter.</small>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-bordered align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th class="text-center" width="4%">No</th>
                        <th width="9%">Tgl Kunjungan</th>
                        <th width="5%" class="text-center">No<br>Urut</th>
                        <th width="15%">Nama Pasien</th>
                        <th width="15%">Dokter</th>
                        <th width="17%">Keluhan Awal</th>
                        <th width="18%">Hasil Diagnosis</th>
                        <th width="17%">Obat Diresepkan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($data_rekap as $row): ?>
                    <tr>
                        <td class="text-center text-muted small"><?= $no++ ?></td>
                        <td class="small"><?= date('d M Y', strtotime($row['tanggal_kunjungan'])) ?></td>
                        <td class="text-center">
                            <span class="badge bg-primary rounded-pill"><?= $row['no_urut'] ?></span>
                        </td>
                        <td class="fw-semibold small"><?= htmlspecialchars($row['nama_pasien']) ?></td>
                        <td>
                            <div class="fw-semibold small"><?= htmlspecialchars($row['nama_dokter']) ?></div>
                            <div class="text-muted" style="font-size:11px;"><?= htmlspecialchars($row['spesialis'] ?: 'Umum') ?></div>
                        </td>
                        <td class="small text-muted">
                            <?= $row['keluhan_awal'] ? htmlspecialchars($row['keluhan_awal']) : '<span class="fst-italic">-</span>' ?>
                        </td>
                        <td class="small">
                            <?php if ($row['hasil_diagnosis']): ?>
                                <?= htmlspecialchars($row['hasil_diagnosis']) ?>
                                <?php if ($row['catatan']): ?>
                                <div class="text-muted mt-1" style="font-size:11px;">
                                    <i class="fa-solid fa-note-sticky me-1"></i><?= htmlspecialchars($row['catatan']) ?>
                                </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Belum diperiksa</span>
                            <?php endif; ?>
                        </td>
                        <td class="small">
                            <?php if ($row['daftar_obat']): ?>
                                <span class="text-success">
                                    <i class="fa-solid fa-pills me-1"></i><?= htmlspecialchars($row['daftar_obat']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted fst-italic">Tidak ada resep</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr class="fw-semibold small">
                        <td colspan="3" class="text-end">Total:</td>
                        <td colspan="5">
                            <span class="me-3">
                                <i class="fa-solid fa-stethoscope me-1 text-primary"></i><?= $total_pemeriksaan ?> pemeriksaan
                            </span>
                            <span>
                                <i class="fa-solid fa-pills me-1 text-success"></i><?= $total_obat_diresepkan ?> item obat diresepkan
                            </span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function cetakHalaman() {
    document.querySelectorAll('.no-print').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.print-only').forEach(el => el.style.display = 'block');
    window.print();
    setTimeout(function () {
        document.querySelectorAll('.no-print').forEach(el => el.style.display = '');
        document.querySelectorAll('.print-only').forEach(el => el.style.display = 'none');
    }, 800);
}
</script>