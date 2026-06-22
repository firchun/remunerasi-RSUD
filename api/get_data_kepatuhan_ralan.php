<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/conf.php';
$koneksi = bukakoneksi();

$draw   = $_POST['draw']   ?? 1;
$start  = $_POST['start']  ?? 0;
$length = $_POST['length'] ?? 25;
$search = $_POST['search']['value'] ?? '';

$bulan = $_POST['bulan'] ?? date('m');
$tahun = $_POST['tahun'] ?? date('Y');

$bulan_padded = str_pad($bulan, 2, '0', STR_PAD_LEFT);
$tgl_awal  = "$tahun-$bulan_padded-01 00:00:00";
$tgl_akhir = date("Y-m-t 23:59:59", strtotime($tgl_awal));

$konsul_list = "'RJ00769','RJ00768','RJ00764','RJ00644','RJ00012','RJ00011','RJ00010','RJ00009','RJ000008','RJ000007','RJ000003'";

$base = "
    FROM reg_periksa
    JOIN poliklinik ON reg_periksa.kd_poli = poliklinik.kd_poli
    JOIN penjab ON reg_periksa.kd_pj = penjab.kd_pj
    LEFT JOIN bridging_sep ON bridging_sep.no_rawat = reg_periksa.no_rawat
    LEFT JOIN rawat_jl_drpr ON rawat_jl_drpr.no_rawat = reg_periksa.no_rawat
    WHERE reg_periksa.status_lanjut = 'Ralan'
    AND NOT (
        reg_periksa.kd_poli = 'IGDK'
        AND EXISTS (SELECT 1 FROM kamar_inap ki WHERE ki.no_rawat = reg_periksa.no_rawat)
    )
    AND (
        CONCAT(reg_periksa.tgl_registrasi,' ',reg_periksa.jam_reg)
        BETWEEN '$tgl_awal' AND '$tgl_akhir'
        OR CONCAT(rawat_jl_drpr.tgl_perawatan,' ',rawat_jl_drpr.jam_rawat)
        BETWEEN '$tgl_awal' AND '$tgl_akhir'
    )
";

if (!empty($search)) {
    $base .= " AND (
        poliklinik.nm_poli LIKE '%$search%'
        OR reg_periksa.no_rawat LIKE '%$search%'
    )";
}

$select = "
    SELECT
        poliklinik.nm_poli,
        COUNT(DISTINCT reg_periksa.no_rawat) AS jumlah_pasien,
        COUNT(DISTINCT CASE WHEN penjab.png_jawab LIKE '%BPJS%' THEN reg_periksa.no_rawat END) AS pasien_bpjs,
        COUNT(DISTINCT CASE WHEN bridging_sep.no_sep IS NOT NULL AND bridging_sep.no_sep != '' AND bridging_sep.no_sep != '-' THEN reg_periksa.no_rawat END) AS jumlah_sep,
        COUNT(DISTINCT CASE WHEN penjab.png_jawab NOT LIKE '%BPJS%' THEN reg_periksa.no_rawat END) AS pasien_non_bpjs,
        COUNT(DISTINCT CASE WHEN reg_periksa.stts = 'Belum' THEN reg_periksa.no_rawat END) AS belum_dilayani,
        COUNT(DISTINCT CASE WHEN reg_periksa.stts = 'Batal' THEN reg_periksa.no_rawat END) AS pasien_batal,
        COUNT(DISTINCT CASE WHEN reg_periksa.stts NOT IN ('Belum','Batal') THEN reg_periksa.no_rawat END) AS pasien_terlayani,
        COUNT(CASE WHEN rawat_jl_drpr.kd_jenis_prw IN ($konsul_list) THEN 1 END) AS tindakan_konsultasi,
        COUNT(CASE WHEN rawat_jl_drpr.kd_jenis_prw NOT IN ($konsul_list) THEN 1 END) AS tindakan_lain,
        COUNT(DISTINCT CASE WHEN bridging_sep.no_sep IS NOT NULL AND bridging_sep.no_sep != '' AND bridging_sep.no_sep != '-' AND penjab.png_jawab LIKE '%BPJS%' THEN reg_periksa.no_rawat END) AS klaim_bpjs,
        COUNT(DISTINCT CASE WHEN (bridging_sep.no_sep IS NULL OR bridging_sep.no_sep = '' OR bridging_sep.no_sep = '-') AND penjab.png_jawab LIKE '%BPJS%' THEN reg_periksa.no_rawat END) AS tidak_terklaim_bpjs
";

$query = "$select $base
    GROUP BY poliklinik.nm_poli
    ORDER BY jumlah_pasien DESC
    LIMIT $start, $length
";

$result = mysqli_query($koneksi, $query);

$count_all = mysqli_fetch_assoc(mysqli_query($koneksi, "
    SELECT COUNT(DISTINCT poliklinik.nm_poli) AS total
    FROM reg_periksa
    JOIN poliklinik ON reg_periksa.kd_poli = poliklinik.kd_poli
    JOIN penjab ON reg_periksa.kd_pj = penjab.kd_pj
    WHERE reg_periksa.status_lanjut = 'Ralan'
    AND NOT (
        reg_periksa.kd_poli = 'IGDK'
        AND EXISTS (SELECT 1 FROM kamar_inap ki WHERE ki.no_rawat = reg_periksa.no_rawat)
    )
"));
$total_records = $count_all['total'];

$count_filtered = mysqli_fetch_assoc(mysqli_query($koneksi, "
    SELECT COUNT(*) AS total FROM (SELECT 1 $base GROUP BY poliklinik.nm_poli) sub
"));
$filtered_records = $count_filtered['total'];

$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $row['jumlah_pasien']      = (int) $row['jumlah_pasien'];
    $row['pasien_bpjs']        = (int) $row['pasien_bpjs'];
    $row['jumlah_sep']         = (int) $row['jumlah_sep'];
    $row['pasien_non_bpjs']    = (int) $row['pasien_non_bpjs'];
    $row['belum_dilayani']     = (int) $row['belum_dilayani'];
    $row['pasien_batal']       = (int) $row['pasien_batal'];
    $row['pasien_terlayani']   = (int) $row['pasien_terlayani'];
    $row['tindakan_konsultasi'] = (int) $row['tindakan_konsultasi'];
    $row['tindakan_lain']       = (int) $row['tindakan_lain'];
    $row['klaim_bpjs']          = (int) $row['klaim_bpjs'];
    $row['tidak_terklaim_bpjs'] = (int) $row['tidak_terklaim_bpjs'];
    $data[] = $row;
}

echo json_encode([
    "draw"            => intval($draw),
    "recordsTotal"    => intval($total_records),
    "recordsFiltered" => intval($filtered_records),
    "data"            => $data,
]);
mysqli_close($koneksi);
