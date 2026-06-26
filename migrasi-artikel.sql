-- ════════════════════════════════════════════════════════════
-- MIGRASI: Tambah fitur ARTIKEL ke database yang SUDAH ada
-- Jalankan di phpMyAdmin → pilih database mitloinc_mitloin → tab SQL → tempel → Go
-- Aman dijalankan: tabel hanya dibuat bila belum ada.
-- ════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS artikel (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    judul       VARCHAR(255) NOT NULL,
    tag         VARCHAR(80)  DEFAULT '',
    emoji       VARCHAR(16)  DEFAULT '📰',
    gambar      MEDIUMTEXT,
    ringkasan   TEXT,
    isi         MEDIUMTEXT,
    waktu_baca  VARCHAR(40)  DEFAULT '',
    status      ENUM('publish','draft') DEFAULT 'publish',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Isi 4 artikel awal (hanya kalau tabel masih kosong)
INSERT INTO artikel (judul, tag, emoji, ringkasan, isi, waktu_baca, status)
SELECT * FROM (
  SELECT
   'Panduan Lengkap Memilih Steak yang Sempurna: Grade, Marbling, dan Cara Masak' AS judul,
   'Tips Memasak' AS tag, '🥩' AS emoji,
   'Memilih potongan steak yang tepat bisa membuat perbedaan besar pada hasil masakan. Dari ribeye hingga tenderloin, pelajari cara memilih yang terbaik untuk kebutuhan Anda.' AS ringkasan,
   'Steak yang sempurna dimulai dari pemilihan potongan yang tepat. Ribeye dikenal dengan marbling (serat lemak) yang kaya sehingga juicy dan beraroma kuat. Tenderloin adalah potongan paling empuk namun lebih sedikit lemak. Sirloin memberi keseimbangan antara rasa dan tekstur. Perhatikan grade daging: semakin tinggi marbling, semakin lembut dan gurih.' AS isi,
   '8 menit baca' AS waktu_baca, 'publish' AS status
  UNION ALL SELECT '5 Resep Ayam Premium yang Mudah Dibuat di Rumah','Resep','🍗',
   'Dari ayam panggang herbal hingga sup krim ayam mushroom — semua bisa dibuat dengan bahan dari Mitloin.',
   'Ayam premium memberi hasil masakan yang jauh lebih lezat. Coba: ayam panggang herbal, sup krim ayam mushroom, chicken katsu, ayam bakar madu, dan sup ayam kampung. Gunakan dada fillet untuk rendah lemak dan paha untuk rasa lebih juicy.',
   '5 menit baca','publish'
  UNION ALL SELECT 'Cara Menyimpan Seafood Segar Agar Tahan Lama','Pengetahuan','🦐',
   'Teknik penyimpanan yang benar bisa memperpanjang kesegaran seafood hingga 2x lebih lama. Simak tipsnya!',
   'Cuci bersih, tiriskan, lalu simpan dalam wadah kedap udara. Untuk 1-2 hari simpan di chiller 0-4°C. Untuk jangka panjang bekukan pada -18°C dalam porsi kecil agar mudah dipakai.',
   '4 menit baca','publish'
  UNION ALL SELECT 'Mengapa Restoran Top Jakarta Memilih Mitloin sebagai Supplier Utama','Bisnis Kuliner','🌭',
   'Konsistensi kualitas, layanan custom cut, dan jaminan kesegaran menjadi alasan utama resto-resto premium mempercayai Mitloin.',
   'Restoran premium membutuhkan supplier yang konsisten. Mitloin menyediakan daging berkualitas terjaga, layanan custom cut, serta pengiriman tepat waktu dengan rantai dingin terjaga.',
   '6 menit baca','publish'
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM artikel LIMIT 1);
