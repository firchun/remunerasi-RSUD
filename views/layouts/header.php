<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$pageTitle = $pageTitle ?? 'Remunerasi RSUD MERAUKE';
$extraHead = $extraHead ?? '';
$extraFooter = $extraFooter ?? '';

$username = $_SESSION['username'] ?? 'User';
$rootPath = $rootPath ?? '';

$uri = $_SERVER['REQUEST_URI'];
function isActive($paths)
{
  global $uri;
  foreach ((array)$paths as $p) {
    if (strpos($uri, $p) !== false) return true;
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
      background: rgba(255, 255, 255, 0.12);
      border-left-color: #22c55e;
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
    <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-0.5">

      <p class="text-[11px] font-semibold text-emerald-400/70 uppercase tracking-wider px-3 mb-2 mt-1">Menu Utama</p>

      <a href="<?= $rootPath ?>"
        class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-emerald-100/90 hover:text-white <?= empty($rootPath) && !isActive(['/rajal', '/ranap', '/bulanan-rajal', '/bulanan-ranap', '/bpjs', '/laporan-gabungan', '/cari-petugas', '/jasaraharja', '/tunsus']) ? 'active text-white' : '' ?>">
        <i class="fas fa-home w-5 text-center text-emerald-300"></i>
        <span>Dashboard</span>
      </a>

      <p class="text-[11px] font-semibold text-emerald-400/70 uppercase tracking-wider px-3 mb-2 mt-4">Data Transaksi
      </p>

      <a href="<?= $rootPath ?>rajal"
        class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-emerald-100/90 hover:text-white <?= isActive('/rajal') ? 'active text-white' : '' ?>">
        <i class="fas fa-user-doctor w-5 text-center text-emerald-300"></i>
        <span>Rawat Jalan</span>
      </a>

      <a href="<?= $rootPath ?>ranap"
        class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-emerald-100/90 hover:text-white <?= isActive('/ranap') ? 'active text-white' : '' ?>">
        <i class="fas fa-bed w-5 text-center text-emerald-300"></i>
        <span>Rawat Inap</span>
      </a>

      <p class="text-[11px] font-semibold text-emerald-400/70 uppercase tracking-wider px-3 mb-2 mt-4">Laporan</p>

      <a href="<?= $rootPath ?>bulanan-rajal"
        class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-emerald-100/90 hover:text-white <?= isActive('/bulanan-rajal') ? 'active text-white' : '' ?>">
        <i class="fas fa-calendar-week w-5 text-center text-emerald-300"></i>
        <span>RALAN Per-Bulan</span>
      </a>

      <a href="<?= $rootPath ?>bulanan-ranap"
        class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-emerald-100/90 hover:text-white <?= isActive('/bulanan-ranap') ? 'active text-white' : '' ?>">
        <i class="fas fa-calendar-alt w-5 text-center text-emerald-300"></i>
        <span>RANAP Per-Bulan</span>
      </a>

      <a href="<?= $rootPath ?>laporan-gabungan"
        class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-emerald-100/90 hover:text-white <?= isActive('/laporan-gabungan') ? 'active text-white' : '' ?>">
        <i class="fas fa-chart-pie w-5 text-center text-emerald-300"></i>
        <span>Laporan Gabungan</span>
      </a>

      <p class="text-[11px] font-semibold text-emerald-400/70 uppercase tracking-wider px-3 mb-2 mt-4">Keuangan</p>

      <a href="<?= $rootPath ?>bpjs"
        class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-emerald-100/90 hover:text-white <?= isActive('/bpjs') ? 'active text-white' : '' ?>">
        <i class="fas fa-money-bill-wave w-5 text-center text-emerald-300"></i>
        <span>BPJS</span>
      </a>

      <a href="<?= $rootPath ?>jasaraharja"
        class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-emerald-100/90 hover:text-white <?= isActive('/jasaraharja') ? 'active text-white' : '' ?>">
        <i class="fas fa-car w-5 text-center text-emerald-300"></i>
        <span>Jasa Raharja</span>
      </a>

      <p class="text-[11px] font-semibold text-emerald-400/70 uppercase tracking-wider px-3 mb-2 mt-4">Lainnya</p>

      <a href="<?= $rootPath ?>cari-petugas"
        class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-emerald-100/90 hover:text-white <?= isActive('/cari-petugas') ? 'active text-white' : '' ?>">
        <i class="fas fa-user-md w-5 text-center text-emerald-300"></i>
        <span>Cari Paramedis/Dokter</span>
      </a>

      <a href="<?= $rootPath ?>tunsus"
        class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-emerald-100/90 hover:text-white <?= isActive('/tunsus') ? 'active text-white' : '' ?>">
        <i class="fas fa-stethoscope w-5 text-center text-emerald-300"></i>
        <span>Tunjangan Khusus</span>
      </a>
    </nav>

    <!-- Bottom user -->
    <div class="border-t border-emerald-700/40 px-4 py-3 shrink-0">
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded-full bg-emerald-600 flex items-center justify-center text-xs font-bold text-white">
          <?= strtoupper(substr($username, 0, 1)) ?>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-medium text-white truncate"><?= htmlspecialchars($username) ?></p>
        </div>
        <a href="<?= $rootPath ?>config/logout.php" class="text-emerald-300/70 hover:text-white transition"
          title="Logout">
          <i class="fas fa-sign-out-alt"></i>
        </a>
      </div>
    </div>
  </aside>

  <!-- Main Area -->
  <div class="lg:ml-64 flex flex-col min-h-screen transition-all duration-300">

    <!-- Navbar -->
    <header class="sticky top-0 z-20 bg-white/90 backdrop-blur-md border-b border-gray-200 shadow-sm">
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
          <span class="text-sm text-gray-500 hidden sm:block">
            <i class="far fa-calendar-alt mr-1"></i>
            <?= date('d/m/Y') ?>
          </span>
          <a href="<?= $rootPath ?>config/logout.php"
            class="flex items-center gap-2 px-3 py-1.5 text-sm text-red-600 hover:text-red-700 hover:bg-red-50 rounded-lg transition">
            <i class="fas fa-sign-out-alt"></i>
            <span class="hidden sm:inline">Logout</span>
          </a>
        </div>
      </div>
    </header>

    <!-- Content -->
    <main class="flex-1 p-4 lg:p-6 overflow-y-auto">