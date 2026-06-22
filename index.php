<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once './config/conf.php';

$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$uriPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = trim(str_replace($basePath, '', $uriPath), '/');

$routes = [
    'rajal'          => './views/rajal/index.php',
    'ranap'          => './views/ranap/index.php',
    'bpjs'           => './views/bpjs/index.php',
    'bulanan-rajal'  => './views/bulanan_rajal/index.php',
    'bulanan-ranap'  => './views/bulanan_ranap/index.php',
    'cari-petugas'   => './views/cari_petugas/index.php',
    'jasaraharja'    => './views/jasaraharja/index.php',
    'laporan-gabungan' => './views/laporan_gabungan/index.php',
    'tunsus'         => './views/tunsus.php',
    'hitung-jasa-ralan' => './views/hitung_jasa_ralan/index.php',
    'hitung-jasa-dokter-ralan' => './views/hitung_jasa_dokter_ralan/index.php',
    'hitung-jasa-dokter-ralan/detail' => './views/hitung_jasa_dokter_ralan/detail.php',
    'hitung-jasa-dokter-ranap' => './views/hitung_jasa_dokter_ranap/index.php',
    'hitung-jasa-dokter-ranap/detail' => './views/hitung_jasa_dokter_ranap/detail.php',
    'hitung-jasa-ranap' => './views/hitung_jasa_ranap/index.php',
    'bpjs-verifikasi'   => './views/bpjs_verifikasi/index.php',
    'kepatuhan-ralan'   => './views/kepatuhan_ralan/index.php',
    'kepatuhan-penunjang-ralan' => './views/kepatuhan_penunjang_ralan/index.php',
    'kepatuhan-bpjs'            => './views/kepatuhan_bpjs/index.php',
    'kepatuhan-remunerasi'      => './views/kepatuhan_remunerasi/index.php',
];

if ($path !== '' && isset($routes[$path])) {
    $load = $routes[$path];
    chdir(dirname($load));
    require basename($load);
    exit;
}

// Dashboard
$pageTitle = 'Dashboard - RSUD MERAUKE';
$rootPath = '';
$extraHead = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>';
require_once './views/layouts/header.php';
require_once './views/dashboard.php';
require_once './views/layouts/footer.php';
