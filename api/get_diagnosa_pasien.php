<?php
require_once '../config/conf.php';
$koneksi = bukakoneksi();

$no_rawat = $_POST['no_rawat'] ?? '';
$status = $_POST['status'] ?? 'Ralan';

if (empty($no_rawat)) {
    echo json_encode(['success' => false, 'message' => 'No. Rawat tidak ditemukan']);
    exit;
}

$no_rawat = mysqli_real_escape_string($koneksi, $no_rawat);
$status = mysqli_real_escape_string($koneksi, $status);

// Data pasien
$q = mysqli_query($koneksi, "
    SELECT rp.no_rawat, rp.no_rkm_medis, rp.tgl_registrasi, rp.jam_reg,
           p.nm_pasien, p.tgl_lahir, p.jk, pj.png_jawab, poli.nm_poli
    FROM reg_periksa rp
    JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
    LEFT JOIN penjab pj ON rp.kd_pj = pj.kd_pj
    LEFT JOIN poliklinik poli ON rp.kd_poli = poli.kd_poli
    WHERE rp.no_rawat = '$no_rawat'
");
$pasien = mysqli_fetch_assoc($q);

// Diagnosa terdaftar
$q2 = mysqli_query($koneksi, "
    SELECT dp.kd_penyakit, dp.prioritas, dp.status_penyakit, p.nm_penyakit
    FROM diagnosa_pasien dp
    LEFT JOIN penyakit p ON dp.kd_penyakit = p.kd_penyakit
    WHERE dp.no_rawat = '$no_rawat' AND dp.status = '$status'
    ORDER BY dp.prioritas
");
$diagnosa = [];
while ($r = mysqli_fetch_assoc($q2)) {
    $diagnosa[] = $r;
}

echo json_encode([
    'success' => true,
    'pasien' => $pasien,
    'diagnosa' => $diagnosa
]);
mysqli_close($koneksi);
