<?php
require_once '../config/conf.php';
$koneksi = bukakoneksi();

$bulan = $_POST['bulan'] ?? date('m');
$tahun = $_POST['tahun'] ?? date('Y');
$status_lanjut = $_POST['status_lanjut'] ?? 'Ralan';
$tcari = $_POST['tcari'] ?? '';

$bulan = sprintf('%02d', intval($bulan));
$tahun = intval($tahun);

$where = "WHERE dp.no_rawat IS NULL
AND rp.status_lanjut = '$status_lanjut'
AND YEAR(rp.tgl_registrasi) = $tahun
AND MONTH(rp.tgl_registrasi) = $bulan";

if (!empty($tcari)) {
    $cari = mysqli_real_escape_string($koneksi, $tcari);
    $where .= " AND (rp.no_rawat LIKE '%$cari%' OR p.nm_pasien LIKE '%$cari%' OR rp.no_rkm_medis LIKE '%$cari%')";
}

$sql = "
SELECT
    rp.no_rawat,
    rp.no_rkm_medis,
    rp.tgl_registrasi,
    rp.jam_reg,
    rp.status_lanjut,
    rp.kd_poli,
    p.nm_pasien,
    p.tgl_lahir,
    p.jk,
    pj.png_jawab,
    poli.nm_poli
FROM reg_periksa rp
JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
LEFT JOIN penjab pj ON rp.kd_pj = pj.kd_pj
LEFT JOIN poliklinik poli ON rp.kd_poli = poli.kd_poli
LEFT JOIN diagnosa_pasien dp ON rp.no_rawat = dp.no_rawat
$where
GROUP BY rp.no_rawat
ORDER BY rp.tgl_registrasi DESC, rp.jam_reg DESC
LIMIT 500
";

$result = mysqli_query($koneksi, $sql);
if (!$result) {
    echo json_encode(['error' => mysqli_error($koneksi)]);
    exit;
}

$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $row['tgl_registrasi'] = date('d-m-Y', strtotime($row['tgl_registrasi']));
    $row['jam_reg'] = substr($row['jam_reg'] ?? '00:00:00', 0, 5);
    $data[] = $row;
}

// Count total
$count_sql = "
SELECT COUNT(DISTINCT rp.no_rawat) AS total
FROM reg_periksa rp
LEFT JOIN diagnosa_pasien dp ON rp.no_rawat = dp.no_rawat
WHERE dp.no_rawat IS NULL
AND rp.status_lanjut = '$status_lanjut'
AND YEAR(rp.tgl_registrasi) = $tahun
AND MONTH(rp.tgl_registrasi) = $bulan
";
$count_result = mysqli_query($koneksi, $count_sql);
$count_row = mysqli_fetch_assoc($count_result);

echo json_encode([
    'success' => true,
    'data' => $data,
    'total' => $count_row['total']
]);

mysqli_close($koneksi);
