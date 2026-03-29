<?php
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'pasien') {
    exit('Akses Ditolak! Anda bukan pasien.');
}

$pesan = ''; $status = '';

// Ambil id_pasien
$stmt_p = $pdo->prepare("SELECT id_pasien FROM pasien WHERE id_user = ?");
$stmt_p->execute([$_SESSION['id_user']]);
$profil_pasien = $stmt_p->fetch();
if (!$profil_pasien) {
    echo "<div class='alert alert-danger'>Profil pasien tidak ditemukan. Hubungi resepsionis.</div>";
    return;
}
$id_pasien = $profil_pasien['id_pasien'];

// ============================================================
//  BATALKAN ANTRIAN
// ============================================================
if (isset($_GET['act']) && $_GET['act'] === 'batal' && isset($_GET['id'])) {
    $id_antrian = (int) $_GET['id'];
    try {
        // Pastikan antrian milik pasien ini & masih bisa dibatal
        $cek = $pdo->prepare("SELECT id_antrian FROM antrian WHERE id_antrian = ? AND id_pasien = ? AND status_kedatangan = 'belum datang'");
        $cek->execute([$id_antrian, $id_pasien]);
        if ($cek->rowCount() > 0) {
            $pdo->prepare("UPDATE antrian SET status_kedatangan = 'batal' WHERE id_antrian = ?")
                ->execute([$id_antrian]);
            $status = 'success';
            $pesan  = 'Antrian berhasil dibatalkan.';
        } else {
            $status = 'error';
            $pesan  = 'Antrian tidak ditemukan atau sudah tidak bisa dibatalkan.';
        }
    } catch (PDOException $e) {
        $status = 'error';
        $pesan  = 'Gagal membatalkan antrian.';
    }
}

// ============================================================
//  BUAT ANTRIAN BARU
// ============================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['buat_antrian'])) {
    $id_dokter     = (int)$_POST['id_dokter'];
    $tgl_kunjungan = $_POST['tanggal_kunjungan'];
    $keluhan       = htmlspecialchars(trim($_POST['keluhan_awal']));

    try {
        $cek = $pdo->prepare("SELECT id_antrian FROM antrian WHERE id_pasien = ? AND id_dokter = ? AND tanggal_kunjungan = ? AND status_kedatangan != 'batal'");
        $cek->execute([$id_pasien, $id_dokter, $tgl_kunjungan]);
        if ($cek->rowCount() > 0) {
            $status = 'error';
            $pesan  = 'Anda sudah memiliki antrian aktif ke dokter ini pada tanggal yang sama!';
        } else {
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
                $pesan  = "Maaf, <b>" . htmlspecialchars($dokter_data['nama_dokter']) . "</b> tidak berpraktek pada hari <b>{$hari_kunjungan}</b>. Hari praktek: <b>" . implode(', ', $hari_praktek) . "</b>. Silakan pilih tanggal lain.";
            } else {
                $cek_kap = $pdo->prepare("
                    SELECT d.kapasitas, COUNT(a.id_antrian) AS total_antrian
                    FROM dokter d
                    LEFT JOIN antrian a ON a.id_dokter = d.id_dokter
                        AND a.tanggal_kunjungan = ? AND a.status_kedatangan != 'batal'
                    WHERE d.id_dokter = ?
                    GROUP BY d.id_dokter, d.kapasitas
                ");
                $cek_kap->execute([$tgl_kunjungan, $id_dokter]);
                $info_kap  = $cek_kap->fetch();
                $kapasitas = (int)($info_kap['kapasitas'] ?? 10);
                $total     = (int)($info_kap['total_antrian'] ?? 0);

                if ($total >= $kapasitas) {
                    $tgl_fmt = date('d M Y', strtotime($tgl_kunjungan));
                    $status  = 'error';
                    $pesan   = "Maaf, antrian untuk tanggal <b>{$tgl_fmt}</b> sudah penuh (maks. {$kapasitas} pasien). Pilih tanggal lain.";
                } else {
                    $no_stmt = $pdo->prepare("SELECT COALESCE(MAX(no_urut), 0) + 1 FROM antrian WHERE id_dokter = ? AND tanggal_kunjungan = ?");
                    $no_stmt->execute([$id_dokter, $tgl_kunjungan]);
                    $no_urut = $no_stmt->fetchColumn();

                    $pdo->prepare("INSERT INTO antrian (id_pasien, id_dokter, no_urut, tanggal_kunjungan, keluhan_awal, status_kedatangan) VALUES (?, ?, ?, ?, ?, 'belum datang')")
                        ->execute([$id_pasien, $id_dokter, $no_urut, $tgl_kunjungan, $keluhan]);
                    $status = 'success';
                    $pesan  = "Pendaftaran berhasil! Nomor antrian Anda: <b>{$no_urut}</b>. Datang ke klinik dan tunjukkan nomor ini ke resepsionis.";
                }
            }
        }
    } catch (PDOException $e) {
        $status = 'error'; $pesan = 'Gagal mendaftar: ' . $e->getMessage();
    }
}

