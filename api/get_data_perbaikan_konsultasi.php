<?php
require_once '../config/conf.php';
$koneksi = bukakoneksi();

header('Content-Type: application/json');

// Parameters for filtering
$bulan = isset($_POST['bulan']) ? mysqli_real_escape_string($koneksi, $_POST['bulan']) : date('m');
$tahun = isset($_POST['tahun']) ? mysqli_real_escape_string($koneksi, $_POST['tahun']) : date('Y');
$kd_poli = isset($_POST['kd_poli']) ? mysqli_real_escape_string($koneksi, $_POST['kd_poli']) : '';

// DataTables parameters
$draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
$start = isset($_POST['start']) ? intval($_POST['start']) : 0;
$length = isset($_POST['length']) ? intval($_POST['length']) : 10;
$searchValue = isset($_POST['search']['value']) ? mysqli_real_escape_string($koneksi, $_POST['search']['value']) : '';

// Base query (Only Rajal and valid statuses)
$base_where = "r.status_lanjut = 'Ralan' AND r.stts != 'Batal' AND r.stts != 'Belum'
               AND MONTH(r.tgl_registrasi) = '$bulan' AND YEAR(r.tgl_registrasi) = '$tahun'";

if (!empty($kd_poli)) {
    $base_where .= " AND r.kd_poli = '$kd_poli'";
}

if (!empty($searchValue)) {
    $base_where .= " AND (r.no_rawat LIKE '%$searchValue%' OR r.no_rkm_medis LIKE '%$searchValue%' OR p.nm_pasien LIKE '%$searchValue%')";
}

// Check if no_rawat is not in rawat_jl_drpr for category KP003
// We only check rawat_jl_drpr according to user's instruction
$missing_kp003_clause = "r.no_rawat NOT IN (
    SELECT a.no_rawat 
    FROM rawat_jl_drpr a 
    JOIN jns_perawatan b ON a.kd_jenis_prw = b.kd_jenis_prw 
    WHERE b.kd_kategori = 'KP003'
)";

$where_clause = "WHERE $base_where AND $missing_kp003_clause";

// Count total records before search
$count_query = "
    SELECT COUNT(*) as total 
    FROM reg_periksa r
    JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
    WHERE r.status_lanjut = 'Ralan' AND r.stts != 'Batal' AND r.stts != 'Belum' 
    AND MONTH(r.tgl_registrasi) = '$bulan' AND YEAR(r.tgl_registrasi) = '$tahun'
    " . (!empty($kd_poli) ? " AND r.kd_poli = '$kd_poli'" : "") . "
    AND $missing_kp003_clause
";
$res_count = mysqli_query($koneksi, $count_query);
$total_records = mysqli_fetch_assoc($res_count)['total'];

// Count total records after search
$count_filtered_query = "
    SELECT COUNT(*) as total 
    FROM reg_periksa r
    JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
    $where_clause
";
$res_filtered_count = mysqli_query($koneksi, $count_filtered_query);
$total_filtered = mysqli_fetch_assoc($res_filtered_count)['total'];

// Fetch data
$query = "
    SELECT r.no_rawat, r.no_rkm_medis, p.nm_pasien, r.tgl_registrasi, d.nm_dokter, pl.nm_poli, r.status_lanjut
    FROM reg_periksa r
    JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
    JOIN dokter d ON r.kd_dokter = d.kd_dokter
    JOIN poliklinik pl ON r.kd_poli = pl.kd_poli
    $where_clause
    ORDER BY r.tgl_registrasi ASC, r.jam_reg ASC
    LIMIT $start, $length
";
$result = mysqli_query($koneksi, $query);

$data = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
}

echo json_encode([
    "draw" => $draw,
    "recordsTotal" => $total_records,
    "recordsFiltered" => $total_filtered,
    "data" => $data
]);
?>