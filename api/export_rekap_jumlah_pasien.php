<?php
set_time_limit(600);
ini_set('memory_limit', '512M');

require_once '../config/conf.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

$koneksi = bukakoneksi();

$tahun = $_GET['tahun'] ?? date('Y');
$kd_pj_filter = $_GET['kd_pj'] ?? '';

$where_pj = '';
if (!empty($kd_pj_filter)) {
    $where_pj = " AND rp.kd_pj = '" . mysqli_real_escape_string($koneksi, $kd_pj_filter) . "'";
}

// Get all active payment types
$pj_query = "SELECT kd_pj, png_jawab FROM penjab WHERE status = '1' ORDER BY kd_pj";
$pj_result = mysqli_query($koneksi, $pj_query);
$payment_types = [];
while ($row = mysqli_fetch_assoc($pj_result)) {
    $payment_types[] = $row;
}
$pt_keys = [];
foreach ($payment_types as $pt) {
    $pt_keys[] = 'pt_' . $pt['kd_pj'];
}

// Get all active polis
$poli_query = "SELECT kd_poli, nm_poli FROM poliklinik WHERE status = '1' ORDER BY nm_poli";
$poli_result = mysqli_query($koneksi, $poli_query);
$polis = [];
while ($row = mysqli_fetch_assoc($poli_result)) {
    $polis[] = $row;
}

// Get all room groups
$room_query = "SELECT DISTINCT grup_bangsal FROM v_bangsal_grup WHERE grup_bangsal IS NOT NULL ORDER BY grup_bangsal";
$room_result = mysqli_query($koneksi, $room_query);
$rooms = [];
while ($row = mysqli_fetch_assoc($room_result)) {
    $rooms[] = $row['grup_bangsal'];
}

// Ralan counts per month per poli per kd_pj
$ralan_sql = "
SELECT MONTH(rp.tgl_registrasi) AS bulan, rp.kd_poli, rp.kd_pj, COUNT(DISTINCT rp.no_rawat) AS jumlah
FROM reg_periksa rp
WHERE rp.status_lanjut = 'Ralan'
  AND YEAR(rp.tgl_registrasi) = '$tahun'
  AND NOT (
    rp.kd_poli = 'IGDK'
    AND EXISTS (SELECT 1 FROM kamar_inap ki WHERE ki.no_rawat = rp.no_rawat)
  )
  $where_pj
GROUP BY MONTH(rp.tgl_registrasi), rp.kd_poli, rp.kd_pj
";
$ralan_result = mysqli_query($koneksi, $ralan_sql);
$ralan_data = [];
while ($row = mysqli_fetch_assoc($ralan_result)) {
    $ralan_data[$row['bulan']][$row['kd_poli']][$row['kd_pj']] = (int)$row['jumlah'];
}

// Ranap counts per month per room per kd_pj
$ranap_sql = "
SELECT MONTH(ki.tgl_masuk) AS bulan, vbg.grup_bangsal, rp.kd_pj, COUNT(DISTINCT rp.no_rawat) AS jumlah
FROM reg_periksa rp
JOIN kamar_inap ki ON rp.no_rawat = ki.no_rawat
JOIN kamar km ON ki.kd_kamar = km.kd_kamar
JOIN bangsal bs ON km.kd_bangsal = bs.kd_bangsal
JOIN v_bangsal_grup vbg ON bs.kd_bangsal = vbg.kd_bangsal
WHERE rp.status_lanjut = 'Ranap'
  AND YEAR(ki.tgl_masuk) = '$tahun'
  AND vbg.grup_bangsal IS NOT NULL
  AND (ki.stts_pulang IS NULL OR ki.stts_pulang != 'Pindah Kamar')
  $where_pj
GROUP BY MONTH(ki.tgl_masuk), vbg.grup_bangsal, rp.kd_pj
";
$ranap_result = mysqli_query($koneksi, $ranap_sql);
$ranap_data = [];
while ($row = mysqli_fetch_assoc($ranap_result)) {
    $ranap_data[$row['bulan']][$row['grup_bangsal']][$row['kd_pj']] = (int)$row['jumlah'];
}

