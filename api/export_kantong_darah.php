<?php
set_time_limit(600);
ini_set('memory_limit', '512M');
require_once '../config/conf.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$koneksi = bukakoneksi();

$tahun = $_GET['tahun'] ?? date('Y');
if (!preg_match('/^\d{4}$/', $tahun)) {
    $tahun = date('Y');
}
$bulan = $_GET['bulan'] ?? '';
if ($bulan !== '' && (!ctype_digit((string)$bulan) || (int)$bulan < 1 || (int)$bulan > 12)) {
    $bulan = '';
}

$bulan_nama = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$bulan_singkat = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

$bulan_list = ($bulan !== '') ? [(int)$bulan] : range(1, 12);

function getKantongData($koneksi, $jenis, $tgl1, $tgl2)
{
    $where_tgl = " AND r.tgl_perawatan >= '" . mysqli_real_escape_string($koneksi, $tgl1) . "'"
        . " AND r.tgl_perawatan <= '" . mysqli_real_escape_string($koneksi, $tgl2) . "'";

    if ($jenis === 'ranap') {
        $sql = "SELECT rp.no_rawat, rp.tgl_registrasi, rp.no_rkm_medis, p.nm_pasien, bs.no_sep,
                       kk.nm_bangsal AS lokasi,
                       COUNT(*) AS jumlah_kantong,
                       IFNULL(SUM(j.total_byrdrpr), 0) AS total_biaya
                FROM rawat_inap_drpr r
                JOIN jns_perawatan_inap j ON r.kd_jenis_prw = j.kd_jenis_prw
                JOIN reg_periksa rp ON r.no_rawat = rp.no_rawat
                JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                LEFT JOIN (
                    SELECT no_rawat,
                           SUBSTRING_INDEX(GROUP_CONCAT(no_sep ORDER BY tglsep DESC SEPARATOR ','), ',', 1) AS no_sep
                    FROM bridging_sep
                    GROUP BY no_rawat
                ) bs ON bs.no_rawat = rp.no_rawat
                LEFT JOIN (
                    SELECT ki.no_rawat, b.nm_bangsal
                    FROM kamar_inap ki
                    JOIN kamar km ON ki.kd_kamar = km.kd_kamar
                    JOIN bangsal b ON km.kd_bangsal = b.kd_bangsal
                    WHERE (ki.stts_pulang IS NULL OR ki.stts_pulang != 'Pindah Kamar')
                ) kk ON kk.no_rawat = rp.no_rawat
                WHERE j.kd_kategori = 'KP058'
                  $where_tgl
                GROUP BY rp.no_rawat, rp.tgl_registrasi, rp.no_rkm_medis, p.nm_pasien, bs.no_sep, kk.nm_bangsal
                ORDER BY rp.no_rawat DESC";
    } else {
        $sql = "SELECT rp.no_rawat, rp.tgl_registrasi, rp.no_rkm_medis, p.nm_pasien, bs.no_sep,
                       pl.nm_poli AS lokasi,
                       COUNT(*) AS jumlah_kantong,
                       IFNULL(SUM(j.total_byrdrpr), 0) AS total_biaya
                FROM rawat_jl_drpr r
                JOIN jns_perawatan j ON r.kd_jenis_prw = j.kd_jenis_prw
                JOIN reg_periksa rp ON r.no_rawat = rp.no_rawat
                JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                LEFT JOIN (
                    SELECT no_rawat,
                           SUBSTRING_INDEX(GROUP_CONCAT(no_sep ORDER BY tglsep DESC SEPARATOR ','), ',', 1) AS no_sep
                    FROM bridging_sep
                    GROUP BY no_rawat
                ) bs ON bs.no_rawat = rp.no_rawat
                LEFT JOIN poliklinik pl ON rp.kd_poli = pl.kd_poli
                WHERE j.kd_kategori = 'KP058'
                  $where_tgl
                GROUP BY rp.no_rawat, rp.tgl_registrasi, rp.no_rkm_medis, p.nm_pasien, bs.no_sep, pl.nm_poli
                ORDER BY rp.no_rawat DESC";
    }

    $result = mysqli_query($koneksi, $sql);
    if (!$result) {
        return ['rows' => [], 'total_kantong' => 0, 'total_pasien' => 0, 'total_biaya' => 0];
    }

    $rows = [];
    $total_kantong = 0;
    $total_biaya = 0;
    while ($r = mysqli_fetch_assoc($result)) {
        $rows[] = $r;
        $total_kantong += (int)$r['jumlah_kantong'];
        $total_biaya += (float)$r['total_biaya'];
    }
    return ['rows' => $rows, 'total_kantong' => $total_kantong, 'total_pasien' => count($rows), 'total_biaya' => $total_biaya];
}

$sheets = [
    'ralan' => ['title' => 'RAJAL', 'label' => 'Poliklinik'],
    'ranap' => ['title' => 'RANAP', 'label' => 'Kamar'],
];

