<?php
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'apoteker') {
    exit('Akses Ditolak! Anda bukan apoteker.');
}

$pesan  = '';
$status = '';

// ============================================================
//  AKSI: SELESAIKAN RESEP
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selesaikan'])) {
    $id_pemeriksaan = (int) $_POST['id_pemeriksaan'];

    try {
        // Stok sudah dikurangi otomatis oleh trigger 'kurangi_stok_obat' saat INSERT.
        // Di sini hanya perlu update status_resep → 'selesai'.
        $stmtUpdate = $pdo->prepare("
            UPDATE resep_obat SET status_resep = 'selesai'
            WHERE id_pemeriksaan = ? AND status_resep = 'menunggu'
        ");
        $stmtUpdate->execute([$id_pemeriksaan]);

        if ($stmtUpdate->rowCount() === 0) {
            throw new Exception("Tidak ada resep yang perlu diproses.");
        }

        $status = 'success';
        $pesan  = 'Resep berhasil diselesaikan.';

    } catch (Exception $e) {
        $status = 'error';
        $pesan  = $e->getMessage();
    }
}

// ============================================================
//  AMBIL DATA RESEP — dikelompokkan per pemeriksaan
// ============================================================
$tab_aktif = $_GET['tab'] ?? 'menunggu';
$tab_aktif = in_array($tab_aktif, ['menunggu', 'selesai']) ? $tab_aktif : 'menunggu';