// Ambil daftar dokter
$daftar_dokter = $pdo->query("
    SELECT d.*,
           COALESCE(COUNT(a.id_antrian), 0) AS antrian_hari_ini
    FROM dokter d
    LEFT JOIN antrian a ON a.id_dokter = d.id_dokter
        AND a.tanggal_kunjungan = CURDATE()
        AND a.status_kedatangan != 'batal'
    GROUP BY d.id_dokter
    ORDER BY d.nama_dokter ASC
")->fetchAll();

// Ambil antrian aktif pasien
$stmt_antrian = $pdo->prepare("
    SELECT a.*, d.nama_dokter, d.spesialis
    FROM antrian a
    JOIN dokter d ON a.id_dokter = d.id_dokter
    WHERE a.id_pasien = ? AND a.status_kedatangan = 'belum datang'
    ORDER BY a.tanggal_kunjungan ASC
");
$stmt_antrian->execute([$id_pasien]);
$antrian_aktif = $stmt_antrian->fetchAll();
?>

<div class="mb-4 pb-3 border-bottom">
    <h4 class="fw-bold text-dark mb-1">Buat Antrian Online</h4>
    <p class="text-muted small mb-0">Daftarkan jadwal kunjungan Anda ke dokter pilihan</p>
</div>

<div class="row g-4">

    <!-- ===== FORM ANTRIAN ===== -->
    <div class="col-lg-7">
        <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-header bg-primary text-white rounded-top-4 py-3 px-4">
                <h5 class="fw-bold mb-0"><i class="fa-solid fa-calendar-plus me-2"></i>Form Pendaftaran Antrian</h5>
            </div>
            <div class="card-body p-4">
                <form action="?f=pasien&m=pengajuan" method="POST">

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Pilih Dokter <span class="text-danger">*</span></label>
                        <?php if (empty($daftar_dokter)): ?>
                            <div class="alert alert-warning py-2 small border-0">Belum ada dokter terdaftar.</div>
                        <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($daftar_dokter as $dok):
                                $kapasitas_dok  = (int)($dok['kapasitas'] ?? 10);
                                $sisa_hari_ini  = $kapasitas_dok - (int)$dok['antrian_hari_ini'];
                                $penuh_hari_ini = $sisa_hari_ini <= 0;
                                $hampir_penuh   = $sisa_hari_ini <= 3 && !$penuh_hari_ini;
                                $hari_arr       = !empty($dok['hari_praktek'])
                                    ? array_map('trim', explode(',', $dok['hari_praktek'])) : [];
                            ?>
                            <div class="col-md-6">
                                <input class="btn-check dokter-radio" type="radio"
                                       name="id_dokter" id="dok_<?= $dok['id_dokter'] ?>"
                                       value="<?= $dok['id_dokter'] ?>"
                                       data-hari="<?= htmlspecialchars($dok['hari_praktek'] ?? '') ?>"
                                       required>
                                <label class="btn btn-outline-primary w-100 rounded-3 text-start p-3"
                                       for="dok_<?= $dok['id_dokter'] ?>">
                                    <i class="fa-solid fa-user-doctor me-2"></i>
                                    <strong><?= htmlspecialchars($dok['nama_dokter']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($dok['spesialis'] ?: 'Dokter Umum') ?></small><br>
                                    <?php if ($dok['jam_praktek']): ?>
                                    <small><i class="fa-solid fa-clock me-1"></i><?= htmlspecialchars($dok['jam_praktek']) ?></small><br>
                                    <?php endif; ?>
                                    <?php if (!empty($hari_arr)): ?>
                                    <small class="text-info d-block">
                                        <i class="fa-solid fa-calendar-days me-1"></i><?= implode(', ', $hari_arr) ?>
                                    </small>
                                    <?php endif; ?>
                                    <small class="mt-1 d-block">
                                        <?php if ($penuh_hari_ini): ?>
                                            <span class="badge bg-danger"><i class="fa-solid fa-circle-xmark me-1"></i>Penuh hari ini</span>
                                        <?php elseif ($hampir_penuh): ?>
                                            <span class="badge bg-warning text-dark"><i class="fa-solid fa-triangle-exclamation me-1"></i>Sisa <?= $sisa_hari_ini ?> slot</span>
                                        <?php else: ?>
                                            <span class="badge bg-success"><i class="fa-solid fa-circle-check me-1"></i>Tersedia (<?= $sisa_hari_ini ?> slot)</span>
                                        <?php endif; ?>
                                    </small>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Tanggal Kunjungan <span class="text-danger">*</span></label>
                        <input type="date" name="tanggal_kunjungan" class="form-control form-control-lg"
                               required min="<?= date('Y-m-d') ?>">
                        <div class="form-text">Pilih tanggal sesuai hari praktek dokter yang dipilih.</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Keluhan Awal</label>
                        <textarea name="keluhan_awal" class="form-control" rows="3"
                                  placeholder="Ceritakan keluhan utama Anda saat ini..."></textarea>
                    </div>

                    <div class="alert alert-info border-0 rounded-3 small py-2">
                        <i class="fa-solid fa-circle-info me-1"></i>
                        Setelah daftar, Anda <b>wajib datang ke klinik</b> dan melapor ke resepsionis untuk konfirmasi kehadiran.
                    </div>

                    <button type="submit" name="buat_antrian"
                            class="btn btn-primary w-100 rounded-3 py-2 fw-semibold mt-2 shadow-sm">
                        <i class="fa-solid fa-ticket me-2"></i>Daftarkan Antrian Saya
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- ===== ANTRIAN AKTIF ===== -->
    <div class="col-lg-5">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-header bg-light border-bottom rounded-top-4 py-3 px-4
                        d-flex align-items-center justify-content-between">
                <h5 class="fw-bold mb-0">
                    <i class="fa-solid fa-ticket text-warning me-2"></i>Antrian Aktif Saya
                </h5>
                <?php if (!empty($antrian_aktif)): ?>
                <span class="badge bg-warning text-dark rounded-pill"><?= count($antrian_aktif) ?></span>
                <?php endif; ?>
            </div>
            <div class="card-body p-4">
                <?php if (empty($antrian_aktif)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="fa-solid fa-calendar-xmark fa-3x mb-3 d-block opacity-50"></i>
                    <p class="small">Anda tidak memiliki antrian aktif saat ini.</p>
                </div>
                <?php else: ?>
                    <?php foreach ($antrian_aktif as $a):
                        $tgl_display = date('d M Y', strtotime($a['tanggal_kunjungan']));
                        $is_today    = $a['tanggal_kunjungan'] === date('Y-m-d');
                    ?>
                    <div class="card border-0 rounded-3 mb-3 overflow-hidden"
                         style="border: 1.5px solid #f0ece7 !important;">

                        <!-- Header kartu antrian -->
                        <div class="px-3 py-2 d-flex align-items-center justify-content-between"
                             style="background:#f7f3ee;">
                            <span class="badge bg-primary rounded-pill">
                                No. Urut: <?= $a['no_urut'] ?>
                            </span>
                            <?php if ($is_today): ?>
                                <span class="badge bg-success rounded-pill">
                                    <i class="fa-solid fa-circle-dot me-1" style="font-size:0.6rem;"></i>Hari Ini
                                </span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark rounded-pill">Mendatang</span>
                            <?php endif; ?>
                        </div>

                        <!-- Info antrian -->
                        <div class="p-3">
                            <h6 class="fw-bold mb-1">
                                <i class="fa-solid fa-user-doctor text-primary me-1"></i>
                                <?= htmlspecialchars($a['nama_dokter']) ?>
                            </h6>
                            <small class="text-muted d-block mb-1">
                                <?= htmlspecialchars($a['spesialis'] ?: 'Dokter Umum') ?>
                            </small>
                            <small class="text-muted">
                                <i class="fa-solid fa-calendar me-1"></i><?= $tgl_display ?>
                            </small>
                            <?php if ($a['keluhan_awal']): ?>
                            <p class="small text-muted mt-2 mb-0 fst-italic">
                                <i class="fa-solid fa-comment-medical me-1"></i>
                                "<?= htmlspecialchars(mb_substr($a['keluhan_awal'], 0, 60)) ?><?= mb_strlen($a['keluhan_awal']) > 60 ? '...' : '' ?>"
                            </p>
                            <?php endif; ?>
                        </div>

                        <!-- Tombol Batal -->
                        <div class="px-3 pb-3">
                            <a href="?f=pasien&m=pengajuan&act=batal&id=<?= $a['id_antrian'] ?>"
                               class="btn btn-outline-danger btn-sm w-100 rounded-3 btn-batal-antrian"
                               data-nama="<?= htmlspecialchars($a['nama_dokter']) ?>"
                               data-tgl="<?= $tgl_display ?>">
                                <i class="fa-solid fa-xmark me-1"></i>Batalkan Antrian Ini
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<?php if ($pesan): ?>
<script>
document.addEventListener("DOMContentLoaded", function () {
    Swal.fire({
        title: "<?= $status === 'success' ? 'Berhasil!' : 'Gagal!' ?>",
        html : "<?= addslashes($pesan) ?>",
        icon : "<?= $status ?>",
        confirmButtonColor: "#0d6efd",
        confirmButtonText: "OK"
    }).then(() => {
        window.location.href = "?f=pasien&m=pengajuan";
    });
});
</script>
<?php endif; ?>

<script>
// ===== KONFIRMASI SWEETALERT SEBELUM BATAL =====
document.querySelectorAll('.btn-batal-antrian').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
        e.preventDefault();
        const url  = this.href;
        const nama = this.dataset.nama;
        const tgl  = this.dataset.tgl;
        Swal.fire({
            title: 'Batalkan Antrian?',
            html : `Anda akan membatalkan antrian ke <b>${nama}</b> pada <b>${tgl}</b>.<br>
                    <small class="text-muted">Tindakan ini tidak dapat diurungkan.</small>`,
            icon : 'warning',
            showCancelButton  : true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor : '#6c757d',
            confirmButtonText : '<i class="fa-solid fa-xmark me-1"></i>Ya, Batalkan',
            cancelButtonText  : 'Tidak Jadi',
        }).then(result => {
            if (result.isConfirmed) window.location.href = url;
        });
    });
});

