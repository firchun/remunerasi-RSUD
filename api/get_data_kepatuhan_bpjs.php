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

$base = "
    FROM reg_periksa
    JOIN poliklinik ON reg_periksa.kd_poli = poliklinik.kd_poli
    JOIN penjab ON reg_periksa.kd_pj = penjab.kd_pj
    LEFT JOIN bridging_sep ON bridging_sep.no_rawat = reg_periksa.no_rawat
    LEFT JOIN bridging_surat_kontrol_bpjs sk ON sk.no_sep = bridging_sep.no_sep
    WHERE reg_periksa.status_lanjut = 'Ralan'
    AND NOT (
        reg_periksa.kd_poli = 'IGDK'
        AND EXISTS (SELECT 1 FROM kamar_inap ki WHERE ki.no_rawat = reg_periksa.no_rawat)
    )
    AND (
        CONCAT(reg_periksa.tgl_registrasi,' ',reg_periksa.jam_reg)
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
        COUNT(DISTINCT reg_periksa.no_rawat) AS total_pasien,
        COUNT(DISTINCT CASE WHEN penjab.png_jawab LIKE '%BPJS%' THEN reg_periksa.no_rawat END) AS pasien_bpjs,
        COUNT(DISTINCT sk.no_sep) AS surat_kontrol,
        COUNT(DISTINCT CASE WHEN bridging_sep.no_sep IS NOT NULL AND bridging_sep.no_sep != '' AND bridging_sep.no_sep != '-' THEN reg_periksa.no_rawat END) AS sep_terbit,
        COUNT(DISTINCT CASE WHEN reg_periksa.stts NOT IN ('Belum','Batal') THEN reg_periksa.no_rawat END) AS terlayani,
        COUNT(DISTINCT CASE WHEN reg_periksa.stts = 'Belum' THEN reg_periksa.no_rawat END) AS belum_dilayani,
        COUNT(DISTINCT CASE WHEN reg_periksa.stts = 'Batal' THEN reg_periksa.no_rawat END) AS batal_periksa
";

$query = "$select $base
    GROUP BY poliklinik.nm_poli
    ORDER BY total_pasien DESC
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
    $tp   = (int) $row['total_pasien'];
    $bpjs = (int) $row['pasien_bpjs'];
    $sk   = (int) $row['surat_kontrol'];
    $sep  = (int) $row['sep_terbit'];
    $row['total_pasien']    = $tp;
    $row['pasien_bpjs']     = $bpjs;
    $row['surat_kontrol']   = $sk;
    $row['sep_terbit']      = $sep;
    $row['terlayani']       = (int) $row['terlayani'];
    $row['belum_dilayani']  = (int) $row['belum_dilayani'];
    $row['batal_periksa']   = (int) $row['batal_periksa'];
    $row['pct_sep']         = $bpjs > 0 ? round($sep / $bpjs * 100, 2) : 0;
    $row['pct_surat_kontrol'] = $bpjs > 0 ? round($sk / $bpjs * 100, 2) : 0;
    $data[] = $row;
}

echo json_encode([
    "draw"            => intval($draw),
    "recordsTotal"    => intval($total_records),
    "recordsFiltered" => intval($filtered_records),
    "data"            => $data,
]);
mysqli_close($koneksi);