$stmtData = $pdo->prepare("
    SELECT 
        p.id_pemeriksaan,
        p.hasil_diagnosis,
        p.catatan,
        pa.nama_pasien,
        d.nama_dokter,
        d.spesialis,
        a.tanggal_kunjungan,
        a.no_urut,
        a.keluhan_awal,
        GROUP_CONCAT(
            ob.nama_obat, '|', r.dosis, '|', r.jumlah_obat_keluar, '|', ob.satuan, '|', ob.stock
            ORDER BY ob.nama_obat SEPARATOR ';;'
        ) AS detail_obat,
        COUNT(r.id_resep) AS jumlah_item
    FROM pemeriksaan p
    JOIN antrian a      ON p.id_antrian    = a.id_antrian
    JOIN pasien pa      ON a.id_pasien     = pa.id_pasien
    JOIN dokter d       ON a.id_dokter     = d.id_dokter
    JOIN resep_obat r   ON r.id_pemeriksaan = p.id_pemeriksaan
    JOIN obat ob        ON r.id_obat       = ob.id_obat
    WHERE r.status_resep = :status
    GROUP BY p.id_pemeriksaan
    ORDER BY a.tanggal_kunjungan DESC, a.no_urut ASC
");
$stmtData->execute([':status' => $tab_aktif]);
$daftar_resep = $stmtData->fetchAll();

// Hitung badge tab
$stmtCount = $pdo->query("
    SELECT status_resep, COUNT(DISTINCT id_pemeriksaan) as total
    FROM resep_obat GROUP BY status_resep
");
$tab_count = ['menunggu' => 0, 'selesai' => 0];
foreach ($stmtCount->fetchAll() as $row) {
    $tab_count[$row['status_resep']] = $row['total'];
}
?>

<!-- ===== HEADER ===== -->
<div class="d-flex justify-content-between align-items-start mb-4 pb-3 border-bottom flex-wrap gap-3">
    <div>
        <h4 class="fw-bold text-dark mb-1">
            <i class="fa-solid fa-prescription-bottle-medical text-success me-2"></i>Resep Masuk
        </h4>
        <p class="text-muted small mb-0">Daftar resep dari dokter yang perlu diproses oleh apoteker</p>
    </div>
</div>

<!-- ===== STAT CARDS ===== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 rounded-3 shadow-sm h-100" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
                    <i class="fa-solid fa-hourglass-half text-white fs-5"></i>
                </div>
                <div>
                    <div class="text-white-50 small">Menunggu</div>
                    <div class="text-white fw-bold fs-4 lh-1"><?= $tab_count['menunggu'] ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 rounded-3 shadow-sm h-100" style="background:linear-gradient(135deg,#198754,#157347);">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
                    <i class="fa-solid fa-circle-check text-white fs-5"></i>
                </div>
                <div>
                    <div class="text-white-50 small">Selesai</div>
                    <div class="text-white fw-bold fs-4 lh-1"><?= $tab_count['selesai'] ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 rounded-3 shadow-sm h-100" style="background:linear-gradient(135deg,#0d6efd,#0b5ed7);">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
                    <i class="fa-solid fa-file-prescription text-white fs-5"></i>
                </div>
                <div>
                    <div class="text-white-50 small">Total Resep</div>
                    <div class="text-white fw-bold fs-4 lh-1"><?= $tab_count['menunggu'] + $tab_count['selesai'] ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 rounded-3 shadow-sm h-100" style="background:linear-gradient(135deg,#6f42c1,#5a32a3);">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
                    <i class="fa-solid fa-calendar-day text-white fs-5"></i>
                </div>
                <div>
                    <div class="text-white-50 small">Hari Ini</div>
                    <?php
                    $stmtHariIni = $pdo->prepare("
                        SELECT COUNT(DISTINCT r.id_pemeriksaan) as total
                        FROM resep_obat r
                        JOIN pemeriksaan p ON r.id_pemeriksaan = p.id_pemeriksaan
                        JOIN antrian a ON p.id_antrian = a.id_antrian
                        WHERE a.tanggal_kunjungan = CURDATE()
                    ");
                    $stmtHariIni->execute();
                    $hari_ini_count = $stmtHariIni->fetchColumn();
                    ?>
                    <div class="text-white fw-bold fs-4 lh-1"><?= $hari_ini_count ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===== TAB ===== -->
<ul class="nav nav-tabs mb-4 border-bottom">
    <li class="nav-item">
        <a class="nav-link fw-semibold <?= $tab_aktif === 'menunggu' ? 'active' : 'text-muted' ?>"
           href="?f=apoteker&m=resep_masuk&tab=menunggu">
            <i class="fa-solid fa-hourglass-half me-2 text-warning"></i>Menunggu
            <?php if ($tab_count['menunggu'] > 0): ?>
            <span class="badge bg-warning text-dark ms-1 rounded-pill"><?= $tab_count['menunggu'] ?></span>
            <?php endif; ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link fw-semibold <?= $tab_aktif === 'selesai' ? 'active' : 'text-muted' ?>"
           href="?f=apoteker&m=resep_masuk&tab=selesai">
            <i class="fa-solid fa-circle-check me-2 text-success"></i>Selesai
            <span class="badge bg-success ms-1 rounded-pill"><?= $tab_count['selesai'] ?></span>
        </a>
    </li>
</ul>

<!-- ===== DAFTAR RESEP ===== -->
<?php if (empty($daftar_resep)): ?>
<div class="text-center py-5 text-muted">
    <i class="fa-solid fa-prescription-bottle fa-3x mb-3 d-block text-secondary opacity-40"></i>
    <p class="fw-semibold mb-1">
        <?= $tab_aktif === 'menunggu' ? 'Tidak ada resep yang menunggu' : 'Belum ada resep yang selesai' ?>
    </p>
    <small>Resep akan muncul di sini setelah dokter menyelesaikan pemeriksaan.</small>
</div>

<?php else: ?>
<div class="row g-3">
    <?php foreach ($daftar_resep as $resep):
        // Parse detail obat dari GROUP_CONCAT
        $obat_list = [];
        foreach (explode(';;', $resep['detail_obat']) as $item) {
            $parts = explode('|', $item);
            if (count($parts) === 5) {
                $obat_list[] = [
                    'nama'    => $parts[0],
                    'dosis'   => $parts[1],
                    'jumlah'  => (int)$parts[2],
                    'satuan'  => $parts[3],
                    'stock'   => (int)$parts[4],
                ];
            }
        }

    ?>
    <div class="col-12 col-xl-6">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <!-- Card Header -->
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-start py-3">
                <div>
                    <div class="fw-bold text-dark mb-1">
                        <i class="fa-solid fa-user me-1 text-primary"></i>
                        <?= htmlspecialchars($resep['nama_pasien']) ?>
                    </div>
                    <div class="text-muted small">
                        <i class="fa-solid fa-stethoscope me-1"></i>
                        <?= htmlspecialchars($resep['nama_dokter']) ?>
                        <span class="text-secondary"> — <?= htmlspecialchars($resep['spesialis'] ?: 'Umum') ?></span>
                    </div>
                    <div class="text-muted small mt-1">
                        <i class="fa-regular fa-calendar me-1"></i>
                        <?= date('d M Y', strtotime($resep['tanggal_kunjungan'])) ?>
                        <span class="badge bg-primary bg-opacity-10 text-primary ms-1">No. <?= $resep['no_urut'] ?></span>
                    </div>
                </div>
                <span class="badge rounded-pill px-3 py-2 <?= $tab_aktif === 'menunggu' ? 'bg-warning text-dark' : 'bg-success' ?>">
                    <?= $tab_aktif === 'menunggu' ? '<i class="fa-solid fa-hourglass-half me-1"></i>Menunggu' : '<i class="fa-solid fa-circle-check me-1"></i>Selesai' ?>
                </span>
            </div>

            <div class="card-body">
                <!-- Diagnosis & Keluhan -->
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <div class="bg-light rounded-2 p-2">
                            <div class="text-muted" style="font-size:0.7rem;">Keluhan Awal</div>
                            <div class="small fw-semibold text-dark">
                                <?= htmlspecialchars(mb_substr($resep['keluhan_awal'] ?? '-', 0, 60)) ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="bg-light rounded-2 p-2">
                            <div class="text-muted" style="font-size:0.7rem;">Diagnosis</div>
                            <div class="small fw-semibold text-dark">
                                <?= htmlspecialchars(mb_substr($resep['hasil_diagnosis'] ?? '-', 0, 60)) ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Daftar Obat -->
                <div class="mb-3">
                    <div class="text-muted small fw-semibold mb-2">
                        <i class="fa-solid fa-pills me-1 text-success"></i>
                        Daftar Obat (<?= $resep['jumlah_item'] ?> item)
                    </div>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($obat_list as $o): ?>
                        <div class="d-flex justify-content-between align-items-center p-2 rounded-2 bg-success bg-opacity-10">
                            <div>
                                <span class="fw-semibold small text-dark"><?= htmlspecialchars($o['nama']) ?></span>
                                <span class="text-muted small ms-2"><?= htmlspecialchars($o['dosis']) ?></span>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-success rounded-pill">
                                    <?= $o['jumlah'] ?> <?= htmlspecialchars($o['satuan']) ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if (!empty($resep['catatan'])): ?>
                <div class="alert alert-info py-2 small border-0 bg-info bg-opacity-10 mb-3">
                    <i class="fa-solid fa-note-medical me-1"></i>
                    <strong>Catatan dokter:</strong> <?= htmlspecialchars($resep['catatan']) ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Card Footer: Tombol Aksi -->
            <?php if ($tab_aktif === 'menunggu'): ?>
            <div class="card-footer bg-white border-top pt-3">
                <form method="POST">
                    <input type="hidden" name="id_pemeriksaan" value="<?= $resep['id_pemeriksaan'] ?>">
                    <input type="hidden" name="selesaikan" value="1"> 
                    <button type="submit" name="selesaikan"
                            class="btn btn-success w-100 rounded-pill fw-semibold btn-selesaikan">
                        <i class="fa-solid fa-circle-check me-2"></i>Selesaikan Resep
                    </button>
                </form>
            </div>
            <?php endif; ?>

        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- SweetAlert feedback -->
<?php if ($pesan): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        title  : '<?= $status === "success" ? "Berhasil!" : "Gagal!" ?>',
        text   : '<?= addslashes($pesan) ?>',
        icon   : '<?= $status ?>',
        confirmButtonColor: '<?= $status === "success" ? "#198754" : "#dc3545" ?>',
        timer  : <?= $status === 'success' ? 2000 : 0 ?>,
        showConfirmButton: <?= $status === 'success' ? 'false' : 'true' ?>
    });
});
</script>
<?php endif; ?>

<!-- Konfirmasi sebelum selesaikan -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-selesaikan').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const form = this.closest('form');
            Swal.fire({
                title             : 'Selesaikan Resep?',
                html              : 'Stok obat akan dikurangi secara otomatis.<br>Pastikan obat sudah disiapkan.',
                icon              : 'question',
                showCancelButton  : true,
                confirmButtonColor: '#198754',
                cancelButtonColor : '#6c757d',
                confirmButtonText : '<i class="fa-solid fa-circle-check me-1"></i>Ya, Selesaikan',
                cancelButtonText  : 'Batal'
            }).then(function(result) {
                if (result.isConfirmed) form.submit();
            });
        });
    });
});
</script>