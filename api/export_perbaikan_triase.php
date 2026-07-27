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

$where = "WHERE dti.tgl_kunjungan >= '$tgl_awal 00:00:00' AND dti.tgl_kunjungan <= '$tgl_akhir 23:59:59'";
if (!empty($tcari)) {
    $cari = mysqli_real_escape_string($koneksi, $tcari);
    $where .= " AND (dti.no_rawat LIKE '%$cari%' OR p.nm_pasien LIKE '%$cari%' OR rp.no_rkm_medis LIKE '%$cari%')";
}

$skala_join = "
LEFT JOIN (SELECT no_rawat, 1 AS ada FROM data_triase_igddetail_skala1 GROUP BY no_rawat) s1 ON dti.no_rawat = s1.no_rawat
LEFT JOIN (SELECT no_rawat, 1 AS ada FROM data_triase_igddetail_skala2 GROUP BY no_rawat) s2 ON dti.no_rawat = s2.no_rawat
LEFT JOIN (SELECT no_rawat, 1 AS ada FROM data_triase_igddetail_skala3 GROUP BY no_rawat) s3 ON dti.no_rawat = s3.no_rawat
LEFT JOIN (SELECT no_rawat, 1 AS ada FROM data_triase_igddetail_skala4 GROUP BY no_rawat) s4 ON dti.no_rawat = s4.no_rawat
LEFT JOIN (SELECT no_rawat, 1 AS ada FROM data_triase_igddetail_skala5 GROUP BY no_rawat) s5 ON dti.no_rawat = s5.no_rawat
";

