<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
  $loginUrl = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/login.php';
  header("Location: " . $loginUrl);
  exit;
}

$pageTitle = $pageTitle ?? 'Remunerasi RSUD MERAUKE';
$extraHead = $extraHead ?? '';
$extraFooter = $extraFooter ?? '';

$username = $_SESSION['username'] ?? 'User';
$rootPath = $rootPath ?? '';

$baseUrl = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$uri = $_SERVER['REQUEST_URI'];
function isActive($paths)
{
  global $uri;
  $segments = explode('/', trim(parse_url($uri, PHP_URL_PATH), '/'));
  foreach ((array) $paths as $p) {
    if (in_array(trim($p, '/'), $segments))
      return true;
  }
  return false;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
  <script>
    window.BASE_URL = '<?= $baseUrl ?>';
  </script>
  <?= $extraHead ?>
  <style>
    ::-webkit-scrollbar {
      width: 6px;
      height: 6px;
    }

    ::-webkit-scrollbar-track {
      background: #f1f5f9;
    }

    ::-webkit-scrollbar-thumb {
      background: #94a3b8;
      border-radius: 3px;
    }

    ::-webkit-scrollbar-thumb:hover {
      background: #64748b;
    }

    .sidebar-item {
      transition: all 0.2s;
      border-left: 3px solid transparent;
    }

    .sidebar-item:hover {
      background: rgba(255, 255, 255, 0.08);
    }

    .sidebar-item.active {
      background: rgba(255, 255, 255, 0.1);
      border-left: 4px solid #10b981;
    }

    /* Sidebar collapsed (icon only) styles for Desktop */
    @media (min-width: 1024px) {
      .sidebar-collapsed {
        width: 4.5rem !important;
      }

      .sidebar-collapsed #menuSearchContainer,
      .sidebar-collapsed span,
      .sidebar-collapsed p,
      .sidebar-collapsed h1,
      .sidebar-collapsed .min-w-0,
      .sidebar-collapsed a[title="Logout"] {
        display: none !important;
      }

      .sidebar-collapsed .sidebar-item {
        justify-content: center;
        padding-left: 0;
        padding-right: 0;
        gap: 0 !important;
      }

      .sidebar-collapsed .border-b,
      .sidebar-collapsed .border-t {
        justify-content: center;
        padding-left: 0;
        padding-right: 0;
      }

      .content-expanded {
        margin-left: 4.5rem !important;
      }
    }

    .dt-button.buttons-excel.buttons-html5 {
      background-color: #16a34a !important;
      color: white !important;
      border: none !important;
      padding: 10px 18px !important;
      border-radius: 8px !important;
      font-size: 13px !important;
      font-weight: 600 !important;
      cursor: pointer;
      transition: 0.2s;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .dt-button.buttons-excel.buttons-html5:hover {
      background-color: #15803d !important;
    }

    .dt-button.buttons-pdf.buttons-html5 {
      background-color: #dc2626 !important;
      color: white !important;
      border: none !important;
      padding: 10px 18px !important;
      border-radius: 8px !important;
      font-size: 13px !important;
      font-weight: 600 !important;
      cursor: pointer;
      transition: 0.2s;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .dt-button.buttons-pdf.buttons-html5:hover {
      background-color: #b91c1c !important;
    }

    #tabelTindakan td,
    #tabelTindakan th {
      color: #1f2937 !important;
      white-space: nowrap;
    }

    table.dataTable td,
    table.dataTable th {
      color: #1f2937 !important;
    }

    table.dataTable {
      width: auto !important;
    }

    #tabelTindakan tbody td {
      padding: 2px 4px !important;
      margin: 0 !important;
      line-height: 1.4 !important;
      height: auto;
      border: 0.5px solid #d1d5db;
      vertical-align: top !important;
    }
  </style>
</head>

<body class="bg-gray-50 text-gray-800 antialiased">

  <!-- Sidebar Overlay (mobile) -->
  <div id="sidebarOverlay" class="fixed inset-0 bg-black/40 z-30 hidden" onclick="toggleSidebar()"></div>

  <!-- Sidebar -->
  <aside id="sidebar"
    class="fixed top-0 left-0 z-40 h-screen w-64 -translate-x-full lg:translate-x-0 transition-transform duration-300 bg-gradient-to-b from-emerald-900 via-emerald-800 to-emerald-900 shadow-2xl flex flex-col">

    <!-- Logo -->
    <div class="flex items-center gap-3 px-5 py-4 border-b border-emerald-700/40 shrink-0">
      <img src="https://absenrsudmerauke.rifill.id/assetsdata/img/logorsud.png" alt="Logo"
        class="w-10 h-10 rounded-lg bg-white/10 p-1">
      <div>
        <h1 class="text-sm font-bold leading-tight text-white">RSUD MERAUKE</h1>
        <p class="text-xs text-emerald-300/80">Sistem Remunerasi</p>
      </div>
    </div>

    <!-- Menu -->
    <?php
    $menus = [
      [
        'title' => 'Menu Utama',
        'items' => [
          ['url' => '/', 'icon' => 'fas fa-home', 'label' => 'Dashboard', 'active' => !isActive(['perbaikan-tarif', 'rajal', 'ranap', 'bulanan-rajal', 'bulanan-ranap', 'bpjs', 'bpjs-verifikasi', 'laporan-gabungan', 'cari-petugas', 'jasaraharja', 'tunsus', 'hitung-jasa-ralan', 'hitung-jasa-dokter-ralan', 'hitung-jasa-ranap', 'hitung-jasa-dokter-ranap', 'kepatuhan-ralan', 'kepatuhan-penunjang-ralan', 'kepatuhan-bpjs', 'kepatuhan-remunerasi', 'hitung-jasa-ralan-umum', 'hitung-jasa-dokter-ralan-umum', 'hitung-jasa-ranap-umum', 'hitung-jasa-dokter-ranap-umum'])]
        ]
      ],
      [
        'title' => 'Data Tindakan',
        'items' => [
          ['url' => '/rajal', 'icon' => 'fas fa-user-doctor', 'label' => 'Rawat Jalan', 'active' => isActive('rajal')],
          ['url' => '/ranap', 'icon' => 'fas fa-bed', 'label' => 'Rawat Inap', 'active' => isActive('ranap')],
          ['url' => '/perbaikan-tarif', 'icon' => 'fas fa-tags', 'label' => 'Perbaikan Tarif', 'active' => isActive('perbaikan-tarif')],
        ]
      ],
      [
        'title' => 'Data Kepatuhan',
        'items' => [
          ['url' => '/kepatuhan-ralan', 'icon' => 'fas fa-clipboard-check', 'label' => 'Kepatuhan Rawat Jalan', 'active' => isActive('kepatuhan-ralan')],
          ['url' => '/kepatuhan-penunjang-ralan', 'icon' => 'fas fa-flask', 'label' => 'Kepatuhan Penunjang Ralan', 'active' => isActive('kepatuhan-penunjang-ralan')],
          ['url' => '/kepatuhan-bpjs', 'icon' => 'fas fa-file-invoice', 'label' => 'Kepatuhan BPJS', 'active' => isActive('kepatuhan-bpjs')],
          ['url' => '/kepatuhan-remunerasi', 'icon' => 'fas fa-coins', 'label' => 'Kepatuhan Remunerasi', 'active' => isActive('kepatuhan-remunerasi')],
        ]
      ],
      [
        'title' => 'Data Perhitungan Ralan BPJS',
        'items' => [
          ['url' => '/hitung-jasa-ralan', 'icon' => 'fas fa-calculator', 'label' => 'Hitung Jasa Ralan', 'active' => isActive('hitung-jasa-ralan') && !isActive('hitung-jasa-ralan-umum')],
          ['url' => '/hitung-jasa-dokter-ralan', 'icon' => 'fas fa-user-md', 'label' => 'Jasa Dokter Ralan', 'active' => isActive('hitung-jasa-dokter-ralan') && !isActive('hitung-jasa-dokter-ralan-umum')],
        ]
      ],
      [
        'title' => 'Data Perhitungan Ralan UMUM',
        'items' => [
          ['url' => '/hitung-jasa-ralan-umum', 'icon' => 'fas fa-calculator', 'label' => 'Hitung Jasa Ralan Umum', 'active' => isActive('hitung-jasa-ralan-umum')],
          ['url' => '/hitung-jasa-dokter-ralan-umum', 'icon' => 'fas fa-user-md', 'label' => 'Jasa Dokter Ralan Umum', 'active' => isActive('hitung-jasa-dokter-ralan-umum')],
        ]
      ],
      [
        'title' => 'Data Perhitungan Ranap BPJS',
        'items' => [
          ['url' => '/hitung-jasa-ranap', 'icon' => 'fas fa-bed', 'label' => 'Hitung Jasa Ranap', 'active' => isActive('hitung-jasa-ranap') && !isActive('hitung-jasa-ranap-umum')],
          ['url' => '/hitung-jasa-dokter-ranap', 'icon' => 'fas fa-user-md', 'label' => 'Jasa Dokter Ranap', 'active' => isActive('hitung-jasa-dokter-ranap') && !isActive('hitung-jasa-dokter-ranap-umum')],
        ]
      ],
      [
        'title' => 'Data Perhitungan Ranap UMUM',
        'items' => [
          ['url' => '/hitung-jasa-ranap-umum', 'icon' => 'fas fa-bed', 'label' => 'Hitung Jasa Ranap Umum', 'active' => isActive('hitung-jasa-ranap-umum')],
          ['url' => '/hitung-jasa-dokter-ranap-umum', 'icon' => 'fas fa-user-md', 'label' => 'Jasa Dokter Ranap Umum', 'active' => isActive('hitung-jasa-dokter-ranap-umum')],
        ]
      ],
      [
        'title' => 'Data Umpan Balik',
        'items' => [
          ['url' => '/bpjs-verifikasi', 'icon' => 'fas fa-file-invoice', 'label' => 'Umbal BPJS', 'active' => isActive('bpjs-verifikasi')],
        ]
      ],
      [
        'title' => 'Laporan',
        'items' => [
          ['url' => '/bulanan-rajal', 'icon' => 'fas fa-calendar-week', 'label' => 'RALAN Per-Bulan', 'active' => isActive('bulanan-rajal')],
          ['url' => '/bulanan-ranap', 'icon' => 'fas fa-calendar-alt', 'label' => 'RANAP Per-Bulan', 'active' => isActive('bulanan-ranap')],
          ['url' => '/laporan-gabungan', 'icon' => 'fas fa-chart-pie', 'label' => 'Laporan Gabungan', 'active' => isActive('laporan-gabungan')],
        ]
      ],
      [
        'title' => 'Keuangan',
        'items' => [
          ['url' => '/jasaraharja', 'icon' => 'fas fa-car', 'label' => 'Jasa Raharja', 'active' => isActive('jasaraharja')],
        ]
      ],
      [
        'title' => 'Lainnya',
        'items' => [
          ['url' => '/cari-petugas', 'icon' => 'fas fa-user-md', 'label' => 'Cari Paramedis/Dokter', 'active' => isActive('cari-petugas')],
          ['url' => '/tunsus', 'icon' => 'fas fa-stethoscope', 'label' => 'Tunjangan Khusus', 'active' => isActive('tunsus')],
        ]
      ]
    ];
    ?>
    <!-- Menu Search -->
    <div id="menuSearchContainer" class="px-4 py-2 mt-2">
      <div class="relative ">
        <input type="text" id="menuSearch" onkeyup="filterMenu()" placeholder="Cari menu..."
          class="w-full bg-emerald-600/50 text-emerald-100 placeholder-emerald-400/70 border border-emerald-700/50 rounded-lg pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 transition-colors">
        <i class="fas fa-search absolute left-3 top-2.5 text-emerald-400/70 text-sm "></i>
      </div>
    </div>

    <nav class="flex-1 overflow-y-auto py-2 px-3 space-y-0.5" id="sidebarMenu">
      <?php foreach ($menus as $menu_index => $section): ?>
        <div class="menu-section">
          <?php if (!empty($section['title'])): ?>
            <p
              class="menu-title text-[11px] font-bold text-white uppercase tracking-wider px-3 mb-2 <?= $menu_index > 0 ? 'mt-4' : 'mt-1' ?>">
              <?= $section['title'] ?>
            </p>
          <?php endif; ?>

          <?php foreach ($section['items'] as $item): ?>
            <a href="<?= $baseUrl . $item['url'] ?>"
              class="sidebar-item menu-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-emerald-100/90 hover:text-white <?= $item['active'] ? 'active text-white' : '' ?>">
              <i class="<?= $item['icon'] ?> w-5 text-center text-emerald-300"></i>
              <span class="menu-text"><?= $item['label'] ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </nav>


  </aside>

  <!-- Main Area -->
  <div id="mainContent" class="lg:ml-64 flex flex-col min-h-screen transition-all duration-300">

    <!-- Navbar -->
    <header class="sticky top-0 z-20 bg-white/90 backdrop-blur-md border-b border-gray-200 ">
      <div class="flex items-center justify-between px-4 lg:px-6 h-16">
        <div class="flex items-center gap-3">
          <button onclick="toggleSidebar()" class="lg:hidden text-gray-500 hover:text-gray-700 focus:outline-none">
            <i class="fas fa-bars text-xl"></i>
          </button>
          <button onclick="toggleSidebar()"
            class="hidden lg:block text-gray-400 hover:text-gray-600 focus:outline-none">
            <i class="fas fa-bars text-lg"></i>
          </button>
          <div>
            <h2 class="text-lg font-semibold text-gray-800"><?= htmlspecialchars($pageTitle) ?></h2>
          </div>
        </div>
        <div class="flex items-center gap-4">
          <span class="text-sm text-gray-500 hidden sm:block bg-gray-100 p-2 rounded-lg">
            <i class="far fa-calendar-alt mr-1 text-emerald-600"></i>
            <?= date('d/m/Y') ?>
          </span>

          <!-- User Dropdown -->
          <div class="relative">
            <button onclick="toggleUserDropdown(event)"
              class="flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-gray-100 transition focus:outline-none">
              <div
                class="w-8 h-8 rounded-full bg-emerald-600 flex items-center justify-center text-xs font-bold text-white">
                <?= strtoupper(substr($username, 0, 1)) ?>
              </div>
              <span class="text-sm font-medium text-gray-700 hidden sm:block"><?= htmlspecialchars($username) ?></span>
              <i class="fas fa-chevron-down text-xs text-gray-400"></i>
            </button>

            <!-- Dropdown Menu -->
            <div id="userDropdown"
              class="hidden absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-lg py-1 border border-gray-100 z-50">
              <div class="px-4 py-3 border-b border-gray-100 sm:hidden">
                <p class="text-sm font-medium text-gray-800 truncate"><?= htmlspecialchars($username) ?></p>
              </div>
              <a href="<?= $baseUrl ?>/config/logout.php"
                class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition">
                <i class="fas fa-sign-out-alt mr-2"></i> Logout
              </a>
            </div>
          </div>
        </div>
      </div>
    </header>

    <!-- Content -->
    <main class="flex-1 p-4 lg:p-6 overflow-y-auto ">