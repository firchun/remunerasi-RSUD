<?php
require_once '../config/conf.php';
$koneksi = bukakoneksi();

$tgl_awal  = $_POST['tgl_awal'] ?? '';
$tgl_akhir = $_POST['tgl_akhir'] ?? '';
$tcari = $_POST['tcari'] ?? '';

$where_primer = "WHERE dp.keluhan_utama IS NOT NULL AND TRIM(dp.keluhan_utama) != '' AND TRIM(dp.keluhan_utama) != '-'";
$where_sekunder = "WHERE ds.anamnesa_singkat IS NOT NULL AND TRIM(ds.anamnesa_singkat) != '' AND TRIM(ds.anamnesa_singkat) != '-'";

if (!empty($tgl_awal)) {
    $where_primer .= " AND dp.tanggaltriase >= '$tgl_awal 00:00:00'";
    $where_sekunder .= " AND ds.tanggaltriase >= '$tgl_awal 00:00:00'";
}
if (!empty($tgl_akhir)) {
    $where_primer .= " AND dp.tanggaltriase <= '$tgl_akhir 23:59:59'";
    $where_sekunder .= " AND ds.tanggaltriase <= '$tgl_akhir 23:59:59'";
}
if (!empty($tcari)) {
    $cari = mysqli_real_escape_string($koneksi, $tcari);
    $where_primer .= " AND (dp.no_rawat LIKE '%$cari%' OR dp.keluhan_utama LIKE '%$cari%')";
    $where_sekunder .= " AND (ds.no_rawat LIKE '%$cari%' OR ds.anamnesa_singkat LIKE '%$cari%')";
}

$sql = "
SELECT * FROM (
    SELECT
        'PRIMER' AS sumber,
        dp.no_rawat,
        rp.no_rkm_medis,
        p.nm_pasien,
        dp.tanggaltriase AS tgl,
        dp.keluhan_utama AS anamnesa,
        LOWER(TRIM(dp.keluhan_utama)) AS anamnesa_key
    FROM data_triase_igdprimer dp
    JOIN reg_periksa rp ON dp.no_rawat = rp.no_rawat
    LEFT JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
    $where_primer

    UNION ALL

    SELECT
        'SEKUNDER' AS sumber,
        ds.no_rawat,
        rp.no_rkm_medis,
        p.nm_pasien,
        ds.tanggaltriase AS tgl,
        ds.anamnesa_singkat AS anamnesa,
        LOWER(TRIM(ds.anamnesa_singkat)) AS anamnesa_key
    FROM data_triase_igdsekunder ds
    JOIN reg_periksa rp ON ds.no_rawat = rp.no_rawat
    LEFT JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
    $where_sekunder
) semua
ORDER BY anamnesa_key, no_rawat
";

$q = mysqli_query($koneksi, $sql);
if (!$q) {
    echo json_encode(['success' => false, 'error' => mysqli_error($koneksi)]);
    exit;
}

$all = [];
while ($r = mysqli_fetch_assoc($q)) {
    $all[] = $r;
}

// Group by anamnesa_key to find duplicates
$groups = [];
foreach ($all as $row) {
    $key = $row['anamnesa_key'];
    if (!isset($groups[$key])) {
        $groups[$key] = [];
    }
    $groups[$key][] = $row;
}

// Filter only groups with duplicates (count > 1)
$result = [];
$no = 0;
foreach ($groups as $key => $items) {
    $cnt = count($items);
    if ($cnt > 1) {
        $no++;
        $rawat_list = array_map(function($x) { return $x['no_rawat']; }, $items);
        $sumber_list = array_map(function($x) { return $x['sumber']; }, $items);
        $result[] = [
            'no' => $no,
            'anamesa_text' => $items[0]['anamnesa'],
            'anamesa_key' => $key,
            'jumlah' => $cnt,
            'no_rawat_list' => implode(', ', $rawat_list),
            'sumber_list' => implode(', ', $sumber_list),
            'items' => $items
        ];
    }
}

echo json_encode([
    'success' => true,
    'data' => $result,
    'total' => count($result)
]);