// Helper functions
function parseSistolik($td)
{
    $td_clean = str_replace(',', '.', $td);
    if (!empty($td_clean) && $td_clean !== '-' && $td_clean !== '0') {
        $parts = explode('/', $td_clean);
        return intval(trim($parts[0]));
    }
    return 0;
}
function parseNumeric($val) { return floatval(str_replace(',', '.', $val)); }
function parseNyeri($val) { return intval(preg_replace('/[^0-9].*/', '', $val)); }
function hitungSkalaDariVitalSign($td, $nadi, $rr, $suhu, $spo2, $nyeri)
{
    $sistolik = parseSistolik($td);
    $nadi_val = intval(parseNumeric($nadi));
    $rr_val = intval(parseNumeric($rr));
    $suhu_val = parseNumeric($suhu);
    $spo2_val = intval(parseNumeric($spo2));
    $nyeri_val = parseNyeri($nyeri);
    $s1 = false;
    if ($suhu_val > 0 && ($suhu_val <= 35.0 || $suhu_val >= 39.1)) $s1 = true;
    if ($nyeri_val >= 9 && $nyeri_val <= 10) $s1 = true;
    if ($sistolik > 0 && ($sistolik <= 90 || $sistolik >= 220)) $s1 = true;
    if ($nadi_val > 0 && ($nadi_val <= 40 || $nadi_val >= 131)) $s1 = true;
    if ($spo2_val > 0 && $spo2_val <= 91) $s1 = true;
    if ($rr_val > 0 && ($rr_val <= 8 || $rr_val >= 25)) $s1 = true;
    if ($s1) return 1;
    $s2 = false;
    if ($suhu_val > 0 && ($suhu_val <= 35.0 || $suhu_val >= 38.1)) $s2 = true;
    if ($nyeri_val >= 7 && $nyeri_val <= 10) $s2 = true;
    if ($sistolik > 0 && ($sistolik <= 100 || $sistolik >= 220)) $s2 = true;
    if ($nadi_val > 0 && ($nadi_val <= 50 || $nadi_val >= 111)) $s2 = true;
    if ($spo2_val > 0 && $spo2_val <= 93) $s2 = true;
    if ($rr_val > 0 && ($rr_val <= 8 || $rr_val >= 21)) $s2 = true;
    if ($s2) return 2;
    $s3 = false;
    if ($suhu_val > 0 && ($suhu_val <= 36.0 || $suhu_val >= 38.1)) $s3 = true;
    if ($sistolik > 0 && ($sistolik <= 110 || $sistolik >= 220)) $s3 = true;
    if ($nadi_val > 0 && ($nadi_val <= 50 || $nadi_val >= 91)) $s3 = true;
    if ($spo2_val > 0 && $spo2_val <= 95) $s3 = true;
    if ($rr_val > 0 && ($rr_val <= 11 || $rr_val >= 21)) $s3 = true;
    if ($s3) return 3;
    return 5;
}
function getSkalaNama($skala)
{
    $names = [1 => 'SKALA 1 - RESUSITASI', 2 => 'SKALA 2 - EMERGENT', 3 => 'SKALA 3 - URGENT', 4 => 'SKALA 4 - SEMI URGENT', 5 => 'SKALA 5 - NON URGENT'];
    return $names[$skala] ?? 'TIDAK ADA';
}
function isSupportedByVitalSigns($actual_skala, $td, $nadi, $rr, $suhu, $spo2, $nyeri)
{
    if ($actual_skala == 0) return false;
    $sistolik = parseSistolik($td);
    $nadi_val = intval(parseNumeric($nadi));
    $rr_val = intval(parseNumeric($rr));
    $suhu_val = parseNumeric($suhu);
    $spo2_val = intval(parseNumeric($spo2));
    $nyeri_val = parseNyeri($nyeri);
    if ($actual_skala == 1) {
        if ($suhu_val > 0 && ($suhu_val <= 35.0 || $suhu_val >= 39.1)) return true;
        if ($nyeri_val >= 9 && $nyeri_val <= 10) return true;
        if ($sistolik > 0 && ($sistolik <= 90 || $sistolik >= 220)) return true;
        if ($nadi_val > 0 && ($nadi_val <= 40 || $nadi_val >= 131)) return true;
        if ($spo2_val > 0 && $spo2_val <= 91) return true;
        if ($rr_val > 0 && ($rr_val <= 8 || $rr_val >= 25)) return true;
        return false;
    }
    if ($actual_skala == 2) {
        if ($suhu_val > 0 && ($suhu_val <= 35.0 || $suhu_val >= 38.1)) return true;
        if ($nyeri_val >= 7 && $nyeri_val <= 10) return true;
        if ($sistolik > 0 && ($sistolik <= 100 || $sistolik >= 220)) return true;
        if ($nadi_val > 0 && ($nadi_val <= 50 || $nadi_val >= 111)) return true;
        if ($spo2_val > 0 && $spo2_val <= 93) return true;
        if ($rr_val > 0 && ($rr_val <= 8 || $rr_val >= 21)) return true;
        return false;
    }
    if ($actual_skala == 3 || $actual_skala == 4) {
        if ($suhu_val > 0 && ($suhu_val <= 36.0 || $suhu_val >= 38.1)) return true;
        if ($sistolik > 0 && ($sistolik <= 110 || $sistolik >= 220)) return true;
        if ($nadi_val > 0 && ($nadi_val <= 50 || $nadi_val >= 91)) return true;
        if ($spo2_val > 0 && $spo2_val <= 95) return true;
        if ($rr_val > 0 && ($rr_val <= 11 || $rr_val >= 21)) return true;
        return false;
    }
    return true;
}

// ─── Buat Spreadsheet & sheet ──────────────────────────────────────────
$header = ['No', 'No. Rawat', 'Tgl Kunjungan', 'Nama Pasien', 'No. RM', 'Cara Bayar', 'No. SEP',
           'TD', 'Nadi', 'RR', 'Suhu', 'SpO2', 'Nyeri', 'Skala (DB)', 'Skala (Vital)'];
$lastCol = Coordinate::stringFromColumnIndex(count($header));

$spreadsheet = new Spreadsheet();
$spreadsheet->removeSheetByIndex(0);

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

$sheetNames = ['TIDAK DIDUKUNG VITAL', 'SESUAI GD', 'URGENT', 'TIDAK SESUAI GD', 'TANPA SKALA'];
$wsSheets = [];
$rowSheets = [];
$sheetIdx = 0;

foreach ($sheetNames as $name) {
    $ws = $spreadsheet->createSheet($sheetIdx++);
    $ws->setTitle($name);
    $ws->fromArray([$header], null, 'A1');
    $ws->getStyle('A1:' . $lastCol . '1')->applyFromArray($hStyle);
    $ws->getRowDimension(1)->setRowHeight(24);
    $ws->freezePane('A2');
    $wsSheets[$name] = $ws;
    $rowSheets[$name] = 2; // baris berikutnya setelah header
}

