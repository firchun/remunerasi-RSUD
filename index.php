<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once './config/conf.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePath = '/remon';
$path = trim(str_replace($basePath, '', $uri), '/');

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
