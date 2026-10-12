<?php
set_time_limit(600);
ini_set('memory_limit', '512M');

require_once '../config/conf.php';
require_once '../vendor/autoload.php';
require_once __DIR__ . '/kepatuhan_ranap_query.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$koneksi = bukakoneksi();

$tahun = $_GET['tahun'] ?? date('Y');
$kd_bangsal = $_GET['kd_bangsal'] ?? '';
$status_pulang = $_GET['status_pulang'] ?? 'semua';
$jenis_pelanggaran = $_GET['jenis_pelanggaran'] ?? 'semua';

if (!preg_match('/^\d{4}$/', $tahun)) {
    $tahun = date('Y');
}

$jenis_list = kepatuhan_ranap_jenis_list();
$bulan_nama = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

$header = [
    'No', 'No Rawat', 'No RM', 'Nama Pasien', 'Kd PJ', 'Penjamin', 'No SEP',
    'Bangsal', 'Kamar', 'Tgl Masuk', 'Tgl Keluar', 'Lama', 'Status Pulang',
    'Jenis Pelanggaran', 'Keterangan',
];
$lastCol = Coordinate::stringFromColumnIndex(count($header));

function styleJudul()
{
    return [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 12, 'name' => 'Arial'],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '166534']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    ];
}

function styleHeader()
{
    return [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10, 'name' => 'Arial'],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '15803D']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '86C5A0']]],
    ];
}

function styleData()
{
    return [
        'font' => ['size' => 9, 'name' => 'Arial'],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9D9D9']]],
    ];
}

function styleTotal()
{
    return [
        'font' => ['bold' => true, 'size' => 10, 'name' => 'Arial'],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DCFCE7']],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '86C5A0']]],
    ];
}

$spreadsheet = new Spreadsheet();
$spreadsheet->removeSheetByIndex(0);
$sheetIdx = 0;

$rekap = [];
$total_semua = array_fill_keys(array_keys($jenis_list), 0);
$total_semua['total'] = 0;

$waktu_tampil = fn($tgl, $jam) => ($tgl && $tgl !== '0000-00-00')
    ? $tgl . (($jam && $jam !== '00:00:00') ? ' ' . substr($jam, 0, 5) : '')
    : '-';

