<?php
set_time_limit(600);
ini_set('memory_limit', '512M');
require_once '../config/conf.php';
require_once '../vendor/autoload.php';
require_once __DIR__ . '/rekap_kunjungan_ralan_query.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$koneksi = bukakoneksi();

$tahun = rekap_kunjungan_ralan_tahun($_GET['tahun'] ?? date('Y'));
$bulan = $_GET['bulan'] ?? '';
if ($bulan !== '' && (!ctype_digit((string)$bulan) || (int)$bulan < 1 || (int)$bulan > 12)) {
    $bulan = '';
}
$kd_poli = trim($_GET['kd_poli'] ?? '');

$bulan_nama = rekap_kunjungan_ralan_bulan_nama();
$bulan_list = ($bulan !== '') ? [(int)$bulan] : range(1, 12);

$semua_rows = rekap_kunjungan_ralan_rows($koneksi, $tahun);

$rows_per_bulan = array_fill_keys($bulan_list, []);
foreach ($semua_rows as $r) {
    if ($kd_poli !== '' && $r['kd_poli'] !== $kd_poli) {
        continue;
    }
    if (!isset($rows_per_bulan[(int)$r['bulan']])) {
        continue;
    }
    $rows_per_bulan[(int)$r['bulan']][] = $r;
}

$spreadsheet = new Spreadsheet();
$spreadsheet->removeSheetByIndex(0);
$sheetIdx = 0;

$hStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '166534']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
];
$cStyle = [
    'font' => ['size' => 9, 'name' => 'Arial'],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9D9D9']]],
];
$tStyle = [
    'font' => ['bold' => true, 'size' => 10, 'name' => 'Arial'],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D1FAE5']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '065F46']]],
];

// ---------- Sheet REKAP (per poliklinik x per bulan + data lebih) ----------
$pivot = [];
foreach ($rows_per_bulan as $m => $rows_bulan) {
    foreach ($rows_bulan as $r) {
        $key = $r['kd_poli'];
        if (!isset($pivot[$key])) {
            $pivot[$key] = [
                'nm_poli' => $r['nm_poli'],
                'jumlah' => array_fill_keys($bulan_list, 0),
                'data_lebih' => array_fill_keys($bulan_list, 0),
                'batal' => array_fill_keys($bulan_list, 0),
                'perminggu' => array_fill_keys($bulan_list, 0),
            ];
        }
        $pivot[$key]['jumlah'][$m] += $r['jumlah_kunjungan'];
        $pivot[$key]['data_lebih'][$m] += $r['data_lebih'];
        $pivot[$key]['batal'][$m] += $r['batal_kunjungan'];
        $pivot[$key]['perminggu'][$m] += $r['kunjungan_perminggu'];
    }
}

foreach ($pivot as $key => $p) {
    $total_jumlah = 0;
    $total_data_lebih = 0;
    $total_batal = 0;
    $total_perminggu = 0;
    foreach ($bulan_list as $m) {
        $total_jumlah += $p['jumlah'][$m];
        $total_data_lebih += $p['data_lebih'][$m];
        $total_batal += $p['batal'][$m];
        $total_perminggu += $p['perminggu'][$m];
    }
    $pivot[$key]['total_jumlah'] = $total_jumlah;
    $pivot[$key]['total_data_lebih'] = $total_data_lebih;
    $pivot[$key]['total_batal'] = $total_batal;
    $pivot[$key]['total_perminggu'] = $total_perminggu;
}

usort($pivot, function ($a, $b) {
    return strcasecmp($a['nm_poli'], $b['nm_poli']);
});

$wsRekap = $spreadsheet->createSheet($sheetIdx++);
$wsRekap->setTitle('rekap');

$tailHeaders = ['Total Kunjungan', 'Total Data Lebih', 'Total Batal', 'Total Kunjungan/7 Hari'];
$colCount = 1 + (2 * count($bulan_list)) + count($tailHeaders);
$lastColR = Coordinate::stringFromColumnIndex($colCount);

$wsRekap->setCellValue('A1', 'REKAP KUNJUNGAN PASIEN RALAN (BPJS) - TAHUN ' . $tahun . ' (PASIEN > 2 KUNJUNGAN/BULAN)');
$wsRekap->mergeCells("A1:{$lastColR}1");
$wsRekap->getStyle('A1')->getFont()->setBold(true)->setSize(13);
$wsRekap->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$wsRekap->getRowDimension(1)->setRowHeight(22);

$wsRekap->setCellValue('A2', 'Periode: ' . $tahun . '-01-01 s/d ' . ($tahun + 1) . '-01-01'
    . ($bulan !== '' ? ' | Bulan: ' . $bulan_nama[(int)$bulan] : '')
    . ($kd_poli !== '' ? ' | Poliklinik: ' . $kd_poli : ''));
$wsRekap->mergeCells("A2:{$lastColR}2");
$wsRekap->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Header dua tingkat: bulan -> (Kunjungan | Data Lebih)
$hrR = 4;
$wsRekap->setCellValue('A' . $hrR, 'Poliklinik');
$wsRekap->mergeCells("A{$hrR}:A" . ($hrR + 1));

