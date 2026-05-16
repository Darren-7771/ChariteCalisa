CREATE DATABASE IF NOT EXISTS charite_calisa
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE charite_calisa;


CREATE TABLE pengelola (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    nama_kantor   VARCHAR(200)  NOT NULL,
    email         VARCHAR(150)  NOT NULL UNIQUE,
    no_telp       VARCHAR(20)   NOT NULL,
    alamat        TEXT          NOT NULL,
    username      VARCHAR(100)  NOT NULL UNIQUE,
    password      VARCHAR(255)  NOT NULL,
    created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


CREATE TABLE donatur (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    nama          VARCHAR(150)  NOT NULL,
    email         VARCHAR(150)  NOT NULL UNIQUE,
    no_telp       VARCHAR(20)   NOT NULL,
    username      VARCHAR(100)  NOT NULL UNIQUE,
    password      VARCHAR(255)  NOT NULL,
    created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE kampanye (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    pengelola_id      INT           NOT NULL,
    judul             VARCHAR(300)  NOT NULL,
    kategori          ENUM('bencana','pendidikan','kesehatan','lingkungan','fasilitas') NOT NULL,
    lokasi            VARCHAR(200)  NOT NULL,
    deskripsi         TEXT,
    target_dana       DECIMAL(15,2) NOT NULL,
    collected_amount  DECIMAL(15,2) NOT NULL DEFAULT 0,
    tanggal_mulai     DATE          NOT NULL,
    tanggal_selesai   DATE          NOT NULL,
    gambar_path       VARCHAR(500),
    status            ENUM('aktif','selesai','nonaktif') NOT NULL DEFAULT 'aktif',
    created_at        TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pengelola_id) REFERENCES pengelola(id) ON DELETE CASCADE
) ENGINE=InnoDB;



CREATE TABLE rekening (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    kampanye_id     INT          NOT NULL,
    nama_bank       VARCHAR(100) NOT NULL,
    nomor_rekening  VARCHAR(50)  NOT NULL,
    atas_nama       VARCHAR(200) NOT NULL,
    FOREIGN KEY (kampanye_id) REFERENCES kampanye(id) ON DELETE CASCADE
) ENGINE=InnoDB;



CREATE TABLE donasi (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    donatur_id          INT           NOT NULL,
    kampanye_id         INT           NOT NULL,
    nominal             DECIMAL(15,2) NOT NULL,
    metode_pembayaran   VARCHAR(50)   NOT NULL,
    pesan               TEXT,
    bukti_transfer_path VARCHAR(500)  NOT NULL,
    status              ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
    created_at          TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    verified_at         TIMESTAMP     NULL DEFAULT NULL,
    FOREIGN KEY (donatur_id)  REFERENCES donatur(id),
    FOREIGN KEY (kampanye_id) REFERENCES kampanye(id)
) ENGINE=InnoDB;



INSERT INTO pengelola (nama_kantor, email, no_telp, alamat, username, password) VALUES
(
    'Badan Amil Zakat Nasional',
    'baznas@example.com',
    '021-1234567',
    'Jl. Kebon Sirih No.57, Jakarta Pusat',
    'baznas',
    '$2y$10$GANTIDENGANHASHBCRYPT.baznas'
),
(
    'Human Initiative',
    'humaninitiative@example.com',
    '021-9876543',
    'Jl. Raya Pasar Minggu No.36, Jakarta Selatan',
    'humaninitiative',
    '$2y$10$GANTIDENGANHASHBCRYPT.human'
),
(
    'Disaster Management Center Dompet Dhuafa',
    'dmc@example.com',
    '021-5551234',
    'Jl. Ir. H. Juanda No.50, Ciputat, Tangerang Selatan',
    'dompetdhuafa',
    '$2y$10$GANTIDENGANHASHBCRYPT.dompet'
),
(
    'Nyalakan Harapan',
    'nyalakanharapan@example.com',
    '022-3334444',
    'Jl. Asia Afrika No.8, Bandung',
    'nyalakanharapan',
    '$2y$10$GANTIDENGANHASHBCRYPT.nyala'
);



INSERT INTO donatur (nama, email, no_telp, username, password) VALUES
(
    'Budi Santoso',
    'budi@example.com',
    '08123456789',
    'budisantoso',
    '$2y$10$GANTIDENGANHASHBCRYPT.budi'
),
(
    'Siti Rahayu',
    'siti@example.com',
    '08234567890',
    'sitirahayu',
    '$2y$10$GANTIDENGANHASHBCRYPT.siti'
);



INSERT INTO kampanye
    (pengelola_id, judul, kategori, lokasi, deskripsi, target_dana, collected_amount, tanggal_mulai, tanggal_selesai, gambar_path, status)
VALUES
(
    1,
    'Bantu Korban Bencana Alam di Sumatera',
    'bencana',
    'Aceh, Sumatera Barat, dan Sumatera Utara',
    'Di penghujung tahun ini, Indonesia kembali diuji dengan bencana alam yang melanda Aceh, Sumatera Utara, dan Sumatera Barat. Ribuan saudara kita kehilangan rumah, akses kehidupan terputus, dan terpaksa bertahan di pengungsian. Merespons kondisi darurat ini, BAZNAS melalui Tim BAZNAS Tanggap Bencana (BTB) terus bergerak di lapangan. Mulai dari evakuasi warga, penyaluran bantuan darurat, penyediaan makanan dan air bersih, layanan kesehatan, hingga dukungan logistik bagi para penyintas.',
    500000000.00,
    390000000.00,
    '2025-11-25',
    DATE_ADD(CURDATE(), INTERVAL 3 DAY),
    'assets/BencanaAlamSumatera.jpg',
    'aktif'
),
(
    1,
    'Bantu Cerdaskan Papua',
    'pendidikan',
    'Papua',
    'Membantu anak-anak Papua mendapatkan akses pendidikan yang layak dan merata. Program ini mencakup penyediaan buku, alat tulis, seragam, dan biaya operasional sekolah di daerah terpencil Papua.',
    300000000.00,
    165000000.00,
    '2025-12-01',
    DATE_ADD(CURDATE(), INTERVAL 32 DAY),
    'assets/PendidikanGratisPapua.png',
    'aktif'
),
(
    2,
    'Bersama drg. Maesa Penuhi Hak Kesehatan bagi Anak-anak Yatim Duafa',
    'kesehatan',
    'DKI Jakarta',
    'Program ini menyediakan layanan kesehatan gigi dan umum bagi anak-anak yatim piatu dari keluarga dhuafa. Berkolaborasi dengan tenaga medis sukarela untuk menjangkau yang paling membutuhkan.',
    600000000.00,
    258000000.00,
    '2026-01-10',
    DATE_ADD(CURDATE(), INTERVAL 45 DAY),
    'assets/DonasiKesehatan.jpg',
    'aktif'
),
(
    3,
    'Sedekah Pohon Mulai 25.000',
    'lingkungan',
    'Jawa Barat',
    'Gerakan menanam pohon untuk menghijaukan kembali lahan kritis di Jawa Barat. Setiap donasi Rp25.000 ditanamkan satu bibit pohon produktif yang akan dirawat oleh komunitas lokal.',
    200000000.00,
    182000000.00,
    '2025-10-01',
    DATE_ADD(CURDATE(), INTERVAL 3 DAY),
    'assets/DonasiLingkungan.jpg',
    'aktif'
),
(
    4,
    'Bangun Jembatan Desa untuk Wilayah Pelosok Negeri',
    'fasilitas',
    'Jawa Barat',
    'Membangun jembatan sederhana namun kokoh untuk menghubungkan desa terpencil di Sukabumi dengan pusat kecamatan. Jembatan ini akan memperlancar akses pendidikan, kesehatan, dan ekonomi warga.',
    200000000.00,
    124000000.00,
    '2026-01-15',
    DATE_ADD(CURDATE(), INTERVAL 20 DAY),
    'assets/DonasiJembatanSukabumi.jpeg',
    'aktif'
),
(
    2,
    'Bantu Warga Cisolok dari Bencana Tanah Bergerak',
    'bencana',
    'Jawa Barat',
    'Warga Cisolok, Sukabumi menghadapi bencana tanah bergerak yang mengancam ratusan rumah. Dana digunakan untuk evakuasi, hunian sementara, dan kebutuhan dasar para pengungsi.',
    300000000.00,
    87000000.00,
    '2026-02-01',
    DATE_ADD(CURDATE(), INTERVAL 60 DAY),
    'assets/DonasiBencanaCisolok.jpg',
    'aktif'
);


INSERT INTO rekening (kampanye_id, nama_bank, nomor_rekening, atas_nama) VALUES
(1, 'Bank BCA',     '686 073 7777',      'Badan Amil Zakat Nasional'),
(1, 'BSI',          '900 005 5740',      'Badan Amil Zakat Nasional'),
(2, 'Bank BCA',     '686 073 7777',      'Badan Amil Zakat Nasional'),
(2, 'BSI',          '900 005 5740',      'Badan Amil Zakat Nasional'),
(3, 'Bank BNI',     '1234 5678',         'Human Initiative'),
(4, 'Bank BRI',     '0987 6543',         'DMC Dompet Dhuafa'),
(5, 'Bank Mandiri', '1400 1234 5678',    'Nyalakan Harapan'),
(6, 'Bank BNI',     '8765 4321',         'Human Initiative');

