<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (isset($_SESSION['user_id'])) {
  header("Location: index.php");
  exit;
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login Sistem</title>
  <!-- Tailwind via CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    /* Small custom shadow to mimic original look but keep Tailwind utilities primary */
    .glass {
      background: rgba(255, 255, 255, 0.72);
      backdrop-filter: blur(6px);
    }
  </style>
</head>

<body class="min-h-screen bg-gradient-to-br from-sky-100 via-white to-slate-100 flex items-center justify-center p-6">

  <main class="w-full max-w-md">
    <section class="glass rounded-2xl border border-gray-200 p-8 sm:p-10">
      <header class="flex items-center gap-4 mb-6">
        <!-- Logo placeholder -->
        <div class="w-12 h-12 flex items-center justify-center rounded-lg  text-white text-xl font-semibold">
          <img src="https://absenrsudmerauke.rifill.id/assetsdata/img/logorsud.png" alt="Logo RSUD Merauke">
        </div>
        <div>
          <h1 class="text-2xl font-semibold text-slate-700">Masuk ke Sistem</h1>
          <p class="text-sm text-slate-500">Masukkan username dan password Anda</p>
        </div>
      </header>

      <?php if (isset($_GET['error'])): ?>
        <div class="mb-4 p-3 rounded-md bg-red-50 border border-red-200 text-red-700">
          <?= htmlspecialchars($_GET['error']) ?>
        </div>
      <?php endif; ?>

      <form action="config/proses_login.php" method="POST" class="space-y-4" novalidate>

        <label class="block">
          <span class="text-sm font-medium text-slate-600">Username</span>
          <div class="mt-1 relative">
            <input type="text" name="username" required autocomplete="username" placeholder="Username..."
              class="peer block w-full rounded-lg border border-slate-200 bg-white py-3 px-4 text-slate-700 placeholder-slate-400 shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-300 focus:border-sky-300" />
            <span class="absolute right-3 top-3 text-slate-400 text-sm hidden peer-invalid:block"></span>
          </div>
        </label>

        <label class="block">
          <span class="text-sm font-medium text-slate-600">Password</span>
          <div class="mt-1">
            <input type="password" name="password" required autocomplete="current-password" placeholder="Password..."
              class="block w-full rounded-lg border border-slate-200 bg-white py-3 px-4 text-slate-700 placeholder-slate-400 shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-300 focus:border-sky-300" />
          </div>
        </label>

        <button type="submit"
          class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 py-3 px-4 font-medium text-white shadow hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-sky-300">
          <!-- simple SVG icon -->
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"
            aria-hidden="true">
            <path fill-rule="evenodd"
              d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v3a1 1 0 00.293.707l2 2a1 1 0 101.414-1.414L11 9.586V7z"
              clip-rule="evenodd" />
          </svg>
          Masuk
        </button>

      </form>

      <footer class="mt-6 text-center text-sm text-slate-500">
        <p class="mt-2">&copy; <span id="year"></span> Remunerasi - PIT - RSUD MERAUKE</p>
      </footer>
    </section>

  </main>

  <script>
    // set year
    document.getElementById('year').textContent = new Date().getFullYear();
  </script>
</body>

</html>