<?php
/* ════════════════════════════════════════════════════════════
   MITLOIN — API BACKEND
   Semua request dari admin-dashboard.html mengarah ke file ini.
   Dipanggil dengan: api.php?action=NAMA_AKSI
   ════════════════════════════════════════════════════════════ */

error_reporting(E_ALL);
ini_set('display_errors', 0); // jangan tampilkan error PHP mentah ke browser di production

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . (defined('ALLOWED_ORIGIN') ? ALLOWED_ORIGIN : '*'));
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$pdo = getDB();

// ─────────────────────────────────────────────
// HELPER FUNCTIONS
// ─────────────────────────────────────────────
function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function getInput() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function generateToken($userId, $role) {
    $payload = base64_encode(json_encode(['uid' => $userId, 'role' => $role, 'exp' => time() + 86400 * 7]));
    $sig = hash_hmac('sha256', $payload, SECRET_KEY);
    return $payload . '.' . $sig;
}

function verifyToken($token) {
    if (!$token || strpos($token, '.') === false) return null;
    [$payload, $sig] = explode('.', $token, 2);
    $expectedSig = hash_hmac('sha256', $payload, SECRET_KEY);
    if (!hash_equals($expectedSig, $sig)) return null;
    $data = json_decode(base64_decode($payload), true);
    if (!$data || !isset($data['exp']) || $data['exp'] < time()) return null;
    return $data; // ['uid' => ..., 'role' => ..., 'exp' => ...]
}

function getBearerToken() {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $auth = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.*)$/i', $auth, $m)) return $m[1];
    return null;
}

// Wajibkan login. $allowedRoles contoh: ['owner','admin']
function requireAuth($allowedRoles = ['owner', 'admin']) {
    $token = getBearerToken();
    $data = verifyToken($token);
    if (!$data) respond(['success' => false, 'message' => 'Sesi tidak valid atau sudah habis. Silakan login kembali.'], 401);
    if (!in_array($data['role'], $allowedRoles)) respond(['success' => false, 'message' => 'Anda tidak punya akses ke fitur ini.'], 403);
    return $data;
}