function initCounts($payment_types) {
    $c = [];
    foreach ($payment_types as $pt) $c[$pt['kd_pj']] = 0;
    return $c;
}

$bulan_nama = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

// Build Ralan sheet data
$ralan_rows = [];
$ralan_monthly = [];
for ($b = 1; $b <= 12; $b++) {
    $total_payments = initCounts($payment_types);
    $poli_rows = [];
    foreach ($polis as $poli) {
        $payments = initCounts($payment_types);
        if (isset($ralan_data[$b][$poli['kd_poli']])) {
            foreach ($ralan_data[$b][$poli['kd_poli']] as $kdpj => $jml) {
                if (isset($payments[$kdpj])) {
                    $payments[$kdpj] = $jml;
                    $total_payments[$kdpj] += $jml;
                }
            }
        }
        $total = array_sum($payments);
        if ($total > 0) {
            $row = [$bulan_nama[$b], $poli['nm_poli']];
            foreach ($payment_types as $pt) $row[] = $payments[$pt['kd_pj']];
            $row[] = $total;
            $poli_rows[] = $row;
        }
    }
    $ralan_monthly[$b] = $total_payments;
    if (!empty($poli_rows)) {
        $sub = [$bulan_nama[$b], 'SUBTOTAL RAWAT JALAN'];
        foreach ($payment_types as $pt) $sub[] = $total_payments[$pt['kd_pj']];
        $sub[] = array_sum($total_payments);
        $ralan_rows[] = $sub;
        $ralan_rows = array_merge($ralan_rows, $poli_rows);
    }
}

// Build Ranap sheet data
$ranap_rows = [];
$ranap_monthly = [];
for ($b = 1; $b <= 12; $b++) {
    $total_payments = initCounts($payment_types);
    $room_rows = [];
    foreach ($rooms as $room) {
        $payments = initCounts($payment_types);
        if (isset($ranap_data[$b][$room])) {
            foreach ($ranap_data[$b][$room] as $kdpj => $jml) {
                if (isset($payments[$kdpj])) {
                    $payments[$kdpj] = $jml;
                    $total_payments[$kdpj] += $jml;
                }
            }
        }
        $total = array_sum($payments);
        if ($total > 0) {
            $row = [$bulan_nama[$b], $room];
            foreach ($payment_types as $pt) $row[] = $payments[$pt['kd_pj']];
            $row[] = $total;
            $room_rows[] = $row;
        }
    }
    $ranap_monthly[$b] = $total_payments;
    if (!empty($room_rows)) {
        $sub = [$bulan_nama[$b], 'SUBTOTAL RAWAT INAP'];
        foreach ($payment_types as $pt) $sub[] = $total_payments[$pt['kd_pj']];
        $sub[] = array_sum($total_payments);
        $ranap_rows[] = $sub;
        $ranap_rows = array_merge($ranap_rows, $room_rows);
    }
}

// Build summary sheet data (ringkasan tanpa poli/ruangan)
$summary_rows = [];
for ($b = 1; $b <= 12; $b++) {
    $ralan_pay = $ralan_monthly[$b] ?? initCounts($payment_types);
    $ranap_pay = $ranap_monthly[$b] ?? initCounts($payment_types);
    $ralan_total = array_sum($ralan_pay);
    $ranap_total = array_sum($ranap_pay);
    if ($ralan_total > 0 || $ranap_total > 0) {
        $r1 = [$bulan_nama[$b], 'RAWAT JALAN'];
        foreach ($payment_types as $pt) $r1[] = $ralan_pay[$pt['kd_pj']];
        $r1[] = $ralan_total;
        $summary_rows[] = $r1;

        $r2 = [$bulan_nama[$b], 'RAWAT INAP'];
        foreach ($payment_types as $pt) $r2[] = $ranap_pay[$pt['kd_pj']];
        $r2[] = $ranap_total;
        $summary_rows[] = $r2;

        $r3 = [$bulan_nama[$b], 'TOTAL'];
        foreach ($payment_types as $pt) $r3[] = $ralan_pay[$pt['kd_pj']] + $ranap_pay[$pt['kd_pj']];
        $r3[] = $ralan_total + $ranap_total;
        $summary_rows[] = $r3;
    }
}

