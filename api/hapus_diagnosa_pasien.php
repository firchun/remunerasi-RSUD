<?php
require_once '../config/conf.php';
$koneksi = bukakoneksi();

$no_rawat = $_POST['no_rawat'] ?? '';
$kd_penyakit = $_POST['kd_penyakit'] ?? '';
$status = $_POST['status'] ?? 'Ralan';

if (empty($no_rawat) || empty($kd_penyakit)) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
    exit;
}

$no_rawat = mysqli_real_escape_string($koneksi, $no_rawat);
$kd_penyakit = mysqli_real_escape_string($koneksi, $kd_penyakit);
$status = mysqli_real_escape_string($koneksi, $status);

$sql = "DELETE FROM diagnosa_pasien WHERE no_rawat='$no_rawat' AND kd_penyakit='$kd_penyakit' AND status='$status'";
if (mysqli_query($koneksi, $sql)) {
    echo json_encode(['success' => true, 'message' => 'Diagnosa berhasil dihapus']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal: ' . mysqli_error($koneksi)]);
}

mysqli_close($koneksi);
