<?php
// FILE DIAGNOSIS SEMENTARA — HAPUS SETELAH SELESAI
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');

$host = 'localhost';
$nama = 'mitloinc_mitloin';   // ganti kalau beda
$user = 'mitloinc_admin';     // ganti kalau beda
$pass = 'mitloinadmin123';    // GANTI dengan password asli Anda

echo "Mencoba koneksi database...\n";
echo "Host: $host\n";
echo "DB  : $nama\n";
echo "User: $user\n\n";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$nama;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "✅ KONEKSI BERHASIL!\n\n";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tabel ditemukan (" . count($tables) . "):\n";
    foreach ($tables as $t) echo "  - $t\n";
} catch (PDOException $e) {
    echo "❌ KONEKSI GAGAL!\n";
    echo "Error: " . $e->getMessage() . "\n\n";
    echo "Solusi:\n";
    echo "- Cek DB_PASS di config.php, harus sama persis dengan password di cPanel MySQL\n";
    echo "- Cek DB_NAME dan DB_USER sudah benar (ada prefix mitloinc_)\n";
}
