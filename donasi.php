<?php
session_start();
require_once 'config.php';

// =====================================================
// CEK LOGIN DAN ROLE
// =====================================================

$kampanye_id = (int)($_GET['id'] ?? 0);

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'donatur') {
    $redirect_url = 'donasi.php' . ($kampanye_id > 0 ? '?id=' . $kampanye_id : '');
    header('Location: login.php?redirect=' . urlencode($redirect_url));
    exit;
}

// =====================================================
// VALIDASI ID KAMPANYE
// =====================================================

if ($kampanye_id <= 0) {
    header('Location: index.php');
    exit;
}

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
// FETCH DATA KAMPANYE
// =====================================================

$sql = "
    SELECT k.id, k.judul, k.kategori, k.lokasi,
           k.target_dana, k.collected_amount,
           k.tanggal_selesai, k.gambar_path, k.status,
           p.nama_kantor
    FROM kampanye k
    JOIN pengelola p ON k.pengelola_id = p.id
    WHERE k.id = ? AND k.status = 'aktif'
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $kampanye_id);
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
$stmt_rek->bind_param('i', $kampanye_id);
$stmt_rek->execute();
$rekenings = $stmt_rek->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_rek->close();

// =====================================================
// FORMATTING DATA UNTUK TAMPILAN
// =====================================================

$target    = (float)$kampanye['target_dana'];
$collected = (float)$kampanye['collected_amount'];
$pct       = ($target > 0) ? min(100, (int)round($collected / $target * 100)) : 0;
$hari_sisa = hitung_hari_sisa($kampanye['tanggal_selesai']);

$kategori  = $kampanye['kategori'];
$emoji     = $kategori_emoji[$kategori] ?? '📌';
$label     = $kategori_label[$kategori] ?? ucfirst($kategori);

$tanggal_selesai_fmt = date('d F Y', strtotime($kampanye['tanggal_selesai']));

// =====================================================
// DATA DONATUR DARI SESSION
// =====================================================

$donatur_id    = (int)$_SESSION['user']['id'];
$donatur_nama  = $_SESSION['user']['nama'];
$donatur_email = $_SESSION['user']['email'];

// =====================================================
// PROSES FORM DONASI (POST)
// =====================================================

$error   = '';
$success = '';

$metode_options = [
    'bca'     => 'Transfer Bank BCA',
    'mandiri' => 'Transfer Bank Mandiri',
    'bni'     => 'Transfer Bank BNI',
    'bri'     => 'Transfer Bank BRI',
    'gopay'   => 'GoPay',
    'ovo'     => 'OVO',
    'dana'    => 'DANA',
    'qris'    => 'QRIS',
];

