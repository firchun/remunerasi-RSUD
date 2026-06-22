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
    LEFT JOIN permintaan_lab ON permintaan_lab.no_rawat = reg_periksa.no_rawat
        AND permintaan_lab.status = 'ralan'
    LEFT JOIN permintaan_radiologi ON permintaan_radiologi.no_rawat = reg_periksa.no_rawat
        AND permintaan_radiologi.status = 'Ralan'
    LEFT JOIN resep_obat ON resep_obat.no_rawat = reg_periksa.no_rawat
        AND resep_obat.status = 'ralan'
        AND resep_obat.tgl_perawatan != '0000-00-00'
    WHERE reg_periksa.status_lanjut = 'Ralan'
    AND reg_periksa.stts != 'Batal'
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
        COUNT(DISTINCT reg_periksa.no_rawat) AS jumlah_pasien,
        COUNT(DISTINCT permintaan_lab.no_rawat) AS permintaan_lab,
        COUNT(DISTINCT permintaan_radiologi.no_rawat) AS permintaan_radiologi,
        COUNT(DISTINCT resep_obat.no_rawat) AS peresepan
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
    WHERE reg_periksa.status_lanjut = 'Ralan'
    AND reg_periksa.stts != 'Batal'
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
    $jp = (int) $row['jumlah_pasien'];
    $lab = (int) $row['permintaan_lab'];
    $rad = (int) $row['permintaan_radiologi'];
    $resep = (int) $row['peresepan'];
    $row['jumlah_pasien']          = $jp;
    $row['permintaan_lab']         = $lab;
    $row['pct_lab']                = $jp > 0 ? round($lab / $jp * 100, 2) : 0;
    $row['permintaan_radiologi']   = $rad;
    $row['pct_radiologi']          = $jp > 0 ? round($rad / $jp * 100, 2) : 0;
    $row['peresepan']              = $resep;
    $row['pct_peresepan']          = $jp > 0 ? round($resep / $jp * 100, 2) : 0;
    $data[] = $row;
}

echo json_encode([
    "draw"            => intval($draw),
    "recordsTotal"    => intval($total_records),
    "recordsFiltered" => intval($filtered_records),
    "data"            => $data,
]);
mysqli_close($koneksi);