// Header untuk detail sheet (kolom Rekap ditambahkan di paling awal)
$header = ['Rekap', 'No. Rawat', 'No. RM', 'Nama Pasien', 'Tgl Registrasi', 'No. SEP', 'Poli/Kamar', 'Jumlah Kantong', 'Total Biaya'];

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

// Kumpulkan data rekap per bulan untuk sheet REKAP
$rekapList = [];
foreach ($bulan_list as $m) {
    $tgl1 = sprintf('%04d-%02d-01', $tahun, $m);
    $tgl2 = date('Y-m-t', strtotime($tgl1));
    $jmlHari = (int)date('t', strtotime($tgl1));

    $totalPasienAll = 0;
    $totalKantongAll = 0;
    $totalBiayaAll = 0;
    foreach ($sheets as $jenis => $info) {
        $data = getKantongData($koneksi, $jenis, $tgl1, $tgl2);
        $totalPasienAll += $data['total_pasien'];
        $totalKantongAll += $data['total_kantong'];
        $totalBiayaAll += $data['total_biaya'];
    }
    $rataPasien = $totalPasienAll > 0 ? ($totalKantongAll / $totalPasienAll) : 0;
    $rataHari = $jmlHari > 0 ? ($totalKantongAll / $jmlHari) : 0;
    $rekapList[] = [
        'bulan' => $m,
        'nama_bulan' => $bulan_nama[$m],
        'tgl1' => $tgl1,
        'tgl2' => $tgl2,
        'jml_hari' => $jmlHari,
        'total_pasien' => $totalPasienAll,
        'total_kantong' => $totalKantongAll,
        'total_biaya' => $totalBiayaAll,
        'rata_per_pasien' => $rataPasien,
        'rata_per_hari' => $rataHari,
    ];
}

// Buat sheet REKAP di awal
$wsRekap = $spreadsheet->createSheet($sheetIdx++);
$wsRekap->setTitle('REKAP');

$headerRekap = ['Bulan', 'Periode', 'Jumlah Hari', 'Total Pasien', 'Total Kantong', 'Rata-rata Kantong/Pasien', 'Rata-rata Kantong/Hari', 'Total Biaya'];
$lastColR = Coordinate::stringFromColumnIndex(count($headerRekap));

$wsRekap->setCellValue('A1', 'REKAP PENGGUNAAN KANTONG DARAH - TAHUN ' . $tahun . ($bulan !== '' ? ' (BULAN ' . strtoupper($bulan_nama[(int)$bulan]) . ')' : ' (PER BULAN)'));
$wsRekap->mergeCells("A1:{$lastColR}1");
$wsRekap->getStyle('A1')->getFont()->setBold(true)->setSize(13);
$wsRekap->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$wsRekap->getRowDimension(1)->setRowHeight(22);

$hrR = 3;
$wsRekap->fromArray([$headerRekap], null, 'A' . $hrR);
$wsRekap->getStyle("A{$hrR}:{$lastColR}{$hrR}")->applyFromArray($hStyle);
$wsRekap->freezePane('A' . ($hrR + 1));

