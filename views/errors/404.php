<?php
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>404 - Halaman Tidak Ditemukan</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
    .card { animation: fadeIn 0.4s ease-out; }
    @keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
  </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-100 to-slate-200 flex items-center justify-center p-6">
  <div class="w-full max-w-lg card">
    <div class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden text-center">
      <div class="bg-gradient-to-r from-amber-500 to-orange-600 px-6 py-10">
        <div class="text-7xl font-bold text-white opacity-80">404</div>
        <p class="text-amber-100 text-lg mt-2">Halaman Tidak Ditemukan</p>
      </div>
      <div class="p-8">
        <div class="w-20 h-20 rounded-full bg-amber-100 flex items-center justify-center mx-auto mb-5">
          <i class="fas fa-map-signs text-3xl text-amber-600"></i>
        </div>
        <p class="text-slate-600 mb-6">Halaman yang Anda cari tidak tersedia atau telah dipindahkan.</p>
        <div class="flex justify-center gap-3">
          <a href="javascript:history.back()" class="px-5 py-2.5 border border-slate-300 text-slate-600 rounded-xl hover:bg-slate-50 transition text-sm flex items-center gap-2">
            <i class="fas fa-arrow-left"></i> Kembali
          </a>
          <a href="/remon/index.php" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl transition text-sm flex items-center gap-2">
            <i class="fas fa-home"></i> Dashboard
          </a>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
