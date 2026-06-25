<?php
// FILE DIAGNOSIS WRITE — HAPUS SETELAH SELESAI
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/config.php';
echo "=== DIAGNOSIS SIMPAN MEMBER ===\n\n";

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "✅ Koneksi OK (user: " . DB_USER . ")\n\n";
} catch (Exception $e) {
    echo "❌ Koneksi gagal: " . $e->getMessage() . "\n"; exit;
}

// 1) Tampilkan semua user yang ada
echo "--- ISI TABEL users SAAT INI ---\n";
$rows = $pdo->query("SELECT id, nama, email, role, status FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
if (!$rows) echo "(kosong)\n";
foreach ($rows as $r) {
    echo "  #{$r['id']}  [{$r['role']}] {$r['email']}  ({$r['nama']}) - {$r['status']}\n";
}
$member = array_filter($rows, fn($r) => $r['role'] === 'member');
echo "\nJumlah MEMBER: " . count($member) . "\n\n";

// 2) Tes INSERT (lalu hapus) untuk pastikan user DB boleh menulis
echo "--- TES SIMPAN (INSERT) ---\n";
$testEmail = 'tes-write-' . substr(md5(DB_NAME), 0, 6) . '@example.com';
try {
    $pdo->prepare("DELETE FROM users WHERE email = ?")->execute([$testEmail]); // bersihkan sisa tes lama
    $hash = password_hash('tes12345', PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT INTO users (nama, email, password, no_wa, role, status) VALUES (?,?,?,?,'member','aktif')");
    $stmt->execute(['TES WRITE', $testEmail, $hash, '0800']);
    $id = $pdo->lastInsertId();
    echo "✅ BISA MENYIMPAN! (baris tes #$id dibuat)\n";
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
    echo "   (baris tes sudah dihapus kembali)\n\n";
    echo "KESIMPULAN: Database bisa menyimpan member.\n";
    echo "Kalau pendaftaran dari website tetap gagal, berarti file index.html\n";
    echo "masih versi lama (cache browser). Tekan Ctrl+Shift+R di website.\n";
} catch (Exception $e) {
    echo "❌ GAGAL MENYIMPAN!\n";
    echo "Error: " . $e->getMessage() . "\n\n";
    echo "Kemungkinan: user database '" . DB_USER . "' tidak punya izin INSERT.\n";
    echo "Solusi: di cPanel MySQL Databases, bagian 'Add User To Database',\n";
    echo "tambahkan user ke database lalu centang ALL PRIVILEGES.\n";
}
