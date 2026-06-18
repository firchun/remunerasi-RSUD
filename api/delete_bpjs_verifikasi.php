<?php
require_once '../config/conf.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID tidak ditemukan']);
    exit;
}

$id = (int)$input['id'];
$koneksi = bukakoneksi();

$sql = "DELETE FROM bpjs_verifikasi WHERE id = '$id'";
if (mysqli_query($koneksi, $sql)) {
    echo json_encode(['success' => true, 'message' => 'Data berhasil dihapus']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal menghapus: ' . mysqli_error($koneksi)]);
}

mysqli_close($koneksi);
