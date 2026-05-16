<?php
session_start();
require_once 'config.php';

// =====================================================
// HELPER FUNCTIONS
// =====================================================

function format_rupiah(float $amount): string {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function hitung_hari_sisa(string $tanggal_selesai): int {
    $selesai  = strtotime($tanggal_selesai);
    $sekarang = mktime(0, 0, 0, (int)date('n'), (int)date('j'), (int)date('Y'));
    return (int)floor(($selesai - $sekarang) / 86400);
}

// =====================================================
// LABEL & EMOJI MAP
// =====================================================

$kategori_label = [
    'bencana'    => 'Bencana Alam',
    'pendidikan' => 'Pendidikan',
    'kesehatan'  => 'Kesehatan',
    'lingkungan' => 'Lingkungan',
    'fasilitas'  => 'Fasilitas Umum',
];

$kategori_emoji = [
    'bencana'    => '🚨',
    'pendidikan' => '📚',
    'kesehatan'  => '❤️',
    'lingkungan' => '🌿',
    'fasilitas'  => '🏗️',
];

// =====================================================
// AMBIL ID KAMPANYE DARI URL
// =====================================================

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: index.php');
    exit;
}

// =====================================================
// FETCH DATA KAMPANYE
// =====================================================

$sql = "
    SELECT k.id, k.judul, k.kategori, k.lokasi, k.deskripsi,
           k.target_dana, k.collected_amount,
           k.tanggal_mulai, k.tanggal_selesai,
           k.gambar_path, k.status,
           p.nama_kantor, p.email, p.no_telp, p.alamat
    FROM kampanye k
    JOIN pengelola p ON k.pengelola_id = p.id
    WHERE k.id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id);
$stmt->execute();
$kampanye = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$kampanye) {
    header('Location: index.php');
    exit;
}

// =====================================================
// FETCH REKENING KAMPANYE
// =====================================================

$sql_rek = "SELECT nama_bank, nomor_rekening, atas_nama FROM rekening WHERE kampanye_id = ?";
$stmt_rek = $conn->prepare($sql_rek);
$stmt_rek->bind_param('i', $id);
$stmt_rek->execute();
$rekenings = $stmt_rek->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_rek->close();

// =====================================================
// STATISTIK DONASI
// =====================================================

$sql_stat = "
    SELECT COUNT(*)              AS total_donatur,
           MAX(nominal)          AS donasi_terbesar,
           AVG(nominal)          AS rata_rata
    FROM donasi
    WHERE kampanye_id = ? AND status = 'verified'
";
$stmt_stat = $conn->prepare($sql_stat);
$stmt_stat->bind_param('i', $id);
$stmt_stat->execute();
$stat = $stmt_stat->get_result()->fetch_assoc();
$stmt_stat->close();

// =====================================================
// FORMATTING DATA UNTUK TAMPILAN
// =====================================================

$target      = (float)$kampanye['target_dana'];
$collected   = (float)$kampanye['collected_amount'];
$pct         = ($target > 0) ? min(100, (int)round($collected / $target * 100)) : 0;
$hari_sisa   = hitung_hari_sisa($kampanye['tanggal_selesai']);

$kategori    = $kampanye['kategori'];
$emoji       = $kategori_emoji[$kategori] ?? '📌';
$label       = $kategori_label[$kategori] ?? ucfirst($kategori);

$tanggal_mulai   = date('d F Y', strtotime($kampanye['tanggal_mulai']));
$tanggal_selesai = date('d F Y', strtotime($kampanye['tanggal_selesai']));

$status_kampanye = $kampanye['status'];

