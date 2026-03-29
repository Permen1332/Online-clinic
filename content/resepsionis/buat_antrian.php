<?php
// Pastikan file ini tidak diakses langsung
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'resepsionis') {
    exit('Akses Ditolak! Anda bukan resepsionis.');
}

$pesan = '';
$status = '';

// --- LOGIKA SIMPAN ANTRIAN WALK-IN ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['buat_antrian'])) {
    $id_pasien  = (int)$_POST['id_pasien'];
    $id_dokter  = (int)$_POST['id_dokter'];
    $tgl_kunjungan = $_POST['tanggal_kunjungan'];
    $keluhan    = htmlspecialchars(trim($_POST['keluhan_awal']));

    try {
        // Cek apakah pasien sudah mendaftar ke dokter yang sama pada tanggal yang sama
        $cek = $pdo->prepare("SELECT id_antrian FROM antrian WHERE id_pasien = ? AND id_dokter = ? AND tanggal_kunjungan = ? AND status_kedatangan != 'batal'");
        $cek->execute([$id_pasien, $id_dokter, $tgl_kunjungan]);
        if ($cek->rowCount() > 0) {
            $status = 'error'; $pesan = 'Pasien ini sudah memiliki antrian aktif ke dokter yang sama pada tanggal tersebut!';
        } else {
            // --- Cek hari praktek dokter ---
            $info_dokter = $pdo->prepare("SELECT kapasitas, hari_praktek, nama_dokter FROM dokter WHERE id_dokter = ?");
            $info_dokter->execute([$id_dokter]);
            $dokter_data = $info_dokter->fetch();

            $hari_indo      = ['Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu',
                               'Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu','Sunday'=>'Minggu'];
            $hari_kunjungan = $hari_indo[date('l', strtotime($tgl_kunjungan))];
            $hari_praktek   = !empty($dokter_data['hari_praktek'])
                               ? array_map('trim', explode(',', $dokter_data['hari_praktek'])) : [];

            if (!empty($hari_praktek) && !in_array($hari_kunjungan, $hari_praktek)) {
                $status = 'error';
                $pesan  = "Dr. <b>" . htmlspecialchars($dokter_data['nama_dokter']) . "</b> tidak berpraktek pada hari <b>{$hari_kunjungan}</b>. Hari praktek: <b>" . implode(', ', $hari_praktek) . "</b>.";
            } else {
                // --- Cek kapasitas ---
                $cek_kap = $pdo->prepare("
                    SELECT d.kapasitas, COUNT(a.id_antrian) AS total_antrian
                    FROM dokter d
                    LEFT JOIN antrian a ON a.id_dokter = d.id_dokter
                        AND a.tanggal_kunjungan = ?
                        AND a.status_kedatangan != 'batal'
                    WHERE d.id_dokter = ?
                    GROUP BY d.id_dokter, d.kapasitas
                ");
                $cek_kap->execute([$tgl_kunjungan, $id_dokter]);
                $info_kap = $cek_kap->fetch();
                $kapasitas = (int)($info_kap['kapasitas'] ?? 10);
                $total     = (int)($info_kap['total_antrian'] ?? 0);

                if ($total >= $kapasitas) {
                    $tgl_fmt = date('d M Y', strtotime($tgl_kunjungan));
                    $status  = 'error';
                    $pesan   = "Kuota pasien untuk tanggal <b>{$tgl_fmt}</b> sudah penuh (maks. {$kapasitas} pasien). Silakan pilih tanggal lain.";
                } else {
                    // --- Ambil nomor urut & simpan ---
                    $no_stmt = $pdo->prepare("SELECT COALESCE(MAX(no_urut), 0) + 1 FROM antrian WHERE id_dokter = ? AND tanggal_kunjungan = ?");
                    $no_stmt->execute([$id_dokter, $tgl_kunjungan]);
                    $no_urut = $no_stmt->fetchColumn();

                    $stmt = $pdo->prepare("INSERT INTO antrian (id_pasien, id_dokter, no_urut, tanggal_kunjungan, keluhan_awal, status_kedatangan) VALUES (?, ?, ?, ?, ?, 'datang')");
                    $stmt->execute([$id_pasien, $id_dokter, $no_urut, $tgl_kunjungan, $keluhan]);
                    $sisa   = $kapasitas - $total - 1;
                    $status = 'success';
                    $pesan  = "Antrian walk-in berhasil dibuat! Nomor urut: <b>{$no_urut}</b>. Sisa slot hari ini: <b>{$sisa}</b>.";
                }
            }
        }
    } catch (PDOException $e) {
        $status = 'error'; $pesan = 'Gagal membuat antrian: ' . $e->getMessage();
    }
}

