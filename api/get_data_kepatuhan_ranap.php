<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/conf.php';
require_once __DIR__ . '/kepatuhan_ranap_query.php';
$koneksi = bukakoneksi();

$draw = $_POST['draw'] ?? 1;
$start = $_POST['start'] ?? 0;
$length = $_POST['length'] ?? 25;
$search = $_POST['search']['value'] ?? '';

$bulan = $_POST['bulan'] ?? date('m');
$tahun = $_POST['tahun'] ?? date('Y');
$kd_bangsal = $_POST['kd_bangsal'] ?? '';
$status_pulang = $_POST['status_pulang'] ?? 'semua';
$jenis_pelanggaran = $_POST['jenis_pelanggaran'] ?? 'semua';

$bulan_padded = str_pad($bulan, 2, '0', STR_PAD_LEFT);
$tgl_awal = "$tahun-$bulan_padded-01";
$tgl_akhir = date("Y-m-t", strtotime($tgl_awal));

$jenis_list = kepatuhan_ranap_jenis_list();

$from_filters = kepatuhan_ranap_from($koneksi, $tgl_awal, $tgl_akhir, $kd_bangsal, $status_pulang, $jenis_pelanggaran);
$from = kepatuhan_ranap_from($koneksi, $tgl_awal, $tgl_akhir, $kd_bangsal, $status_pulang, $jenis_pelanggaran, $search);

$query = kepatuhan_ranap_select() . " $from
    ORDER BY ki.tgl_masuk DESC, ki.jam_masuk DESC, jt.kode
    LIMIT $start, $length
";

$result = mysqli_query($koneksi, $query);

$count_all = mysqli_fetch_assoc(mysqli_query($koneksi, "
    SELECT COUNT(*) AS total FROM (SELECT 1 $from_filters) sub
"));
$total_records = $count_all['total'];

$count_filtered = mysqli_fetch_assoc(mysqli_query($koneksi, "
    SELECT COUNT(*) AS total FROM (SELECT 1 $from) sub
"));
$filtered_records = $count_filtered['total'];

$summary = array_fill_keys(array_keys($jenis_list), 0);
$summary['total'] = 0;
$sum_result = mysqli_query($koneksi, "SELECT jt.kode, COUNT(*) AS jml $from GROUP BY jt.kode");
while ($row = mysqli_fetch_assoc($sum_result)) {
    $summary[$row['kode']] = (int) $row['jml'];
    $summary['total'] += (int) $row['jml'];
}

$bulanan = [];
for ($m = 1; $m <= 12; $m++) {
    $bulanan[$m] = array_fill_keys(array_keys($jenis_list), 0);
    $bulanan[$m]['total'] = 0;
}
$from_tahun = kepatuhan_ranap_from($koneksi, "$tahun-01-01", "$tahun-12-31", $kd_bangsal, $status_pulang, $jenis_pelanggaran);
$bulan_result = mysqli_query($koneksi, "
    SELECT MONTH(ki.tgl_masuk) AS bulan, jt.kode, COUNT(*) AS jml
    $from_tahun
    GROUP BY MONTH(ki.tgl_masuk), jt.kode
");
while ($row = mysqli_fetch_assoc($bulan_result)) {
    $m = (int) $row['bulan'];
    if (isset($bulanan[$m][$row['kode']])) {
        $bulanan[$m][$row['kode']] = (int) $row['jml'];
        $bulanan[$m]['total'] += (int) $row['jml'];
    }
}

$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $row['lama'] = (float) ($row['lama'] ?? 0);
    $row['tgl_masuk'] = ($row['tgl_masuk'] && $row['tgl_masuk'] !== '0000-00-00') ? $row['tgl_masuk'] : '';
    $row['tgl_keluar'] = ($row['tgl_keluar'] && $row['tgl_keluar'] !== '0000-00-00') ? $row['tgl_keluar'] : '';
    $row['jam_masuk'] = $row['jam_masuk'] ?? '';
    $row['jam_keluar'] = $row['jam_keluar'] ?? '';
    $row['ruang'] = $row['nm_bangsal'] . ' / ' . $row['kd_kamar'];
    $row['keterangan'] = kepatuhan_ranap_keterangan($row);
    $data[] = $row;
}

echo json_encode([
    "draw" => intval($draw),
    "recordsTotal" => intval($total_records),
    "recordsFiltered" => intval($filtered_records),
    "summary" => $summary,
    "bulanan" => $bulanan,
    "data" => $data,
]);
mysqli_close($koneksi);