function logActivity($pdo, $userId, $aksi, $detail = '') {
    try {
        $stmt = $pdo->prepare("INSERT INTO log_aktivitas (user_id, aksi, detail) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $aksi, $detail]);
    } catch (Exception $e) { /* jangan sampai log gagal mematikan request utama */ }
}

function generateKodePesanan($pdo) {
    $prefix = 'MTL-' . date('Ymd') . '-';
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pesanan WHERE kode_pesanan LIKE ?");
    $stmt->execute([$prefix . '%']);
    $count = (int)$stmt->fetchColumn() + 1;
    return $prefix . str_pad($count, 4, '0', STR_PAD_LEFT);
}

$action = $_GET['action'] ?? '';

try {

switch ($action) {

    // ═══════════════════════════════════════════
    // AUTH
    // ═══════════════════════════════════════════
    case 'login': {
        $in = getInput();
        $email = trim($in['email'] ?? '');
        $password = $in['password'] ?? '';
        if (!$email || !$password) respond(['success' => false, 'message' => 'Email dan password wajib diisi.'], 400);

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            respond(['success' => false, 'message' => 'Email atau password salah.'], 401);
        }
        if ($user['status'] !== 'aktif') {
            respond(['success' => false, 'message' => 'Akun Anda nonaktif. Hubungi owner.'], 403);
        }

        $token = generateToken($user['id'], $user['role']);
        logActivity($pdo, $user['id'], 'Login', $user['email']);

        respond([
            'success' => true,
            'token' => $token,
            'user' => ['id' => $user['id'], 'nama' => $user['nama'], 'email' => $user['email'], 'role' => $user['role'], 'no_wa' => $user['no_wa']]
        ]);
        break;
    }

    case 'me': {
        $auth = requireAuth(['owner', 'admin', 'member']);
        $stmt = $pdo->prepare("SELECT id, nama, email, role, no_wa, status FROM users WHERE id = ?");
        $stmt->execute([$auth['uid']]);
        respond(['success' => true, 'user' => $stmt->fetch()]);
        break;
    }

    case 'ganti_password': {
        $auth = requireAuth(['owner', 'admin', 'member']);
        $in = getInput();
        $lama = $in['password_lama'] ?? '';
        $baru = $in['password_baru'] ?? '';
        if (strlen($baru) < 6) respond(['success' => false, 'message' => 'Password baru minimal 6 karakter.'], 400);

        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$auth['uid']]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($lama, $row['password'])) {
            respond(['success' => false, 'message' => 'Password lama salah.'], 401);
        }
        $hash = password_hash($baru, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $auth['uid']]);
        logActivity($pdo, $auth['uid'], 'Ganti password', '');
        respond(['success' => true, 'message' => 'Password berhasil diganti.']);
        break;
    }

    // ═══════════════════════════════════════════
    // DASHBOARD SUMMARY (untuk kartu statistik)
    // ═══════════════════════════════════════════
    case 'dashboard_summary': {
        requireAuth(['owner', 'admin']);

        $totalPenjualan = $pdo->query("SELECT COALESCE(SUM(total),0) FROM pesanan WHERE status != 'batal'")->fetchColumn();
        $totalPesanan = $pdo->query("SELECT COUNT(*) FROM pesanan")->fetchColumn();
        $pesananBaru = $pdo->query("SELECT COUNT(*) FROM pesanan WHERE status = 'baru'")->fetchColumn();
        $totalProduk = $pdo->query("SELECT COUNT(*) FROM produk WHERE status = 'aktif'")->fetchColumn();
        $stokMenipis = $pdo->query("SELECT COUNT(*) FROM produk WHERE stok <= 5 AND status = 'aktif'")->fetchColumn();
        $totalMember = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'member'")->fetchColumn();

        $penjualan7Hari = $pdo->query("
            SELECT DATE(created_at) as tgl, COALESCE(SUM(total),0) as total
            FROM pesanan
            WHERE status != 'batal' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            GROUP BY DATE(created_at)
            ORDER BY tgl ASC
        ")->fetchAll();

        $produkTerlaris = $pdo->query("
            SELECT pi.nama_produk, SUM(pi.qty) as total_qty
            FROM pesanan_item pi
            JOIN pesanan p ON p.id = pi.pesanan_id
            WHERE p.status != 'batal'
            GROUP BY pi.nama_produk
            ORDER BY total_qty DESC
            LIMIT 5
        ")->fetchAll();

        respond([
            'success' => true,
            'data' => [
                'total_penjualan' => (float)$totalPenjualan,
                'total_pesanan' => (int)$totalPesanan,
                'pesanan_baru' => (int)$pesananBaru,
                'total_produk' => (int)$totalProduk,
                'stok_menipis' => (int)$stokMenipis,
                'total_member' => (int)$totalMember,
                'penjualan_7hari' => $penjualan7Hari,
                'produk_terlaris' => $produkTerlaris,
            ]
        ]);
        break;
    }

    // ═══════════════════════════════════════════
    // PRODUK — CRUD
    // ═══════════════════════════════════════════
    case 'produk_list': {
        requireAuth(['owner', 'admin']);
        $rows = $pdo->query("
            SELECT p.*, k.nama as kategori_nama, k.slug as kategori_slug
            FROM produk p LEFT JOIN kategori k ON k.id = p.kategori_id
            ORDER BY p.created_at DESC
        ")->fetchAll();
        respond(['success' => true, 'data' => $rows]);
        break;
    }

    case 'produk_publik': {
        // Endpoint PUBLIK (tanpa login) — dipakai website mitloin.html untuk
        // menampilkan katalog. Perubahan harga & foto dari dashboard langsung
        // tampil di sini. Hanya produk berstatus 'aktif' yang dikirim.
        $rows = $pdo->query("
            SELECT p.id, p.nama, p.emoji, p.foto, p.harga, p.satuan, p.deskripsi,
                   p.badge, p.badge_type, COALESCE(k.slug,'lain') AS kategori
            FROM produk p LEFT JOIN kategori k ON k.id = p.kategori_id
            WHERE p.status = 'aktif'
            ORDER BY p.id ASC
        ")->fetchAll();
        // bentuk ulang agar sesuai struktur produkData di website
        $out = array_map(function ($r) {
            return [
                'id'        => (int)$r['id'],
                'nama'      => $r['nama'],
                'kategori'  => $r['kategori'],
                'emoji'     => $r['emoji'] ?: '🥩',
                'foto'      => $r['foto'] ?: null,
                'harga'     => (float)$r['harga'],
                'satuan'    => $r['satuan'],
                'desc'      => $r['deskripsi'] ?: '',
                'badge'     => $r['badge'] ?: '',
                'badgeType' => $r['badge_type'] ?: '',
            ];
        }, $rows);
        respond(['success' => true, 'data' => $out]);
        break;
    }

    case 'kategori_list': {
        requireAuth(['owner', 'admin']);
        respond(['success' => true, 'data' => $pdo->query("SELECT * FROM kategori ORDER BY nama")->fetchAll()]);
        break;
    }

    case 'produk_simpan': {
        $auth = requireAuth(['owner', 'admin']);
        $in = getInput();
        $nama = trim($in['nama'] ?? '');
        $kategori_id = (int)($in['kategori_id'] ?? 0);
        $emoji = trim($in['emoji'] ?? '') ?: '🥩';
        $harga = (float)($in['harga'] ?? 0);
        $satuan = trim($in['satuan'] ?? '/kg');
        $stok = (int)($in['stok'] ?? 0);
        $deskripsi = trim($in['deskripsi'] ?? '');
        $badge = trim($in['badge'] ?? '') ?: null;
        $badge_type = trim($in['badge_type'] ?? '');
        $status = ($in['status'] ?? 'aktif') === 'nonaktif' ? 'nonaktif' : 'aktif';
        // foto: bisa data URL base64 atau URL gambar. Jika tidak dikirim, jangan ubah foto lama.
        $fotoDikirim = array_key_exists('foto', $in);
        $foto = $fotoDikirim ? ($in['foto'] ?: null) : null;

        if (!$nama || !$kategori_id) respond(['success' => false, 'message' => 'Nama produk dan kategori wajib diisi.'], 400);

        if (!empty($in['id'])) {
            // update
            if ($fotoDikirim) {
                $stmt = $pdo->prepare("UPDATE produk SET nama=?, kategori_id=?, emoji=?, foto=?, harga=?, satuan=?, stok=?, deskripsi=?, badge=?, badge_type=?, status=? WHERE id=?");
                $stmt->execute([$nama, $kategori_id, $emoji, $foto, $harga, $satuan, $stok, $deskripsi, $badge, $badge_type, $status, (int)$in['id']]);
            } else {
                // tidak ada foto baru → biarkan foto lama
                $stmt = $pdo->prepare("UPDATE produk SET nama=?, kategori_id=?, emoji=?, harga=?, satuan=?, stok=?, deskripsi=?, badge=?, badge_type=?, status=? WHERE id=?");
                $stmt->execute([$nama, $kategori_id, $emoji, $harga, $satuan, $stok, $deskripsi, $badge, $badge_type, $status, (int)$in['id']]);
            }
            logActivity($pdo, $auth['uid'], 'Update produk', $nama);
            respond(['success' => true, 'message' => 'Produk berhasil diperbarui.', 'id' => (int)$in['id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO produk (nama, kategori_id, emoji, foto, harga, satuan, stok, deskripsi, badge, badge_type, status) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$nama, $kategori_id, $emoji, $foto, $harga, $satuan, $stok, $deskripsi, $badge, $badge_type, $status]);
            $newId = $pdo->lastInsertId();
            logActivity($pdo, $auth['uid'], 'Tambah produk', $nama);
            respond(['success' => true, 'message' => 'Produk berhasil ditambahkan.', 'id' => (int)$newId]);
        }
        break;
    }

    case 'produk_harga_update': {
        // ubah harga cepat dari tabel produk tanpa buka form penuh
        $auth = requireAuth(['owner', 'admin']);
        $in = getInput();
        $id = (int)($in['id'] ?? 0);
        $harga = (float)($in['harga'] ?? -1);
        if (!$id || $harga < 0) respond(['success' => false, 'message' => 'Data tidak valid.'], 400);
        $pdo->prepare("UPDATE produk SET harga = ? WHERE id = ?")->execute([$harga, $id]);
        logActivity($pdo, $auth['uid'], 'Update harga', "Produk #$id -> $harga");
        respond(['success' => true, 'message' => 'Harga berhasil diperbarui.']);
        break;
    }

    case 'produk_foto_update': {
        // ubah foto cepat (kirim data URL base64 atau URL gambar)
        $auth = requireAuth(['owner', 'admin']);
        $in = getInput();
        $id = (int)($in['id'] ?? 0);
        if (!$id || !array_key_exists('foto', $in)) respond(['success' => false, 'message' => 'Data tidak valid.'], 400);
        $foto = $in['foto'] ?: null;
        $pdo->prepare("UPDATE produk SET foto = ? WHERE id = ?")->execute([$foto, $id]);
        logActivity($pdo, $auth['uid'], 'Update foto produk', "Produk #$id");
        respond(['success' => true, 'message' => 'Foto berhasil diperbarui.']);
        break;
    }

    case 'produk_hapus': {
        $auth = requireAuth(['owner', 'admin']);
        $in = getInput();
        $id = (int)($in['id'] ?? 0);
        if (!$id) respond(['success' => false, 'message' => 'ID produk tidak valid.'], 400);
        $stmt = $pdo->prepare("DELETE FROM produk WHERE id = ?");
        $stmt->execute([$id]);
        logActivity($pdo, $auth['uid'], 'Hapus produk', "ID #$id");
        respond(['success' => true, 'message' => 'Produk berhasil dihapus.']);
        break;
    }

    case 'produk_stok_update': {
        // update stok cepat (misal habis restock) tanpa harus buka form penuh
        $auth = requireAuth(['owner', 'admin']);
        $in = getInput();
        $id = (int)($in['id'] ?? 0);
        $stok = (int)($in['stok'] ?? -1);
        if (!$id || $stok < 0) respond(['success' => false, 'message' => 'Data tidak valid.'], 400);
        $stmt = $pdo->prepare("UPDATE produk SET stok = ? WHERE id = ?");
        $stmt->execute([$stok, $id]);
        logActivity($pdo, $auth['uid'], 'Update stok', "Produk #$id -> $stok");
        respond(['success' => true, 'message' => 'Stok berhasil diperbarui.']);
        break;
    }

    // ═══════════════════════════════════════════
    // PESANAN — Lihat & Kelola
    // ═══════════════════════════════════════════
    case 'pesanan_list': {
        requireAuth(['owner', 'admin']);
        $status = $_GET['status'] ?? '';
        $sql = "SELECT * FROM pesanan";
        $params = [];
        if ($status && $status !== 'semua') {
            $sql .= " WHERE status = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY created_at DESC LIMIT 200";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        respond(['success' => true, 'data' => $stmt->fetchAll()]);
        break;
    }

    case 'pesanan_detail': {
        requireAuth(['owner', 'admin']);
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM pesanan WHERE id = ?");
        $stmt->execute([$id]);
        $pesanan = $stmt->fetch();
        if (!$pesanan) respond(['success' => false, 'message' => 'Pesanan tidak ditemukan.'], 404);

        $stmtItem = $pdo->prepare("SELECT * FROM pesanan_item WHERE pesanan_id = ?");
        $stmtItem->execute([$id]);
        $pesanan['items'] = $stmtItem->fetchAll();

        respond(['success' => true, 'data' => $pesanan]);
        break;
    }

    case 'pesanan_buat': {
        // Endpoint ini dipanggil dari WEBSITE PUBLIK (checkout pelanggan), bukan dashboard.
        // Sengaja tidak requireAuth supaya pelanggan bisa checkout tanpa login admin.
        $in = getInput();
        $nama = trim($in['nama_pelanggan'] ?? '');
        $wa = trim($in['no_wa'] ?? '');
        $alamat = trim($in['alamat'] ?? '');
        $items = $in['items'] ?? [];
        $catatan = trim($in['catatan'] ?? '');
        // metode pembayaran: transfer | qris | cod | wa (default wa)
        $metodeValid = ['transfer', 'qris', 'cod', 'wa'];
        $metode = in_array($in['metode_bayar'] ?? '', $metodeValid) ? $in['metode_bayar'] : 'wa';

        if (!$nama || !$wa || empty($items)) {
            respond(['success' => false, 'message' => 'Nama, no. WhatsApp, dan minimal 1 produk wajib diisi.'], 400);
        }

        $pdo->beginTransaction();
        try {
            $total = 0;
            foreach ($items as $it) {
                $total += (float)$it['harga_satuan'] * (int)$it['qty'];
            }
            $kode = generateKodePesanan($pdo);

            $stmt = $pdo->prepare("INSERT INTO pesanan (kode_pesanan, nama_pelanggan, no_wa, alamat, total, catatan, metode_bayar, status) VALUES (?,?,?,?,?,?,?,'baru')");
            $stmt->execute([$kode, $nama, $wa, $alamat, $total, $catatan, $metode]);
            $pesananId = $pdo->lastInsertId();

            $stmtItem = $pdo->prepare("INSERT INTO pesanan_item (pesanan_id, produk_id, nama_produk, harga_satuan, qty, subtotal) VALUES (?,?,?,?,?,?)");
            foreach ($items as $it) {
                $sub = (float)$it['harga_satuan'] * (int)$it['qty'];
                $stmtItem->execute([$pesananId, $it['produk_id'] ?? null, $it['nama_produk'], $it['harga_satuan'], $it['qty'], $sub]);

                // kurangi stok otomatis jika produk_id valid
                if (!empty($it['produk_id'])) {
                    $pdo->prepare("UPDATE produk SET stok = GREATEST(0, stok - ?) WHERE id = ?")
                        ->execute([(int)$it['qty'], (int)$it['produk_id']]);
                }
            }

            $pdo->commit();
            respond(['success' => true, 'message' => 'Pesanan berhasil dibuat.', 'kode_pesanan' => $kode]);
        } catch (Exception $e) {
            $pdo->rollBack();
            respond(['success' => false, 'message' => 'Gagal membuat pesanan: ' . $e->getMessage()], 500);
        }
        break;
    }

    case 'pesanan_update_status': {
        $auth = requireAuth(['owner', 'admin']);
        $in = getInput();
        $id = (int)($in['id'] ?? 0);
        $status = $in['status'] ?? '';
        $validStatus = ['baru', 'diproses', 'dikirim', 'selesai', 'batal'];
        if (!$id || !in_array($status, $validStatus)) respond(['success' => false, 'message' => 'Data tidak valid.'], 400);

        $stmt = $pdo->prepare("UPDATE pesanan SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        logActivity($pdo, $auth['uid'], 'Update status pesanan', "Pesanan #$id -> $status");
        respond(['success' => true, 'message' => 'Status pesanan berhasil diperbarui.']);
        break;
    }

    // ═══════════════════════════════════════════
    // MEMBER / PELANGGAN
    // ═══════════════════════════════════════════
    case 'member_list': {
        requireAuth(['owner', 'admin']);
        $rows = $pdo->query("
            SELECT u.id, u.nama, u.email, u.no_wa, u.status, u.created_at,
                   COUNT(p.id) as total_pesanan,
                   COALESCE(SUM(CASE WHEN p.status != 'batal' THEN p.total ELSE 0 END),0) as total_belanja
            FROM users u
            LEFT JOIN pesanan p ON p.user_id = u.id
            WHERE u.role = 'member'
            GROUP BY u.id
            ORDER BY u.created_at DESC
        ")->fetchAll();
        respond(['success' => true, 'data' => $rows]);
        break;
    }

    case 'member_daftar': {
        // Dipanggil dari form "Daftar" di website publik (bukan dashboard admin)
        $in = getInput();
        $nama = trim($in['nama'] ?? '');
        $email = trim($in['email'] ?? '');
        $wa = trim($in['no_wa'] ?? '');
        $password = $in['password'] ?? '';

        if (!$nama || !$email || !$password) respond(['success' => false, 'message' => 'Nama, email, dan password wajib diisi.'], 400);

        $cek = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $cek->execute([$email]);
        if ($cek->fetch()) respond(['success' => false, 'message' => 'Email sudah terdaftar.'], 409);

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (nama, email, password, no_wa, role, status) VALUES (?,?,?,?,'member','aktif')");
        $stmt->execute([$nama, $email, $hash, $wa]);

        respond(['success' => true, 'message' => 'Pendaftaran berhasil! Silakan login.']);
        break;
    }

    case 'member_update_status': {
        $auth = requireAuth(['owner', 'admin']);
        $in = getInput();
        $id = (int)($in['id'] ?? 0);
        $status = ($in['status'] ?? '') === 'nonaktif' ? 'nonaktif' : 'aktif';
        if (!$id) respond(['success' => false, 'message' => 'ID tidak valid.'], 400);
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'member'");
        $stmt->execute([$status, $id]);
        logActivity($pdo, $auth['uid'], 'Update status member', "Member #$id -> $status");
        respond(['success' => true, 'message' => 'Status member berhasil diperbarui.']);
        break;
    }

    // ═══════════════════════════════════════════
    // LAPORAN PENJUALAN
    // ═══════════════════════════════════════════
    case 'laporan_penjualan': {
        requireAuth(['owner', 'admin']);
        $dari = $_GET['dari'] ?? date('Y-m-01');
        $sampai = $_GET['sampai'] ?? date('Y-m-d');

        $stmt = $pdo->prepare("
            SELECT DATE(created_at) as tanggal, COUNT(*) as jumlah_pesanan, COALESCE(SUM(total),0) as total_penjualan
            FROM pesanan
            WHERE status != 'batal' AND DATE(created_at) BETWEEN ? AND ?
            GROUP BY DATE(created_at)
            ORDER BY tanggal ASC
        ");
        $stmt->execute([$dari, $sampai]);
        $harian = $stmt->fetchAll();

        $stmtTotal = $pdo->prepare("
            SELECT COUNT(*) as jumlah_pesanan, COALESCE(SUM(total),0) as total_penjualan
            FROM pesanan
            WHERE status != 'batal' AND DATE(created_at) BETWEEN ? AND ?
        ");
        $stmtTotal->execute([$dari, $sampai]);
        $ringkasan = $stmtTotal->fetch();

        $stmtProduk = $pdo->prepare("
            SELECT pi.nama_produk, SUM(pi.qty) as total_qty, SUM(pi.subtotal) as total_omset
            FROM pesanan_item pi
            JOIN pesanan p ON p.id = pi.pesanan_id
            WHERE p.status != 'batal' AND DATE(p.created_at) BETWEEN ? AND ?
            GROUP BY pi.nama_produk
            ORDER BY total_omset DESC
        ");
        $stmtProduk->execute([$dari, $sampai]);
        $perProduk = $stmtProduk->fetchAll();

        respond(['success' => true, 'data' => [
            'periode' => ['dari' => $dari, 'sampai' => $sampai],
            'ringkasan' => $ringkasan,
            'harian' => $harian,
            'per_produk' => $perProduk,
        ]]);
        break;
    }

    // Catat aktivitas ekspor laporan (Excel/PDF) ke database → tersimpan di log_aktivitas
    case 'laporan_export_log': {
        $u = requireAuth(['owner', 'admin']);
        $in = getInput();
        $format = strtoupper(trim($in['format'] ?? '-'));
        $dari   = trim($in['dari'] ?? '');
        $sampai = trim($in['sampai'] ?? '');
        $omset  = (int)($in['total_omset'] ?? 0);
        $jml    = (int)($in['jumlah_pesanan'] ?? 0);
        $detail = "Periode $dari s/d $sampai • Omset Rp " . number_format($omset, 0, ',', '.') . " • $jml pesanan";
        logActivity($pdo, $u['uid'], "Ekspor Laporan $format", $detail);
        respond(['success' => true]);
        break;
    }

    // ═══════════════════════════════════════════
    // LOG AKTIVITAS (audit trail, khusus owner)
    // ═══════════════════════════════════════════
    case 'log_list': {
        requireAuth(['owner']);
        $rows = $pdo->query("
            SELECT l.*, u.nama as user_nama, u.role as user_role
            FROM log_aktivitas l LEFT JOIN users u ON u.id = l.user_id
            ORDER BY l.created_at DESC LIMIT 100
        ")->fetchAll();
        respond(['success' => true, 'data' => $rows]);
        break;
    }

    // ═══════════════════════════════════════════
    // KELOLA STAFF (khusus owner: tambah/nonaktifkan admin)
    // ═══════════════════════════════════════════
    case 'staff_list': {
        requireAuth(['owner']);
        respond(['success' => true, 'data' => $pdo->query("SELECT id, nama, email, role, status, created_at FROM users WHERE role IN ('owner','admin') ORDER BY created_at")->fetchAll()]);
        break;
    }

    case 'staff_tambah': {
        $auth = requireAuth(['owner']);
        $in = getInput();
        $nama = trim($in['nama'] ?? '');
        $email = trim($in['email'] ?? '');
        $password = $in['password'] ?? '';
        $role = ($in['role'] ?? '') === 'owner' ? 'owner' : 'admin';

        if (!$nama || !$email || !$password) respond(['success' => false, 'message' => 'Lengkapi semua data.'], 400);

        $cek = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $cek->execute([$email]);
        if ($cek->fetch()) respond(['success' => false, 'message' => 'Email sudah digunakan.'], 409);

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (nama, email, password, role, status) VALUES (?,?,?,?,'aktif')");
        $stmt->execute([$nama, $email, $hash, $role]);
        logActivity($pdo, $auth['uid'], 'Tambah staff', "$nama ($role)");
        respond(['success' => true, 'message' => 'Staff berhasil ditambahkan.']);
        break;
    }

    case 'staff_update_status': {
        $auth = requireAuth(['owner']);
        $in = getInput();
        $id = (int)($in['id'] ?? 0);
        $status = ($in['status'] ?? '') === 'nonaktif' ? 'nonaktif' : 'aktif';
        if (!$id) respond(['success' => false, 'message' => 'ID tidak valid.'], 400);
        if ($id === (int)$auth['uid']) respond(['success' => false, 'message' => 'Tidak bisa menonaktifkan akun sendiri.'], 400);
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ? AND role IN ('owner','admin')");
        $stmt->execute([$status, $id]);
        logActivity($pdo, $auth['uid'], 'Update status staff', "Staff #$id -> $status");
        respond(['success' => true, 'message' => 'Status staff berhasil diperbarui.']);
        break;
    }

    // ═══════════════════════════════════════════
    // PENGATURAN PEMBAYARAN
    // ═══════════════════════════════════════════
    case 'pengaturan_get': {
        // Endpoint PUBLIK — dipakai website untuk menampilkan info pembayaran
        // (rekening bank, gambar QRIS, metode yang aktif) di keranjang.
        $rows = $pdo->query("SELECT nama, nilai FROM pengaturan")->fetchAll();
        $cfg = [];
        foreach ($rows as $r) { $cfg[$r['nama']] = $r['nilai']; }
        respond([
            'success' => true,
            'data' => [
                'bank_nama'      => $cfg['bank_nama'] ?? '',
                'bank_rekening'  => $cfg['bank_rekening'] ?? '',
                'bank_atas_nama' => $cfg['bank_atas_nama'] ?? '',
                'qris_gambar'    => $cfg['qris_gambar'] ?? '',
                'logo_gambar'    => $cfg['logo_gambar'] ?? '',
                'bayar_transfer' => ($cfg['bayar_transfer'] ?? '1') === '1',
                'bayar_qris'     => ($cfg['bayar_qris'] ?? '1') === '1',
                'bayar_cod'      => ($cfg['bayar_cod'] ?? '1') === '1',
                'bayar_wa'       => ($cfg['bayar_wa'] ?? '1') === '1',
            ]
        ]);
        break;
    }

    case 'pengaturan_simpan': {
        $auth = requireAuth(['owner', 'admin']);
        $in = getInput();
        // Hanya key yang dikenal yang boleh disimpan
        $allowed = ['bank_nama', 'bank_rekening', 'bank_atas_nama', 'qris_gambar', 'logo_gambar',
                    'bayar_transfer', 'bayar_qris', 'bayar_cod', 'bayar_wa'];
        $stmt = $pdo->prepare("INSERT INTO pengaturan (nama, nilai) VALUES (?, ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)");
        foreach ($allowed as $key) {
            if (array_key_exists($key, $in)) {
                $val = $in[$key];
                // boolean toggle disimpan sebagai '1'/'0'
                if (in_array($key, ['bayar_transfer', 'bayar_qris', 'bayar_cod', 'bayar_wa'])) {
                    $val = ($val === true || $val === '1' || $val === 1) ? '1' : '0';
                }
                $stmt->execute([$key, (string)$val]);
            }
        }
        logActivity($pdo, $auth['uid'], 'Update pengaturan pembayaran', '');
        respond(['success' => true, 'message' => 'Pengaturan pembayaran berhasil disimpan.']);
        break;
    }

    // ═══════════════════════════════════════════
    // ARTIKEL (blog / edukasi)
    // ═══════════════════════════════════════════
    case 'artikel_publik': {
        // PUBLIK — dipakai website untuk menampilkan daftar artikel
        $rows = $pdo->query("
            SELECT id, judul, tag, emoji, gambar, ringkasan, isi, waktu_baca, created_at
            FROM artikel WHERE status = 'publish'
            ORDER BY created_at DESC, id DESC
        ")->fetchAll();
        respond(['success' => true, 'data' => $rows]);
        break;
    }

    case 'artikel_list': {
        // ADMIN — semua artikel termasuk draft
        requireAuth(['owner', 'admin']);
        $rows = $pdo->query("
            SELECT id, judul, tag, emoji, gambar, ringkasan, isi, waktu_baca, status, created_at
            FROM artikel ORDER BY created_at DESC, id DESC
        ")->fetchAll();
        respond(['success' => true, 'data' => $rows]);
        break;
    }

    case 'artikel_simpan': {
        $auth = requireAuth(['owner', 'admin']);
        $in = getInput();
        $judul = trim($in['judul'] ?? '');
        $tag = trim($in['tag'] ?? '');
        $emoji = trim($in['emoji'] ?? '') ?: '📰';
        $ringkasan = trim($in['ringkasan'] ?? '');
        $isi = trim($in['isi'] ?? '');
        $waktu_baca = trim($in['waktu_baca'] ?? '');
        $status = ($in['status'] ?? 'publish') === 'draft' ? 'draft' : 'publish';
        // gambar: bisa data URL base64 / URL. Jika tidak dikirim, jangan ubah gambar lama.
        $gambarDikirim = array_key_exists('gambar', $in);
        $gambar = $gambarDikirim ? ($in['gambar'] ?: null) : null;

        if (!$judul) respond(['success' => false, 'message' => 'Judul artikel wajib diisi.'], 400);

        if (!empty($in['id'])) {
            if ($gambarDikirim) {
                $stmt = $pdo->prepare("UPDATE artikel SET judul=?, tag=?, emoji=?, gambar=?, ringkasan=?, isi=?, waktu_baca=?, status=? WHERE id=?");
                $stmt->execute([$judul, $tag, $emoji, $gambar, $ringkasan, $isi, $waktu_baca, $status, (int)$in['id']]);
            } else {
                $stmt = $pdo->prepare("UPDATE artikel SET judul=?, tag=?, emoji=?, ringkasan=?, isi=?, waktu_baca=?, status=? WHERE id=?");
                $stmt->execute([$judul, $tag, $emoji, $ringkasan, $isi, $waktu_baca, $status, (int)$in['id']]);
            }
            logActivity($pdo, $auth['uid'], 'Update artikel', $judul);
            respond(['success' => true, 'message' => 'Artikel berhasil diperbarui.', 'id' => (int)$in['id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO artikel (judul, tag, emoji, gambar, ringkasan, isi, waktu_baca, status) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([$judul, $tag, $emoji, $gambar, $ringkasan, $isi, $waktu_baca, $status]);
            $newId = $pdo->lastInsertId();
            logActivity($pdo, $auth['uid'], 'Tambah artikel', $judul);
            respond(['success' => true, 'message' => 'Artikel berhasil ditambahkan.', 'id' => (int)$newId]);
        }
        break;
    }

    case 'artikel_hapus': {
        $auth = requireAuth(['owner', 'admin']);
        $in = getInput();
        $id = (int)($in['id'] ?? 0);
        if (!$id) respond(['success' => false, 'message' => 'ID artikel tidak valid.'], 400);
        $pdo->prepare("DELETE FROM artikel WHERE id = ?")->execute([$id]);
        logActivity($pdo, $auth['uid'], 'Hapus artikel', "ID #$id");
        respond(['success' => true, 'message' => 'Artikel berhasil dihapus.']);
        break;
    }

    default:
        respond(['success' => false, 'message' => 'Aksi tidak dikenal: ' . $action], 404);
}

} catch (Throwable $e) {
    respond(['success' => false, 'message' => 'Terjadi kesalahan server: ' . $e->getMessage()], 500);
}
