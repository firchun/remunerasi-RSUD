<?php
require_once '../config/conf.php';
$koneksi = bukakoneksi();

header('Content-Type: application/json');

$type = isset($_GET['type']) ? $_GET['type'] : '';
$kd = isset($_GET['kd']) ? $_GET['kd'] : '';

$table = '';
$kd_col = '';

switch($type) {
    case 'ralan':
        $table = 'jns_perawatan';
        $kd_col = 'kd_jenis_prw';
        break;
    case 'ranap':
        $table = 'jns_perawatan_inap';
        $kd_col = 'kd_jenis_prw';
        break;
    case 'lab':
        $table = 'jns_perawatan_lab';
        $kd_col = 'kd_jenis_prw';
        break;
    case 'radiologi':
        $table = 'jns_perawatan_radiologi';
        $kd_col = 'kd_jenis_prw';
        break;
    case 'operasi':
        $table = 'paket_operasi';
        $kd_col = 'kode_paket';
        break;
}

if(!$table || !$kd) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

$kd = mysqli_real_escape_string($koneksi, $kd);
$query = "SELECT * FROM `$table` WHERE `$kd_col` = '$kd'";
$res = mysqli_query($koneksi, $query);

if($res && mysqli_num_rows($res) > 0) {
    $data = mysqli_fetch_assoc($res);
    echo json_encode(['success' => true, 'data' => $data]);
} else {
    echo json_encode(['success' => false, 'message' => 'Data not found']);
}
?>
