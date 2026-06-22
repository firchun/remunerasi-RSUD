<?php
require_once '../config/conf.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['bulan']) || !isset($input['tahun']) || !isset($input['rows'])) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
    exit;
}

$bulan = (int)$input['bulan'];
$tahun = (int)$input['tahun'];
$jenis = $input['jenis'] ?? '';
$filename = $input['filename'] ?? '';
$rows = $input['rows'];

if (!is_array($rows) || empty($rows)) {
    echo json_encode(['success' => false, 'message' => 'Data kosong']);
    exit;
}

$koneksi = bukakoneksi();

$bulan_esc = mysqli_real_escape_string($koneksi, $bulan);
$tahun_esc = mysqli_real_escape_string($koneksi, $tahun);
$jenis_esc = mysqli_real_escape_string($koneksi, $jenis);
$filename_esc = mysqli_real_escape_string($koneksi, $filename);
$data_json = mysqli_real_escape_string($koneksi, json_encode($rows));

$sql = "INSERT INTO bpjs_verifikasi (filename, bulan, tahun, jenis, data)
        VALUES ('$filename_esc', '$bulan_esc', '$tahun_esc', '$jenis_esc', '$data_json')";

if (mysqli_query($koneksi, $sql)) {
    $id = mysqli_insert_id($koneksi);
    echo json_encode(['success' => true, 'id' => $id, 'message' => 'Data berhasil disimpan']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan: ' . mysqli_error($koneksi)]);
}

mysqli_close($koneksi);
