<?php
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'apoteker') {
    exit('Akses Ditolak! Anda bukan apoteker.');
}

// --- FILTER ---
$filter_dari   = $_GET['dari']   ?? date('Y-m-01');        // default: awal bulan ini
$filter_sampai = $_GET['sampai'] ?? date('Y-m-d');         // default: hari ini
$filter_obat   = $_GET['id_obat'] ?? '';

// --- AMBIL DATA RIWAYAT ---
$sql = "
    SELECT
        r.id_resep,
        r.jumlah_obat_keluar,
        r.dosis,
        o.nama_obat,
        o.satuan,
        o.stock AS stok_sekarang,
        pa.nama_pasien,
        d.nama_dokter,
        a.tanggal_kunjungan,
        a.no_urut,
        p.hasil_diagnosis
    FROM resep_obat r
    JOIN obat ob         ON r.id_obat        = ob.id_obat
    JOIN obat o          ON r.id_obat        = o.id_obat
    JOIN pemeriksaan p   ON r.id_pemeriksaan = p.id_pemeriksaan
    JOIN antrian a       ON p.id_antrian     = a.id_antrian
    JOIN pasien pa       ON a.id_pasien      = pa.id_pasien
    JOIN dokter d        ON a.id_dokter      = d.id_dokter
    WHERE r.status_resep = 'selesai'
      AND a.tanggal_kunjungan BETWEEN :dari AND :sampai
";
$params = [':dari' => $filter_dari, ':sampai' => $filter_sampai];

if ($filter_obat !== '') {
    $sql .= " AND r.id_obat = :id_obat";
    $params[':id_obat'] = (int) $filter_obat;
}
$sql .= " ORDER BY a.tanggal_kunjungan DESC, a.no_urut ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$riwayat = $stmt->fetchAll();

// --- STATISTIK ---
$total_item      = count($riwayat);
$total_keluar    = array_sum(array_column($riwayat, 'jumlah_obat_keluar'));
$obat_unik       = count(array_unique(array_column($riwayat, 'nama_obat')));
$pasien_unik     = count(array_unique(array_column($riwayat, 'nama_pasien')));

// --- DAFTAR OBAT untuk filter dropdown ---
$daftar_obat = $pdo->query("SELECT id_obat, nama_obat FROM obat ORDER BY nama_obat ASC")->fetchAll();

