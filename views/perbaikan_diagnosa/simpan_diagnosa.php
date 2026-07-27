<?php
require_once '../../config/conf.php';
$koneksi = bukakoneksi();

$no_rawat = $_POST['no_rawat'] ?? '';
$kd_penyakit = $_POST['kd_penyakit'] ?? '';
$status = $_POST['status'] ?? 'Ralan';
$prioritas = intval($_POST['prioritas'] ?? 1);
$status_penyakit = $_POST['status_penyakit'] ?? 'Baru';

if (empty($no_rawat) || empty($kd_penyakit)) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
    exit;
}

$no_rawat = mysqli_real_escape_string($koneksi, $no_rawat);
$kd_penyakit = mysqli_real_escape_string($koneksi, $kd_penyakit);
$status = mysqli_real_escape_string($koneksi, $status);
$status_penyakit = mysqli_real_escape_string($koneksi, $status_penyakit);

// Cek apakah sudah ada
$check = mysqli_query($koneksi, "SELECT COUNT(*) AS ada FROM diagnosa_pasien WHERE no_rawat='$no_rawat' AND kd_penyakit='$kd_penyakit' AND status='$status'");
$row = mysqli_fetch_assoc($check);
if ($row['ada'] > 0) {
    echo json_encode(['success' => false, 'message' => 'Diagnosa sudah terdaftar']);
    exit;
}

$sql = "INSERT INTO diagnosa_pasien (no_rawat, kd_penyakit, status, prioritas, status_penyakit)
VALUES ('$no_rawat', '$kd_penyakit', '$status', $prioritas, '$status_penyakit')";

if (mysqli_query($koneksi, $sql)) {
    echo json_encode(['success' => true, 'message' => 'Diagnosa berhasil disimpan']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal: ' . mysqli_error($koneksi)]);
}

mysqli_close($koneksi);
