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

$lokasi_map = [
    'aceh'    => 'Aceh',
    'jakarta' => 'Jakarta',
    'jabar'   => 'Jawa Barat',
    'jateng'  => 'Jawa Tengah',
    'diy'     => 'Yogyakarta',
    'jatim'   => 'Jawa Timur',
    'sulsel'  => 'Sulawesi Selatan',
    'ntb'     => 'NTB',
    'papua'   => 'Papua',
];

// =====================================================
// SEARCH, FILTER, & PAGINATION
// =====================================================

$search_judul    = trim($_GET['search_judul']    ?? '');
$filter_kategori = $_GET['filter_kategori']      ?? '';
$filter_lokasi   = $_GET['filter_lokasi']        ?? '';
$page            = max(1, (int)($_GET['page']    ?? 1));
$per_page        = 6;

$where  = ["k.tanggal_selesai >= CURDATE()", "k.status = 'aktif'"];
$types  = '';
$params = [];

if ($search_judul !== '') {
    $where[]  = 'k.judul LIKE ?';
    $types   .= 's';
    $params[] = '%' . $search_judul . '%';
}

if ($filter_kategori !== '' && array_key_exists($filter_kategori, $kategori_label)) {
    $where[]  = 'k.kategori = ?';
    $types   .= 's';
    $params[] = $filter_kategori;
}

if ($filter_lokasi !== '' && isset($lokasi_map[$filter_lokasi])) {
    $where[]  = 'k.lokasi LIKE ?';
    $types   .= 's';
    $params[] = '%' . $lokasi_map[$filter_lokasi] . '%';
}

$where_sql = 'WHERE ' . implode(' AND ', $where);