// --- OBAT TERBANYAK KELUAR (top 5) ---
$stmtTop = $pdo->prepare("
    SELECT o.nama_obat, o.satuan, SUM(r.jumlah_obat_keluar) AS total_keluar
    FROM resep_obat r
    JOIN obat o ON r.id_obat = o.id_obat
    WHERE r.status_resep = 'selesai'
      AND EXISTS (
          SELECT 1 FROM pemeriksaan p
          JOIN antrian a ON p.id_antrian = a.id_antrian
          WHERE p.id_pemeriksaan = r.id_pemeriksaan
            AND a.tanggal_kunjungan BETWEEN :dari AND :sampai
      )
    GROUP BY r.id_obat
    ORDER BY total_keluar DESC
    LIMIT 5
");
$stmtTop->execute([':dari' => $filter_dari, ':sampai' => $filter_sampai]);
$top_obat = $stmtTop->fetchAll();
?>

<!-- ===== HEADER ===== -->
<div class="d-flex justify-content-between align-items-start mb-4 pb-3 border-bottom flex-wrap gap-3">
    <div>
        <h4 class="fw-bold text-dark mb-1">
            <i class="fa-solid fa-clock-rotate-left text-success me-2"></i>Riwayat Pengeluaran Obat
        </h4>
        <p class="text-muted small mb-0">
            Rekap obat yang telah dikeluarkan —
            <strong><?= date('d M Y', strtotime($filter_dari)) ?></strong>
            s/d
            <strong><?= date('d M Y', strtotime($filter_sampai)) ?></strong>
        </p>
    </div>
</div>

<!-- ===== STAT CARDS ===== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 rounded-3 shadow-sm h-100"
             style="background:linear-gradient(135deg,#0d6efd,#0b5ed7);">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center"
                     style="width:44px;height:44px;">
                    <i class="fa-solid fa-receipt text-white fs-5"></i>
                </div>
                <div>
                    <div class="text-white-50 small">Total Transaksi</div>
                    <div class="text-white fw-bold fs-4 lh-1"><?= $total_item ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 rounded-3 shadow-sm h-100"
             style="background:linear-gradient(135deg,#198754,#157347);">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center"
                     style="width:44px;height:44px;">
                    <i class="fa-solid fa-pills text-white fs-5"></i>
                </div>
                <div>
                    <div class="text-white-50 small">Total Unit Keluar</div>
                    <div class="text-white fw-bold fs-4 lh-1"><?= $total_keluar ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 rounded-3 shadow-sm h-100"
             style="background:linear-gradient(135deg,#6f42c1,#5a32a3);">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center"
                     style="width:44px;height:44px;">
                    <i class="fa-solid fa-boxes-stacked text-white fs-5"></i>
                </div>
                <div>
                    <div class="text-white-50 small">Jenis Obat</div>
                    <div class="text-white fw-bold fs-4 lh-1"><?= $obat_unik ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 rounded-3 shadow-sm h-100"
             style="background:linear-gradient(135deg,#0dcaf0,#0aa2c0);">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center"
                     style="width:44px;height:44px;">
                    <i class="fa-solid fa-users text-white fs-5"></i>
                </div>
                <div>
                    <div class="text-white-50 small">Jumlah Pasien</div>
                    <div class="text-white fw-bold fs-4 lh-1"><?= $pasien_unik ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- ===== KOLOM KIRI: FILTER + TABEL ===== -->
    <div class="col-12 col-xl-8">

        <!-- Filter -->
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-body py-3">
                <form method="GET" class="row g-2 align-items-end">
                    <input type="hidden" name="f" value="apoteker">
                    <input type="hidden" name="m" value="riwayat_obat">

                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-semibold text-muted mb-1">Dari Tanggal</label>
                        <input type="date" name="dari" value="<?= $filter_dari ?>"
                               class="form-control form-control-sm rounded-pill">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-semibold text-muted mb-1">Sampai Tanggal</label>
                        <input type="date" name="sampai" value="<?= $filter_sampai ?>"
                               class="form-control form-control-sm rounded-pill">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-semibold text-muted mb-1">Filter Obat</label>
                        <select name="id_obat" class="form-select form-select-sm rounded-pill">
                            <option value="">-- Semua Obat --</option>
                            <?php foreach ($daftar_obat as $o): ?>
                            <option value="<?= $o['id_obat'] ?>"
                                <?= $filter_obat == $o['id_obat'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($o['nama_obat']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 d-flex gap-2 mt-1">
                        <button type="submit" class="btn btn-success btn-sm rounded-pill px-4">
                            <i class="fa-solid fa-filter me-1"></i>Terapkan Filter
                        </button>
                        <a href="?f=apoteker&m=riwayat_obat" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                            <i class="fa-solid fa-rotate-left me-1"></i>Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabel Riwayat -->
        <div class="table-responsive">
            <table class="table table-hover table-striped table-bordered align-middle table-datatable" style="width:100%">
                <thead class="table-light">
                    <tr>
                        <th class="text-center">No</th>
                        <th>Nama Obat</th>
                        <th>Nama Pasien</th>
                        <th>Dokter</th>
                        <th class="text-center">Tgl. Kunjungan</th>
                        <th class="text-center">Jml Keluar</th>
                        <th>Dosis</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($riwayat)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-box-open fa-3x mb-3 d-block opacity-30"></i>
                            <span class="fw-semibold">Tidak ada riwayat pengeluaran</span><br>
                            <small>pada periode yang dipilih</small>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php $no = 1; foreach ($riwayat as $row): ?>
                    <tr>
                        <td class="text-center text-muted small"><?= $no++ ?></td>
                        <td>
                            <span class="fw-semibold text-dark"><?= htmlspecialchars($row['nama_obat']) ?></span><br>
                            <small class="text-muted"><?= htmlspecialchars($row['satuan']) ?></small>
                        </td>
                        <td class="fw-semibold small"><?= htmlspecialchars($row['nama_pasien']) ?></td>
                        <td class="small text-muted"><?= htmlspecialchars($row['nama_dokter']) ?></td>
                        <td class="text-center small">
                            <?= date('d M Y', strtotime($row['tanggal_kunjungan'])) ?>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-success rounded-pill px-3 fw-semibold">
                                <?= $row['jumlah_obat_keluar'] ?> <?= htmlspecialchars($row['satuan']) ?>
                            </span>
                        </td>
                        <td class="small text-muted"><?= htmlspecialchars($row['dosis'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ===== KOLOM KANAN: TOP 5 OBAT ===== -->
    <div class="col-12 col-xl-4">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-ranking-star text-warning me-2"></i>
                    Top 5 Obat Terbanyak Keluar
                </h6>
                <small class="text-muted">Periode yang dipilih</small>
            </div>
            <div class="card-body">
                <?php if (empty($top_obat)): ?>
                <div class="text-center text-muted py-4">
                    <i class="fa-solid fa-chart-bar fa-2x mb-2 d-block opacity-30"></i>
                    <small>Belum ada data</small>
                </div>
                <?php else:
                    $max_keluar = $top_obat[0]['total_keluar'];
                    $colors = ['#0d6efd','#198754','#6f42c1','#fd7e14','#0dcaf0'];
                ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($top_obat as $i => $item):
                        $persen = $max_keluar > 0 ? round($item['total_keluar'] / $max_keluar * 100) : 0;
                    ?>
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge rounded-circle d-flex align-items-center justify-content-center fw-bold"
                                      style="width:24px;height:24px;font-size:0.7rem;background:<?= $colors[$i] ?>;">
                                    <?= $i + 1 ?>
                                </span>
                                <span class="small fw-semibold text-dark">
                                    <?= htmlspecialchars($item['nama_obat']) ?>
                                </span>
                            </div>
                            <span class="small fw-bold" style="color:<?= $colors[$i] ?>;">
                                <?= $item['total_keluar'] ?> <?= htmlspecialchars($item['satuan']) ?>
                            </span>
                        </div>
                        <div class="progress rounded-pill" style="height:6px;">
                            <div class="progress-bar rounded-pill"
                                 style="width:<?= $persen ?>%;background:<?= $colors[$i] ?>;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Ringkasan stok saat ini -->
            <div class="card-footer bg-light border-top">
                <div class="small text-muted fw-semibold mb-2">
                    <i class="fa-solid fa-boxes-stacked me-1"></i>Stok Saat Ini (Top 5)
                </div>
                <?php if (!empty($top_obat)):
                    $nama_list = array_map(fn($o) => $pdo->quote($o['nama_obat']), $top_obat);
                    $stok_list = $pdo->query(
                        "SELECT nama_obat, stock FROM obat WHERE nama_obat IN (" . implode(',', $nama_list) . ")"
                    )->fetchAll();
                ?>
                <div class="d-flex flex-column gap-1">
                    <?php foreach ($stok_list as $s):
                        $warn = $s['stock'] == 0 ? 'danger' : ($s['stock'] <= 10 ? 'warning' : 'success');
                    ?>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small text-muted"><?= htmlspecialchars($s['nama_obat']) ?></span>
                        <span class="badge bg-<?= $warn ?> rounded-pill"><?= $s['stock'] ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>