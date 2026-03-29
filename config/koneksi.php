<?php
// config/koneksi.php
date_default_timezone_set('Asia/Jakarta'); 
$host = 'localhost';
$db   = 'db_online_clinic'; // Sesuaikan nama databasemu
$user = 'yopi_haikal';
$pass = '144109'; // Isi jika pakai password
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Mode error strict
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Ambil data dalam bentuk array asosiatif
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Matikan emulasi untuk keamanan ekstra
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Di mode production, jangan tampilkan $e->getMessage() agar struktur DB tidak bocor
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
    // die("Koneksi Database Gagal. Silakan hubungi Administrator."); 
}
?>