// Ambil data dokter dan pasien untuk dropdown
$daftar_dokter = $pdo->query("SELECT * FROM dokter ORDER BY nama_dokter ASC")->fetchAll();
$daftar_pasien = $pdo->query("SELECT p.id_pasien, p.nama_pasien, u.username FROM pasien p JOIN users u ON p.id_user = u.id_user ORDER BY p.nama_pasien ASC")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
    <div>
        <h4 class="fw-bold text-dark mb-1">Daftarkan Pasien Walk-in</h4>
        <p class="text-muted small mb-0">Buat antrian langsung untuk pasien yang datang tanpa daftar online</p>
    </div>
    <a href="?f=resepsionis&m=data_antrian" class="btn btn-outline-secondary rounded-pill px-4">
        <i class="fa-solid fa-list me-2"></i>Lihat Semua Antrian
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-header bg-primary text-white rounded-top-4 py-3 px-4">
                <h5 class="fw-bold mb-0"><i class="fa-solid fa-user-plus me-2"></i>Form Pendaftaran Walk-in</h5>
            </div>
            <div class="card-body p-4">
                <form action="?f=resepsionis&m=buat_antrian" method="POST">
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Pilih Pasien <span class="text-danger">*</span></label>
                        <select name="id_pasien" class="form-select form-select-lg" required>
                            <option value="">-- Cari & Pilih Pasien --</option>
                            <?php foreach ($daftar_pasien as $p): ?>
                            <option value="<?= $p['id_pasien'] ?>"><?= htmlspecialchars($p['nama_pasien']) ?> (<?= htmlspecialchars($p['username']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text"><i class="fa-solid fa-circle-info me-1"></i>Jika pasien belum terdaftar, minta pasien untuk registrasi terlebih dahulu.</div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-7">
                            <label class="form-label fw-semibold">Pilih Dokter <span class="text-danger">*</span></label>
                            <select name="id_dokter" class="form-select" required>
                                <option value="">-- Pilih Dokter --</option>
                                <?php foreach ($daftar_dokter as $d):
                                    $hari_p = !empty($d['hari_praktek']) ? $d['hari_praktek'] : 'Semua hari';
                                ?>
                                <option value="<?= $d['id_dokter'] ?>"
                                        data-hari="<?= htmlspecialchars($d['hari_praktek'] ?? '') ?>">
                                    <?= htmlspecialchars($d['nama_dokter']) ?> - <?= htmlspecialchars($d['spesialis'] ?: 'Umum') ?> | Praktek: <?= htmlspecialchars($hari_p) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Tanggal Kunjungan <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal_kunjungan" class="form-control" required value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Keluhan Awal</label>
                        <textarea name="keluhan_awal" class="form-control" rows="3" placeholder="Deskripsikan keluhan yang disampaikan pasien..."></textarea>
                    </div>

                    <div class="alert alert-info border-0 rounded-3 py-2 small">
                        <i class="fa-solid fa-circle-info me-1"></i> Antrian walk-in akan langsung berstatus <b>"Sudah Datang"</b> secara otomatis.
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-3">
                        <a href="?f=resepsionis&m=data_antrian" class="btn btn-secondary rounded-pill px-4">Batal</a>
                        <button type="submit" name="buat_antrian" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm">
                            <i class="fa-solid fa-ticket me-2"></i>Buat Antrian
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if($pesan != ''): ?>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        Swal.fire({
            title: "<?= $status == 'success' ? 'Antrian Dibuat!' : 'Gagal!' ?>",
            html: "<?= $pesan ?>",
            icon: "<?= $status ?>",
            confirmButtonColor: "#0d6efd"
        }).then(() => {
            <?php if($status == 'success'): ?>
            window.location.href = "?f=resepsionis&m=validasi";
            <?php else: ?>
            window.location.href = "?f=resepsionis&m=buat_antrian";
            <?php endif; ?>
        });
    });
</script>
<?php endif; ?>