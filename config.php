<?php
/* ════════════════════════════════════════════════════════════
   MITLOIN — KONFIGURASI DATABASE
   EDIT 5 BARIS DI BAWAH sesuai data hosting (cPanel) Anda.
   ════════════════════════════════════════════════════════════ */

// ─── Kredensial Database (ganti sesuai hosting Anda) ───
define('DB_HOST', 'localhost');
define('DB_NAME', 'namauser_mitloin_db');     // ← ganti
define('DB_USER', 'namauser_mitloin_user');   // ← ganti
define('DB_PASS', 'password_anda_disini');    // ← ganti

// ─── Kunci rahasia untuk token login (ganti dgn string acak panjang) ───
define('SECRET_KEY', 'ganti-dengan-string-acak-yang-sangat-rahasia-minimal-32-karakter');

// ─── Domain yang diizinkan memanggil API (CORS) ───
// Saat masih uji coba boleh '*'. Setelah live, ganti dgn domain asli, contoh: 'https://mitloin.com'
define('ALLOWED_ORIGIN', '*');

/* ── Jangan diubah di bawah ini ───────────────────────────── */
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $opt = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $opt);
    }
    return $pdo;
}
