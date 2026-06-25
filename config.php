<?php
/* ════════════════════════════════════════════════════════════
   MITLOIN — KONFIGURASI DATABASE
   EDIT 5 BARIS DI BAWAH sesuai data hosting (cPanel) Anda.
   ════════════════════════════════════════════════════════════ */

// ─── Kredensial Database (ganti sesuai hosting Anda) ───
define('DB_HOST', 'localhost');
define('DB_NAME', 'mitloinc_mitloin');
define('DB_USER', 'mitloinc_dbuser');
define('DB_PASS', 'MitloinDB2026!');

// ─── Kunci rahasia untuk token login (ganti dgn string acak panjang) ───
define('SECRET_KEY', 'mitloin_R4h4s14_2026_xK9pQ7mZ_jangan_dibagi_ke_siapapun');

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
