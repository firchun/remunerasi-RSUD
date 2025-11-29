<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// header('Content-Type: application/json; charset=utf-8');
require_once '../config/conf.php';
$koneksi = bukakoneksi();
$koneksi2 = bukakoneksi2();


// Get DataTables parameters
$draw   = $_POST['draw']   ?? 1;
$start  = $_POST['start']  ?? 0;
$length = $_POST['length'] ?? 25;
$search = $_POST['search']['value'] ?? '';

// Filters
$tgl1      = $_POST['tgl1']      ?? '';
$tgl2      = $_POST['tgl2']      ?? '';
$kd_dokter = $_POST['kd_dokter'] ?? '';
$nip       = $_POST['nip']       ?? '';
$kd_poli   = $_POST['kd_poli']   ?? '';
$kd_pj     = $_POST['kd_pj']     ?? '';
$tcari     = $_POST['tcari']     ?? '';
$status    = $_POST['status']    ?? '';

// Convert datetime-local → MYSQL
$tgl1_formatted = !empty($tgl1) ? str_replace("T", " ", $tgl1) . ":00" : "";
$tgl2_formatted = !empty($tgl2) ? str_replace("T", " ", $tgl2) . ":59" : "";

// ===============================
// BASE QUERY (tanpa group, tanpa order, tanpa limit)
// ===============================
$base = "
    FROM pasien 
    JOIN reg_periksa ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis
    JOIN bridging_sep ON bridging_sep.no_rawat = reg_periksa.no_rawat
    JOIN rawat_jl_drpr ON rawat_jl_drpr.no_rawat = reg_periksa.no_rawat
    JOIN jns_perawatan ON rawat_jl_drpr.kd_jenis_prw = jns_perawatan.kd_jenis_prw
    JOIN dokter ON rawat_jl_drpr.kd_dokter = dokter.kd_dokter
    JOIN poliklinik ON reg_periksa.kd_poli = poliklinik.kd_poli
    JOIN penjab ON reg_periksa.kd_pj = penjab.kd_pj
    JOIN petugas ON rawat_jl_drpr.nip = petugas.nip
    LEFT JOIN detail_pemberian_obat ON detail_pemberian_obat.no_rawat =  reg_periksa.no_rawat AND detail_pemberian_obat.status = 'Ralan'
    LEFT JOIN periksa_lab ON periksa_lab.no_rawat =  reg_periksa.no_rawat AND periksa_lab.status = 'Ralan'
    LEFT JOIN periksa_radiologi ON periksa_radiologi.no_rawat =  reg_periksa.no_rawat AND periksa_radiologi.status = 'Ralan'
    WHERE 1=1
    AND reg_periksa.kd_poli != 'IGDK'
";

// ===============================
// FILTERS
// ===============================

// Date filter
if (!empty($tgl1) && !empty($tgl2)) {
    $base .= " AND CONCAT(rawat_jl_drpr.tgl_perawatan,' ',rawat_jl_drpr.jam_rawat) 
               BETWEEN '$tgl1_formatted' AND '$tgl2_formatted'";
}


// Poli
if (!empty($kd_poli)) {
    $base .= " AND reg_periksa.kd_poli = '$kd_poli'";
}



// Cari manual (tcari)
if (!empty($tcari)) {
    $base .= " AND (
        rawat_jl_drpr.no_rawat LIKE '%$tcari%' 
        OR pasien.nm_pasien LIKE '%$tcari%' 
        OR dokter.nm_dokter LIKE '%$tcari%' 
        OR reg_periksa.no_rkm_medis LIKE '%$tcari%'
        OR bridging_sep.no_sep LIKE '%$tcari%'
    )";
}

// Search DataTables
if (!empty($search)) {
    $base .= " AND (
        rawat_jl_drpr.no_rawat LIKE '%$search%' 
        OR reg_periksa.no_rkm_medis LIKE '%$search%'
        OR pasien.nm_pasien LIKE '%$search%'
         OR dokter.nm_dokter LIKE '%$tcari%'
        OR poliklinik.nm_poli LIKE '%$search%'
        OR bridging_sep.no_sep LIKE '%$search%'
    )";
}