// Ringkasan header (lebih sederhana)
$summary_header = ['Bulan', 'Jenis'];
foreach ($payment_types as $pt) $summary_header[] = $pt['png_jawab'];
$summary_header[] = 'Total';

// Build Excel
$spreadsheet = new Spreadsheet();

// Header row
$header = ['Bulan', 'Pelayanan'];
foreach ($payment_types as $pt) $header[] = $pt['png_jawab'];
$header[] = 'Total';

function styleHeader() {
    return [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10, 'name' => 'Arial'],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E79']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B8CCE4']]],
    ];
}
function styleSubtotal() {
    return [
        'font' => ['bold' => true, 'size' => 10, 'name' => 'Arial'],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E2F3']],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B8CCE4']]],
    ];
}
function styleData() {
    return [
        'font' => ['size' => 9, 'name' => 'Arial'],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9D9D9']]],
    ];
}

$lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($header));

// Sheet 1: RAWAT JALAN
$sheet1 = $spreadsheet->getActiveSheet();
$sheet1->setTitle('RAWAT JALAN');
$sheet1->fromArray([$header], null, 'A1');
$sheet1->getStyle('A1:' . $lastCol . '1')->applyFromArray(styleHeader());
$sheet1->freezePane('A2');

$rowNum = 2;
foreach ($ralan_rows as $r) {
    $sheet1->fromArray([$r], null, 'A' . $rowNum);
    $range = 'A' . $rowNum . ':' . $lastCol . $rowNum;
    if (strpos($r[1], 'SUBTOTAL') === 0) {
        $sheet1->getStyle($range)->applyFromArray(styleSubtotal());
    } else {
        $sheet1->getStyle($range)->applyFromArray(styleData());
    }
    $rowNum++;
}

foreach (range(1, count($header)) as $c) {
    $sheet1->getColumnDimensionByColumn($c)->setAutoSize(true);
}

// Sheet 2: RAWAT INAP
$sheet2 = $spreadsheet->createSheet();
$sheet2->setTitle('RAWAT INAP');
$sheet2->fromArray([$header], null, 'A1');
$sheet2->getStyle('A1:' . $lastCol . '1')->applyFromArray(styleHeader());
$sheet2->freezePane('A2');

$rowNum = 2;
foreach ($ranap_rows as $r) {
    $sheet2->fromArray([$r], null, 'A' . $rowNum);
    $range = 'A' . $rowNum . ':' . $lastCol . $rowNum;
    if (strpos($r[1], 'SUBTOTAL') === 0) {
        $sheet2->getStyle($range)->applyFromArray(styleSubtotal());
    } else {
        $sheet2->getStyle($range)->applyFromArray(styleData());
    }
    $rowNum++;
}

foreach (range(1, count($header)) as $c) {
    $sheet2->getColumnDimensionByColumn($c)->setAutoSize(true);
}

// Sheet 3: RINGKASAN
$sheet3 = $spreadsheet->createSheet();
$sheet3->setTitle('RINGKASAN');
$sheet3->fromArray([$summary_header], null, 'A1');
$sheet3->getStyle('A1:' . $lastCol . '1')->applyFromArray(styleHeader());
$sheet3->freezePane('A2');

$rowNum = 2;
foreach ($summary_rows as $r) {
    $sheet3->fromArray([$r], null, 'A' . $rowNum);
    $range = 'A' . $rowNum . ':' . $lastCol . $rowNum;
    if ($r[1] === 'TOTAL') {
        $sheet3->getStyle($range)->applyFromArray(styleSubtotal());
    } else {
        $sheet3->getStyle($range)->applyFromArray(styleData());
    }
    $rowNum++;
}

foreach (range(1, count($summary_header)) as $c) {
    $sheet3->getColumnDimensionByColumn($c)->setAutoSize(true);
}

$spreadsheet->setActiveSheetIndex(0);

$filename = 'rekap_jumlah_pasien_' . $tahun . '_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
$spreadsheet->disconnectWorksheets();
unset($spreadsheet);
mysqli_close($koneksi);
exit;
