<?php
// FILE DIAGNOSIS SEMENTARA — HAPUS SETELAH SELESAI
// Versi ini membaca config.php Anda, jadi TIDAK perlu edit apapun.
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');

echo "=== DIAGNOSIS KONEKSI MITLOIN ===\n\n";

// 1) Cek config.php ada
$cfg = __DIR__ . '/config.php';
if (!file_exists($cfg)) {
    echo "❌ config.php TIDAK ditemukan di folder yang sama!\n";
    exit;
}
echo "✅ config.php ditemukan.\n";

require_once $cfg;

// 2) Cek konstanta terdefinisi
foreach (['DB_HOST','DB_NAME','DB_USER','DB_PASS'] as $k) {
    if (!defined($k)) { echo "❌ $k belum didefinisikan di config.php\n"; exit; }
}
echo "DB_HOST = " . DB_HOST . "\n";
echo "DB_NAME = " . DB_NAME . "\n";
echo "DB_USER = " . DB_USER . "\n";
echo "DB_PASS = " . str_repeat('*', strlen(DB_PASS)) . " (" . strlen(DB_PASS) . " karakter)\n\n";

// 3) Coba koneksi
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "✅ KONEKSI DATABASE BERHASIL!\n\n";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tabel ditemukan (" . count($tables) . "):\n";
    foreach ($tables as $t) echo "  - $t\n";
    echo "\nKesimpulan: Database OK. Kalau api.php masih error 500,\nberarti masalah lain (kirim screenshot ini ke saya).\n";
} catch (PDOException $e) {
    echo "❌ KONEKSI DATABASE GAGAL!\n";
    echo "Pesan error: " . $e->getMessage() . "\n\n";
    echo "Solusi tergantung pesan di atas:\n";
    echo "- 'Access denied'      => password (DB_PASS) salah. Reset password user di cPanel MySQL, samakan di config.php.\n";
    echo "- 'Unknown database'   => nama database (DB_NAME) salah.\n";
    echo "- 'getaddrinfo' / host => DB_HOST salah (coba 'localhost' atau '127.0.0.1').\n";
}
