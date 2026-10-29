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
use PhpOffice\PhpSpreadsheet\Cell\DataType;

$koneksi = bukakoneksi();

$bulan = (int) ($_GET['bulan'] ?? date('m'));
if ($bulan < 1 || $bulan > 12) {
    $bulan = (int) date('m');
}
$tahun = (int) ($_GET['tahun'] ?? date('Y'));
$status_resume = $_GET['status_resume'] ?? 'semua';
if (!in_array($status_resume, ['semua', 'ada', 'tidak'], true)) {
    $status_resume = 'semua';
}

$bulan_padded = str_pad($bulan, 2, '0', STR_PAD_LEFT);
$tgl_awal = "$tahun-$bulan_padded-01";
$tgl_akhir = date("Y-m-t", strtotime($tgl_awal));
$bulan_nama = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$periode = $bulan_nama[$bulan] . ' ' . $tahun;

$sql = "
    SELECT
        rp.no_rawat,
        rp.no_rkm_medis,
        ps.nm_pasien,
        pl.nm_poli,
        IFNULL(d.nm_dokter, '-') AS nm_dokter,
        CASE WHEN rs.no_rawat IS NULL THEN 'Belum' ELSE 'Sudah' END AS status_resume
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

if ($status_resume === 'ada') {
    $sql .= " AND rs.no_rawat IS NOT NULL";
} elseif ($status_resume === 'tidak') {
    $sql .= " AND rs.no_rawat IS NULL";
}

$sql .= " ORDER BY pl.nm_poli, rp.tgl_registrasi, rp.no_rawat";

$result = mysqli_query($koneksi, $sql);

$per_poli = [];
while ($row = mysqli_fetch_assoc($result)) {
    $poli = $row['nm_poli'] !== '' ? $row['nm_poli'] : '-';
    $per_poli[$poli][] = $row;
}

$rekap = [];
foreach ($per_poli as $poli => $rows) {
    $rekap[$poli] = ['sudah' => 0, 'belum' => 0];
    foreach ($rows as $r) {
        if ($r['status_resume'] === 'Sudah') {
            $rekap[$poli]['sudah']++;
        } else {
            $rekap[$poli]['belum']++;
        }
    }
}
ksort($rekap, SORT_NATURAL | SORT_FLAG_CASE);

function resume_ralan_style_title()
{
    return [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 12, 'name' => 'Arial'],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '166534']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    ];
}

function resume_ralan_style_header()
{
    return [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10, 'name' => 'Arial'],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '15803D']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '86C5A0']]],
    ];
}

function resume_ralan_style_data()
{
    return [
        'font' => ['size' => 9, 'name' => 'Arial'],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9D9D9']]],
    ];
}

function resume_ralan_style_total()
{
    return [
        'font' => ['bold' => true, 'size' => 10, 'name' => 'Arial'],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DCFCE7']],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '86C5A0']]],
    ];
}

function resume_ralan_sheet_title($name, &$used)
{
    $title = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', ' ', $name);
    $title = trim(preg_replace('/\s+/', ' ', $title));
    if ($title === '') {
        $title = 'Sheet';
    }
    $base = mb_substr($title, 0, 31);
    $candidate = $base;
    $i = 2;
    while (isset($used[mb_strtolower($candidate)])) {
        $suffix = ' (' . $i . ')';
        $candidate = mb_substr($base, 0, 31 - mb_strlen($suffix)) . $suffix;
        $i++;
    }
    $used[mb_strtolower($candidate)] = true;
    return $candidate;
}

$spreadsheet = new Spreadsheet();
$spreadsheet->removeSheetByIndex(0);
$used_titles = [];

$header_rekap = ['Poli', 'Total Sudah', 'Total Belum'];
$rekap_ws = $spreadsheet->createSheet(0);
$rekap_ws->setTitle('REKAP');
$rekap_ws->setCellValue('A1', 'REKAP KEPATUHAN RESUME PASIEN RAWAT JALAN - ' . strtoupper($periode));
$rekap_ws->mergeCells('A1:C1');
$rekap_ws->getStyle('A1')->applyFromArray(resume_ralan_style_title());
$rekap_ws->getRowDimension(1)->setRowHeight(24);
$rekap_ws->fromArray([$header_rekap], null, 'A2', true);
$rekap_ws->getStyle('A2:C2')->applyFromArray(resume_ralan_style_header());
$rekap_ws->freezePane('A3');

$rowNum = 3;
if (empty($rekap)) {
    $rekap_ws->setCellValue('A3', 'TIDAK ADA DATA');
    $rekap_ws->mergeCells('A3:C3');
    $rekap_ws->getStyle('A3')->applyFromArray([
        'font' => ['bold' => true, 'italic' => true, 'size' => 10, 'name' => 'Arial', 'color' => ['rgb' => '6B7280']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);
} else {
    $total_sudah = 0;
    $total_belum = 0;
    foreach ($rekap as $poli => $h) {
        $rekap_ws->fromArray([[$poli, $h['sudah'], $h['belum']]], null, 'A' . $rowNum, true);
        $rekap_ws->getStyle('A' . $rowNum . ':C' . $rowNum)->applyFromArray(resume_ralan_style_data());
        $total_sudah += $h['sudah'];
        $total_belum += $h['belum'];
        $rowNum++;
    }
    $rekap_ws->fromArray([['TOTAL', $total_sudah, $total_belum]], null, 'A' . $rowNum, true);
    $rekap_ws->getStyle('A' . $rowNum . ':C' . $rowNum)->applyFromArray(resume_ralan_style_total());
}

foreach (range(1, 3) as $c) {
    $rekap_ws->getColumnDimensionByColumn($c)->setAutoSize(true);
}

$header_detail = ['No Rawat', 'No RM', 'Nama Pasien', 'Nama Dokter', 'Sudah/Belum'];
$sheetIdx = 1;
foreach ($rekap as $poli => $h) {
    $ws = $spreadsheet->createSheet($sheetIdx++);
    $ws->setTitle(resume_ralan_sheet_title($poli, $used_titles));
    $ws->setCellValue('A1', 'KEPATUHAN RESUME - ' . strtoupper($poli) . ' - ' . strtoupper($periode));
    $ws->mergeCells('A1:E1');
    $ws->getStyle('A1')->applyFromArray(resume_ralan_style_title());
    $ws->getRowDimension(1)->setRowHeight(24);
    $ws->fromArray([$header_detail], null, 'A2', true);
    $ws->getStyle('A2:E2')->applyFromArray(resume_ralan_style_header());
    $ws->freezePane('A3');

    $r = 3;
    foreach ($per_poli[$poli] as $row) {
        $ws->setCellValueExplicit('A' . $r, $row['no_rawat'], DataType::TYPE_STRING);
        $ws->setCellValueExplicit('B' . $r, $row['no_rkm_medis'], DataType::TYPE_STRING);
        $ws->setCellValue('C' . $r, $row['nm_pasien']);
        $ws->setCellValue('D' . $r, $row['nm_dokter']);
        $ws->setCellValue('E' . $r, $row['status_resume']);
        $ws->getStyle('A' . $r . ':E' . $r)->applyFromArray(resume_ralan_style_data());
        $r++;
    }

    foreach (range(1, 5) as $c) {
        $ws->getColumnDimensionByColumn($c)->setAutoSize(true);
    }
}

$spreadsheet->setActiveSheetIndex(0);

$filename = 'Kepatuhan_Resume_Ralan_' . $bulan_padded . '_' . $tahun . '.xlsx';
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
