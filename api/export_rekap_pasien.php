<?php
require_once '../config/conf.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\Title;

$koneksi = bukakoneksi();

$tahun = $_GET['tahun'] ?? date('Y');
$bulan = $_GET['bulan'] ?? '';

$bulan_nama = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$periode_text = !empty($bulan) ? ($bulan_nama[(int)$bulan] . ' ' . $tahun) : 'Tahun ' . $tahun;

function getSheetData($koneksi, $jenis, $tahun, $bulan, $bulan_nama) {
    if ($jenis === 'per_poli') {
        $label_title = 'Poliklinik';
        $label_select = 'pl.nm_poli AS label';
        $label_join = 'LEFT JOIN poliklinik pl ON rp.kd_poli = pl.kd_poli';
        $label_group = 'rp.kd_poli, pl.nm_poli';
        $where_type = "AND rp.status_lanjut = 'Ralan' AND NOT (rp.kd_poli = 'IGDK' AND EXISTS (SELECT 1 FROM kamar_inap ki WHERE ki.no_rawat = rp.no_rawat))";
        $date_col = 'rp.tgl_registrasi';
    } elseif ($jenis === 'per_ruangan') {
        $label_title = 'Ruangan';
        $label_select = 'vbg.grup_bangsal AS label';
        $label_join = 'JOIN kamar_inap ki ON rp.no_rawat = ki.no_rawat JOIN kamar km ON ki.kd_kamar = km.kd_kamar JOIN bangsal bs ON km.kd_bangsal = bs.kd_bangsal JOIN v_bangsal_grup vbg ON bs.kd_bangsal = vbg.kd_bangsal';
        $label_group = 'vbg.grup_bangsal';
        $where_type = "AND rp.status_lanjut = 'Ranap' AND vbg.grup_bangsal IS NOT NULL AND (ki.stts_pulang IS NULL OR ki.stts_pulang != 'Pindah Kamar')";
        $date_col = 'ki.tgl_masuk';
    } elseif ($jenis === 'per_dokter') {
        $label_title = 'Dokter';
        $label_select = "COALESCE(d.nm_dokter, 'Tidak Diketahui') AS label";
        $label_join = 'LEFT JOIN dokter d ON rp.kd_dokter = d.kd_dokter';
        $label_group = 'rp.kd_dokter, d.nm_dokter';
        $where_type = "AND rp.status_lanjut IN ('Ralan','Ranap')";
        $date_col = 'rp.tgl_registrasi';
    }

    $where_date = "AND YEAR($date_col) = '$tahun'";
    if (!empty($bulan)) {
        $bulan_pad = str_pad($bulan, 2, '0', STR_PAD_LEFT);
        $where_date .= " AND MONTH($date_col) = '$bulan_pad'";
    }

    $rows = [];

    if ($jenis === 'per_dokter') {
        if (empty($bulan)) {
            $header1 = ['Dokter'];
            $header2 = [''];
            for ($m = 1; $m <= 12; $m++) {
                $header1[] = $bulan_nama[$m];
                $header1[] = '';
                $header2[] = 'Rajal';
                $header2[] = 'Ranap';
            }
            $header1[] = 'Total';
            $header1[] = '';
            $header2[] = 'Rajal';
            $header2[] = 'Ranap';

            $select_months = [];
            for ($m = 1; $m <= 12; $m++) {
                $select_months[] = "COUNT(DISTINCT CASE WHEN MONTH(rp.tgl_registrasi) = $m AND rp.status_lanjut = 'Ralan' THEN rp.no_rawat END) AS m{$m}_ralan";
                $select_months[] = "COUNT(DISTINCT CASE WHEN MONTH(rp.tgl_registrasi) = $m AND rp.status_lanjut = 'Ranap' THEN rp.no_rawat END) AS m{$m}_ranap";
            }
            $select_months_str = implode(", ", $select_months);

            $sql = "SELECT COALESCE(d.nm_dokter, 'Tidak Diketahui') AS label,
                           $select_months_str,
                           COUNT(DISTINCT CASE WHEN rp.status_lanjut = 'Ralan' THEN rp.no_rawat END) AS total_ralan,
                           COUNT(DISTINCT CASE WHEN rp.status_lanjut = 'Ranap' THEN rp.no_rawat END) AS total_ranap
                    FROM reg_periksa rp
                    LEFT JOIN dokter d ON rp.kd_dokter = d.kd_dokter
                    WHERE 1=1 $where_type $where_date
                    GROUP BY rp.kd_dokter, d.nm_dokter
                    ORDER BY label";
            $result = mysqli_query($koneksi, $sql);
            while ($r = mysqli_fetch_assoc($result)) {
                $row = [$r['label'] ?? 'Tidak Diketahui'];
                for ($m = 1; $m <= 12; $m++) {
                    $row[] = (int)$r['m' . $m . '_ralan'];
                    $row[] = (int)$r['m' . $m . '_ranap'];
                }
                $row[] = (int)$r['total_ralan'];
                $row[] = (int)$r['total_ranap'];
                $rows[] = $row;
            }
            return ['is_double_header' => true, 'header1' => $header1, 'header2' => $header2, 'rows' => $rows];
        } else {
            $m_int = (int)$bulan;
            $header1 = ['Dokter', $bulan_nama[$m_int], '', 'Total', ''];
            $header2 = ['', 'Rajal', 'Ranap', 'Rajal', 'Ranap'];

            $sql = "SELECT COALESCE(d.nm_dokter, 'Tidak Diketahui') AS label,
                           COUNT(DISTINCT CASE WHEN rp.status_lanjut = 'Ralan' THEN rp.no_rawat END) AS ralan,
                           COUNT(DISTINCT CASE WHEN rp.status_lanjut = 'Ranap' THEN rp.no_rawat END) AS ranap
                    FROM reg_periksa rp
                    LEFT JOIN dokter d ON rp.kd_dokter = d.kd_dokter
                    WHERE 1=1 $where_type $where_date
                    GROUP BY rp.kd_dokter, d.nm_dokter
                    ORDER BY label";
            $result = mysqli_query($koneksi, $sql);
            while ($r = mysqli_fetch_assoc($result)) {
                $ralan = (int)$r['ralan'];
                $ranap = (int)$r['ranap'];
                $rows[] = [$r['label'] ?? 'Tidak Diketahui', $ralan, $ranap, $ralan, $ranap];
            }
            return ['is_double_header' => true, 'header1' => $header1, 'header2' => $header2, 'rows' => $rows];
        }
    }

    if (in_array($jenis, ['per_poli', 'per_ruangan']) && empty($bulan)) {
        $headers = [$label_title];
        for ($m = 1; $m <= 12; $m++) {
            $headers[] = $bulan_nama[$m];
        }
        $headers[] = 'Total';

        $select_months = [];
        for ($m = 1; $m <= 12; $m++) {
            $select_months[] = "COUNT(DISTINCT CASE WHEN MONTH($date_col) = $m THEN rp.no_rawat END) AS m$m";
        }
        $select_months_str = implode(", ", $select_months);

        $sql = "SELECT $label_select, $select_months_str, COUNT(DISTINCT rp.no_rawat) AS total
                FROM reg_periksa rp $label_join
                WHERE 1=1 $where_type $where_date
                GROUP BY $label_group ORDER BY label";
        $result = mysqli_query($koneksi, $sql);
        while ($r = mysqli_fetch_assoc($result)) {
            $row = [$r['label'] ?? 'Tidak Diketahui'];
            for ($m = 1; $m <= 12; $m++) {
                $row[] = (int)$r['m' . $m];
            }
            $row[] = (int)$r['total'];
            $rows[] = $row;
        }
        return ['is_double_header' => false, 'headers' => $headers, 'rows' => $rows];
    } else {
        $headers = [$label_title, 'Total'];

        $sql = "SELECT $label_select, COUNT(DISTINCT rp.no_rawat) AS total
                FROM reg_periksa rp $label_join
                WHERE 1=1 $where_type $where_date
                GROUP BY $label_group ORDER BY label";
        $result = mysqli_query($koneksi, $sql);
        while ($r = mysqli_fetch_assoc($result)) {
            $rows[] = [$r['label'] ?? 'Tidak Diketahui', (int)$r['total']];
        }
        return ['is_double_header' => false, 'headers' => $headers, 'rows' => $rows];
    }
}

