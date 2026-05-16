<?php
session_start();

if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';

$error         = '';
$selected_role = 'donatur';
$prefill_email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_role = $_POST['role']           ?? 'donatur';
    $email         = trim($_POST['login_email']    ?? '');
    $password_in   = $_POST['login_password'] ?? '';

    if ($email === '' || $password_in === '') {
        $error = 'Email dan kata sandi wajib diisi.';
    } else {
        $prefill_email = htmlspecialchars($email);

        if ($selected_role === 'pengelola') {
            $sql = 'SELECT id, nama_kantor AS nama, email, password FROM pengelola WHERE email = ? LIMIT 1';
        } else {
            $sql = 'SELECT id, nama, email, password FROM donatur WHERE email = ? LIMIT 1';
        }

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user   = $result->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password_in, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id'    => $user['id'],
                'nama'  => $user['nama'],
                'email' => $user['email'],
                'role'  => $selected_role,
            ];

            $redirect = $_GET['redirect'] ?? 'index.php';
            if (!preg_match('/^[a-zA-Z0-9_\-\.\/\?=&]+$/', $redirect)) {
                $redirect = 'index.php';
            }
            header('Location: ' . $redirect);
            exit;
        } else {
            $error = 'Email atau kata sandi salah. Periksa kembali data Anda.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Masuk / Daftar - ChariteCalisa</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>

  <!-- ========== HEADER ========== -->
  <header>
    <div class="header-inner">
      <a href="index.php" class="logo">
        <div class="logo-icon"><img src="assets/LogoChariteCalisaTealDark.png" alt="Logo ChariteCalisa"></div>
        <span class="logo-text">Charite<span>Calisa</span></span>
      </a>
      <nav>
        <a href="index.php">Beranda</a>
        <a href="index.php">Kampanye</a>
        <a href="index.php">Tentang Kami</a>
        <a href="login.php" class="nav-login-btn active">Masuk / Daftar</a>
      </nav>
    </div>
  </header>

  <!-- ========== LOGIN WRAPPER ========== -->
  <div class="login-page-wrapper">
    <div class="login-container">
      <div class="login-card">

        <!-- Logo -->
        <div class="login-logo">
          <div class="login-logo-icon"><img src="assets/LogoChariteCalisaAmberLight.png" alt="Logo Charite Calisa"></div>
          <h2>Selamat Datang Kembali</h2>
          <p>Masuk ke akun Anda</p>
        </div>

        <?php if ($error !== ''): ?>
        <div class="login-error-msg" style="background:#fee2e2;color:#b91c1c;border:1px solid #fca5a5;border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:0.9rem;">
          ⚠️ <?= $error ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="login.php<?= isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>">

          <!-- Role Selector -->
          <div class="role-selector-wrap">
            <p class="role-selector-label">Masuk Sebagai</p>
            <div class="role-selector">
              <div class="role-option">
                <input type="radio" name="role" id="role-donatur" value="donatur"
                  <?= $selected_role === 'donatur' ? 'checked' : '' ?>>
                <label for="role-donatur" class="role-label">
                  <span class="role-icon">🙏</span>
                  <span class="role-name">Donatur</span>
                  <span class="role-desc">Saya ingin berdonasi</span>
                </label>
              </div>
              <div class="role-option">
                <input type="radio" name="role" id="role-pengelola" value="pengelola"
                  <?= $selected_role === 'pengelola' ? 'checked' : '' ?>>
                <label for="role-pengelola" class="role-label">
                  <span class="role-icon">📋</span>
                  <span class="role-name">Pengelola</span>
                  <span class="role-desc">Saya mengelola kampanye</span>
                </label>
              </div>
            </div>
          </div>

          <!-- Email -->
          <div class="login-form-group">
            <label for="login-email">Email / Username</label>
            <div class="login-input-wrap">
              <span class="input-icon">✉️</span>
              <input type="email" id="login-email" name="login_email"
                placeholder="email@contoh.com" autocomplete="email"
                value="<?= $prefill_email ?>">
            </div>
          </div>

          <!-- Password -->
          <div class="login-form-group">
            <label for="login-password">Kata Sandi</label>
            <div class="login-input-wrap">
              <span class="input-icon">🔒</span>
              <input type="password" id="login-password" name="login_password"
                placeholder="Masukkan kata sandi" autocomplete="current-password">
            </div>
          </div>

          <!-- Lupa Password -->
          <div class="login-forgot">
            <a href="#">Lupa kata sandi?</a>
          </div>

          <!-- Submit -->
          <button type="submit" class="btn-login">
            Masuk ke Akun
          </button>

        </form>

        <div class="login-divider">atau</div>

        <div class="login-register-link">
          Belum punya akun? <a href="#">Daftar Sekarang</a>
        </div>

        <div class="login-info-box">
          <p>
            Dengan masuk, Anda menyetujui <a href="#">Syarat &amp; Ketentuan</a> serta <a href="#">Kebijakan Privasi</a> ChariteCalisa.
          </p>
        </div>

      </div>

      <!-- Kembali ke Beranda -->
      <div class="login-back-wrap">
        <a href="index.php" class="login-back-link">
          ← Kembali ke Beranda
        </a>
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