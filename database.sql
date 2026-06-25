-- ════════════════════════════════════════════════════════════
-- MITLOIN — SKEMA DATABASE + DATA AWAL
-- Import file ini lewat phpMyAdmin (tab "Import").
-- ════════════════════════════════════════════════════════════

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─────────────────────────────────────────────
-- TABEL: kategori
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS kategori (
    id     INT AUTO_INCREMENT PRIMARY KEY,
    slug   VARCHAR(40)  NOT NULL UNIQUE,
    nama   VARCHAR(100) NOT NULL,
    emoji  VARCHAR(10)  DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
-- TABEL: users (owner / admin / member)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nama       VARCHAR(120) NOT NULL,
    email      VARCHAR(160) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    no_wa      VARCHAR(30)  DEFAULT '',
    role       ENUM('owner','admin','member') NOT NULL DEFAULT 'member',
    status     ENUM('aktif','nonaktif')       NOT NULL DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
-- TABEL: produk
-- (foto disimpan sebagai data URL base64 / atau URL gambar biasa)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS produk (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nama        VARCHAR(160) NOT NULL,
    kategori_id INT,
    emoji       VARCHAR(10)  DEFAULT '🥩',
    foto        MEDIUMTEXT,
    harga       DECIMAL(12,2) NOT NULL DEFAULT 0,
    satuan      VARCHAR(20)  DEFAULT '/kg',
    stok        INT          NOT NULL DEFAULT 0,
    deskripsi   TEXT,
    badge       VARCHAR(60)  DEFAULT NULL,
    badge_type  VARCHAR(30)  DEFAULT '',
    status      ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kategori_id) REFERENCES kategori(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
-- TABEL: pesanan
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS pesanan (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    kode_pesanan   VARCHAR(40) NOT NULL UNIQUE,
    user_id        INT DEFAULT NULL,
    nama_pelanggan VARCHAR(120) NOT NULL,
    no_wa          VARCHAR(30)  NOT NULL,
    alamat         TEXT,
    total          DECIMAL(12,2) NOT NULL DEFAULT 0,
    catatan        TEXT,
    metode_bayar   VARCHAR(20)  NOT NULL DEFAULT 'wa',
    status         ENUM('baru','diproses','dikirim','selesai','batal') NOT NULL DEFAULT 'baru',
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
-- TABEL: pesanan_item
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS pesanan_item (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    pesanan_id   INT NOT NULL,
    produk_id    INT DEFAULT NULL,
    nama_produk  VARCHAR(160) NOT NULL,
    harga_satuan DECIMAL(12,2) NOT NULL,
    qty          INT NOT NULL,
    subtotal     DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (pesanan_id) REFERENCES pesanan(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
-- TABEL: log_aktivitas (audit trail)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS log_aktivitas (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT DEFAULT NULL,
    aksi       VARCHAR(120) NOT NULL,
    detail     VARCHAR(255) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
-- TABEL: pengaturan (key-value, mis. konfigurasi pembayaran)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS pengaturan (
    nama  VARCHAR(50) PRIMARY KEY,
    nilai MEDIUMTEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ════════════════════════════════════════════════════════════
-- DATA AWAL
-- ════════════════════════════════════════════════════════════

-- Kategori
INSERT INTO kategori (id, slug, nama, emoji) VALUES
 (1,'sapi','Daging Sapi','🥩'),
 (2,'ayam','Ayam & Bebek','🍗'),
 (3,'seafood','Seafood','🦐'),
 (4,'olahan','Produk Olahan','🌭'),
 (6,'premium','Daging Premium','🍖'),
 (7,'pokok','Bahan Pokok','🌾'),
 (8,'kering','Bahan Kering','🫙'),
 (9,'saos','Saos','🥫'),
 (10,'minuman','Bahan Minuman','🥤');

-- Akun default (password keduanya: mitloin2026 — GANTI setelah login!)
INSERT INTO users (nama, email, password, no_wa, role, status) VALUES
 ('Owner Mitloin','owner@mitloin.com','$2y$12$MDJQvAA6wDKrgs8jhWOYZ.kmx6HA9Cr4msUJ4Bk2VpRMo8PSe3nhu','6285173308990','owner','aktif'),
 ('Admin Mitloin','admin@mitloin.com','$2y$12$MDJQvAA6wDKrgs8jhWOYZ.kmx6HA9Cr4msUJ4Bk2VpRMo8PSe3nhu','6285173308990','admin','aktif');

-- Produk awal (sinkron dgn katalog website)
INSERT INTO produk (nama, kategori_id, emoji, harga, satuan, stok, deskripsi, badge, badge_type, status) VALUES
 ('Ribeye Wagyu Grade A',6,'🥩',285000,'/250g',25,'Marbling indah, lembut, kaya rasa umami. Perfect untuk pan-seared steak.','Best Seller','',  'aktif'),
 ('Tenderloin Sapi Lokal',6,'🥩',165000,'/300g',30,'Potongan paling empuk dari sapi lokal pilihan. Cocok untuk steak atau tumis.',NULL,'','aktif'),
 ('Sirloin Premium Aus',6,'🥩',195000,'/300g',20,'Impor Australia, tekstur padat berasa dengan lemak yang seimbang.','Premium','','aktif'),
 ('Daging Giling Sapi',1,'🥩',75000,'/500g',40,'Digiling segar setiap hari. Ideal untuk burger, bakso, atau bolognese.',NULL,'','aktif'),
 ('Brisket Asap Ready Cook',1,'🥩',125000,'/500g',15,'Pre-marinated brisket siap dimasak. Tinggal bakar atau kukus, langsung lezat!','New','','aktif'),
 ('Short Rib (Iga Pendek)',6,'🦴',145000,'/500g',18,'Iga pendek sapi dengan daging tebal. Sempurna untuk sup atau braised ribs.',NULL,'','aktif'),
 ('Ayam Kampung Utuh',2,'🍗',55000,'/ekor',35,'Ayam kampung segar dipilih hari ini. Tekstur kenyal, rasa autentik.','Segar','','aktif'),
 ('Chicken Breast Fillet',2,'🍗',45000,'/500g',50,'Fillet dada ayam tanpa tulang, rendah lemak, tinggi protein. Untuk gym & diet.','Best Seller','','aktif'),
 ('Chicken Wings Premium',2,'🍗',38000,'/500g',45,'Sayap ayam jumbo untuk BBQ, fried chicken, atau buffalo wings.',NULL,'','aktif'),
 ('Ceker Ayam Segar',2,'🐾',22000,'/500g',60,'Ceker segar bersih, cocok untuk sup kolagen atau dimsum.',NULL,'','aktif'),
 ('Ayam Broiler Utuh',2,'🍗',38000,'/ekor',40,'Ayam broiler segar utuh, daging tebal dan empuk. Cocok untuk ungkep, goreng, atau bakar.',NULL,'','aktif'),
 ('Dada Bebek Fillet',2,'🦆',78000,'/500g',20,'Fillet dada bebek boneless, tekstur padat dan gurih khas bebek. Premium untuk steak atau panggang.','Bebek','gold','aktif'),
 ('Drumstick Ayam (Paha Bawah)',2,'🍗',42000,'/500g',45,'Paha bawah ayam (drumstick) segar, juicy. Favorit untuk fried chicken & BBQ.',NULL,'','aktif'),
 ('Paha Ayam Utuh',2,'🍗',40000,'/500g',38,'Paha ayam utuh (atas + bawah) segar, daging tebal. Mantap untuk ungkep & ayam bakar.',NULL,'','aktif'),
 ('Udang Vaname Segar',3,'🦐',95000,'/500g',28,'Udang segar size 30, langsung dari tambak. Manis dan segar tanpa amis.','Segar','','aktif'),
 ('Salmon Fillet Impor',3,'🐟',185000,'/300g',22,'Salmon Atlantik impor dengan kandungan omega-3 tinggi. Siap sashimi atau panggang.','Premium','','aktif'),
 ('Cumi-Cumi Segar',3,'🦑',65000,'/500g',30,'Cumi segar berukuran sedang, bersih. Cocok untuk calamari, sambal, atau sautéed.',NULL,'','aktif'),
 ('Ikan Gurame Hidup',3,'🐠',55000,'/ekor',24,'Gurame hidup ukuran 600-700g, segar. Sempurna untuk bakar atau steam.',NULL,'','aktif'),
 ('Bakso Sapi Premium',4,'🍡',48000,'/pack',40,'Bakso sapi homemade tanpa pengawet, bouncy dan kenyal. Isi 20 biji.','Halal','','aktif'),
 ('Sosis Sapi Homemade',4,'🌭',52000,'/pack',38,'Sosis sapi hand-made tanpa MSG, rasa daging asli. Isi 6 pcs.','New','','aktif'),
 ('Dendeng Sapi Balado',4,'🥩',85000,'/250g',26,'Dendeng sapi tipis crispy dengan bumbu balado pedas manis khas Minang.','Best Seller','','aktif');

-- Pengaturan pembayaran (diisi/diubah dari dashboard → menu Pembayaran)
INSERT INTO pengaturan (nama, nilai) VALUES
 ('bank_nama','Bank BCA'),
 ('bank_rekening','1234567890'),
 ('bank_atas_nama','PT Mitloin Global Group'),
 ('qris_gambar',''),
 ('bayar_transfer','1'),
 ('bayar_qris','1'),
 ('bayar_cod','1'),
 ('bayar_wa','1')
ON DUPLICATE KEY UPDATE nilai = VALUES(nilai);