// ===== DISABLE TANGGAL NON-PRAKTEK =====
(function () {
    const hariToNum = {
        'Minggu':0,'Senin':1,'Selasa':2,'Rabu':3,'Kamis':4,'Jumat':5,'Sabtu':6
    };
    const inputTgl = document.querySelector('input[name="tanggal_kunjungan"]');

    function updateTanggal(hariStr) {
        if (!inputTgl) return;
        if (!hariStr) { inputTgl.onchange = null; return; }

        const hariDiizinkan = hariStr.split(',')
            .map(h => hariToNum[h.trim()])
            .filter(n => n !== undefined);

        const today = new Date(); today.setHours(0,0,0,0);
        let cari = new Date(today);
        for (let i = 0; i < 7; i++) {
            if (hariDiizinkan.includes(cari.getDay())) break;
            cari.setDate(cari.getDate() + 1);
        }
        const yyyy = cari.getFullYear();
        const mm   = String(cari.getMonth()+1).padStart(2,'0');
        const dd   = String(cari.getDate()).padStart(2,'0');
        inputTgl.value = `${yyyy}-${mm}-${dd}`;

        inputTgl.onchange = function () {
            const tgl    = new Date(this.value + 'T00:00:00');
            const hariJS = tgl.getDay();
            if (!hariDiizinkan.includes(hariJS)) {
                this.setCustomValidity('Dokter tidak praktek pada hari yang dipilih!');
                this.reportValidity();
                this.value = '';
            } else {
                this.setCustomValidity('');
            }
        };
    }

    document.querySelectorAll('.dokter-radio').forEach(function (radio) {
        radio.addEventListener('change', function () {
            updateTanggal(this.dataset.hari || '');
        });
    });

    const checked = document.querySelector('.dokter-radio:checked');
    if (checked) updateTanggal(checked.dataset.hari || '');
})();
</script>