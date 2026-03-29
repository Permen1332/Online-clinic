<?php
session_start();

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'resepsionis') {
    http_response_code(403);
    exit('Akses Ditolak!');
}

require_once '../../config/koneksi.php';

// --- FILTER ---
$filter_dokter = isset($_GET['id_dokter']) && $_GET['id_dokter'] !== '' ? (int)$_GET['id_dokter'] : '';
$filter_dari   = isset($_GET['dari'])   && $_GET['dari']   !== '' ? $_GET['dari']   : date('Y-m-01');
$filter_sampai = isset($_GET['sampai']) && $_GET['sampai'] !== '' ? $_GET['sampai'] : date('Y-m-d');
$filter_cari   = isset($_GET['cari'])   ? trim($_GET['cari']) : '';

// --- QUERY ---
$sql = "
    SELECT
        pa.nama_pasien,
        d.nama_dokter,
        d.spesialis,
        a.tanggal_kunjungan,
        a.no_urut,
        a.keluhan_awal,
        p.hasil_diagnosis,
        p.catatan,
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
$sql .= " GROUP BY p.id_pemeriksaan ORDER BY a.tanggal_kunjungan ASC, a.no_urut ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll();

$total_pemeriksaan     = count($data);
$total_obat_diresepkan = array_sum(array_column($data, 'jumlah_obat'));

// Nama dokter untuk info header (jika filter per dokter)
$nama_dokter_filter = '';
if ($filter_dokter !== '') {
    $qd = $pdo->prepare("SELECT nama_dokter FROM dokter WHERE id_dokter = ?");
    $qd->execute([$filter_dokter]);
    $nama_dokter_filter = $qd->fetchColumn() ?: '';
}

// --- HEADER DOWNLOAD EXCEL ---
$filename = 'Rekap_Pemeriksaan_' . $filter_dari . '_sd_' . $filter_sampai . '.xls';
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta charset="UTF-8">
<!--[if gte mso 9]>
<xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>
<x:Name>Rekap Pemeriksaan</x:Name>
<x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions>
</x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml>
<![endif]-->
<style>
    body     { font-family: Arial, sans-serif; font-size: 10pt; }

    .klinik  { font-size: 14pt; font-weight: bold; }
    .judul   { font-size: 11pt; font-weight: bold; margin-bottom: 2px; }
    .info    { font-size: 9pt;  color: #333; }

    table    { border-collapse: collapse; width: 100%; margin-top: 12px; }
    th, td   { border: 1px solid #aaa; padding: 5px 8px; vertical-align: middle; }

    thead th {
        background-color: #1a5276;
        color: #ffffff;
        font-weight: bold;
        text-align: center;
        white-space: nowrap;
    }

    tbody tr:nth-child(odd)  td { background-color: #eaf4fb; }
    tbody tr:nth-child(even) td { background-color: #ffffff; }

    .center  { text-align: center; }
    .bold    { font-weight: bold; }
    .gray    { color: #777; font-style: italic; }
    .green   { color: #1e8449; font-weight: bold; }

    tfoot td {
        background-color: #1a5276;
        color: #ffffff;
        font-weight: bold;
    }

    .header-box {
        border-left: 4px solid #1a5276;
        padding: 8px 14px;
        margin-bottom: 4px;
        background-color: #f4f6f7;
    }
</style>
</head>
<body>

<div class="header-box">
    <div class="klinik">CIPENG CLINIC</div>
    <div class="judul">Rekap Riwayat Pemeriksaan Pasien</div>
    <div class="info">Periode &nbsp;&nbsp;: <b><?= date('d M Y', strtotime($filter_dari)) ?> s/d <?= date('d M Y', strtotime($filter_sampai)) ?></b></div>
    <?php if ($nama_dokter_filter): ?>
    <div class="info">Dokter &nbsp;&nbsp;&nbsp;: <b><?= htmlspecialchars($nama_dokter_filter) ?></b></div>
    <?php endif; ?>
    <div class="info">Dicetak &nbsp;: <b><?= date('d M Y, H:i') ?> WIB</b></div>
    <div class="info">Total &nbsp;&nbsp;&nbsp;&nbsp;: <b><?= $total_pemeriksaan ?> pemeriksaan</b> &nbsp;|&nbsp; <b><?= $total_obat_diresepkan ?> item obat diresepkan</b></div>
</div>

<table>
    <thead>
        <tr>
            <th style="width:30px;">No</th>
            <th style="width:85px;">Tgl Kunjungan</th>
            <th style="width:50px;">No. Urut</th>
            <th style="width:130px;">Nama Pasien</th>
            <th style="width:130px;">Nama Dokter</th>
            <th style="width:80px;">Spesialisasi</th>
            <th style="width:160px;">Keluhan Awal</th>
            <th style="width:160px;">Hasil Diagnosis</th>
            <th style="width:150px;">Catatan Dokter</th>
            <th style="width:150px;">Obat Diresepkan</th>
            <th style="width:50px;">Jml Obat</th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($data)): ?>
        <tr>
            <td colspan="11" class="center gray" style="padding:14px;">
                Tidak ada data pemeriksaan untuk periode ini.
            </td>
        </tr>
    <?php else: ?>
        <?php $no = 1; foreach ($data as $row): ?>
        <tr>
            <td class="center"><?= $no++ ?></td>

            <!-- Tanggal: pakai format string dd/mm/yyyy agar Excel tidak ubah jadi angka serial -->
            <td class="center"><?= date('d/m/Y', strtotime($row['tanggal_kunjungan'])) ?></td>

            <td class="center bold"><?= (int)$row['no_urut'] ?></td>
            <td class="bold"><?= htmlspecialchars($row['nama_pasien']) ?></td>
            <td><?= htmlspecialchars($row['nama_dokter']) ?></td>
            <td class="center"><?= htmlspecialchars($row['spesialis'] ?: 'Umum') ?></td>
            <td class="gray"><?= $row['keluhan_awal']    ? htmlspecialchars($row['keluhan_awal'])    : '-' ?></td>
            <td><?= $row['hasil_diagnosis'] ? htmlspecialchars($row['hasil_diagnosis']) : '<span class="gray">Belum diperiksa</span>' ?></td>
            <td class="gray"><?= $row['catatan']         ? htmlspecialchars($row['catatan'])         : '-' ?></td>
            <td class="green"><?= $row['daftar_obat']   ? htmlspecialchars($row['daftar_obat'])     : '<span class="gray">-</span>' ?></td>
            <td class="center"><?= (int)$row['jumlah_obat'] ?></td>
        </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="3" style="text-align:right;">TOTAL</td>
            <td colspan="7"><?= $total_pemeriksaan ?> pemeriksaan &nbsp;|&nbsp; <?= $total_obat_diresepkan ?> item obat diresepkan</td>
            <td class="center"><?= $total_obat_diresepkan ?></td>
        </tr>
    </tfoot>
</table>

</body>
</html>