$total_donatur   = (int)($stat['total_donatur'] ?? 0);
$donasi_terbesar = (float)($stat['donasi_terbesar'] ?? 0);
$rata_rata       = (float)($stat['rata_rata'] ?? 0);
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($kampanye['judul']) ?> - ChariteCalisa</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>

  <!-- ========== HEADER ========== -->
  <header>
    <div class="header-inner">
      <a href="index.php" class="logo">
        <div class="logo-icon"><img src="assets/LogoChariteCalisaTealDark.png" alt="Logo Charite Calisa"></div>
        <span class="logo-text">Charite<span>Calisa</span></span>
      </a>
      <nav>
        <a href="index.php">Beranda</a>
        <a href="index.php" class="active">Kampanye</a>
        <a href="index.php">Tentang Kami</a>
        <?php if (isset($_SESSION['donatur_id'])): ?>
          <a href="logout.php" class="nav-login-btn">Keluar</a>
        <?php else: ?>
          <a href="login.php" class="nav-login-btn">Masuk / Daftar</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>

  <!-- ========== BREADCRUMB ========== -->
  <div class="detail-breadcrumb">
    <a href="index.php">🏠︎ Beranda</a>
    <span>&rsaquo;</span>
    <a href="index.php">Kampanye</a>
    <span>&rsaquo;</span>
    <span><?= htmlspecialchars($kampanye['judul']) ?></span>
  </div>

  <!-- ========== MAIN CONTENT ========== -->
  <div class="main-content">
    <a href="index.php" class="back-btn">← Kembali ke Daftar Kampanye</a>

    <div class="detail-layout">

      <!-- ====== KOLOM KIRI ====== -->
      <div class="detail-main">

        <!-- Poster -->
        <div class="detail-poster">
          <img src="<?= htmlspecialchars($kampanye['gambar_path']) ?>" alt="Poster <?= htmlspecialchars($kampanye['judul']) ?>">
        </div>

        <!-- Info Utama -->
        <div class="detail-info-block">
          <span class="detail-category-tag"><?= $emoji ?> <?= htmlspecialchars($label) ?></span>
          <h1 class="detail-title"><?= htmlspecialchars($kampanye['judul']) ?></h1>

          <div class="detail-meta-row">
            <div class="detail-meta-item">
              <span class="meta-label">Penyelenggara</span>
              <span class="meta-value">👤 <?= htmlspecialchars($kampanye['nama_kantor']) ?></span>
            </div>
            <div class="detail-meta-item">
              <span class="meta-label">Lokasi</span>
              <span class="meta-value">📍 <?= htmlspecialchars($kampanye['lokasi']) ?></span>
            </div>
            <div class="detail-meta-item">
              <span class="meta-label">Mulai Kampanye</span>
              <span class="meta-value">📅 <?= $tanggal_mulai ?></span>
            </div>
            <div class="detail-meta-item">
              <span class="meta-label">Batas Waktu</span>
              <span class="meta-value <?= $hari_sisa <= 7 ? 'meta-value-danger' : '' ?>">
                ⏰ <?= $tanggal_selesai ?>
              </span>
            </div>
          </div>

          <h2 class="detail-desc-title">Tentang Kampanye Ini</h2>
          <div class="detail-desc">
            <?php
            $paragraphs = explode("\n", trim($kampanye['deskripsi']));
            foreach ($paragraphs as $p):
                $p = trim($p);
                if ($p !== ''):
            ?>
              <p><?= htmlspecialchars($p) ?></p>
            <?php
                endif;
            endforeach;
            ?>
          </div>
        </div>

        <!-- Informasi Rekening -->
        <?php if (!empty($rekenings)): ?>
        <div class="detail-info-block">
          <h2 class="detail-desc-title">Metode Donasi</h2>
          <div class="rekening-list">
            <?php foreach ($rekenings as $rek): ?>
            <div class="rekening-item">
              <div class="rekening-bank"><?= htmlspecialchars($rek['nama_bank']) ?></div>
              <div class="rekening-no"><?= htmlspecialchars($rek['nomor_rekening']) ?></div>
              <div class="rekening-name">a.n <?= htmlspecialchars($rek['atas_nama']) ?></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

      </div>

      <!-- ====== KOLOM KANAN ====== -->
      <div class="detail-sidebar">

        <!-- Progress Donasi -->
        <div class="sidebar-card">
          <h3 class="sidebar-card-title">Progress Penggalangan Dana</h3>

          <div class="progress-big-stats">
            <span class="big-collected"><?= format_rupiah($collected) ?></span>
            <span class="big-pct"><?= $pct ?>%</span>
          </div>

          <div class="progress-big-track">
            <div class="progress-big-fill" style="width:<?= $pct ?>%"></div>
          </div>

          <p class="progress-target-line">Target: <strong><?= format_rupiah($target) ?></strong></p>
          <p class="progress-deadline-line">
            Berakhir: <strong>
              <?= $tanggal_selesai ?>
              <?php if ($hari_sisa === 0): ?>
                (Hari terakhir)
              <?php elseif ($hari_sisa > 0): ?>
                (<?= $hari_sisa ?> hari lagi)
              <?php else: ?>
                (Sudah berakhir)
              <?php endif; ?>
            </strong>
          </p>

          <a href="donasi.php?id=<?= $id ?>" class="btn-donasi-now">💛 Donasi Sekarang</a>
        </div>

        <!-- Statistik Donasi -->
        <div class="sidebar-card">
          <h3 class="sidebar-card-title">Statistik Kampanye</h3>
          <div class="detail-meta-row stat-list">
            <div class="stat-row">
              <span class="stat-label">Total Donatur</span>
              <span class="stat-value"><?= number_format($total_donatur, 0, ',', '.') ?> orang</span>
            </div>
            <div class="stat-row">
              <span class="stat-label">Donasi Terbesar</span>
              <span class="stat-value"><?= $donasi_terbesar > 0 ? format_rupiah($donasi_terbesar) : '-' ?></span>
            </div>
            <div class="stat-row">
              <span class="stat-label">Rata-rata Donasi</span>
              <span class="stat-value"><?= $rata_rata > 0 ? format_rupiah($rata_rata) : '-' ?></span>
            </div>
            <div class="stat-row">
              <span class="stat-label">Status Kampanye</span>
              <?php if ($status_kampanye === 'aktif'): ?>
                <span class="stat-value-success">✅ Aktif</span>
              <?php elseif ($status_kampanye === 'selesai'): ?>
                <span class="stat-value">🏁 Selesai</span>
              <?php else: ?>
                <span class="stat-value">⛔ Nonaktif</span>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Penyelenggara -->
        <div class="sidebar-card">
          <h3 class="sidebar-card-title">Penyelenggara</h3>
          <div class="penyelenggara-row">
            <div class="penyelenggara-icon">
              <img src="assets/LogoChariteCalisaAmberLight.png" alt="logo <?= htmlspecialchars($kampanye['nama_kantor']) ?>">
            </div>
            <div>
              <div class="penyelenggara-name"><?= htmlspecialchars($kampanye['nama_kantor']) ?></div>
              <div class="penyelenggara-since"><?= htmlspecialchars($kampanye['email']) ?></div>
            </div>
          </div>
          <p class="penyelenggara-desc"><?= htmlspecialchars($kampanye['alamat']) ?></p>
          <div class="penyelenggara-badges">
            <span class="badge-verified">✅ Terverifikasi</span>
          </div>
        </div>

      </div>

    </div>
  </div>

  <!-- ========== FOOTER ========== -->
  <footer>
    <div class="footer-main">
      <div class="footer-brand">
        <a href="index.php" class="logo">
          <div class="logo-icon"><img src="assets/LogoChariteCalisaTealDark.png" alt="Logo Charite Calisa"></div>
          <span class="logo-text">Charite<span>Calisa</span></span>
        </a>
        <p class="footer-brand-desc">Platform crowdfunding sosial terpercaya untuk Indonesia. Setiap rupiah memberi dampak nyata bagi yang membutuhkan.</p>
      </div>
      <div class="footer-col">
        <h4>Kampanye</h4>
        <ul>
          <li><a href="index.php">Bencana Alam</a></li>
          <li><a href="index.php">Pendidikan</a></li>
          <li><a href="index.php">Kesehatan</a></li>
          <li><a href="index.php">Lingkungan</a></li>
          <li><a href="index.php">Fasilitas Umum</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h4>Platform</h4>
        <ul>
          <li><a href="#">Cara Berdonasi</a></li>
          <li><a href="#">Buat Kampanye</a></li>
          <li><a href="#">Transparansi Dana</a></li>
          <li><a href="#">FAQ</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h4>Perusahaan</h4>
        <ul>
          <li><a href="#">Tentang Kami</a></li>
          <li><a href="#">Tim Kami</a></li>
          <li><a href="#">Karir</a></li>
          <li><a href="#">Kontak</a></li>
          <li><a href="#">Kebijakan Privasi</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <span>© 2026 ChariteCalisa. Semua hak dilindungi.</span>
      <span>Terdaftar & diawasi oleh Kemensos RI</span>
    </div>
  </footer>

</body>
</html>