$col = 2;
foreach ($bulan_list as $m) {
    $colStart = Coordinate::stringFromColumnIndex($col);
    $colEnd = Coordinate::stringFromColumnIndex($col + 1);
    $wsRekap->setCellValue($colStart . $hrR, $bulan_nama[$m]);
    $wsRekap->mergeCells("{$colStart}{$hrR}:{$colEnd}{$hrR}");
    $wsRekap->setCellValue($colStart . ($hrR + 1), 'Kunjungan');
    $wsRekap->setCellValue($colEnd . ($hrR + 1), 'Data Lebih');
    $col += 2;
}
foreach ($tailHeaders as $h) {
    $colLetter = Coordinate::stringFromColumnIndex($col);
    $wsRekap->setCellValue($colLetter . $hrR, $h);
    $wsRekap->mergeCells("{$colLetter}{$hrR}:{$colLetter}" . ($hrR + 1));
    $col++;
}
$wsRekap->getStyle("A{$hrR}:{$lastColR}" . ($hrR + 1))->applyFromArray($hStyle);
$wsRekap->freezePane('A' . ($hrR + 2));
$hrR += 2;

if (empty($pivot)) {
    $wsRekap->setCellValue('A' . $hrR, 'TIDAK ADA DATA');
    $wsRekap->mergeCells("A{$hrR}:{$lastColR}{$hrR}");
    $wsRekap->getStyle('A' . $hrR)->getFont()->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('6B7280'));
    $wsRekap->getStyle('A' . $hrR)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $hrR++;
} else {
    $grandJumlah = array_fill_keys($bulan_list, 0);
    $grandDataLebih = array_fill_keys($bulan_list, 0);
    $grandBatal = array_fill_keys($bulan_list, 0);
    $grandPerminggu = array_fill_keys($bulan_list, 0);
    $grandTotalJumlah = 0;
    $grandTotalDataLebih = 0;
    $grandTotalBatal = 0;
    $grandTotalPerminggu = 0;

    foreach ($pivot as $p) {
        $valuesR = [$p['nm_poli']];
        foreach ($bulan_list as $m) {
            $valuesR[] = $p['jumlah'][$m];
            $valuesR[] = $p['data_lebih'][$m];
            $grandJumlah[$m] += $p['jumlah'][$m];
            $grandDataLebih[$m] += $p['data_lebih'][$m];
            $grandBatal[$m] += $p['batal'][$m];
            $grandPerminggu[$m] += $p['perminggu'][$m];
        }
        $valuesR[] = $p['total_jumlah'];
        $valuesR[] = $p['total_data_lebih'];
        $valuesR[] = $p['total_batal'];
        $valuesR[] = $p['total_perminggu'];
        $grandTotalJumlah += $p['total_jumlah'];
        $grandTotalDataLebih += $p['total_data_lebih'];
        $grandTotalBatal += $p['total_batal'];
        $grandTotalPerminggu += $p['total_perminggu'];

        $wsRekap->fromArray([$valuesR], null, 'A' . $hrR, true);
        $wsRekap->getStyle("A{$hrR}:{$lastColR}{$hrR}")->applyFromArray($cStyle);
        foreach (range(2, $colCount) as $c) {
            $colLetter = Coordinate::stringFromColumnIndex($c);
            $wsRekap->getStyle("{$colLetter}{$hrR}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $wsRekap->getStyle("{$colLetter}{$hrR}")->getNumberFormat()->setFormatCode('#,##0');
        }
        $hrR++;
    }

    $valuesTotal = ['TOTAL'];
    foreach ($bulan_list as $m) {
        $valuesTotal[] = $grandJumlah[$m];
        $valuesTotal[] = $grandDataLebih[$m];
    }
    $valuesTotal[] = $grandTotalJumlah;
    $valuesTotal[] = $grandTotalDataLebih;
    $valuesTotal[] = $grandTotalBatal;
    $valuesTotal[] = $grandTotalPerminggu;

    $wsRekap->fromArray([$valuesTotal], null, 'A' . $hrR, true);
    $wsRekap->getStyle("A{$hrR}:{$lastColR}{$hrR}")->applyFromArray($tStyle);
    foreach (range(2, $colCount) as $c) {
        $colLetter = Coordinate::stringFromColumnIndex($c);
        $wsRekap->getStyle("{$colLetter}{$hrR}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $wsRekap->getStyle("{$colLetter}{$hrR}")->getNumberFormat()->setFormatCode('#,##0');
    }
    $hrR++;
}

foreach (range(1, $colCount) as $c) {
    $wsRekap->getColumnDimensionByColumn($c)->setAutoSize(true);
}

// ---------- Sheet per bulan (detail) ----------
$headerDetail = ['No. RM', 'Nama Pasien', 'Poliklinik', 'Dokter', 'Jumlah Kunjungan', 'Data Lebih', 'Batal Kunjungan', 'Kunjungan/7 Hari'];

foreach ($bulan_list as $m) {
    $rows_bulan = $rows_per_bulan[$m];

    usort($rows_bulan, function ($a, $b) {
        $c = strcasecmp($a['nm_poli'], $b['nm_poli']);
        if ($c !== 0) {
            return $c;
        }
        if ($a['jumlah_kunjungan'] !== $b['jumlah_kunjungan']) {
            return $b['jumlah_kunjungan'] <=> $a['jumlah_kunjungan'];
        }
        return strcasecmp($a['nm_pasien'], $b['nm_pasien']);
    });

    $ws = $spreadsheet->createSheet($sheetIdx++);
    $ws->setTitle($bulan_nama[$m]);
    $lastCol = Coordinate::stringFromColumnIndex(count($headerDetail));

    $ws->setCellValue('A1', 'REKAP KUNJUNGAN PASIEN RALAN (BPJS) - ' . strtoupper($bulan_nama[$m]) . ' ' . $tahun . ' (PASIEN > 2 KUNJUNGAN/BULAN)');
    $ws->mergeCells("A1:{$lastCol}1");
    $ws->getStyle('A1')->getFont()->setBold(true)->setSize(13);
    $ws->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $ws->getRowDimension(1)->setRowHeight(22);

    $totalBulan = 0;
    $totalBatalBulan = 0;
    $totalPermingguBulan = 0;
    $totalDataLebihBulan = 0;
    foreach ($rows_bulan as $r) {
        $totalBulan += $r['jumlah_kunjungan'];
        $totalBatalBulan += $r['batal_kunjungan'];
        $totalPermingguBulan += $r['kunjungan_perminggu'];
        $totalDataLebihBulan += $r['data_lebih'];
    }

    $ws->setCellValue('A2', 'Periode: ' . sprintf('%04d-%02d-01', $tahun, $m) . ' s/d ' . date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $tahun, $m)))
        . ' | Jumlah Kunjungan: ' . $totalBulan
        . ' | Data Lebih: ' . $totalDataLebihBulan
        . ' | Batal: ' . $totalBatalBulan
        . ' | Kunjungan/7 Hari: ' . $totalPermingguBulan);
    $ws->mergeCells("A2:{$lastCol}2");
    $ws->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $hr = 4;
    $ws->fromArray([$headerDetail], null, 'A' . $hr, true);
    $ws->getStyle("A{$hr}:{$lastCol}{$hr}")->applyFromArray($hStyle);
    $ws->freezePane('A' . ($hr + 1));
    $hr++;

    if (empty($rows_bulan)) {
        $ws->setCellValue('A' . $hr, 'TIDAK ADA DATA');
        $ws->mergeCells("A{$hr}:{$lastCol}{$hr}");
        $ws->getStyle('A' . $hr)->getFont()->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('6B7280'));
        $ws->getStyle('A' . $hr)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $hr++;
    } else {
        foreach ($rows_bulan as $r) {
            $values = [
                $r['no_rkm_medis'],
                $r['nm_pasien'],
                $r['nm_poli'],
                $r['nm_dokter'],
                $r['jumlah_kunjungan'],
                $r['data_lebih'],
                $r['batal_kunjungan'],
                $r['kunjungan_perminggu'],
            ];
            $ws->fromArray([$values], null, 'A' . $hr, true);
            $ws->getStyle("A{$hr}:{$lastCol}{$hr}")->applyFromArray($cStyle);
            foreach (range(5, 8) as $c) {
                $colLetter = Coordinate::stringFromColumnIndex($c);
                $ws->getStyle("{$colLetter}{$hr}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $ws->getStyle("{$colLetter}{$hr}")->getNumberFormat()->setFormatCode('#,##0');
            }
            $hr++;
        }

        $ws->fromArray([['TOTAL', '', '', '', $totalBulan, $totalDataLebihBulan, $totalBatalBulan, $totalPermingguBulan]], null, 'A' . $hr, true);
        $ws->getStyle("A{$hr}:{$lastCol}{$hr}")->applyFromArray($tStyle);
        foreach (range(5, 8) as $c) {
            $colLetter = Coordinate::stringFromColumnIndex($c);
            $ws->getStyle("{$colLetter}{$hr}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $ws->getStyle("{$colLetter}{$hr}")->getNumberFormat()->setFormatCode('#,##0');
        }
        $hr++;
    }

    foreach (range(1, count($headerDetail)) as $c) {
        $ws->getColumnDimensionByColumn($c)->setAutoSize(true);
    }
}

$spreadsheet->setActiveSheetIndex(0);

$filename = 'rekap_kunjungan_pasien_ralan_' . $tahun . ($bulan !== '' ? '_' . str_pad($bulan, 2, '0', STR_PAD_LEFT) : '') . '.xlsx';

if (ob_get_length()) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
$spreadsheet->disconnectWorksheets();
unset($spreadsheet, $writer);
mysqli_close($koneksi);
exit;