$query = "
SELECT 
    rawat_jl_drpr.no_rawat,
    reg_periksa.no_rkm_medis,
    pasien.nm_pasien,
    bridging_sep.no_sep,
    dokter.nm_dokter,
    poliklinik.nm_poli,
    penjab.png_jawab,
    jns_perawatan.total_byrdrpr,

    MIN(rawat_jl_drpr.tgl_perawatan) AS tgl_perawatan,
    MIN(dokter.nm_dokter) AS nama_dokter,
    MIN(rawat_jl_drpr.jam_rawat) AS jam_rawat,
    MIN(CONCAT(rawat_jl_drpr.tgl_perawatan,' ',rawat_jl_drpr.jam_rawat)) AS waktu_perawatan,

    SUM(jns_perawatan.material) AS total_material,
    SUM(jns_perawatan.bhp) AS total_bhp,
    SUM(jns_perawatan.tarif_tindakandr) AS total_tindakan_dr,
    SUM(jns_perawatan.tarif_tindakanpr) AS total_tindakan_pr,
    SUM(jns_perawatan.kso) AS total_kso,
    SUM(jns_perawatan.menejemen) AS total_menejemen,
    SUM(jns_perawatan.total_byrdrpr) AS total_biaya_rawat,

    SUM(IFNULL(periksa_lab.bagian_rs,0)) AS total_material_lab,
    SUM(IFNULL(periksa_lab.tarif_tindakan_dokter,0)) AS total_dokter_lab,
    SUM(IFNULL(periksa_lab.tarif_tindakan_petugas,0)) AS total_petugas_lab,
    SUM(IFNULL(periksa_lab.menejemen,0)) AS total_menejemen_lab,
    SUM(IFNULL(periksa_lab.biaya,0)) AS total_lab,

     SUM(IFNULL(periksa_radiologi.bagian_rs,0)) AS total_material_radiologi,
    SUM(IFNULL(periksa_radiologi.tarif_tindakan_dokter,0)) AS total_dokter_radiologi,
    SUM(IFNULL(periksa_radiologi.tarif_tindakan_petugas,0)) AS total_petugas_radiologi,
    SUM(IFNULL(periksa_radiologi.menejemen,0)) AS total_menejemen_radiologi,
    SUM(IFNULL(periksa_radiologi.biaya,0)) AS total_radiologi,
    
    SUM(IFNULL(detail_pemberian_obat.total,0)) AS total_obat,
    SUM(IFNULL(detail_pemberian_obat.total,0)) * 1.11 AS total_obat_dan_ppn
    
$base

GROUP BY rawat_jl_drpr.no_rawat
ORDER BY rawat_jl_drpr.no_rawat DESC
LIMIT $start, $length
";

// ===============================
// COUNT TOTAL (tanpa filter)
// ===============================
$count_query = "
SELECT COUNT(DISTINCT rawat_jl_drpr.no_rawat) AS total
FROM pasien 
JOIN reg_periksa ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis
JOIN rawat_jl_drpr ON rawat_jl_drpr.no_rawat = reg_periksa.no_rawat
";

$c_total = mysqli_fetch_assoc(mysqli_query($koneksi, $count_query));
$total_records = $c_total['total'];

// ===============================
// COUNT FILTERED
// ===============================
$count_filtered_query = "
SELECT COUNT(*) AS total FROM (
    SELECT rawat_jl_drpr.no_rawat
    $base
    GROUP BY rawat_jl_drpr.no_rawat
) AS x
";

$c_filtered = mysqli_fetch_assoc(mysqli_query($koneksi, $count_filtered_query));
$filtered_records = $c_filtered['total'];

// ===============================
// GET MAIN DATA
// ===============================
$result = mysqli_query($koneksi, $query);

$data = [];
// while ($row = mysqli_fetch_assoc($result)) {
//     $data[] = $row;
// }
while ($row = mysqli_fetch_assoc($result)) {

    $no_sep = $row['no_sep'];
    $total_bpjs = 0;

    // Ambil dari koneksi2 (table inacbg atau apa pun)
    $q2 = mysqli_query($koneksi2, "SELECT total_bpjs FROM inacbd WHERE no_sep = '$no_sep' LIMIT 1");

    if ($q2 && mysqli_num_rows($q2) > 0) {
        $r2 = mysqli_fetch_assoc($q2);
        $total_bpjs = $r2['total_bpjs'];
    }

    // Tambahkan ke hasil JSON
    $row['total_bpjs'] = $total_bpjs;

    $data[] = $row;
}

// RESPONSE
echo json_encode([
    "draw" => intval($draw),
    "recordsTotal" => intval($total_records),
    "recordsFiltered" => intval($filtered_records),
    "data" => $data
]);

mysqli_close($koneksi);