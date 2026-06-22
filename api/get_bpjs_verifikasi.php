<?php
require_once '../config/conf.php';
header('Content-Type: application/json');

$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : 0;
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : 0;
$jenis = isset($_GET['jenis']) ? $_GET['jenis'] : '';

$koneksi = bukakoneksi();

$where_clauses = [];
if ($bulan && $tahun) {
    $where_clauses[] = "bulan = '$bulan' AND tahun = '$tahun'";
}
if ($jenis) {
    $jenis_esc = mysqli_real_escape_string($koneksi, $jenis);
    $where_clauses[] = "jenis = '$jenis_esc'";
}

$where = count($where_clauses) > 0 ? "WHERE " . implode(' AND ', $where_clauses) : '';

$sql = "SELECT * FROM bpjs_verifikasi $where ORDER BY created_at DESC";
$result = mysqli_query($koneksi, $sql);

$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $row['data'] = json_decode($row['data'], true);
    $data[] = $row;
}

echo json_encode(['success' => true, 'data' => $data]);
mysqli_close($koneksi);
