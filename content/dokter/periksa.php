<?php
// Pastikan file ini tidak diakses langsung
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'dokter') {
    exit('Akses Ditolak! Anda bukan dokter.');
}

// Ambil id_dokter
$stmt_dokter = $pdo->prepare("SELECT id_dokter, nama_dokter FROM dokter WHERE id_user = ?");
$stmt_dokter->execute([$_SESSION['id_user']]);
$profil_dokter = $stmt_dokter->fetch();
if (!$profil_dokter) { echo "<div class='alert alert-danger'>Data dokter tidak ditemukan.</div>"; return; }
$id_dokter = $profil_dokter['id_dokter'];

// Ambil antrian yang akan diperiksa
$id_antrian = isset($_GET['id_antrian']) ? (int)$_GET['id_antrian'] : 0;
if (!$id_antrian) { echo "<div class='alert alert-warning'>ID antrian tidak valid.</div>"; return; }

// Ambil data antrian + pasien, pastikan antrian ini milik dokter ini
$stmt_antrian = $pdo->prepare("SELECT a.*, p.nama_pasien, p.no_hp, p.tanggal_lahir, p.alamat, p.id_pasien
                                FROM antrian a
                                JOIN pasien p ON a.id_pasien = p.id_pasien
                                WHERE a.id_antrian = ? AND a.id_dokter = ?");
$stmt_antrian->execute([$id_antrian, $id_dokter]);
$pasien = $stmt_antrian->fetch();
if (!$pasien) { echo "<div class='alert alert-danger'>Data antrian tidak ditemukan atau bukan hak Anda.</div>"; return; }

// Ambil data pemeriksaan jika sudah ada
$stmt_pem = $pdo->prepare("SELECT * FROM pemeriksaan WHERE id_antrian = ?");
$stmt_pem->execute([$id_antrian]);
$pemeriksaan = $stmt_pem->fetch();
$id_pemeriksaan = $pemeriksaan ? $pemeriksaan['id_pemeriksaan'] : null;

// Persiapkan statement query resep obat di LUAR blok if agar selalu dikenali
$stmt_resep = $pdo->prepare("SELECT ro.*, o.nama_obat, o.satuan FROM resep_obat ro JOIN obat o ON ro.id_obat = o.id_obat WHERE ro.id_pemeriksaan = ?");
$resep_list = [];

if ($id_pemeriksaan) {
    // Eksekusi jika id_pemeriksaan sudah ada (pasien lama/update)
    $stmt_resep->execute([$id_pemeriksaan]);
    $resep_list = $stmt_resep->fetchAll();
}

$pesan = ''; $status = '';

// --- LOGIKA SIMPAN PEMERIKSAAN ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_pemeriksaan'])) {
    $catatan       = htmlspecialchars(trim($_POST['catatan']));
    $hasil_diag    = htmlspecialchars(trim($_POST['hasil_diagnosis']));
    $obat_ids      = $_POST['obat_id'] ?? [];
    $dosis_list    = $_POST['dosis'] ?? [];
    $jumlah_list   = $_POST['jumlah'] ?? [];

    try {
        $pdo->beginTransaction();

        if ($id_pemeriksaan) {
            // Update data pemeriksaan
            $stmt_upd = $pdo->prepare("UPDATE pemeriksaan SET catatan = ?, hasil_diagnosis = ? WHERE id_pemeriksaan = ?");
            $stmt_upd->execute([$catatan, $hasil_diag, $id_pemeriksaan]);
            // Hapus resep lama (trigger di database akan mengembalikan stok otomatis)
            $pdo->prepare("DELETE FROM resep_obat WHERE id_pemeriksaan = ?")->execute([$id_pemeriksaan]);
        } else {
            // Insert data pemeriksaan baru
            $stmt_ins = $pdo->prepare("INSERT INTO pemeriksaan (id_antrian, catatan, hasil_diagnosis) VALUES (?, ?, ?)");
            $stmt_ins->execute([$id_antrian, $catatan, $hasil_diag]);
            $id_pemeriksaan = $pdo->lastInsertId();
        }

        // Insert resep obat baru (trigger di database akan mengurangi stok otomatis)
        if (!empty($obat_ids)) {
            $stmt_ro = $pdo->prepare("INSERT INTO resep_obat (id_pemeriksaan, id_obat, dosis, jumlah_obat_keluar) VALUES (?, ?, ?, ?)");
            foreach ($obat_ids as $i => $id_obat) {
                if ($id_obat && isset($dosis_list[$i]) && isset($jumlah_list[$i]) && (int)$jumlah_list[$i] > 0) {
                    $stmt_ro->execute([$id_pemeriksaan, (int)$id_obat, htmlspecialchars($dosis_list[$i]), (int)$jumlah_list[$i]]);
                }
            }
        }

        $pdo->commit();
        $status = 'success'; $pesan = 'Data pemeriksaan dan resep berhasil disimpan!';
    } catch (PDOException $e) {
        $pdo->rollBack();
        $status = 'error'; $pesan = 'Gagal menyimpan: ' . $e->getMessage();
    }
}

