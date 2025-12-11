<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once './config/conf.php';
cek_login();
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Remunerasi RSUD MERAUKE</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="bg-gray-100">
  <div class="flex h-screen overflow-hidden">

    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-hidden">

      <!-- Header -->
      <header class="fixed top-0 left-0 w-full z-50 bajckdrop-blur-md bg-white/60 shadow-sm">
        <div class="max-w-screen-xl mx-auto px-4 py-3 flex items-center justify-between">

          <!-- Kiri: Logo + Judul -->
          <div class="flex items-center min-w-0">
            <a href="../index.php">
              <img src="https://absenrsudmerauke.rifill.id/assetsdata/img/logorsud.png" alt="Logo RSUD Merauke"
                class="w-14 h-14 mr-3 flex-shrink-0">
            </a>

            <div class="min-w-0">
              <h2 class="text-lg sm:text-xl font-bold text-green-800 truncate">
                Sistem Remunerasi
              </h2>
              <p class="text-xs sm:text-sm text-gray-700 truncate">
                RSUD Merauke
              </p>
            </div>
          </div>

          <!-- Logout -->
          <div class="ml-4 flex-shrink-0">
            <a href="./config/logout.php"
              class="px-4 py-2 border border-red-400  text-red-500 hover:text-white rounded-xl shadow hover:bg-red-600 active:scale-95 transition">
              Logout
            </a>
          </div>

        </div>
      </header>

      <!-- Content -->
      <main class="flex-1 overflow-y-auto px-6 pb-10 pt-[110px] ">

        <!-- Title -->
        <h1 class="text-2xl lg:text-3xl  font-bold text-center text-gray-700 mb-2">
          Hai, <?= $_SESSION['username']; ?>
        </h1>
        <h1 class="text-2xl lg:text-4xl font-bold text-center text-indigo-700 mb-10">
          Selamat datang di Sistem Remunerasi
        </h1>

        <!-- Cards Grid -->
        <div class="flex justify-center">
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

            <!-- Card Rawat Jalan -->
            <a href="/rajal/index.php"
              class="bg-white shadow-lg rounded-xl p-6 w-72 text-center hover:shadow-xl transition duration-200">
              <div class="text-green-700 text-4xl mb-3">
                <i class="fa-solid fa-user-doctor"></i>
              </div>
              <h3 class="text-xl font-bold text-gray-800">Rawat Jalan</h3>
              <p class="text-gray-600 text-sm mt-1">Lihat data tindakan rawat jalan</p>
            </a>

            <!-- Card Rawat Inap -->
            <a href="/ranap/index.php"
              class="bg-white shadow-lg rounded-xl p-6 w-72 text-center hover:shadow-xl transition duration-200">
              <div class="text-blue-700 text-4xl mb-3">
                <i class="fa-solid fa-bed"></i>
              </div>
              <h3 class="text-xl font-bold text-gray-800">Rawat Inap</h3>
              <p class="text-gray-600 text-sm mt-1">Lihat data tindakan rawat inap</p>
            </a>

            <!-- Ralan per-bulan -->
            <a href="/bulanan_rajal/index.php"
              class="bg-white shadow-lg rounded-xl p-6 w-72 text-center hover:shadow-xl transition duration-200">
              <div class="text-yellow-500 text-4xl mb-3">
                <i class="fa-solid fa-user-doctor"></i>
              </div>
              <h3 class="text-xl font-bold text-gray-800">Rawat Jalan per-bulan</h3>
              <p class="text-gray-600 text-sm mt-1">Lihat data ralan pendapatan per-bulan</p>
            </a>

            <!-- Ranap per-bulan -->
            <a href="/ranap/index.php"
              class="bg-white shadow-lg rounded-xl p-6 w-72 text-center hover:shadow-xl transition duration-200">
              <div class="text-purple-700 text-4xl mb-3">
                <i class="fa-solid fa-bed"></i>
              </div>
              <h3 class="text-xl font-bold text-gray-800">Rawat Inap per-bulan</h3>
              <p class="text-gray-600 text-sm mt-1">Lihat data ranap pendapatan per-bulan</p>
            </a>

            <!-- BPJS Ralan -->
            <a href="/ranap/index.php"
              class="bg-white shadow-lg rounded-xl p-6 w-72 text-center hover:shadow-xl transition duration-200">
              <div class="text-green-500 text-4xl mb-3">
                <i class="fa-solid fa-money-bill"></i>
              </div>
              <h3 class="text-xl font-bold text-gray-800">BPJS </h3>
              <p class="text-gray-600 text-sm mt-1">Data Total BPJS DIterima</p>
            </a>


          </div>
        </div>

      </main>
    </div>

  </div>
</body>

</html>