$hrR++;
$grandPasien = 0; $grandKantong = 0; $grandBiaya = 0; $grandHari = 0;
foreach ($rekapList as $rkp) {
    $grandPasien += $rkp['total_pasien'];
    $grandKantong += $rkp['total_kantong'];
    $grandBiaya += $rkp['total_biaya'];
    $grandHari += $rkp['jml_hari'];
    $valuesR = [
        $rkp['nama_bulan'] . ' ' . $tahun,
        $rkp['tgl1'] . ' s/d ' . $rkp['tgl2'],
        $rkp['jml_hari'],
        $rkp['total_pasien'],
        $rkp['total_kantong'],
        $rkp['rata_per_pasien'],
        $rkp['rata_per_hari'],
        $rkp['total_biaya'],
    ];
    $wsRekap->fromArray([$valuesR], null, 'A' . $hrR);
    $wsRekap->getStyle("A{$hrR}:{$lastColR}{$hrR}")->applyFromArray($cStyle);
    $wsRekap->getStyle("C{$hrR}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $wsRekap->getStyle("D{$hrR}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $wsRekap->getStyle("E{$hrR}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $wsRekap->getStyle("F{$hrR}")->getNumberFormat()->setFormatCode('#,##0.00');
    $wsRekap->getStyle("F{$hrR}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $wsRekap->getStyle("G{$hrR}")->getNumberFormat()->setFormatCode('#,##0.00');
    $wsRekap->getStyle("G{$hrR}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $wsRekap->getStyle("H{$hrR}")->getNumberFormat()->setFormatCode('#,##0');
    $wsRekap->getStyle("H{$hrR}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $hrR++;
}
$rataPasienGrand = $grandPasien > 0 ? ($grandKantong / $grandPasien) : 0;
$rataHariGrand = $grandHari > 0 ? ($grandKantong / $grandHari) : 0;
$wsRekap->fromArray([['TOTAL', '', $grandHari, $grandPasien, $grandKantong, $rataPasienGrand, $rataHariGrand, $grandBiaya]], null, 'A' . $hrR);
$wsRekap->getStyle("A{$hrR}:{$lastColR}{$hrR}")->applyFromArray($tStyle);
$wsRekap->getStyle("C{$hrR}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$wsRekap->getStyle("D{$hrR}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$wsRekap->getStyle("E{$hrR}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$wsRekap->getStyle("F{$hrR}")->getNumberFormat()->setFormatCode('#,##0.00');
$wsRekap->getStyle("F{$hrR}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$wsRekap->getStyle("G{$hrR}")->getNumberFormat()->setFormatCode('#,##0.00');
$wsRekap->getStyle("G{$hrR}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$wsRekap->getStyle("H{$hrR}")->getNumberFormat()->setFormatCode('#,##0');
$wsRekap->getStyle("H{$hrR}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

foreach (range(1, count($headerRekap)) as $c) {
    $wsRekap->getColumnDimensionByColumn($c)->setAutoSize(true);
}

foreach ($bulan_list as $m) {
    $tgl1 = sprintf('%04d-%02d-01', $tahun, $m);
    $tgl2 = date('Y-m-t', strtotime($tgl1));
    $rekapBulanLabel = $bulan_nama[$m] . ' ' . $tahun;

    foreach ($sheets as $jenis => $info) {
        $data = getKantongData($koneksi, $jenis, $tgl1, $tgl2);
        $rows = $data['rows'];

        $ws = $spreadsheet->createSheet($sheetIdx++);
        $ws->setTitle($info['title'] . ' ' . $bulan_singkat[$m] . ' ' . $tahun);

        $lastCol = Coordinate::stringFromColumnIndex(count($header));

        $ws->setCellValue('A1', 'REKAP PENGGUNAAN KANTONG DARAH ' . $info['title'] . ' - ' . strtoupper($bulan_nama[$m]) . ' ' . $tahun);
        $ws->mergeCells("A1:{$lastCol}1");
        $ws->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $ws->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getRowDimension(1)->setRowHeight(22);

        $ws->setCellValue('A2', 'Periode: ' . $tgl1 . ' s/d ' . $tgl2 . ' | Total Pasien: ' . $data['total_pasien'] . ' | Total Kantong: ' . $data['total_kantong']);
        $ws->mergeCells("A2:{$lastCol}2");
        $ws->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $hr = 4;
        $ws->fromArray([$header], null, 'A' . $hr);
        $ws->getStyle("A{$hr}:{$lastCol}{$hr}")->applyFromArray($hStyle);
        $ws->freezePane('A' . ($hr + 1));

        $hr++;
        if (empty($rows)) {
            $ws->setCellValue('A' . $hr, 'TIDAK ADA DATA');
            $ws->mergeCells("A{$hr}:{$lastCol}{$hr}");
            $ws->getStyle('A' . $hr)->getFont()->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('6B7280'));
            $ws->getStyle('A' . $hr)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $hr++;
        } else {
            foreach ($rows as $row) {
                $values = [
                    $rekapBulanLabel,
                    $row['no_rawat'],
                    $row['no_rkm_medis'],
                    $row['nm_pasien'],
                    $row['tgl_registrasi'] ?? '-',
                    $row['no_sep'] ?? '-',
                    $row['lokasi'] ?? '-',
                    (int)$row['jumlah_kantong'],
                    (float)$row['total_biaya'],
                ];
                $ws->fromArray([$values], null, 'A' . $hr);
                $ws->getStyle("A{$hr}:{$lastCol}{$hr}")->applyFromArray($cStyle);
                $ws->getStyle("H{$hr}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $ws->getStyle("I{$hr}")->getNumberFormat()->setFormatCode('#,##0');
                $ws->getStyle("I{$hr}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $hr++;
            }
            $avgPerPasien = $data['total_pasien'] > 0 ? ($data['total_kantong'] / $data['total_pasien']) : 0;
            $ws->fromArray([['REKAP', '', 'TOTAL', '', '', '', '', $data['total_kantong'], $data['total_biaya']]], null, 'A' . $hr);
            $ws->getStyle("A{$hr}:{$lastCol}{$hr}")->applyFromArray($tStyle);
            $ws->getStyle("H{$hr}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $ws->getStyle("I{$hr}")->getNumberFormat()->setFormatCode('#,##0');
            $ws->getStyle("I{$hr}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            // Tambah baris rata-rata
            $hr++;
            $ws->fromArray([['RATA-RATA', '', 'Per Pasien', '', '', '', '', round($avgPerPasien, 2), '']], null, 'A' . $hr);
            $ws->getStyle("A{$hr}:{$lastCol}{$hr}")->applyFromArray($tStyle);
            $ws->getStyle("H{$hr}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $ws->getStyle("H{$hr}")->getNumberFormat()->setFormatCode('#,##0.00');
        }

        foreach (range(1, count($header)) as $c) {
            $ws->getColumnDimensionByColumn($c)->setAutoSize(true);
        }
    }
}

$spreadsheet->setActiveSheetIndex(0);

$filename = 'rekap_kantong_darah_' . $tahun . ($bulan !== '' ? '_' . str_pad($bulan, 2, '0', STR_PAD_LEFT) : '') . '.xlsx';

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
