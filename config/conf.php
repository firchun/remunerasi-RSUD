<?php
session_start();
// conf.php - Helper Functions

date_default_timezone_set('Asia/Jayapura');

// DB utama
define('DB_HOST', '192.168.1.222');
define('DB_USER', 'rsud');
define('DB_PASS', 'rsud321');
define('DB_NAME', 'merauke_db');

// DB kedua
define('DB2_HOST', 'localhost:3307');
define('DB2_USER', 'root');
define('DB2_PASS', '');
define('DB2_NAME', 'remon');

// Koneksi 1
function bukakoneksi()
{
    $konektor = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if (!$konektor) {
        die(json_encode([
            'metadata' => [
                'title' => 'Config Not Found!',
                'message' => 'Koneksi database 1 gagal!',
                'code' => 404
            ]
        ]));
    }

    mysqli_set_charset($konektor, "utf8");
    return $konektor;
}

// Koneksi 2
function bukakoneksi2()
{
    $konektor2 = mysqli_connect(DB2_HOST, DB2_USER, DB2_PASS, DB2_NAME);

    if (!$konektor2) {
        die(json_encode([
            'metadata' => [
                'title' => 'Config Not Found!',
                'message' => 'Koneksi database 2 gagal!',
                'code' => 404
            ]
        ]));
    }

    mysqli_set_charset($konektor2, "utf8");
    return $konektor2;
}
// Cek login (untuk halaman yang butuh login)
function cek_login()
{
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login.php");
        exit;
    }
}