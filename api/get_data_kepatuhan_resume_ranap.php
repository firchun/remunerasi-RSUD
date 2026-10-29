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
    JOIN (
        SELECT kr.no_rawat, kr.kd_kamar, kr.tgl_keluar, kr.stts_pulang,
            ROW_NUMBER() OVER (
                PARTITION BY kr.no_rawat
                ORDER BY kr.tgl_masuk DESC, kr.jam_masuk DESC
            ) AS rn
        FROM kamar_inap kr
        WHERE kr.tgl_masuk BETWEEN '$tgl_awal' AND '$tgl_akhir'
    ) ki ON ki.rn = 1 AND ki.no_rawat = rp.no_rawat
    JOIN kamar k ON k.kd_kamar = ki.kd_kamar
    JOIN bangsal b ON b.kd_bangsal = k.kd_bangsal
    LEFT JOIN dpjp_ranap dr ON dr.no_rawat = rp.no_rawat
    LEFT JOIN dokter d ON d.kd_dokter = dr.kd_dokter
    LEFT JOIN resume_pasien_ranap rs ON rs.no_rawat = rp.no_rawat
    WHERE rp.status_lanjut = 'Ranap'
      AND rp.stts <> 'Batal'
      AND ki.tgl_keluar IS NOT NULL
      AND ki.tgl_keluar <> '0000-00-00'
      AND IFNULL(ki.stts_pulang, '-') NOT IN ('-', 'Pindah Kamar')
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
        OR b.nm_bangsal LIKE '%$s%'
        OR ki.kd_kamar LIKE '%$s%'
        OR d.nm_dokter LIKE '%$s%'
    )";
}

$select = "
    SELECT
        rp.no_rawat,
        rp.no_rkm_medis,
        ps.nm_pasien,
        CONCAT(b.nm_bangsal, ' / ', ki.kd_kamar) AS kamar,
        IFNULL(GROUP_CONCAT(DISTINCT d.nm_dokter ORDER BY d.nm_dokter SEPARATOR ', '), '-') AS nm_dokter,
        CASE WHEN rs.no_rawat IS NULL THEN 'Tidak Ada' ELSE 'Ada' END AS status_resume
";

$result = mysqli_query($koneksi, "$select $base $filter
    GROUP BY rp.no_rawat, rp.no_rkm_medis, ps.nm_pasien, b.nm_bangsal, ki.kd_kamar, rs.no_rawat
    ORDER BY MAX(rp.tgl_registrasi) DESC, rp.no_rawat DESC
    LIMIT $start, $length
");

$count_all = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(DISTINCT rp.no_rawat) AS total $base"));
$total_records = $count_all['total'];

$count_filtered = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(DISTINCT rp.no_rawat) AS total $base $filter"));
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
