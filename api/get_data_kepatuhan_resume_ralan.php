<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/conf.php';
$koneksi = bukakoneksi();

$draw   = $_POST['draw'] ?? 1;
$start  = max(0, (int) ($_POST['start'] ?? 0));
$length = (int) ($_POST['length'] ?? 25);
if ($length < 1 || $length > 5000) {
    $length = 25;
}
$search = trim($_POST['search']['value'] ?? '');

$bulan = (int) ($_POST['bulan'] ?? date('m'));
if ($bulan < 1 || $bulan > 12) {
    $bulan = (int) date('m');
}
$tahun = (int) ($_POST['tahun'] ?? date('Y'));
$status_resume = $_POST['status_resume'] ?? 'semua';
if (!in_array($status_resume, ['semua', 'ada', 'tidak'], true)) {
    $status_resume = 'semua';
}

$bulan_padded = str_pad($bulan, 2, '0', STR_PAD_LEFT);
$tgl_awal = "$tahun-$bulan_padded-01";
$tgl_akhir = date("Y-m-t", strtotime($tgl_awal));

$base = "
    FROM reg_periksa rp
    JOIN pasien ps ON ps.no_rkm_medis = rp.no_rkm_medis
    JOIN poliklinik pl ON pl.kd_poli = rp.kd_poli
    LEFT JOIN dokter d ON d.kd_dokter = rp.kd_dokter
    LEFT JOIN resume_pasien rs ON rs.no_rawat = rp.no_rawat
    WHERE rp.status_lanjut = 'Ralan'
      AND rp.stts <> 'Batal'
      AND rp.tgl_registrasi BETWEEN '$tgl_awal' AND '$tgl_akhir'
      AND NOT (
          rp.kd_poli = 'IGDK'
          AND EXISTS (SELECT 1 FROM kamar_inap ki WHERE ki.no_rawat = rp.no_rawat)
      )
";

$filter = '';
if ($status_resume === 'ada') {
    $filter .= " AND rs.no_rawat IS NOT NULL";
} elseif ($status_resume === 'tidak') {
    $filter .= " AND rs.no_rawat IS NULL";
}

if ($search !== '') {
    $s = mysqli_real_escape_string($koneksi, $search);
    $filter .= " AND (
        rp.no_rawat LIKE '%$s%'
        OR rp.no_rkm_medis LIKE '%$s%'
        OR ps.nm_pasien LIKE '%$s%'
        OR pl.nm_poli LIKE '%$s%'
        OR d.nm_dokter LIKE '%$s%'
    )";
}

$select = "
    SELECT
        rp.no_rawat,
        rp.no_rkm_medis,
        ps.nm_pasien,
        pl.nm_poli,
        IFNULL(d.nm_dokter, '-') AS nm_dokter,
        CASE WHEN rs.no_rawat IS NULL THEN 'Tidak Ada' ELSE 'Ada' END AS status_resume
";

$result = mysqli_query($koneksi, "$select $base $filter
    ORDER BY rp.tgl_registrasi DESC, rp.no_rawat DESC
    LIMIT $start, $length
");

$count_all = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS total $base"));
$total_records = $count_all['total'];

$count_filtered = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS total $base $filter"));
$filtered_records = $count_filtered['total'];

$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $data[] = $row;
}

echo json_encode([
    "draw"            => intval($draw),
    "recordsTotal"    => intval($total_records),
    "recordsFiltered" => intval($filtered_records),
    "data"            => $data,
]);
mysqli_close($koneksi);
