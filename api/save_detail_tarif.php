<?php
require_once '../config/conf.php';
$koneksi = bukakoneksi();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$type = isset($_POST['type']) ? $_POST['type'] : '';
$kd = isset($_POST['kd']) ? $_POST['kd'] : '';

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

// Fields that should not be updated directly from the form iteration
$exclude_fields = [$kd_col, 'nm_perawatan', 'kd_kategori', 'kd_pj', 'kd_poli', 'kd_bangsal', 'status', 'kelas', 'kategori'];

$updates = [];
foreach ($_POST as $key => $value) {
    if ($key !== 'type' && $key !== 'kd' && !in_array($key, $exclude_fields)) {
        // Assume all other fields being sent are numeric tarif components
        $val = str_replace(',', '', $value); // remove thousands separator if any
        $val = is_numeric($val) ? (float)$val : 0;
        $key = mysqli_real_escape_string($koneksi, $key);
        $updates[] = "`$key` = $val";
    }
}

if(count($updates) === 0) {
    echo json_encode(['success' => false, 'message' => 'No data to update']);
    exit;
}

// Calculate totals if necessary based on type
// For ralan and ranap, we usually compute total_byrdr, total_byrpr, and total_byrdrpr if they are in the updates
// But we'll just trust what the user inputs or we can calculate them
if ($type == 'ralan' || $type == 'ranap') {
    // If not all are sent, this might be tricky, but we assume the form sends all
    $total_byrdr = (float)($_POST['material'] ?? 0) + (float)($_POST['bhp'] ?? 0) + (float)($_POST['tarif_tindakandr'] ?? 0) + (float)($_POST['kso'] ?? 0) + (float)($_POST['menejemen'] ?? 0);
    $total_byrpr = (float)($_POST['material'] ?? 0) + (float)($_POST['bhp'] ?? 0) + (float)($_POST['tarif_tindakanpr'] ?? 0) + (float)($_POST['kso'] ?? 0) + (float)($_POST['menejemen'] ?? 0);
    $total_byrdrpr = (float)($_POST['material'] ?? 0) + (float)($_POST['bhp'] ?? 0) + (float)($_POST['tarif_tindakandr'] ?? 0) + (float)($_POST['tarif_tindakanpr'] ?? 0) + (float)($_POST['kso'] ?? 0) + (float)($_POST['menejemen'] ?? 0);
    
    $updates[] = "`total_byrdr` = $total_byrdr";
    $updates[] = "`total_byrpr` = $total_byrpr";
    $updates[] = "`total_byrdrpr` = $total_byrdrpr";
} else if ($type == 'lab' || $type == 'radiologi') {
    $total_byr = (float)($_POST['bagian_rs'] ?? 0) + (float)($_POST['bhp'] ?? 0) + (float)($_POST['tarif_perujuk'] ?? 0) + (float)($_POST['tarif_tindakan_dokter'] ?? 0) + (float)($_POST['tarif_tindakan_petugas'] ?? 0) + (float)($_POST['kso'] ?? 0) + (float)($_POST['menejemen'] ?? 0);
    $updates[] = "`total_byr` = $total_byr";
}
// For operasi, total is just computed on the fly in the perbaikan query or if there's a total column, but there's no total column.

$update_str = implode(', ', $updates);
$query = "UPDATE `$table` SET $update_str WHERE `$kd_col` = '$kd'";

if(mysqli_query($koneksi, $query)) {
    echo json_encode(['success' => true, 'message' => 'Tarif berhasil diupdate']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal mengupdate tarif: ' . mysqli_error($koneksi)]);
}
?>
