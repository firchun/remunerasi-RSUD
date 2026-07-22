<?php
require_once '../config/conf.php';
$koneksi = bukakoneksi();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$no_rawat_list = isset($_POST['no_rawat']) ? $_POST['no_rawat'] : [];
$kd_jenis_prw = isset($_POST['kd_jenis_prw']) ? mysqli_real_escape_string($koneksi, $_POST['kd_jenis_prw']) : '';
$nip = isset($_POST['nip']) ? mysqli_real_escape_string($koneksi, $_POST['nip']) : '';

if (empty($no_rawat_list) || empty($kd_jenis_prw) || empty($nip)) {
    echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap']);
    exit;
}

if (!is_array($no_rawat_list)) {
    $no_rawat_list = [$no_rawat_list];
}

// Get tariff details from jns_perawatan
$q_tarif = "SELECT material, bhp, tarif_tindakandr, tarif_tindakanpr, kso, menejemen, total_byrdrpr 
            FROM jns_perawatan WHERE kd_jenis_prw = '$kd_jenis_prw' AND kd_kategori = 'KP003'";
$res_tarif = mysqli_query($koneksi, $q_tarif);

if (!$res_tarif || mysqli_num_rows($res_tarif) == 0) {
    echo json_encode(['success' => false, 'message' => 'Tindakan konsultasi tidak ditemukan']);
    exit;
}

$tarif = mysqli_fetch_assoc($res_tarif);
$material = (float)$tarif['material'];
$bhp = (float)$tarif['bhp'];
$tindakandr = (float)$tarif['tarif_tindakandr'];
$tindakanpr = (float)$tarif['tarif_tindakanpr'];
$kso = (float)$tarif['kso'];
$menejemen = (float)$tarif['menejemen'];
$biaya_rawat = (float)$tarif['total_byrdrpr'];

$tgl_perawatan = date('Y-m-d');
$jam_rawat = date('H:i:s');

mysqli_autocommit($koneksi, false);
$success_count = 0;
$errors = [];

foreach ($no_rawat_list as $no_rawat) {
    $no_rawat = mysqli_real_escape_string($koneksi, $no_rawat);

    // Get kd_dokter from reg_periksa
    $q_dokter = "SELECT kd_dokter FROM reg_periksa WHERE no_rawat = '$no_rawat'";
    $res_dokter = mysqli_query($koneksi, $q_dokter);
    
    if (!$res_dokter || mysqli_num_rows($res_dokter) == 0) {
        $errors[] = "$no_rawat: Registrasi tidak ditemukan";
        continue;
    }
    
    $kd_dokter = mysqli_fetch_assoc($res_dokter)['kd_dokter'];

    // Insert into rawat_jl_drpr (stts_bayar is omitted, database default applies)
    $q_insert = "INSERT INTO rawat_jl_drpr (
                    no_rawat, kd_jenis_prw, kd_dokter, nip, tgl_perawatan, jam_rawat, 
                    material, bhp, tarif_tindakandr, tarif_tindakanpr, kso, menejemen, biaya_rawat
                 ) VALUES (
                    '$no_rawat', '$kd_jenis_prw', '$kd_dokter', '$nip', '$tgl_perawatan', '$jam_rawat',
                    '$material', '$bhp', '$tindakandr', '$tindakanpr', '$kso', '$menejemen', '$biaya_rawat'
                 )";
                 
    if (mysqli_query($koneksi, $q_insert)) {
        $success_count++;
    } else {
        $errors[] = "$no_rawat: " . mysqli_error($koneksi);
    }
}

if ($success_count > 0) {
    mysqli_commit($koneksi);
    echo json_encode(['success' => true, 'message' => "Berhasil menyimpan $success_count data konsultasi"]);
} else {
    mysqli_rollback($koneksi);
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan data. ' . implode(', ', $errors)]);
}
?>
