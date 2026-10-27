<?php
ini_set('memory_limit', '512M');
require_once '../config/conf.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
$koneksi = bukakoneksi();

$tgl_awal  = $_GET['tgl_awal'] ?? '';
$tgl_akhir = $_GET['tgl_akhir'] ?? '';
$tcari = $_GET['tcari'] ?? '';

if (empty($tgl_awal)) $tgl_awal = date('Y-01-01');
if (empty($tgl_akhir)) $tgl_akhir = date('Y-m-d');

$where_primer = "WHERE dp.keluhan_utama IS NOT NULL AND TRIM(dp.keluhan_utama) != '' AND TRIM(dp.keluhan_utama) != '-'";
$where_sekunder = "WHERE ds.anamnesa_singkat IS NOT NULL AND TRIM(ds.anamnesa_singkat) != '' AND TRIM(ds.anamnesa_singkat) != '-'";

$where_primer .= " AND dp.tanggaltriase >= '$tgl_awal 00:00:00' AND dp.tanggaltriase <= '$tgl_akhir 23:59:59'";
$where_sekunder .= " AND ds.tanggaltriase >= '$tgl_awal 00:00:00' AND ds.tanggaltriase <= '$tgl_akhir 23:59:59'";

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
        d.nm_dokter,
        dp.tanggaltriase AS tgl,
        dp.keluhan_utama AS anamnesa,
        LOWER(TRIM(dp.keluhan_utama)) AS anamnesa_key
    FROM data_triase_igdprimer dp
    JOIN reg_periksa rp ON dp.no_rawat = rp.no_rawat
    LEFT JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
    LEFT JOIN dokter d ON rp.kd_dokter = d.kd_dokter
    $where_primer

    UNION ALL

    SELECT
        'SEKUNDER' AS sumber,
        ds.no_rawat,
        rp.no_rkm_medis,
        p.nm_pasien,
        d.nm_dokter,
        ds.tanggaltriase AS tgl,
        ds.anamnesa_singkat AS anamnesa,
        LOWER(TRIM(ds.anamnesa_singkat)) AS anamnesa_key
    FROM data_triase_igdsekunder ds
    JOIN reg_periksa rp ON ds.no_rawat = rp.no_rawat
    LEFT JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
    LEFT JOIN dokter d ON rp.kd_dokter = d.kd_dokter
    $where_sekunder
) semua
ORDER BY anamnesa_key, tgl, no_rawat
";

$q = mysqli_query($koneksi, $sql);
if (!$q) {
    die('Query error: ' . mysqli_error($koneksi));
}

$all = [];
while ($r = mysqli_fetch_assoc($q)) {
    $all[] = $r;
}

$groups = [];
foreach ($all as $row) {
    $key = $row['anamnesa_key'];
    if (!isset($groups[$key])) {
        $groups[$key] = [];
    }
    $groups[$key][] = $row;
}

// ─── Spreadsheet ─────────────────────────────────────────────────────
$spreadsheet = new Spreadsheet();

$hStyle = [
    'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '166534']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '065F46']]],
];
$cStyle = [
    'font' => ['size' => 9, 'name' => 'Arial'],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9D9D9']]],
];

// Sheet 1: Ringkasan (Summary)
$ws1 = $spreadsheet->getActiveSheet();
$ws1->setTitle('RINGKASAN');
$sHeader = ['No', 'Anamnesa / Keluhan Utama', 'Jumlah', 'Sumber', 'Daftar No. Rawat'];
$sLastCol = Coordinate::stringFromColumnIndex(count($sHeader));
$ws1->fromArray([$sHeader], null, 'A1');
$ws1->getStyle('A1:' . $sLastCol . '1')->applyFromArray($hStyle);
$ws1->getRowDimension(1)->setRowHeight(24);
$ws1->freezePane('A2');

$rowIdx = 2;
$no = 0;
foreach ($groups as $key => $items) {
    $cnt = count($items);
    if ($cnt <= 1) continue;
    $no++;
    $sources = array_map(function($x) { return $x['sumber']; }, $items);
    $sourceCounts = array_count_values($sources);
    $sourceStr = [];
    if (isset($sourceCounts['PRIMER'])) $sourceStr[] = 'Primer: ' . $sourceCounts['PRIMER'];
    if (isset($sourceCounts['SEKUNDER'])) $sourceStr[] = 'Sekunder: ' . $sourceCounts['SEKUNDER'];
    $rawatList = implode(', ', array_map(function($x) { return $x['no_rawat']; }, $items));

    $ws1->fromArray([[$no, $items[0]['anamnesa'], $cnt, implode(' | ', $sourceStr), $rawatList]], null, 'A' . $rowIdx);
    $ws1->getStyle('A' . $rowIdx . ':' . $sLastCol . $rowIdx)->applyFromArray($cStyle);
    $rowIdx++;
}

$ws1->setAutoFilter('A1:' . $sLastCol . '1');
foreach (range(1, count($sHeader)) as $c) {
    $ws1->getColumnDimensionByColumn($c)->setAutoSize(true);
}

// Sheet 2: Detail
$ws2 = $spreadsheet->createSheet();
$ws2->setTitle('DETAIL');
$dHeader = ['No', 'Anamnesa / Keluhan Utama', 'No. Rawat', 'No. RM', 'Nama Pasien', 'Dokter', 'Tanggal', 'Sumber'];
$dLastCol = Coordinate::stringFromColumnIndex(count($dHeader));
$ws2->fromArray([$dHeader], null, 'A1');
$ws2->getStyle('A1:' . $dLastCol . '1')->applyFromArray($hStyle);
$ws2->getRowDimension(1)->setRowHeight(24);
$ws2->freezePane('A2');

$rowIdx = 2;
$no = 0;
foreach ($groups as $key => $items) {
    $cnt = count($items);
    if ($cnt <= 1) continue;
    $no++;
    foreach ($items as $r) {
        $ws2->fromArray([[$no, $r['anamnesa'], $r['no_rawat'], $r['no_rkm_medis'] ?? '-', $r['nm_pasien'] ?? '-', $r['nm_dokter'] ?? '-', $r['tgl'] ? date('d-m-Y H:i', strtotime($r['tgl'])) : '-', $r['sumber']]], null, 'A' . $rowIdx);
        $ws2->getStyle('A' . $rowIdx . ':' . $dLastCol . $rowIdx)->applyFromArray($cStyle);
        $rowIdx++;
    }
}

$ws2->setAutoFilter('A1:' . $dLastCol . '1');
foreach (range(1, count($dHeader)) as $c) {
    $ws2->getColumnDimensionByColumn($c)->setAutoSize(true);
}

$spreadsheet->setActiveSheetIndex(0);

$filename = 'export_anamnesa_sama_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
$spreadsheet->disconnectWorksheets();
unset($spreadsheet, $writer);
mysqli_close($koneksi);
exit;
