<?php
require_once '../config/conf.php';
$koneksi = bukakoneksi();

$no_rawat = $_POST['no_rawat'] ?? '';
$tekanan_darah = $_POST['tekanan_darah'] ?? '';
$nadi = $_POST['nadi'] ?? '';
$pernapasan = $_POST['pernapasan'] ?? '';
$suhu = $_POST['suhu'] ?? '';
$saturasi_o2 = $_POST['saturasi_o2'] ?? '';
$nyeri = $_POST['nyeri'] ?? '';

if (empty($no_rawat)) {
    echo json_encode(['success' => false, 'message' => 'No. Rawat tidak boleh kosong']);
    exit;
}

$no_rawat = mysqli_real_escape_string($koneksi, $no_rawat);

$sql = "UPDATE data_triase_igd SET
    tekanan_darah = '$tekanan_darah',
    nadi = '$nadi',
    pernapasan = '$pernapasan',
    suhu = '$suhu',
    saturasi_o2 = '$saturasi_o2',
    nyeri = '$nyeri'
WHERE no_rawat = '$no_rawat'";

if (mysqli_query($koneksi, $sql)) {
    echo json_encode(['success' => true, 'message' => 'Vital sign berhasil diperbarui']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal: ' . mysqli_error($koneksi)]);
}

mysqli_close($koneksi);
