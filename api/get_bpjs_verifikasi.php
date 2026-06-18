<?php
require_once '../config/conf.php';
header('Content-Type: application/json');

$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : 0;
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : 0;

$koneksi = bukakoneksi();

$where = '';
if ($bulan && $tahun) {
    $where = "WHERE bulan = '$bulan' AND tahun = '$tahun'";
}

$sql = "SELECT * FROM bpjs_verifikasi $where ORDER BY created_at DESC";
$result = mysqli_query($koneksi, $sql);

$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $row['data'] = json_decode($row['data'], true);
    $data[] = $row;
}

echo json_encode(['success' => true, 'data' => $data]);
mysqli_close($koneksi);
