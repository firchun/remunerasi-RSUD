<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/conf.php';
$koneksi  = bukakoneksi();
$koneksi2 = bukakoneksi2();

header('Content-Type: application/json');

// ===============
// DataTables
// ===============
$draw   = $_POST['draw']   ?? 1;
$start  = $_POST['start']  ?? 0;
$length = $_POST['length'] ?? 25;
$search = $_POST['search']['value'] ?? '';

// Filter
$tgl1      = $_POST['tgl1']      ?? '';
$tgl2      = $_POST['tgl2']      ?? '';
$kd_bangsal = $_POST['kd_bangsal'] ?? '';
$tcari     = $_POST['tcari']     ?? '';

$tgl1_format = $tgl1 ? str_replace("T", " ", $tgl1) . ":00" : "";
$tgl2_format = $tgl2 ? str_replace("T", " ", $tgl2) . ":59" : "";

// ===============================
// BASE QUERY
// ===============================
$base = "
    FROM pasien
    JOIN reg_periksa 
        ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis
    JOIN bridging_sep 
        ON bridging_sep.no_rawat = reg_periksa.no_rawat
    JOIN rawat_inap_drpr 
        ON rawat_inap_drpr.no_rawat = reg_periksa.no_rawat
    JOIN jns_perawatan_inap 
        ON rawat_inap_drpr.kd_jenis_prw = jns_perawatan_inap.kd_jenis_prw
    JOIN dokter 
        ON rawat_inap_drpr.kd_dokter = dokter.kd_dokter
    JOIN petugas 
        ON rawat_inap_drpr.nip = petugas.nip
    JOIN penjab 
        ON reg_periksa.kd_pj = penjab.kd_pj
    LEFT JOIN kamar_inap 
        ON kamar_inap.no_rawat = reg_periksa.no_rawat
    LEFT JOIN kamar 
        ON kamar_inap.kd_kamar = kamar.kd_kamar
    LEFT JOIN bangsal 
        ON kamar.kd_bangsal = bangsal.kd_bangsal
    WHERE 1=1
";

// ===============================
// FILTER
// ===============================
if ($tgl1 && $tgl2) {
    $base .= " AND CONCAT(rawat_inap_drpr.tgl_perawatan,' ',rawat_inap_drpr.jam_rawat)
               BETWEEN '$tgl1_format' AND '$tgl2_format'";
}

if ($kd_bangsal) {
    $base .= " AND bangsal.kd_bangsal = '$kd_bangsal'";
}

if ($tcari) {
    $base .= " AND (
        rawat_inap_drpr.no_rawat LIKE '%$tcari%' OR
        dokter.nm_dokter LIKE '%$tcari%' OR
        bridging_sep.no_sep LIKE '%$tcari%' OR
        bangsal.nm_bangsal LIKE '%$tcari%'
    )";
}

if ($search) {
    $base .= " AND (
        rawat_inap_drpr.no_rawat LIKE '%$search%' OR
        dokter.nm_dokter LIKE '%$search%' OR
        bangsal.nm_bangsal LIKE '%$search%' OR
        bridging_sep.no_sep LIKE '%$search%'
    )";
}

// ===============================
// MAIN QUERY
// ===============================
$query = "
SELECT 
    rawat_inap_drpr.no_rawat,
    reg_periksa.no_rkm_medis,
    pasien.nm_pasien,

    rawat_inap_drpr.kd_jenis_prw,
    jns_perawatan_inap.nm_perawatan,

    rawat_inap_drpr.kd_dokter,
    dokter.nm_dokter,
    rawat_inap_drpr.nip,
    petugas.nama AS nama_petugas,

    rawat_inap_drpr.tgl_perawatan,
    rawat_inap_drpr.jam_rawat,
    bridging_sep.no_sep,
    penjab.png_jawab,

    IFNULL((
        SELECT bangsal.nm_bangsal 
        FROM kamar_inap
        JOIN kamar ON kamar_inap.kd_kamar = kamar.kd_kamar
        JOIN bangsal ON kamar.kd_bangsal = bangsal.kd_bangsal
        WHERE kamar_inap.no_rawat = rawat_inap_drpr.no_rawat
        LIMIT 1
    ), 'Ruang Terhapus') AS ruang,

    SUM(jns_perawatan_inap.material) AS total_material,
    SUM(jns_perawatan_inap.bhp) AS total_bhp,
    SUM(jns_perawatan_inap.tarif_tindakandr) AS total_tindakan_dr,
    SUM(jns_perawatan_inap.tarif_tindakanpr) AS total_tindakan_pr,
    SUM(jns_perawatan_inap.kso) AS total_kso,
    SUM(jns_perawatan_inap.menejemen) AS total_menejemen,
    SUM(jns_perawatan_inap.total_byrdrpr) AS total_biaya_rawat

$base
GROUP BY rawat_inap_drpr.no_rawat
ORDER BY rawat_inap_drpr.no_rawat DESC
LIMIT $start, $length
";

// ===============================
// COUNT TOTAL
// ===============================
$count_total = mysqli_fetch_assoc(
    mysqli_query($koneksi, "
        SELECT COUNT(DISTINCT rawat_inap_drpr.no_rawat) AS total
        FROM rawat_inap_drpr
        JOIN reg_periksa ON reg_periksa.no_rawat = rawat_inap_drpr.no_rawat
    ")
)['total'];

// ===============================
// COUNT FILTERED
// ===============================
$count_filtered = mysqli_fetch_assoc(
    mysqli_query($koneksi, "
        SELECT COUNT(*) AS total FROM (
            SELECT rawat_inap_drpr.no_rawat
            $base
            GROUP BY rawat_inap_drpr.no_rawat
        ) AS x
    ")
)['total'];

// ===============================
// GET DATA
// ===============================
$result = mysqli_query($koneksi, $query);

$data = [];

while ($row = mysqli_fetch_assoc($result)) {

    // $no_sep = $row['no_sep'];
    // $total_bpjs = 0;

    // // Ambil dari koneksi2
    // $sql2 = mysqli_query($koneksi2, "SELECT total_bpjs FROM inapcbg WHERE no_sep='$no_sep' LIMIT 1");
    // if ($sql2 && mysqli_num_rows($sql2) > 0) {
    //     $r2 = mysqli_fetch_assoc($sql2);
    //     $total_bpjs = $r2['total_bpjs'];
    // }

    // $row['total_bpjs'] = $total_bpjs;
    $data[] = $row;
}

// ===============================
// OUTPUT JSON
// ===============================
echo json_encode([
    "draw"            => intval($draw),
    "recordsTotal"    => intval($count_total),
    "recordsFiltered" => intval($count_filtered),
    "data"            => $data
], JSON_UNESCAPED_UNICODE);