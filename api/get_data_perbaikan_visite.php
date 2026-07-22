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

// Base where clause
$base_where = "r.status_lanjut = 'Ranap' AND r.stts != 'Batal' AND r.stts != 'Belum' 
               AND MONTH(r.tgl_registrasi) = '$bulan' AND YEAR(r.tgl_registrasi) = '$tahun'";

if (!empty($searchValue)) {
    $base_where .= " AND (r.no_rawat LIKE '%$searchValue%' OR r.no_rkm_medis LIKE '%$searchValue%' OR p.nm_pasien LIKE '%$searchValue%')";
}

// Logic: Check if total_visite < total_lama
$having_clause = "IFNULL(v.total_visite, 0) < IFNULL(k.total_lama, 0)";

// Full Where Clause for counting and fetching
$where_clause = "WHERE $base_where AND $having_clause";

// Common JOIN string
$joins = "
    JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
    JOIN dokter d ON r.kd_dokter = d.kd_dokter
    LEFT JOIN (
        SELECT no_rawat, SUM(lama) as total_lama 
        FROM kamar_inap 
        GROUP BY no_rawat
    ) k ON r.no_rawat = k.no_rawat
    LEFT JOIN (
        SELECT a.no_rawat, COUNT(a.no_rawat) as total_visite 
        FROM rawat_inap_drpr a 
        JOIN jns_perawatan_inap b ON a.kd_jenis_prw = b.kd_jenis_prw 
        WHERE b.kd_kategori = 'KP026'
        GROUP BY a.no_rawat
    ) v ON r.no_rawat = v.no_rawat
";

// Count total records before search (we still apply the having clause because it's part of the base feature)
$count_query = "
    SELECT COUNT(*) as total 
    FROM reg_periksa r
    JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
    LEFT JOIN (
        SELECT no_rawat, SUM(lama) as total_lama 
        FROM kamar_inap 
        GROUP BY no_rawat
    ) k ON r.no_rawat = k.no_rawat
    LEFT JOIN (
        SELECT a.no_rawat, COUNT(a.no_rawat) as total_visite 
        FROM rawat_inap_drpr a 
        JOIN jns_perawatan_inap b ON a.kd_jenis_prw = b.kd_jenis_prw 
        WHERE b.kd_kategori = 'KP026'
        GROUP BY a.no_rawat
    ) v ON r.no_rawat = v.no_rawat
    WHERE r.status_lanjut = 'Ranap' AND r.stts != 'Batal' AND r.stts != 'Belum' 
    AND MONTH(r.tgl_registrasi) = '$bulan' AND YEAR(r.tgl_registrasi) = '$tahun'
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
    SELECT r.no_rawat, r.no_rkm_medis, p.nm_pasien, r.tgl_registrasi, d.nm_dokter, r.status_lanjut,
           IFNULL(k.total_lama, 0) as lama_perawatan,
           IFNULL(v.total_visite, 0) as visite_diinput
    FROM reg_periksa r
    $joins
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