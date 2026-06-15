<?php
session_start();

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/ErrorHandler.php';
ErrorHandler::register();

date_default_timezone_set('Asia/Jayapura');

// DB utama
define('DB_HOST', $_ENV['DB_HOST'] ?? '127.0.0.1');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'sik');

// Koneksi 1
function bukakoneksi()
{
    $konektor = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if (!$konektor) {
        die(json_encode([
            'metadata' => [
                'title' => 'Config Not Found!',
                'message' => 'Koneksi database gagal!',
                'code' => 404
            ]
        ]));
    }

    mysqli_set_charset($konektor, "utf8");
    return $konektor;
}
