<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/conf.php';
$koneksi = bukakoneksi();

$draw = $_POST['draw'] ?? 1;
$start = $_POST['start'] ?? 0;
$length = $_POST['length'] ?? 25;
$search = $_POST['search']['value'] ?? '';

$bulan = $_POST['bulan'] ?? date('m');
$tahun = $_POST['tahun'] ?? date('Y');

$bulan_padded = str_pad($bulan, 2, '0', STR_PAD_LEFT);
$tgl_awal = "$tahun-$bulan_padded-01 00:00:00";
$tgl_akhir = date("Y-m-t 23:59:59", strtotime($tgl_awal));

$base = "
    FROM reg_periksa
    JOIN poliklinik ON reg_periksa.kd_poli = poliklinik.kd_poli
    JOIN penjab ON reg_periksa.kd_pj = penjab.kd_pj
    LEFT JOIN bridging_sep ON bridging_sep.no_rawat = reg_periksa.no_rawat
    LEFT JOIN rawat_jl_drpr ON rawat_jl_drpr.no_rawat = reg_periksa.no_rawat
    LEFT JOIN resep_obat ON resep_obat.no_rawat = reg_periksa.no_rawat
        AND resep_obat.status = 'ralan'
        AND resep_obat.tgl_perawatan != '0000-00-00'
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
        COUNT(DISTINCT CASE WHEN reg_periksa.kd_pj = 'BPJ' THEN reg_periksa.no_rawat END) AS jumlah_pasien_bpjs,
        COUNT(DISTINCT CASE WHEN reg_periksa.kd_pj = 'BPJ' AND reg_periksa.stts !='Batal' AND reg_periksa.stts !='Belum' AND (bridging_sep.no_sep IS NULL OR bridging_sep.no_sep = '' OR bridging_sep.no_sep = '-') THEN reg_periksa.no_rawat END) AS tanpa_sep,
        COUNT(DISTINCT CASE WHEN reg_periksa.kd_pj = 'BPJ' AND resep_obat.no_rawat IS NOT NULL AND (bridging_sep.no_sep IS NULL OR bridging_sep.no_sep = '' OR bridging_sep.no_sep = '-') THEN reg_periksa.no_rawat END) AS resep_tanpa_sep,
        COUNT(DISTINCT CASE WHEN reg_periksa.stts = 'Sudah' AND reg_periksa.stts !='Batal' AND rawat_jl_drpr.no_rawat IS NULL THEN reg_periksa.no_rawat END) AS tanpa_tindakan,
        COUNT(DISTINCT CASE WHEN resep_obat.no_rawat IS NOT NULL AND rawat_jl_drpr.no_rawat IS NULL THEN reg_periksa.no_rawat END) AS resep_tanpa_tindakan,
        COUNT(DISTINCT CASE WHEN reg_periksa.stts = 'Belum' AND reg_periksa.tgl_registrasi < CURDATE() THEN reg_periksa.no_rawat END) AS pasien_tidak_dilayani,
        COUNT(DISTINCT CASE WHEN reg_periksa.stts !='Batal' AND bridging_sep.no_sep IS NOT NULL AND bridging_sep.no_sep != '' AND bridging_sep.no_sep != '-' AND reg_periksa.kd_pj != 'BPJ' THEN reg_periksa.no_rawat END) AS status_salah
";

$query = "$select $base
    GROUP BY poliklinik.nm_poli
    ORDER BY jumlah_pasien_bpjs DESC
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
    $row['jumlah_pasien_bpjs'] = (int) $row['jumlah_pasien_bpjs'];
    $row['tanpa_sep'] = (int) $row['tanpa_sep'];
    $row['resep_tanpa_sep'] = (int) $row['resep_tanpa_sep'];
    $row['tanpa_tindakan'] = (int) $row['tanpa_tindakan'];
    $row['resep_tanpa_tindakan'] = (int) $row['resep_tanpa_tindakan'];
    $row['pasien_tidak_dilayani'] = (int) $row['pasien_tidak_dilayani'];
    $row['status_salah'] = (int) $row['status_salah'];
    $data[] = $row;
}

echo json_encode([
    "draw" => intval($draw),
    "recordsTotal" => intval($total_records),
    "recordsFiltered" => intval($filtered_records),
    "data" => $data,
]);
mysqli_close($koneksi);