// ─── Batch process & tulis langsung ke sheet ───────────────────────────
$offset = 0;
$batch = 500;
$globalNo = 1;

while (true) {
    $sql = "
    SELECT dti.no_rawat, dti.tgl_kunjungan, dti.tekanan_darah, dti.nadi, dti.pernapasan,
           dti.suhu, dti.saturasi_o2, dti.nyeri, rp.no_rkm_medis, p.nm_pasien,
           pj.png_jawab, bs.no_sep,
           COALESCE(s1.ada,0) skala1, COALESCE(s2.ada,0) skala2,
           COALESCE(s3.ada,0) skala3, COALESCE(s4.ada,0) skala4, COALESCE(s5.ada,0) skala5
    FROM data_triase_igd dti
    JOIN reg_periksa rp ON dti.no_rawat = rp.no_rawat
    LEFT JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
    LEFT JOIN penjab pj ON rp.kd_pj = pj.kd_pj
    LEFT JOIN bridging_sep bs ON dti.no_rawat = bs.no_rawat
    $skala_join
    $where
    ORDER BY dti.tgl_kunjungan DESC
    LIMIT $offset, $batch
    ";
    $result = mysqli_query($koneksi, $sql);
    if (!$result || mysqli_num_rows($result) === 0) break;

    $batchData = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $actual_skala = 0;
        if ($row['skala1']) $actual_skala = 1;
        elseif ($row['skala2']) $actual_skala = 2;
        elseif ($row['skala3']) $actual_skala = 3;
        elseif ($row['skala4']) $actual_skala = 4;
        elseif ($row['skala5']) $actual_skala = 5;

        $expected_skala = hitungSkalaDariVitalSign(
            $row['tekanan_darah'], $row['nadi'], $row['pernapasan'],
            $row['suhu'], $row['saturasi_o2'], $row['nyeri']
        );

        $is_supported = isSupportedByVitalSigns(
            $actual_skala,
            $row['tekanan_darah'], $row['nadi'], $row['pernapasan'],
            $row['suhu'], $row['saturasi_o2'], $row['nyeri']
        );

        $rowData = [
            $globalNo++,
            $row['no_rawat'],
            date('d-m-Y H:i', strtotime($row['tgl_kunjungan'])),
            $row['nm_pasien'],
            $row['no_rkm_medis'],
            $row['png_jawab'] ?: '-',
            $row['no_sep'] ?: '-',
            $row['tekanan_darah'] ?: '-',
            $row['nadi'] ?: '-',
            $row['pernapasan'] ?: '-',
            $row['suhu'] ?: '-',
            $row['saturasi_o2'] ?: '-',
            $row['nyeri'] ?: '-',
            getSkalaNama($actual_skala),
            getSkalaNama($expected_skala),
        ];

        if ($actual_skala > 0 && !$is_supported) $sheet = 'TIDAK DIDUKUNG VITAL';
        elseif ($actual_skala == 0) $sheet = 'TANPA SKALA';
        elseif ($actual_skala <= 2) $sheet = 'SESUAI GD';
        elseif ($actual_skala == 3) $sheet = 'URGENT';
        else $sheet = 'TIDAK SESUAI GD';

        $batchData[] = ['sheet' => $sheet, 'data' => $rowData];
    }
    unset($row);

    // Tulis batch ke sheet masing-masing
    foreach ($batchData as $item) {
        $name = $item['sheet'];
        $r = $item['data'];
        $ws = $wsSheets[$name];
        $rowIdx = $rowSheets[$name];
        $ws->fromArray([$r], null, 'A' . $rowIdx);
        $range = 'A' . $rowIdx . ':' . $lastCol . $rowIdx;
        $ws->getStyle($range)->applyFromArray($cStyle);
        $rowSheets[$name]++;
    }
    unset($batchData);

    $offset += $batch;
}

// ─── Auto filter & auto width ─────────────────────────────────────────
foreach ($wsSheets as $name => $ws) {
    $lastRow = $rowSheets[$name] - 1;
    if ($lastRow > 1) {
        $ws->setAutoFilter('A1:' . $lastCol . '1');
        foreach (range(1, count($header)) as $c) {
            $ws->getColumnDimensionByColumn($c)->setAutoSize(true);
        }
    }
}

$spreadsheet->setActiveSheetIndex(0);

$filename = 'export_triase_' . date('Ymd_His') . '.xlsx';
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