// Ambil daftar obat untuk dropdown
$daftar_obat = $pdo->query("SELECT * FROM obat WHERE stock > 0 ORDER BY nama_obat ASC")->fetchAll();

// Hitung umur
$umur = '-';
if ($pasien['tanggal_lahir']) {
    $umur = (new DateTime($pasien['tanggal_lahir']))->diff(new DateTime())->y . ' tahun';
}

// Refresh data pemeriksaan setelah simpan (Hanya berjalan jika terjadi error agar form tidak kosong)
if ($status == 'error' || $_SERVER['REQUEST_METHOD'] != 'POST') {
    $stmt_pem->execute([$id_antrian]);
    $pemeriksaan = $stmt_pem->fetch();
    if ($pemeriksaan) {
        $stmt_resep->execute([$pemeriksaan['id_pemeriksaan']]);
        $resep_list = $stmt_resep->fetchAll();
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
    <div>
        <h4 class="fw-bold text-dark mb-1">Form Pemeriksaan Pasien</h4>
        <p class="text-muted small mb-0">Isi hasil diagnosis dan resep obat untuk pasien</p>
    </div>
    <a href="?f=dokter&m=antrian" class="btn btn-outline-secondary rounded-pill px-4">
        <i class="fa-solid fa-arrow-left me-2"></i>Kembali
    </a>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 rounded-4 shadow-sm sticky-top" style="top: 80px;">
            <div class="card-header bg-primary text-white rounded-top-4 py-3 px-4">
                <h6 class="fw-bold mb-0"><i class="fa-solid fa-user-injured me-2"></i>Info Pasien</h6>
            </div>
            <div class="card-body p-4">
                <div class="text-center mb-3">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex align-items-center justify-content-center fw-bold mb-2" style="width:70px;height:70px;font-size:1.8rem;">
                        <?= strtoupper(substr($pasien['nama_pasien'], 0, 1)) ?>
                    </div>
                    <h5 class="fw-bold mb-0"><?= htmlspecialchars($pasien['nama_pasien']) ?></h5>
                    <span class="badge bg-primary bg-opacity-20 text-primary rounded-pill px-3 mt-1">No. Urut: <?= $pasien['no_urut'] ?></span>
                </div>
                <hr>
                <ul class="list-unstyled small mb-0">
                    <li class="mb-2"><i class="fa-solid fa-phone text-muted me-2"></i><?= htmlspecialchars($pasien['no_hp'] ?: '-') ?></li>
                    <li class="mb-2"><i class="fa-solid fa-cake-candles text-muted me-2"></i><?= $umur ?></li>
                    <li class="mb-2"><i class="fa-solid fa-location-dot text-muted me-2"></i><?= htmlspecialchars($pasien['alamat'] ?: '-') ?></li>
                    <li class="mb-2 text-danger"><i class="fa-solid fa-comment-medical text-muted me-2"></i><strong>Keluhan: </strong><?= htmlspecialchars($pasien['keluhan_awal'] ?: 'Tidak ada') ?></li>
                    <li><i class="fa-solid fa-calendar-day text-muted me-2"></i><?= date('d M Y', strtotime($pasien['tanggal_kunjungan'])) ?></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <form action="?f=dokter&m=periksa&id_antrian=<?= $id_antrian ?>" method="POST">
            <div class="card border-0 rounded-4 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-4"><i class="fa-solid fa-notes-medical text-primary me-2"></i>Hasil Pemeriksaan</h5>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Catatan Dokter</label>
                        <textarea name="catatan" class="form-control" rows="3" placeholder="Catatan pemeriksaan fisik, observasi, dll..."><?= htmlspecialchars($pemeriksaan['catatan'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Hasil Diagnosis <span class="text-danger">*</span></label>
                        <textarea name="hasil_diagnosis" class="form-control" rows="3" placeholder="Contoh: Demam berdarah grade II, Influenza, dsb." required><?= htmlspecialchars($pemeriksaan['hasil_diagnosis'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="card border-0 rounded-4 shadow-sm mb-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold mb-0"><i class="fa-solid fa-pills text-success me-2"></i>Resep Obat</h5>
                        <button type="button" class="btn btn-outline-success btn-sm rounded-pill px-3" onclick="tambahBarisobat()">
                            <i class="fa-solid fa-plus me-1"></i>Tambah Obat
                        </button>
                    </div>

                    <div id="container-resep">
                        <?php if (!empty($resep_list)): ?>
                            <?php foreach ($resep_list as $i => $resep): ?>
                            <div class="row g-2 align-items-end mb-3 baris-obat border rounded-3 p-2 bg-light">
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold mb-1">Nama Obat</label>
                                    <select name="obat_id[]" class="form-select form-select-sm" required>
                                        <option value="">-- Pilih --</option>
                                        <?php foreach ($daftar_obat as $obat): ?>
                                        <option value="<?= $obat['id_obat'] ?>" <?= $obat['id_obat'] == $resep['id_obat'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($obat['nama_obat']) ?> (Stok: <?= $obat['stock'] ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold mb-1">Dosis/Aturan Pakai</label>
                                    <input type="text" name="dosis[]" class="form-control form-control-sm" value="<?= htmlspecialchars($resep['dosis']) ?>" placeholder="Cth: 3x1 sesudah makan" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-semibold mb-1">Jumlah</label>
                                    <input type="number" name="jumlah[]" class="form-control form-control-sm" value="<?= $resep['jumlah_obat_keluar'] ?>" min="1" required>
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn btn-danger btn-sm w-100 rounded-3" onclick="this.closest('.baris-obat').remove()">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                        <div class="text-center text-muted py-3 small" id="placeholder-resep">
                            <i class="fa-solid fa-pills fa-2x mb-2 d-block opacity-50"></i>
                            Belum ada obat ditambahkan. Klik "Tambah Obat" untuk menambahkan.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="?f=dokter&m=antrian" class="btn btn-outline-secondary rounded-pill px-4">Batal</a>
                <button type="submit" name="simpan_pemeriksaan" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm">
                    <i class="fa-solid fa-floppy-disk me-2"></i>Simpan Pemeriksaan
                </button>
            </div>
        </form>
    </div>
</div>

<template id="template-obat">
    <div class="row g-2 align-items-end mb-3 baris-obat border rounded-3 p-2 bg-light">
        <div class="col-md-4">
            <label class="form-label small fw-semibold mb-1">Nama Obat</label>
            <select name="obat_id[]" class="form-select form-select-sm" required>
                <option value="">-- Pilih Obat --</option>
                <?php foreach ($daftar_obat as $obat): ?>
                <option value="<?= $obat['id_obat'] ?>"><?= htmlspecialchars($obat['nama_obat']) ?> (Stok: <?= $obat['stock'] ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-semibold mb-1">Dosis/Aturan Pakai</label>
            <input type="text" name="dosis[]" class="form-control form-control-sm" placeholder="Cth: 3x1 sesudah makan" required>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold mb-1">Jumlah</label>
            <input type="number" name="jumlah[]" class="form-control form-control-sm" min="1" value="1" required>
        </div>
        <div class="col-md-2">
            <button type="button" class="btn btn-danger btn-sm w-100 rounded-3" onclick="this.closest('.baris-obat').remove()">
                <i class="fa-solid fa-trash-can"></i>
            </button>
        </div>
    </div>
</template>

<script>
function tambahBarisobat() {
    const placeholder = document.getElementById('placeholder-resep');
    if (placeholder) placeholder.remove();
    const template = document.getElementById('template-obat');
    const clone = template.content.cloneNode(true);
    document.getElementById('container-resep').appendChild(clone);
}
</script>

<?php if($pesan != ''): ?>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        Swal.fire({
            title: "<?= $status == 'success' ? 'Tersimpan!' : 'Gagal!' ?>",
            text: "<?= $pesan ?>",
            icon: "<?= $status ?>",
            confirmButtonColor: "#0d6efd"
        }).then(() => {
            // PERBAIKAN DI SINI:
            // Jika sukses, arahkan kembali ke daftar antrian. Jika gagal, tetap di halaman form.
            <?php if($status == 'success'): ?>
                window.location.href = "?f=dokter&m=antrian";
            <?php else: ?>
                window.location.href = "?f=dokter&m=periksa&id_antrian=<?= $id_antrian ?>";
            <?php endif; ?>
        });
    });
</script>
<?php endif; ?>