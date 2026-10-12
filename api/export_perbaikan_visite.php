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
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$koneksi = bukakoneksi();

$tahun = $_GET['tahun'] ?? date('Y');
if (!preg_match('/^\d{4}$/', $tahun)) {
    $tahun = date('Y');
}

$bulan_nama = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

$header = ['No', 'No Rawat', 'No RM', 'Nama Pasien', 'Tanggal Registrasi', 'Dokter', 'Status', 'Lama Inap', 'Visite Diinput', 'Kekurangan Visite'];
$lastCol = Coordinate::stringFromColumnIndex(count($header));

function styleJudul()
{
    return [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 12, 'name' => 'Arial'],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '047857']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    ];
}

function styleHeader()
{
    return [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10, 'name' => 'Arial'],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '10B981']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '065F46']]],
    ];
}

function styleData()
{
    return [
        'font' => ['size' => 9, 'name' => 'Arial'],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9D9D9']]],
    ];
}

function styleTotal()
{
    return [
        'font' => ['bold' => true, 'size' => 10, 'name' => 'Arial'],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D1FAE5']],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '065F46']]],
    ];
}

function ambil_data_bulan($koneksi, $bulan, $tahun)
{
    $sql = "
        SELECT r.no_rawat, r.no_rkm_medis, p.nm_pasien, r.tgl_registrasi, d.nm_dokter, r.status_lanjut,
               IFNULL(k.total_lama, 0) AS lama_perawatan,
               IFNULL(v.total_visite, 0) AS visite_diinput
        FROM reg_periksa r
        JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
        JOIN dokter d ON r.kd_dokter = d.kd_dokter
        LEFT JOIN (
            SELECT no_rawat, SUM(lama) AS total_lama
            FROM kamar_inap
            GROUP BY no_rawat
        ) k ON r.no_rawat = k.no_rawat
        LEFT JOIN (
            SELECT a.no_rawat, COUNT(a.no_rawat) AS total_visite
            FROM rawat_inap_drpr a
            JOIN jns_perawatan_inap b ON a.kd_jenis_prw = b.kd_jenis_prw
            WHERE b.kd_kategori = 'KP026'
            GROUP BY a.no_rawat
        ) v ON r.no_rawat = v.no_rawat
        WHERE r.status_lanjut = 'Ranap' AND r.stts != 'Batal' AND r.stts != 'Belum'
          AND MONTH(r.tgl_registrasi) = '$bulan' AND YEAR(r.tgl_registrasi) = '$tahun'
          AND IFNULL(v.total_visite, 0) < IFNULL(k.total_lama, 0)
        ORDER BY r.tgl_registrasi ASC, r.jam_reg ASC
    ";
    $result = mysqli_query($koneksi, $sql);
    $rows = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
    }
    return $rows;
}

$spreadsheet = new Spreadsheet();
$spreadsheet->removeSheetByIndex(0);
$sheetIdx = 0;

$rekap = [];
$total_kurang_semua = 0;
$total_pasien_semua = 0;

for ($m = 1; $m <= 12; $m++) {
    $bulan_padded = str_pad($m, 2, '0', STR_PAD_LEFT);
    $rows = ambil_data_bulan($koneksi, $bulan_padded, $tahun);

    $jumlah_pasien = count($rows);
    $jumlah_kurang = 0;
    foreach ($rows as $r) {
        $jumlah_kurang += max(0, (float) $r['lama_perawatan'] - (float) $r['visite_diinput']);
    }
    $rekap[$m] = ['pasien' => $jumlah_pasien, 'kurang' => $jumlah_kurang];
    $total_pasien_semua += $jumlah_pasien;
    $total_kurang_semua += $jumlah_kurang;

    $ws = $spreadsheet->createSheet($sheetIdx++);
    $ws->setTitle($bulan_nama[$m]);
    $ws->setCellValue('A1', 'PERBAIKAN VISITE RAWAT INAP - ' . strtoupper($bulan_nama[$m]) . ' ' . $tahun);
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
            $lama = (float) $r['lama_perawatan'];
            $visite = (float) $r['visite_diinput'];
            $kurang = max(0, $lama - $visite);
            $ws->fromArray([[
                $no++,
                $r['no_rawat'],
                $r['no_rkm_medis'],
                $r['nm_pasien'],
                $r['tgl_registrasi'],
                $r['nm_dokter'],
                $r['status_lanjut'],
                $lama,
                $visite,
                $kurang,
            ]], null, 'A' . $rowNum);
            $ws->getStyle('A' . $rowNum . ':' . $lastCol . $rowNum)->applyFromArray(styleData());
            $rowNum++;
        }

        $ws->fromArray([[
            '', 'TOTAL', '', $jumlah_pasien . ' pasien', '', '', '', '', '', $jumlah_kurang,
        ]], null, 'A' . $rowNum);
        $ws->getStyle('A' . $rowNum . ':' . $lastCol . $rowNum)->applyFromArray(styleTotal());
    }

    foreach (range(1, count($header)) as $c) {
        $ws->getColumnDimensionByColumn($c)->setAutoSize(true);
    }
}

// Sheet REKAP tahunan
$header_rekap = ['No', 'Bulan', 'Jumlah Pasien', 'Total Kekurangan Visite'];
$lastColRekap = Coordinate::stringFromColumnIndex(count($header_rekap));

$rekap_ws = $spreadsheet->createSheet($sheetIdx++);
$rekap_ws->setTitle('REKAP');
$rekap_ws->setCellValue('A1', 'REKAP PERBAIKAN VISITE RAWAT INAP ' . $tahun);
$rekap_ws->mergeCells('A1:' . $lastColRekap . '1');
$rekap_ws->getStyle('A1')->applyFromArray(styleJudul());
$rekap_ws->getRowDimension(1)->setRowHeight(24);
$rekap_ws->fromArray([$header_rekap], null, 'A2');
$rekap_ws->getStyle('A2:' . $lastColRekap . '2')->applyFromArray(styleHeader());
$rekap_ws->freezePane('A3');

$rowNum = 3;
$no = 1;
for ($m = 1; $m <= 12; $m++) {
    $rekap_ws->fromArray([[
        $no++,
        $bulan_nama[$m] . ' ' . $tahun,
        $rekap[$m]['pasien'],
        $rekap[$m]['kurang'],
    ]], null, 'A' . $rowNum);
    $rekap_ws->getStyle('A' . $rowNum . ':' . $lastColRekap . $rowNum)->applyFromArray(styleData());
    $rowNum++;
}

$rekap_ws->fromArray([['', 'TOTAL ' . $tahun, $total_pasien_semua, $total_kurang_semua]], null, 'A' . $rowNum);
$rekap_ws->getStyle('A' . $rowNum . ':' . $lastColRekap . $rowNum)->applyFromArray(styleTotal());

foreach (range(1, count($header_rekap)) as $c) {
    $rekap_ws->getColumnDimensionByColumn($c)->setAutoSize(true);
}

$spreadsheet->setActiveSheetIndex(0);

$filename = 'perbaikan_visite_' . $tahun . '.xlsx';
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