for ($m = 1; $m <= 12; $m++) {
    $awal = sprintf('%04d-%02d-01', $tahun, $m);
    $akhir = date('Y-m-t', strtotime($awal));

    $from = kepatuhan_ranap_from($koneksi, $awal, $akhir, $kd_bangsal, $status_pulang, $jenis_pelanggaran);
    $result = mysqli_query($koneksi, kepatuhan_ranap_select() . " $from
        ORDER BY ki.tgl_masuk DESC, ki.jam_masuk DESC, jt.kode");

    $rows = [];
    $hitung = array_fill_keys(array_keys($jenis_list), 0);
    $hitung['total'] = 0;
    while ($row = mysqli_fetch_assoc($result)) {
        $row['lama'] = (float) ($row['lama'] ?? 0);
        $row['tgl_masuk'] = ($row['tgl_masuk'] && $row['tgl_masuk'] !== '0000-00-00') ? $row['tgl_masuk'] : '';
        $row['tgl_keluar'] = ($row['tgl_keluar'] && $row['tgl_keluar'] !== '0000-00-00') ? $row['tgl_keluar'] : '';
        $row['jam_masuk'] = $row['jam_masuk'] ?? '';
        $row['jam_keluar'] = $row['jam_keluar'] ?? '';

        $rows[] = [
            'no_rawat' => $row['no_rawat'],
            'no_rkm_medis' => $row['no_rkm_medis'],
            'nm_pasien' => $row['nm_pasien'],
            'kd_pj' => $row['kd_pj'],
            'png_jawab' => $row['png_jawab'] ?? '-',
            'no_sep' => $row['no_sep'] ?? '-',
            'ruang' => $row['nm_bangsal'] . ' / ' . $row['kd_kamar'],
            'bangsal' => $row['nm_bangsal'],
            'kamar' => $row['kd_kamar'],
            'tgl_masuk' => $waktu_tampil($row['tgl_masuk'], $row['jam_masuk']),
            'tgl_keluar' => $waktu_tampil($row['tgl_keluar'], $row['jam_keluar']),
            'lama' => $row['lama'],
            'stts_pulang' => ($row['stts_pulang'] ?: '-'),
            'jenis' => $row['jenis_pelanggaran'],
            'keterangan' => kepatuhan_ranap_keterangan($row),
        ];

        $kode = $row['jenis_kode'];
        if (isset($hitung[$kode])) {
            $hitung[$kode]++;
            $hitung['total']++;
            $total_semua[$kode]++;
            $total_semua['total']++;
        }
    }

    $rekap[$m] = $hitung;

    $ws = $spreadsheet->createSheet($sheetIdx++);
    $ws->setTitle($bulan_nama[$m]);
    $ws->setCellValue('A1', 'LAPORAN KEPATUHAN RAWAT INAP - ' . strtoupper($bulan_nama[$m]) . ' ' . $tahun);
    $ws->mergeCells('A1:' . $lastCol . '1');
    $ws->getStyle('A1')->applyFromArray(styleJudul());
    $ws->getRowDimension(1)->setRowHeight(24);

    $ws->fromArray([$header], null, 'A2');
    $ws->getStyle('A2:' . $lastCol . '2')->applyFromArray(styleHeader());
    $ws->freezePane('A3');

    $rowNum = 3;
    if (empty($rows)) {
        $ws->setCellValue('A3', 'TIDAK ADA DATA');
        $ws->mergeCells('A3:' . $lastCol . '3');
        $ws->getStyle('A3')->applyFromArray([
            'font' => ['bold' => true, 'italic' => true, 'size' => 10, 'name' => 'Arial', 'color' => ['rgb' => '6B7280']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $rowNum = 4;
    } else {
        $no = 1;
        foreach ($rows as $r) {
            $ws->fromArray([[
                $no++, $r['no_rawat'], $r['no_rkm_medis'], $r['nm_pasien'], $r['kd_pj'], $r['png_jawab'], $r['no_sep'],
                $r['bangsal'], $r['kamar'], $r['tgl_masuk'], $r['tgl_keluar'], $r['lama'], $r['stts_pulang'],
                $r['jenis'], $r['keterangan'],
            ]], null, 'A' . $rowNum);
            $ws->getStyle('A' . $rowNum . ':' . $lastCol . $rowNum)->applyFromArray(styleData());
            $rowNum++;
        }

        $ws->fromArray([[
            '', 'TOTAL', '', '', '', '', '', '', '', '', '', '', '',
            count($rows) . ' pelanggaran', '',
        ]], null, 'A' . $rowNum);
        $ws->getStyle('A' . $rowNum . ':' . $lastCol . $rowNum)->applyFromArray(styleTotal());
    }

    foreach (range(1, count($header)) as $c) {
        $ws->getColumnDimensionByColumn($c)->setAutoSize(true);
    }
}

// Sheet rekap tahunan
$header_rekap = ['No', 'Bulan'];
foreach ($jenis_list as $label) {
    $header_rekap[] = $label;
}
$header_rekap[] = 'Total';
$lastColRekap = Coordinate::stringFromColumnIndex(count($header_rekap));

$rekap_ws = $spreadsheet->createSheet($sheetIdx++);
$rekap_ws->setTitle('REKAP');
$rekap_ws->setCellValue('A1', 'REKAP KEPATUHAN RAWAT INAP ' . $tahun);
$rekap_ws->mergeCells('A1:' . $lastColRekap . '1');
$rekap_ws->getStyle('A1')->applyFromArray(styleJudul());
$rekap_ws->getRowDimension(1)->setRowHeight(24);
$rekap_ws->fromArray([$header_rekap], null, 'A2');
$rekap_ws->getStyle('A2:' . $lastColRekap . '2')->applyFromArray(styleHeader());
$rekap_ws->freezePane('A3');

$rowNum = 3;
$no = 1;
for ($m = 1; $m <= 12; $m++) {
    $h = $rekap[$m];
    $baris = [$no++, $bulan_nama[$m] . ' ' . $tahun];
    foreach (array_keys($jenis_list) as $kode) {
        $baris[] = $h[$kode];
    }
    $baris[] = $h['total'];
    $rekap_ws->fromArray([$baris], null, 'A' . $rowNum);
    $rekap_ws->getStyle('A' . $rowNum . ':' . $lastColRekap . $rowNum)->applyFromArray(styleData());
    $rowNum++;
}

$total_baris = ['', 'TOTAL ' . $tahun];
foreach (array_keys($jenis_list) as $kode) {
    $total_baris[] = $total_semua[$kode];
}
$total_baris[] = $total_semua['total'];
$rekap_ws->fromArray([$total_baris], null, 'A' . $rowNum);
$rekap_ws->getStyle('A' . $rowNum . ':' . $lastColRekap . $rowNum)->applyFromArray(styleTotal());

foreach (range(1, count($header_rekap)) as $c) {
    $rekap_ws->getColumnDimensionByColumn($c)->setAutoSize(true);
}

$spreadsheet->setActiveSheetIndex(0);

$filename = 'Kepatuhan_Ranap_' . $tahun . '.xlsx';
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