$form = [
    'nominal' => '',
    'metode'  => '',
    'pesan'   => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nominal = (float)($_POST['nominal'] ?? 0);
    $metode  = trim($_POST['metode']     ?? '');
    $pesan   = trim($_POST['pesan']      ?? '');

    $form['nominal'] = $_POST['nominal'] ?? '';
    $form['metode']  = $metode;
    $form['pesan']   = $pesan;

    if ($nominal < 5000) {
        $error = 'Nominal donasi minimal Rp 5.000.';
    } elseif (!array_key_exists($metode, $metode_options)) {
        $error = 'Metode pembayaran tidak valid.';
    } elseif (empty($_FILES['bukti_transfer']['name'])) {
        $error = 'Bukti transfer wajib diunggah.';
    } else {
        $upload_dir  = 'uploads/bukti/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $allowed_ext  = ['jpg', 'jpeg', 'png', 'pdf'];
        $file_tmp     = $_FILES['bukti_transfer']['tmp_name'];
        $file_name    = $_FILES['bukti_transfer']['name'];
        $file_size    = $_FILES['bukti_transfer']['size'];
        $file_ext     = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if (!in_array($file_ext, $allowed_ext)) {
            $error = 'Format file tidak didukung. Gunakan JPG, PNG, atau PDF.';
        } elseif ($file_size > 5 * 1024 * 1024) {
            $error = 'Ukuran file maksimal 5 MB.';
        } else {
            $new_filename  = 'bukti_' . $donatur_id . '_' . time() . '.' . $file_ext;
            $upload_path   = $upload_dir . $new_filename;

            if (!move_uploaded_file($file_tmp, $upload_path)) {
                $error = 'Gagal mengunggah file. Silakan coba lagi.';
            } else {
                $sql_insert = "
                    INSERT INTO donasi
                        (donatur_id, kampanye_id, nominal, metode_pembayaran, pesan, bukti_transfer_path, status)
                    VALUES (?, ?, ?, ?, ?, ?, 'pending')
                ";
                $stmt_ins = $conn->prepare($sql_insert);
                $stmt_ins->bind_param(
                    'iidsss',
                    $donatur_id,
                    $kampanye_id,
                    $nominal,
                    $metode,
                    $pesan,
                    $upload_path
                );

                if ($stmt_ins->execute()) {
                    $success = 'Donasi berhasil dikirim! Tim kami akan melakukan verifikasi dalam 1x24 jam.';
                    $form = ['nominal' => '', 'metode' => '', 'pesan' => ''];
                } else {
                    $error = 'Terjadi kesalahan saat menyimpan donasi. Silakan coba lagi.';
                }
                $stmt_ins->close();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Donasi - <?= htmlspecialchars($kampanye['judul']) ?> - ChariteCalisa</title>
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
        <a href="logout.php" class="nav-login-btn">Keluar</a>
      </nav>
    </div>
  </header>

  <!-- ========== BREADCRUMB ========== -->
  <div class="detail-breadcrumb">
    <a href="index.php">🏠︎ Beranda</a>
    <span>&rsaquo;</span>
    <a href="detail.php?id=<?= $kampanye_id ?>">Detail Kampanye</a>
    <span>&rsaquo;</span>
    <span>Form Donasi</span>
  </div>

  <!-- ========== MAIN CONTENT ========== -->
  <div class="main-content">
    <a href="detail.php?id=<?= $kampanye_id ?>" class="back-btn">← Kembali ke Detail Kampanye</a>

    <?php if ($success !== ''): ?>
    <div style="background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;border-radius:10px;padding:14px 18px;margin-bottom:20px;font-size:0.95rem;">
      ✅ <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
    <div style="background:#fee2e2;color:#b91c1c;border:1px solid #fca5a5;border-radius:10px;padding:14px 18px;margin-bottom:20px;font-size:0.95rem;">
      ⚠️ <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <div class="donasi-layout">

      <!-- ====== KOLOM KIRI ====== -->
      <div class="donasi-left">

        <!-- Summary Card -->
        <div class="donasi-summary-card">
          <div class="donasi-summary-img">
            <img src="<?= htmlspecialchars($kampanye['gambar_path']) ?>" alt="Poster <?= htmlspecialchars($kampanye['judul']) ?>">
          </div>
          <div class="donasi-summary-body">
            <span class="tag tag-<?= htmlspecialchars($kategori) ?> tag-mb"><?= $emoji ?> <?= htmlspecialchars($label) ?></span>
            <h3><?= htmlspecialchars($kampanye['judul']) ?></h3>
            <div class="summary-meta-list">
              <div class="donasi-summary-row">
                <span>Penyelenggara</span>
                <strong><?= htmlspecialchars($kampanye['nama_kantor']) ?></strong>
              </div>
              <div class="donasi-summary-row">
                <span>Lokasi</span>
                <strong><?= htmlspecialchars($kampanye['lokasi']) ?></strong>
              </div>
              <div class="donasi-summary-row">
                <span>Batas Waktu</span>
                <strong class="<?= $hari_sisa <= 7 ? 'deadline-danger' : '' ?>"><?= $tanggal_selesai_fmt ?></strong>
              </div>
            </div>
            <div class="donasi-mini-progress">
              <div class="progress-info-row">
                <span class="progress-info-label">Dana Terkumpul</span>
                <span class="progress-info-value"><?= $pct ?>% (<?= format_rupiah($collected) ?>)</span>
              </div>
              <div class="progress-bar-track">
                <div class="progress-bar-fill" style="width:<?= $pct ?>%"></div>
              </div>
              <div class="progress-target-hint">Target: <?= format_rupiah($target) ?></div>
            </div>
          </div>
        </div>

        <!-- Rekening Tujuan -->
        <?php if (!empty($rekenings)): ?>
        <div class="detail-info-block">
          <h2 class="detail-desc-title">Informasi Rekening Tujuan</h2>
          <div class="rekening-list">
            <?php foreach ($rekenings as $rek): ?>
            <div class="rekening-item">
              <div class="rekening-bank"><?= htmlspecialchars($rek['nama_bank']) ?></div>
              <div class="rekening-no"><?= htmlspecialchars($rek['nomor_rekening']) ?></div>
              <div class="rekening-name">a.n. <?= htmlspecialchars($rek['atas_nama']) ?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <p class="rekening-note">
            Setelah melakukan transfer, lengkapi formulir donasi di sebelah kanan dan unggah bukti transfer Anda. Tim kami akan melakukan konfirmasi dalam 1x24 jam.
          </p>
        </div>
        <?php endif; ?>

      </div>

      <!-- ====== KOLOM KANAN ====== -->
      <div class="donasi-right">
        <div class="form-card">
          <h2>Form Donasi</h2>

          <form method="POST" action="donasi.php?id=<?= $kampanye_id ?>" enctype="multipart/form-data">

            <!-- Nama Lengkap (readonly dari session) -->
            <div class="form-group">
              <label for="nama">Nama Lengkap <span class="required">*</span></label>
              <input type="text" id="nama" value="<?= htmlspecialchars($donatur_nama) ?>" readonly style="background:#f3f4f6;cursor:not-allowed;">
            </div>

            <!-- Email (readonly dari session) -->
            <div class="form-group">
              <label for="email">Alamat Email <span class="required">*</span></label>
              <input type="email" id="email" value="<?= htmlspecialchars($donatur_email) ?>" readonly style="background:#f3f4f6;cursor:not-allowed;">
            </div>

            <!-- Nominal Donasi -->
            <div class="form-group">
              <label for="nominal">Nominal Donasi <span class="required">*</span></label>
              <div class="nominal-presets">
                <button type="submit" name="nominal" value="10000" class="preset-btn">Rp 10.000</button>
                <button type="submit" name="nominal" value="25000" class="preset-btn">Rp 25.000</button>
                <button type="submit" name="nominal" value="50000" class="preset-btn">Rp 50.000</button>
                <button type="submit" name="nominal" value="100000" class="preset-btn">Rp 100.000</button>
                <button type="submit" name="nominal" value="250000" class="preset-btn">Rp 250.000</button>
                <button type="submit" name="nominal" value="500000" class="preset-btn">Rp 500.000</button>
              </div>
              <input type="number" id="nominal" name="nominal" placeholder="Masukkan nominal lainnya (Rp)" min="5000" value="<?= htmlspecialchars($form['nominal']) ?>" required>
            </div>

            <!-- Metode Pembayaran -->
            <div class="form-group">
              <label for="metode">Metode Pembayaran <span class="required">*</span></label>
              <select id="metode" name="metode" required>
                <option value="">-- Pilih metode pembayaran --</option>
                <?php foreach ($metode_options as $val => $teks): ?>
                <option value="<?= $val ?>" <?= $form['metode'] === $val ? 'selected' : '' ?>><?= $teks ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Pesan Dukungan -->
            <div class="form-group">
              <label for="pesan">Pesan Dukungan <span class="optional">(opsional)</span></label>
              <textarea id="pesan" name="pesan" placeholder="Tulis pesan dukungan untuk penyelenggara dan korban..."><?= htmlspecialchars($form['pesan']) ?></textarea>
            </div>

            <!-- Bukti Transfer -->
            <div class="form-group">
              <label>Bukti Transfer <span class="required">*</span></label>
              <label for="bukti-transfer">
                <div class="upload-area" id="upload-area">
                  <div class="upload-icon">📎</div>
                  <div class="upload-text"><strong>Klik untuk unggah</strong> atau seret file ke sini</div>
                  <div class="upload-hint">Format: PDF, JPG, PNG. Maks. ukuran 5 MB</div>
                </div>
              </label>
              <input type="file" id="bukti-transfer" name="bukti_transfer" accept=".pdf,.jpg,.jpeg,.png">
            </div>

            <!-- Submit -->
            <button type="submit" class="btn-submit">
              💛 Kirim Donasi Sekarang
            </button>

          </form>

          <p class="syarat-note">
            Dengan menekan tombol di atas, Anda menyetujui <a href="#">Syarat &amp; Ketentuan</a> ChariteCalisa.
          </p>
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
      <span>Terdaftar &amp; diawasi oleh Kemensos RI</span>
    </div>
  </footer>

</body>
</html>
