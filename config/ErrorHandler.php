<?php
class ErrorHandler
{
    private static $registered = false;

    public static function register()
    {
        if (self::$registered) return;
        self::$registered = true;

        error_reporting(E_ALL);
        ini_set('display_errors', 0);
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
        ob_start();
    }

    public static function handleError($severity, $message, $file, $line)
    {
        if (!(error_reporting() & $severity)) return;
        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    public static function handleException($e)
    {
        self::cleanOutput();
        if (self::isApiRequest()) {
            self::renderJsonError($e);
        } else {
            self::renderErrorPage($e);
        }
        exit;
    }

    public static function handleShutdown()
    {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            self::cleanOutput();
            $e = new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']);
            if (self::isApiRequest()) {
                self::renderJsonError($e);
            } else {
                self::renderErrorPage($e);
            }
            exit;
        }
    }

    private static function isApiRequest()
    {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }
        if (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            return true;
        }
        return false;
    }

    private static function renderJsonError($e)
    {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'metadata' => [
                'title'   => 'Error',
                'message' => $e->getMessage(),
                'code'    => 500,
            ]
        ]);
    }

    private static function cleanOutput()
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    private static function renderErrorPage($e)
    {
        $message = $e->getMessage();
        $file = $e->getFile();
        $line = $e->getLine();
        $code = $e->getCode();

        $isDev = defined('APP_ENV') ? APP_ENV === 'development' : true;

        http_response_code(500);
        ?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Error - RSUD MERAUKE</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
    .error-card { animation: slideIn 0.3s ease-out; }
    @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    .code-block { scrollbar-width: thin; }
    .line-highlight { background: #fef2f2; display: block; margin: 0 -1rem; padding: 0 1rem; border-left: 3px solid #ef4444; }
  </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-100 to-slate-200 flex items-center justify-center p-6">
  <div class="w-full max-w-4xl error-card">
    <div class="bg-white rounded-2xl shadow-xl border border-red-200 overflow-hidden">
      <div class="bg-gradient-to-r from-red-600 to-red-700 px-6 py-5 flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center text-2xl text-white">
          <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="flex-1">
          <h1 class="text-xl font-bold text-white">Terjadi Kesalahan</h1>
          <p class="text-red-100 text-sm mt-0.5">Sistem menemui error yang tidak terduga</p>
        </div>
        <a href="javascript:history.back()" class="px-4 py-2 bg-white/20 hover:bg-white/30 text-white rounded-lg text-sm transition flex items-center gap-2">
          <i class="fas fa-arrow-left"></i> Kembali
        </a>
      </div>

      <div class="p-6 space-y-5">
        <div>
          <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2">Pesan Error</h2>
          <div class="bg-red-50 border border-red-200 rounded-xl px-5 py-4">
            <p class="text-red-800 font-mono text-sm"><?= htmlspecialchars($message) ?></p>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="bg-slate-50 rounded-xl border border-slate-200 px-5 py-4">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">File</span>
            <p class="text-sm font-mono text-slate-800 mt-1 break-all"><?= htmlspecialchars($file) ?></p>
          </div>
          <div class="bg-slate-50 rounded-xl border border-slate-200 px-5 py-4">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Baris</span>
            <p class="text-2xl font-bold text-red-600 mt-1"><?= (int) $line ?></p>
          </div>
        </div>

        <?php if ($isDev && $file && file_exists($file)): $snippet = self::getFileSnippet($file, $line, 10); ?>
        <div>
          <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2">Kode Sekitar Error</h2>
          <div class="bg-slate-900 rounded-xl overflow-hidden shadow-inner">
            <div class="flex items-center gap-2 px-4 py-2 bg-slate-800 border-b border-slate-700">
              <div class="flex gap-1.5">
                <span class="w-3 h-3 rounded-full bg-red-500"></span>
                <span class="w-3 h-3 rounded-full bg-yellow-500"></span>
                <span class="w-3 h-3 rounded-full bg-green-500"></span>
              </div>
              <span class="text-xs text-slate-400 font-mono ml-2"><?= htmlspecialchars(basename($file)) ?></span>
            </div>
            <div class="overflow-x-auto p-0">
              <table class="w-full text-sm font-mono">
                <?php foreach ($snippet as $num => $code_line): ?>
                <tr class="<?= $num === (int)$line ? 'bg-red-900/30' : 'hover:bg-slate-800/50' ?>">
                  <td class="select-none text-right text-slate-600 px-4 py-0.5 border-r border-slate-700 w-16 <?= $num === (int)$line ? 'text-red-400' : '' ?>"><?= $num ?></td>
                  <td class="text-slate-300 px-4 py-0.5 whitespace-pre <?= $num === (int)$line ? 'border-l-2 border-red-500 text-red-200' : '' ?>"><?= htmlspecialchars($code_line) ?></td>
                </tr>
                <?php endforeach; ?>
              </table>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($isDev && $e instanceof ErrorException): $trace = $e->getTrace(); if (!empty($trace)): ?>
        <div>
          <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2">Stack Trace</h2>
          <div class="bg-slate-50 border border-slate-200 rounded-xl overflow-hidden">
            <div class="max-h-60 overflow-y-auto divide-y divide-slate-200">
              <?php foreach ($trace as $i => $t): ?>
              <div class="px-5 py-3 text-sm <?= $i === 0 ? 'bg-red-50' : '' ?>">
                <span class="font-mono text-xs text-slate-400">#<?= $i ?></span>
                <?php if (isset($t['class'])): ?>
                <span class="font-medium text-slate-700"><?= htmlspecialchars($t['class'] . $t['type'] . $t['function']) ?></span>
                <?php elseif (isset($t['function'])): ?>
                <span class="font-medium text-slate-700"><?= htmlspecialchars($t['function']) ?></span>
                <?php endif; ?>
                <span class="text-slate-500 ml-2"><?= isset($t['file']) ? htmlspecialchars(basename($t['file']) . ':' . $t['line']) : '' ?></span>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <?php endif; endif; ?>

        <div class="flex items-center justify-between pt-2 border-t border-slate-200">
          <p class="text-xs text-slate-400">
            <i class="far fa-clock mr-1"></i>
            <?= date('d/m/Y H:i:s') ?>
          </p>
          <div class="flex gap-2">
            <a href="/remon/index.php" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm transition flex items-center gap-2">
              <i class="fas fa-home"></i> Dashboard
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
        <?php
    }

    private static function getFileSnippet($file, $line, $padding = 10)
    {
        $snippet = [];
        try {
            $handle = fopen($file, 'r');
            if ($handle) {
                $start = max(1, $line - $padding);
                $end = $line + $padding;
                $current = 1;
                while (($buffer = fgets($handle)) !== false) {
                    if ($current > $end) break;
                    if ($current >= $start) {
                        $snippet[$current] = $buffer;
                    }
                    $current++;
                }
                fclose($handle);
            }
        } catch (\Exception $e) {}
        return $snippet;
    }
}