$semua_jenis = [
    'per_poli' => ['title' => 'PER POLIKLINIK'],
    'per_ruangan' => ['title' => 'PER RUANGAN'],
    'per_dokter' => ['title' => 'PER DOKTER'],
];

$spreadsheet = new Spreadsheet();
$first = true;

foreach ($semua_jenis as $jns => $info) {
    $sheetData = getSheetData($koneksi, $jns, $tahun, $bulan, $bulan_nama);
    $rows = $sheetData['rows'];
    $sheetTitle = $info['title'];

    if ($first) {
        $sheet = $spreadsheet->getActiveSheet();
        $first = false;
    } else {
        $sheet = $spreadsheet->createSheet();
    }
    $sheet->setTitle($sheetTitle);

    if (!empty($sheetData['is_double_header'])) {
        $header1 = $sheetData['header1'];
        $header2 = $sheetData['header2'];
        $colCount = count($header1);
        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colCount);

        $sheet->setCellValue('A1', 'REKAP JUMLAH PASIEN ' . $sheetTitle);
        $sheet->mergeCells("A1:{$lastColLetter}1");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A2', 'Periode: ' . $periode_text);
        $sheet->mergeCells("A2:{$lastColLetter}2");
        $sheet->getStyle('A2')->getFont()->setBold(true);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Header Row 1 (Row 4) & Header Row 2 (Row 5)
        $sheet->setCellValue('A4', 'Dokter');
        $sheet->mergeCells('A4:A5');

        for ($c = 1; $c < $colCount; $c += 2) {
            $colLetter1 = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c + 1);
            $colLetter2 = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c + 2);
            $sheet->setCellValue($colLetter1 . '4', $header1[$c]);
            $sheet->mergeCells("{$colLetter1}4:{$colLetter2}4");

            $sheet->setCellValue($colLetter1 . '5', $header2[$c]);
            $sheet->setCellValue($colLetter2 . '5', $header2[$c + 1]);
        }

        $headerRange = "A4:{$lastColLetter}5";
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'));
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('166534');
        $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle($headerRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $startDataRow = 6;
        $hr = $startDataRow;
        foreach ($rows as $rowData) {
            foreach ($rowData as $cIdx => $val) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx + 1);
                $sheet->setCellValue($colLetter . $hr, $val);
                if ($cIdx > 0) {
                    $sheet->getStyle($colLetter . $hr)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
            }
            $sheet->getStyle("A{$hr}:{$lastColLetter}{$hr}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $hr++;
        }

        for ($c = 1; $c <= $colCount; $c++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        // ADD BAR/COLUMN CHART
        $rowCount = count($rows);
        if ($rowCount > 0) {
            $endDataRow = $hr - 1;
            $totalRajalCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colCount - 1);
            $totalRanapCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colCount);

            $catRef = "'{$sheetTitle}'!\$A\$" . $startDataRow . ":\$A\$" . $endDataRow;
            $lblRajalRef = "'{$sheetTitle}'!\$" . $totalRajalCol . "\$5";
            $lblRanapRef = "'{$sheetTitle}'!\$" . $totalRanapCol . "\$5";
            $valRajalRef = "'{$sheetTitle}'!\$" . $totalRajalCol . "\$" . $startDataRow . ":\$" . $totalRajalCol . "\$" . $endDataRow;
            $valRanapRef = "'{$sheetTitle}'!\$" . $totalRanapCol . "\$" . $startDataRow . ":\$" . $totalRanapCol . "\$" . $endDataRow;

            $categories = [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $catRef, null, $rowCount),
            ];
            $labels = [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $lblRajalRef, null, 1),
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $lblRanapRef, null, 1),
            ];
            $values = [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, $valRajalRef, null, $rowCount),
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, $valRanapRef, null, $rowCount),
            ];

            $series = new DataSeries(
                DataSeries::TYPE_BARCHART,
                DataSeries::GROUPING_STANDARD,
                range(0, count($values) - 1),
                $labels,
                $categories,
                $values
            );
            $series->setPlotDirection(DataSeries::DIRECTION_COL);

            $plotArea = new PlotArea(null, [$series]);
            $title = new Title('Grafik Total Pasien Per Dokter (Rajal & Ranap)');
            $legend = new Legend(Legend::POSITION_TOPRIGHT, null, false);

            $chart = new Chart(
                'chart_' . $jns,
                $title,
                $legend,
                $plotArea
            );

            $chartStartRow = $hr + 2;
            $chartEndRow = $chartStartRow + 18;
            $chart->setTopLeftPosition('A' . $chartStartRow);
            $chart->setBottomRightPosition($lastColLetter . $chartEndRow);

            $sheet->addChart($chart);
        }
    } else {
        $headers = $sheetData['headers'];
        $colCount = count($headers);
        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colCount);

        $sheet->setCellValue('A1', 'REKAP JUMLAH PASIEN ' . $sheetTitle);
        $sheet->mergeCells("A1:{$lastColLetter}1");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A2', 'Periode: ' . $periode_text);
        $sheet->mergeCells("A2:{$lastColLetter}2");
        $sheet->getStyle('A2')->getFont()->setBold(true);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $hr = 4;
        foreach ($headers as $cIdx => $hText) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx + 1);
            $sheet->setCellValue($colLetter . $hr, $hText);
        }

        $headerRange = "A{$hr}:{$lastColLetter}{$hr}";
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'));
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('166534');
        $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle($headerRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $startDataRow = 5;
        $hr = $startDataRow;
        foreach ($rows as $rowData) {
            foreach ($rowData as $cIdx => $val) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx + 1);
                $sheet->setCellValue($colLetter . $hr, $val);
                if ($cIdx > 0) {
                    $sheet->getStyle($colLetter . $hr)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
            }
            $sheet->getStyle("A{$hr}:{$lastColLetter}{$hr}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $hr++;
        }

        for ($c = 1; $c <= $colCount; $c++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        // ADD BAR/COLUMN CHART
        $rowCount = count($rows);
        if ($rowCount > 0) {
            $endDataRow = $hr - 1;

            $catRef = "'{$sheetTitle}'!\$A\$" . $startDataRow . ":\$A\$" . $endDataRow;
            $lblRef = "'{$sheetTitle}'!\$" . $lastColLetter . "\$4";
            $valRef = "'{$sheetTitle}'!\$" . $lastColLetter . "\$" . $startDataRow . ":\$" . $lastColLetter . "\$" . $endDataRow;

            $categories = [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $catRef, null, $rowCount),
            ];
            $labels = [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $lblRef, null, 1),
            ];
            $values = [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, $valRef, null, $rowCount),
            ];

            $series = new DataSeries(
                DataSeries::TYPE_BARCHART,
                DataSeries::GROUPING_STANDARD,
                range(0, count($values) - 1),
                $labels,
                $categories,
                $values
            );
            $series->setPlotDirection(DataSeries::DIRECTION_COL);

            $plotArea = new PlotArea(null, [$series]);
            $title = new Title('Grafik Total Pasien - ' . $sheetTitle);
            $legend = new Legend(Legend::POSITION_TOPRIGHT, null, false);

            $chart = new Chart(
                'chart_' . $jns,
                $title,
                $legend,
                $plotArea
            );

            $chartStartRow = $hr + 2;
            $chartEndRow = $chartStartRow + 18;
            $chart->setTopLeftPosition('A' . $chartStartRow);
            $chart->setBottomRightPosition($lastColLetter . $chartEndRow);

            $sheet->addChart($chart);
        }
    }
}

$filename = 'rekap_pasien_' . $tahun . (!empty($bulan) ? '_' . str_pad($bulan, 2, '0', STR_PAD_LEFT) : '');

if (ob_get_length()) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->setIncludeCharts(true);
$writer->save('php://output');
mysqli_close($koneksi);
exit;
