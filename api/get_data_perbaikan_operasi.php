<?php
require_once '../config/conf.php';
$koneksi = bukakoneksi();

header('Content-Type: application/json');

// Parameters for filtering
$bulan = isset($_POST['bulan']) ? mysqli_real_escape_string($koneksi, $_POST['bulan']) : date('m');
$tahun = isset($_POST['tahun']) ? mysqli_real_escape_string($koneksi, $_POST['tahun']) : date('Y');

// DataTables parameters
$draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
$start = isset($_POST['start']) ? intval($_POST['start']) : 0;
$length = isset($_POST['length']) ? intval($_POST['length']) : 10;
$searchValue = isset($_POST['search']['value']) ? mysqli_real_escape_string($koneksi, $_POST['search']['value']) : '';

// Base query (Join reg_periksa and booking_operasi where status = Selesai)
$base_where = "b.status = 'Selesai' 
               AND MONTH(b.tanggal) = '$bulan' AND YEAR(b.tanggal) = '$tahun'";

if (!empty($searchValue)) {
    $base_where .= " AND (r.no_rawat LIKE '%$searchValue%' OR r.no_rkm_medis LIKE '%$searchValue%' OR p.nm_pasien LIKE '%$searchValue%')";
}

// Check if total_kp049 = 0 OR total_kp050 = 0
$having_clause = "(IFNULL(a.total_kp049, 0) = 0 OR IFNULL(m.total_kp050, 0) = 0)";

$where_clause = "WHERE $base_where AND $having_clause";

$joins = "
    JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
    JOIN booking_operasi b ON r.no_rawat = b.no_rawat
    LEFT JOIN (
        SELECT a.no_rawat, COUNT(a.no_rawat) as total_kp049 
        FROM rawat_inap_drpr a 
        JOIN jns_perawatan_inap j ON a.kd_jenis_prw = j.kd_jenis_prw 
        WHERE j.kd_kategori = 'KP049'
        GROUP BY a.no_rawat
    ) a ON r.no_rawat = a.no_rawat
    LEFT JOIN (
        SELECT a.no_rawat, COUNT(a.no_rawat) as total_kp050 
        FROM rawat_inap_drpr a 
        JOIN jns_perawatan_inap j ON a.kd_jenis_prw = j.kd_jenis_prw 
        WHERE j.kd_kategori = 'KP050'
        GROUP BY a.no_rawat
    ) m ON r.no_rawat = m.no_rawat
";

// Count total records before search
$count_query = "
    SELECT COUNT(*) as total 
    FROM reg_periksa r
    JOIN booking_operasi b ON r.no_rawat = b.no_rawat
    LEFT JOIN (
        SELECT a.no_rawat, COUNT(a.no_rawat) as total_kp049 
        FROM rawat_inap_drpr a 
        JOIN jns_perawatan_inap j ON a.kd_jenis_prw = j.kd_jenis_prw 
        WHERE j.kd_kategori = 'KP049'
        GROUP BY a.no_rawat
    ) a ON r.no_rawat = a.no_rawat
    LEFT JOIN (
        SELECT a.no_rawat, COUNT(a.no_rawat) as total_kp050 
        FROM rawat_inap_drpr a 
        JOIN jns_perawatan_inap j ON a.kd_jenis_prw = j.kd_jenis_prw 
        WHERE j.kd_kategori = 'KP050'
        GROUP BY a.no_rawat
    ) m ON r.no_rawat = m.no_rawat
    WHERE b.status = 'Selesai' 
    AND MONTH(b.tanggal) = '$bulan' AND YEAR(b.tanggal) = '$tahun'
    AND $having_clause
";
$res_count = mysqli_query($koneksi, $count_query);
$total_records = mysqli_fetch_assoc($res_count)['total'];

// Count total records after search
$count_filtered_query = "
    SELECT COUNT(*) as total 
    FROM reg_periksa r
    $joins
    $where_clause
";
$res_filtered_count = mysqli_query($koneksi, $count_filtered_query);
$total_filtered = mysqli_fetch_assoc($res_filtered_count)['total'];

// Fetch data
$query = "
    SELECT r.no_rawat, r.no_rkm_medis, p.nm_pasien, b.tanggal, b.status,
           IFNULL(a.total_kp049, 0) as kp049_count,
           IFNULL(m.total_kp050, 0) as kp050_count
    FROM reg_periksa r
    $joins
    $where_clause
    ORDER BY b.tanggal ASC, b.jam_mulai ASC
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