$sql_count = "SELECT COUNT(*) AS total FROM kampanye k $where_sql";
$stmt_count = $conn->prepare($sql_count);
if ($types !== '') {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$total_kampanye = (int)$stmt_count->get_result()->fetch_assoc()['total'];
$stmt_count->close();

$total_pages = max(1, (int)ceil($total_kampanye / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

// =====================================================
// FETCH KAMPANYE
// =====================================================

$sql_data = "
    SELECT k.id, k.judul, k.kategori, k.lokasi,
           k.target_dana, k.collected_amount,
           k.tanggal_selesai, k.gambar_path,
           p.nama_kantor
    FROM kampanye k
    JOIN pengelola p ON k.pengelola_id = p.id
    $where_sql
    ORDER BY k.tanggal_selesai ASC, k.collected_amount ASC
    LIMIT ? OFFSET ?
";

$stmt_data  = $conn->prepare($sql_data);
$bind_types = $types . 'ii';
$bind_vals  = array_merge($params, [$per_page, $offset]);
$stmt_data->bind_param($bind_types, ...$bind_vals);
$stmt_data->execute();
$result    = $stmt_data->get_result();
$campaigns = $result->fetch_all(MYSQLI_ASSOC);
$stmt_data->close();

// =====================================================
// MEMPERTAHANKAN FILTER & SEARCH SAAT PINDAH HALAMAN
// =====================================================

$query_arr = array_filter([
    'search_judul'    => $search_judul,
    'filter_kategori' => $filter_kategori,
    'filter_lokasi'   => $filter_lokasi,
]);
$query_base      = http_build_query($query_arr);
$pagination_base = 'index.php?' . ($query_base ? $query_base . '&' : '');
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ChariteCalisa - Crowdfunding Sosial</title>
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
        <a href="index.php" class="active">Beranda</a>
        <a href="index.php">Kampanye</a>
        <a href="index.php">Tentang Kami</a>
        <?php if (isset($_SESSION['user'])): ?>
          <a href="logout.php" class="nav-login-btn">
            Keluar (<?= htmlspecialchars($_SESSION['user']['nama']) ?>)
          </a>
        <?php else: ?>
          <a href="login.php" class="nav-login-btn">Masuk / Daftar</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>

  <!-- ========== HERO ========== -->
  <section class="hero">
    <div class="hero-content">
      <div class="hero-badge">Platform Donasi Terpercaya</div>
      <h1>Bersama Kita <span>Wujudkan</span> Perubahan Nyata</h1>
      <p>Temukan kampanye sosial yang membutuhkan dukungan Anda. Dari bencana alam hingga pendidikan, setiap donasi memberi dampak nyata.</p>
      <div class="hero-stats">
        <div class="hero-stat">
          <strong>1.240+</strong>
          <span>Kampanye Aktif</span>
        </div>
        <div class="hero-stat">
          <strong>Rp 48,7 M</strong>
          <span>Dana Terkumpul</span>
        </div>
        <div class="hero-stat">
          <strong>32.500+</strong>
          <span>Donatur Aktif</span>
        </div>
      </div>
    </div>
  </section>

  <!-- ========== FILTER dan SEARCH ========== -->
  <div class="filter-section">
    <div class="filter-inner">
      <form method="GET" action="index.php">
        <div class="filter-row">
          <div class="filter-group">
            <label for="search-judul">Judul Kampanye</label>
            <input type="text" id="search-judul" name="search_judul"
              placeholder="Cari nama kampanye..."
              value="<?= htmlspecialchars($search_judul) ?>">
          </div>
          <div class="filter-group">
            <label for="filter-kategori">Kategori</label>
            <select id="filter-kategori" name="filter_kategori">
              <option value="">Semua Kategori</option>
              <option value="bencana"    <?= $filter_kategori === 'bencana'    ? 'selected' : '' ?>>Bencana Alam</option>
              <option value="pendidikan" <?= $filter_kategori === 'pendidikan' ? 'selected' : '' ?>>Pendidikan</option>
              <option value="kesehatan"  <?= $filter_kategori === 'kesehatan'  ? 'selected' : '' ?>>Kesehatan</option>
              <option value="lingkungan" <?= $filter_kategori === 'lingkungan' ? 'selected' : '' ?>>Lingkungan</option>
              <option value="fasilitas"  <?= $filter_kategori === 'fasilitas'  ? 'selected' : '' ?>>Fasilitas Umum</option>
            </select>
          </div>
          <div class="filter-group">
            <label for="filter-lokasi">Lokasi</label>
            <select id="filter-lokasi" name="filter_lokasi">
              <option value="">Semua Lokasi</option>
              <option value="aceh"    <?= $filter_lokasi === 'aceh'    ? 'selected' : '' ?>>Aceh</option>
              <option value="jakarta" <?= $filter_lokasi === 'jakarta' ? 'selected' : '' ?>>DKI Jakarta</option>
              <option value="jabar"   <?= $filter_lokasi === 'jabar'   ? 'selected' : '' ?>>Jawa Barat</option>
              <option value="jateng"  <?= $filter_lokasi === 'jateng'  ? 'selected' : '' ?>>Jawa Tengah</option>
              <option value="diy"     <?= $filter_lokasi === 'diy'     ? 'selected' : '' ?>>DI Yogyakarta</option>
              <option value="jatim"   <?= $filter_lokasi === 'jatim'   ? 'selected' : '' ?>>Jawa Timur</option>
              <option value="sulsel"  <?= $filter_lokasi === 'sulsel'  ? 'selected' : '' ?>>Sulawesi Selatan</option>
              <option value="ntb"     <?= $filter_lokasi === 'ntb'     ? 'selected' : '' ?>>NTB</option>
              <option value="papua"   <?= $filter_lokasi === 'papua'   ? 'selected' : '' ?>>Papua</option>
            </select>
          </div>
          <div class="filter-group">
            <label>&nbsp;</label>
            <button type="submit" class="btn-search">🔍 Cari</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- ========== MAIN CONTENT ========== -->
  <div class="main-content">
    <div class="section-header">
      <h2 class="section-title">Kampanye Berlangsung</h2>
      <span class="section-count">
        <?php if ($total_kampanye > 0): ?>
          Menampilkan
          <?= (($page - 1) * $per_page) + 1 ?>–<?= min($page * $per_page, $total_kampanye) ?>
          dari <?= number_format($total_kampanye, 0, ',', '.') ?> kampanye
        <?php else: ?>
          0 kampanye ditemukan
        <?php endif; ?>
      </span>
    </div>

    <div class="campaigns-grid">

      <?php if (count($campaigns) === 0): ?>
        <div style="grid-column:1/-1;text-align:center;padding:60px 20px;color:#64748b;">
          <div style="font-size:3rem;margin-bottom:12px;">🔍</div>
          <p style="font-size:1.1rem;font-weight:600;">Tidak ada kampanye ditemukan.</p>
          <p>Coba ubah kata kunci atau filter pencarian Anda.</p>
        </div>
      <?php else: ?>

        <?php foreach ($campaigns as $k):
          $pct       = $k['target_dana'] > 0
                         ? min(100, round(($k['collected_amount'] / $k['target_dana']) * 100))
                         : 0;
          $hari_sisa = hitung_hari_sisa($k['tanggal_selesai']);
          $is_urgent = $hari_sisa <= 7;
          $kat_label = $kategori_label[$k['kategori']] ?? $k['kategori'];
        ?>

        <!-- CARD -->
        <div class="campaign-card">
          <div class="card-image">
            <img src="<?= htmlspecialchars($k['gambar_path']) ?>"
                 alt="Poster <?= htmlspecialchars($k['judul']) ?>">
            <span class="card-category <?= htmlspecialchars($k['kategori']) ?>">
              <?= $kat_label ?>
            </span>
          </div>
          <div class="card-body">
            <h3 class="card-title"><?= htmlspecialchars($k['judul']) ?></h3>
            <p class="card-organizer">👤 <?= htmlspecialchars($k['nama_kantor']) ?></p>
            <div class="card-progress">
              <div class="progress-bar-track">
                <div class="progress-bar-fill" style="width:<?= $pct ?>%"></div>
              </div>
              <div class="progress-labels">
                <span class="progress-collected"><?= format_rupiah((float)$k['collected_amount']) ?></span>
                <span class="progress-pct"><?= $pct ?>%</span>
              </div>
            </div>
            <div class="card-meta">
              <div class="card-meta-target">
                <span class="meta-label">Target Dana</span>
                <span class="meta-value"><?= format_rupiah((float)$k['target_dana']) ?></span>
              </div>
              <div class="card-deadline <?= $is_urgent ? 'deadline-urgent' : '' ?>">
                <?php if ($hari_sisa === 0): ?>
                  ⏰ Hari terakhir
                <?php elseif ($is_urgent): ?>
                  ⏰ <?= $hari_sisa ?> hari lagi
                <?php else: ?>
                  📅 <?= $hari_sisa ?> hari lagi
                <?php endif; ?>
              </div>
            </div>
            <a href="detail.php?id=<?= (int)$k['id'] ?>" class="btn-detail">Lihat Detail →</a>
          </div>
        </div>

        <?php endforeach; ?>

      <?php endif; ?>

    </div>

    <!-- ========== PAGINATION ========== -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination-wrap">
      <nav class="pagination">

        <?php if ($page > 1): ?>
          <a href="<?= $pagination_base ?>page=<?= $page - 1 ?>" class="page-btn page-prev">‹ Sebelumnya</a>
        <?php else: ?>
          <span class="page-btn page-prev disabled">‹ Sebelumnya</span>
        <?php endif; ?>

        <?php
        $range = 2;
        $start = max(1, $page - $range);
        $end   = min($total_pages, $page + $range);

        if ($start > 1): ?>
          <a href="<?= $pagination_base ?>page=1" class="page-btn">1</a>
          <?php if ($start > 2): ?><span class="page-ellipsis">...</span><?php endif; ?>
        <?php endif; ?>

        <?php for ($i = $start; $i <= $end; $i++): ?>
          <?php if ($i === $page): ?>
            <span class="page-btn page-active"><?= $i ?></span>
          <?php else: ?>
            <a href="<?= $pagination_base ?>page=<?= $i ?>" class="page-btn"><?= $i ?></a>
          <?php endif; ?>
        <?php endfor; ?>

        <?php if ($end < $total_pages): ?>
          <?php if ($end < $total_pages - 1): ?><span class="page-ellipsis">...</span><?php endif; ?>
          <a href="<?= $pagination_base ?>page=<?= $total_pages ?>" class="page-btn"><?= $total_pages ?></a>
        <?php endif; ?>

        <?php if ($page < $total_pages): ?>
          <a href="<?= $pagination_base ?>page=<?= $page + 1 ?>" class="page-btn page-next">Selanjutnya ›</a>
        <?php else: ?>
          <span class="page-btn page-next disabled">Selanjutnya ›</span>
        <?php endif; ?>

      </nav>
    </div>
    <?php endif; ?>

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