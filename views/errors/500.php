<?php
http_response_code(500);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>500 - Internal Server Error</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-100 to-slate-200 flex items-center justify-center p-6">
  <div class="w-full max-w-lg">
    <div class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden text-center">
      <div class="bg-gradient-to-r from-red-500 to-red-600 px-6 py-10">
        <div class="text-7xl font-bold text-white opacity-80">500</div>
        <p class="text-red-100 text-lg mt-2">Internal Server Error</p>
      </div>
      <div class="p-8">
        <p class="text-slate-600 mb-2"><?= htmlspecialchars($message ?? 'Terjadi kesalahan server') ?></p>
        <?php if (!empty($file)): ?>
          <p class="text-xs text-slate-400 font-mono mt-2"><?= htmlspecialchars($file) ?>:<?= (int)($line ?? 0) ?></p>
        <?php endif; ?>
        <div class="flex justify-center gap-3 mt-6">
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
