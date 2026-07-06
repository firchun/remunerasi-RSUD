<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set("Asia/Jayapura");
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Color;

// ============================================================
// KONEKSI LANGSUNG KE DATABASE
// ============================================================
$db_host = '100.84.210.7';
$db_user = 'rsud';
$db_pass = 'rsud321';
$db_name = 'merauke_db';

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

if (!$conn) {
    if (isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Koneksi database gagal: ' . mysqli_connect_error()]);
        exit;
    }
    die('Koneksi database gagal: ' . mysqli_connect_error());
}

mysqli_set_charset($conn, 'utf8');

function bukaquery($query)
{
    global $conn;
    return mysqli_query($conn, $query);
}

// ============================================================
// AJAX: ambil daftar dokter untuk filter
// ============================================================
if (isset($_GET['ajax']) && $_GET['ajax'] == 'get_dokter_list') {
    header('Content-Type: application/json');
    $res = mysqli_query($conn, "SELECT kd_dokter, nm_dokter FROM dokter ORDER BY nm_dokter ASC");
    $list = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $list[] = $row;
        }
    }
    echo json_encode($list);
    exit;
}

// ============================================================
// EXPORT EXCEL PER DOKTER
// ============================================================
if (isset($_GET['ajax']) && $_GET['ajax'] == 'export_excel') {
    require __DIR__ . '/../vendor/autoload.php';



    $tanggal_mulai = isset($_GET['tanggal_mulai']) ? $_GET['tanggal_mulai'] : date('Y-m-01');
    $tanggal_selesai = isset($_GET['tanggal_selesai']) ? $_GET['tanggal_selesai'] : date('Y-m-t');
    $filter_dokter_arr = isset($_GET['dokter']) && is_array($_GET['dokter']) ? $_GET['dokter'] : [];
    $filter_jenis = isset($_GET['jenis']) && is_array($_GET['jenis']) ? $_GET['jenis'] : ['ralan', 'ranap', 'anastesi', 'lab', 'radiologi', 'konsultasi'];

    // Ambil daftar dokter
    if (!empty($filter_dokter_arr)) {
        $escaped = array_map(function ($v) {
            global $conn;
            return "'" . mysqli_real_escape_string($conn, $v) . "'";
        }, $filter_dokter_arr);
        $in_clause = "WHERE d.nm_dokter IN (" . implode(',', $escaped) . ")";
    } else {
        $in_clause = "";
    }

    $query_dokter = "SELECT d.kd_dokter, d.nm_dokter, p.no_ktp AS nik
        FROM dokter d
        LEFT JOIN pegawai p ON d.kd_dokter = p.nik
        $in_clause
        ORDER BY d.nm_dokter ASC";

    $res_dokter = mysqli_query($conn, $query_dokter);
    $daftar_dokter = [];
    while ($row = mysqli_fetch_assoc($res_dokter)) {
        $daftar_dokter[] = $row;
    }

    // Generate semua tanggal
    $semua_tanggal = [];
    $dt = new DateTime($tanggal_mulai);
    $dt_end = new DateTime($tanggal_selesai);
    while ($dt <= $dt_end) {
        $semua_tanggal[] = $dt->format('Y-m-d');
        $dt->modify('+1 day');
    }

    // Fungsi helper query
    $safe_query = function ($query) {
        global $conn;
        $res = mysqli_query($conn, $query);
        if (!$res)
            return ['error' => mysqli_error($conn), 'query' => $query];
        return $res;
    };

    $fetch = function ($query, &$arr) use ($safe_query) {
        $res = $safe_query($query);
        if (is_array($res) && isset($res['error']))
            return false;
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $arr[$row['nm_dokter']][$row['tgl']] = (int) $row['jml'];
            }
        }
        return true;
    };

    // Query data
    $data_ralan = $data_ranap = $data_anastesi = $data_lab = $data_radiologi = $data_konsultasi = [];

    if (in_array('ralan', $filter_jenis)) {
        $fetch("SELECT d.nm_dokter, DATE(rp.tgl_registrasi) AS tgl, COUNT(rp.no_rawat) AS jml
                FROM reg_periksa rp
                JOIN dokter d ON rp.kd_dokter = d.kd_dokter
                WHERE rp.stts = 'Sudah' AND rp.status_lanjut = 'Ralan'
                  AND rp.tgl_registrasi BETWEEN '$tanggal_mulai' AND '$tanggal_selesai'
                GROUP BY d.nm_dokter, DATE(rp.tgl_registrasi)", $data_ralan);
    }

    if (in_array('ranap', $filter_jenis)) {
        $fetch("SELECT d.nm_dokter, DATE(ri.tgl_perawatan) AS tgl, COUNT(ri.no_rawat) AS jml
                FROM rawat_inap_drpr ri
                JOIN dpjp_ranap dr ON ri.no_rawat = dr.no_rawat
                JOIN dokter d ON dr.kd_dokter = d.kd_dokter
                WHERE ri.kd_jenis_prw IN ('RI00011','RI00012','RI00013','RI02059')
                  AND ri.tgl_perawatan BETWEEN '$tanggal_mulai' AND '$tanggal_selesai'
                GROUP BY d.nm_dokter, DATE(ri.tgl_perawatan)", $data_ranap);
    }

    if (in_array('anastesi', $filter_jenis)) {
        $fetch("SELECT d.nm_dokter, DATE(o.tgl_operasi) AS tgl, COUNT(o.no_rawat) AS jml
                FROM operasi o
                JOIN dokter d ON o.dokter_anestesi = d.kd_dokter
                WHERE o.tgl_operasi BETWEEN '$tanggal_mulai 00:00:00' AND '$tanggal_selesai 23:59:59'
                  AND o.dokter_anestesi IS NOT NULL AND o.dokter_anestesi <> ''
                GROUP BY d.nm_dokter, DATE(o.tgl_operasi)", $data_anastesi);
    }

    if (in_array('lab', $filter_jenis)) {
        $fetch("SELECT d.nm_dokter, DATE(pl.tgl_periksa) AS tgl, COUNT(DISTINCT pl.no_rawat) AS jml
                FROM periksa_lab pl
                JOIN reg_periksa rp ON pl.no_rawat = rp.no_rawat
                JOIN dokter d ON pl.kd_dokter = d.kd_dokter
                WHERE pl.tgl_periksa BETWEEN '$tanggal_mulai' AND '$tanggal_selesai'
                GROUP BY d.nm_dokter, DATE(pl.tgl_periksa)", $data_lab);
    }

    if (in_array('radiologi', $filter_jenis)) {
        $fetch("SELECT d.nm_dokter, DATE(pr.tgl_periksa) AS tgl, COUNT(DISTINCT pr.no_rawat) AS jml
                FROM periksa_radiologi pr
                JOIN reg_periksa rp ON pr.no_rawat = rp.no_rawat
                JOIN dokter d ON pr.kd_dokter = d.kd_dokter
                WHERE pr.tgl_periksa BETWEEN '$tanggal_mulai' AND '$tanggal_selesai'
                GROUP BY d.nm_dokter, DATE(pr.tgl_periksa)", $data_radiologi);
    }

    if (in_array('konsultasi', $filter_jenis)) {
        $fetch("SELECT d.nm_dokter, DATE(km.tanggal) AS tgl, COUNT(DISTINCT km.no_rawat) AS jml
                FROM konsultasi_medik km
                JOIN reg_periksa rp ON km.no_rawat = rp.no_rawat
                JOIN dokter d ON km.kd_dokter_dikonsuli = d.kd_dokter
                WHERE km.tanggal BETWEEN '$tanggal_mulai 00:00:00' AND '$tanggal_selesai 23:59:59'
                GROUP BY d.nm_dokter, DATE(km.tanggal)", $data_konsultasi);
    }

    // Buat spreadsheet
    $spreadsheet = new Spreadsheet();
    $spreadsheet->removeSheetByIndex(0); // Hapus sheet default

    // Define style arrays for PhpSpreadsheet
    $headerStyleArray = [
        'font' => [
            'bold' => true,
            'color' => ['argb' => 'FFFFFFFF'],
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['argb' => 'FF4472C4'],
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
    ];

    $borderStyleArray = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
            ],
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
    ];

    $sundayStyleArray = [
        'font' => [
            'color' => ['argb' => 'FFFF0000'],
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['argb' => 'FFFFCCCC'], // Lighter red fill for Sunday
        ],
    ];

    // Buat sheet per dokter
    foreach ($daftar_dokter as $index => $dokter) {
        $nm = $dokter['nm_dokter'];
        $nik = $dokter['nik'];

        // Nama sheet (max 31 karakter)
        $sheet_name = substr($nm, 0, 31);
        $sheet = $spreadsheet->createSheet($index);
        $sheet->setTitle($sheet_name);

        // Header
        $row = 1;
        $sheet->setCellValue('A' . $row, 'No');
        $sheet->setCellValue('B' . $row, 'NIK');
        $sheet->setCellValue('C' . $row, 'Nama');
        $sheet->setCellValue('D' . $row, 'Tanggal');
        $sheet->setCellValue('E' . $row, 'Hari');
        $sheet->setCellValue('F' . $row, 'Jml Pasien');
        $sheet->setCellValue('G' . $row, 'Jml Bacaan');

        // Format header
        $sheet->getStyle('A1:G1')->applyFromArray($headerStyleArray);

        // Set column width
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(25);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(12);
        $sheet->getColumnDimension('F')->setWidth(12);
        $sheet->getColumnDimension('G')->setWidth(12);

        // Data
        $row = 2;
        $no = 1;
        $hari_indo = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

        foreach ($semua_tanggal as $tgl_raw) {
            $ralan = in_array('ralan', $filter_jenis) ? (isset($data_ralan[$nm][$tgl_raw]) ? $data_ralan[$nm][$tgl_raw] : 0) : 0;
            $ranap = in_array('ranap', $filter_jenis) ? (isset($data_ranap[$nm][$tgl_raw]) ? $data_ranap[$nm][$tgl_raw] : 0) : 0;
            $anastesi = in_array('anastesi', $filter_jenis) ? (isset($data_anastesi[$nm][$tgl_raw]) ? $data_anastesi[$nm][$tgl_raw] : 0) : 0;
            $lab = in_array('lab', $filter_jenis) ? (isset($data_lab[$nm][$tgl_raw]) ? $data_lab[$nm][$tgl_raw] : 0) : 0;
            $radiologi = in_array('radiologi', $filter_jenis) ? (isset($data_radiologi[$nm][$tgl_raw]) ? $data_radiologi[$nm][$tgl_raw] : 0) : 0;
            $konsultasi_medik = in_array('konsultasi', $filter_jenis) ? (isset($data_konsultasi[$nm][$tgl_raw]) ? $data_konsultasi[$nm][$tgl_raw] : 0) : 0;

            $jumlah_pasien = $ralan + $ranap + $anastesi + $konsultasi_medik;
            $jumlah_bacaan = $lab + $radiologi;

            // Deteksi Minggu
            $dt = new DateTime($tgl_raw);
            $day_of_week = (int) $dt->format('w');
            $is_sunday = ($day_of_week == 0);
            $hari_nama = $hari_indo[$day_of_week];

            // Data row
            $sheet->setCellValue('A' . $row, $no);
            $sheet->setCellValue('B' . $row, $nik);
            $sheet->setCellValue('C' . $row, $nm);
            $sheet->setCellValue('D' . $row, date('d/m/Y', strtotime($tgl_raw)));
            $sheet->setCellValue('E' . $row, $hari_nama);
            $sheet->setCellValue('F' . $row, $jumlah_pasien);
            $sheet->setCellValue('G' . $row, $jumlah_bacaan);

            // Format baris
            $sheet->getStyle('A' . $row . ':G' . $row)->applyFromArray($borderStyleArray);
            if ($is_sunday) {
                $sheet->getStyle('A' . $row . ':G' . $row)->applyFromArray($sundayStyleArray);
            }

            $no++;
            $row++;
        }

        // Freeze pane (header)
        $sheet->freezePane('A2');
    }

    // Output
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="Laporan_Dokter_' . date('Ymd_His') . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// ============================================================
// Handle AJAX request untuk get data
// ============================================================
if (isset($_GET['ajax']) && $_GET['ajax'] == 'get_data') {
    ob_start();
    header('Content-Type: application/json');

    set_error_handler(function ($errno, $errstr, $errfile, $errline) {
        ob_clean();
        echo json_encode(['error' => "PHP Error [$errno]: $errstr in $errfile line $errline"]);
        exit;
    });

    $tanggal_mulai = isset($_GET['tanggal_mulai']) ? $_GET['tanggal_mulai'] : date('Y-m-01');
    $tanggal_selesai = isset($_GET['tanggal_selesai']) ? $_GET['tanggal_selesai'] : date('Y-m-t');
    $filter_dokter_arr = isset($_GET['dokter']) && is_array($_GET['dokter']) ? $_GET['dokter'] : [];
    $filter_jenis = isset($_GET['jenis']) && is_array($_GET['jenis']) ? $_GET['jenis'] : ['ralan', 'ranap', 'anastesi', 'lab', 'radiologi', 'konsultasi'];

    if (!empty($filter_dokter_arr)) {
        $escaped = array_map(function ($v) {
            global $conn;
            return "'" . mysqli_real_escape_string($conn, $v) . "'";
        }, $filter_dokter_arr);
        $in_clause = "WHERE d.nm_dokter IN (" . implode(',', $escaped) . ")";
    } else {
        $in_clause = "";
    }

    $query_dokter = "SELECT d.kd_dokter, d.nm_dokter, p.no_ktp AS nik
        FROM dokter d
        LEFT JOIN pegawai p ON d.kd_dokter = p.nik
        $in_clause
        ORDER BY d.nm_dokter ASC";

    $res_dokter = mysqli_query($conn, $query_dokter);
    if (!$res_dokter) {
        ob_clean();
        echo json_encode(['error' => mysqli_error($conn), 'query' => $query_dokter]);
        exit;
    }

    $daftar_dokter = [];
    while ($row = mysqli_fetch_assoc($res_dokter)) {
        $daftar_dokter[] = $row;
    }

    $semua_tanggal = [];
    $dt = new DateTime($tanggal_mulai);
    $dt_end = new DateTime($tanggal_selesai);
    while ($dt <= $dt_end) {
        $semua_tanggal[] = $dt->format('Y-m-d');
        $dt->modify('+1 day');
    }

    $safe_query = function ($query) {
        global $conn;
        $res = mysqli_query($conn, $query);
        if (!$res)
            return ['error' => mysqli_error($conn), 'query' => $query];
        return $res;
    };

    $fetch = function ($query, &$arr) use ($safe_query) {
        $res = $safe_query($query);
        if (is_array($res) && isset($res['error'])) {
            ob_clean();
            echo json_encode(['error' => $res['error'], 'query' => $res['query']]);
            exit;
        }
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $arr[$row['nm_dokter']][$row['tgl']] = (int) $row['jml'];
            }
        }
    };

    $data_ralan = $data_ranap = $data_anastesi = $data_lab = $data_radiologi = $data_konsultasi = [];

    if (in_array('ralan', $filter_jenis)) {
        $fetch("SELECT d.nm_dokter, DATE(rp.tgl_registrasi) AS tgl,
                    COUNT(rp.no_rawat) AS jml
                FROM reg_periksa rp
                JOIN dokter d ON rp.kd_dokter = d.kd_dokter
                WHERE rp.stts = 'Sudah'
                  AND rp.status_lanjut = 'Ralan'
                  AND rp.tgl_registrasi BETWEEN '$tanggal_mulai' AND '$tanggal_selesai'
                GROUP BY d.nm_dokter, DATE(rp.tgl_registrasi)", $data_ralan);
    }

    if (in_array('ranap', $filter_jenis)) {
        $fetch("SELECT d.nm_dokter, DATE(ri.tgl_perawatan) AS tgl,
                    COUNT(ri.no_rawat) AS jml
                FROM rawat_inap_drpr ri
                JOIN dpjp_ranap dr ON ri.no_rawat = dr.no_rawat
                JOIN dokter d ON dr.kd_dokter = d.kd_dokter
                WHERE ri.kd_jenis_prw IN ('RI00011','RI00012','RI00013','RI02059')
                  AND ri.tgl_perawatan BETWEEN '$tanggal_mulai' AND '$tanggal_selesai'
                GROUP BY d.nm_dokter, DATE(ri.tgl_perawatan)", $data_ranap);
    }

    if (in_array('anastesi', $filter_jenis)) {
        $fetch("SELECT d.nm_dokter, DATE(o.tgl_operasi) AS tgl,
                    COUNT(o.no_rawat) AS jml
                FROM operasi o
                JOIN dokter d ON o.dokter_anestesi = d.kd_dokter
                WHERE o.tgl_operasi BETWEEN '$tanggal_mulai 00:00:00' AND '$tanggal_selesai 23:59:59'
                  AND o.dokter_anestesi IS NOT NULL AND o.dokter_anestesi <> ''
                GROUP BY d.nm_dokter, DATE(o.tgl_operasi)", $data_anastesi);
    }

    if (in_array('lab', $filter_jenis)) {
        $fetch("SELECT d.nm_dokter, DATE(pl.tgl_periksa) AS tgl,
                    COUNT(DISTINCT pl.no_rawat) AS jml
                FROM periksa_lab pl
                JOIN reg_periksa rp ON pl.no_rawat = rp.no_rawat
                JOIN dokter d ON pl.kd_dokter = d.kd_dokter
                WHERE pl.tgl_periksa BETWEEN '$tanggal_mulai' AND '$tanggal_selesai'
                GROUP BY d.nm_dokter, DATE(pl.tgl_periksa)", $data_lab);
    }

    if (in_array('radiologi', $filter_jenis)) {
        $fetch("SELECT d.nm_dokter, DATE(pr.tgl_periksa) AS tgl,
                    COUNT(DISTINCT pr.no_rawat) AS jml
                FROM periksa_radiologi pr
                JOIN reg_periksa rp ON pr.no_rawat = rp.no_rawat
                JOIN dokter d ON pr.kd_dokter = d.kd_dokter
                WHERE pr.tgl_periksa BETWEEN '$tanggal_mulai' AND '$tanggal_selesai'
                GROUP BY d.nm_dokter, DATE(pr.tgl_periksa)", $data_radiologi);
    }

    if (in_array('konsultasi', $filter_jenis)) {
        $fetch("SELECT d.nm_dokter, DATE(km.tanggal) AS tgl,
                    COUNT(DISTINCT km.no_rawat) AS jml
                FROM konsultasi_medik km
                JOIN reg_periksa rp ON km.no_rawat = rp.no_rawat
                JOIN dokter d ON km.kd_dokter_dikonsuli = d.kd_dokter
                WHERE km.tanggal BETWEEN '$tanggal_mulai 00:00:00' AND '$tanggal_selesai 23:59:59'
                GROUP BY d.nm_dokter, DATE(km.tanggal)", $data_konsultasi);
    }

    $data = [];
    foreach ($daftar_dokter as $dokter) {
        $nm = $dokter['nm_dokter'];
        $nik = $dokter['nik'];

        foreach ($semua_tanggal as $tgl_raw) {
            $ralan = in_array('ralan', $filter_jenis) ? (isset($data_ralan[$nm][$tgl_raw]) ? $data_ralan[$nm][$tgl_raw] : 0) : 0;
            $ranap = in_array('ranap', $filter_jenis) ? (isset($data_ranap[$nm][$tgl_raw]) ? $data_ranap[$nm][$tgl_raw] : 0) : 0;
            $anastesi = in_array('anastesi', $filter_jenis) ? (isset($data_anastesi[$nm][$tgl_raw]) ? $data_anastesi[$nm][$tgl_raw] : 0) : 0;
            $lab = in_array('lab', $filter_jenis) ? (isset($data_lab[$nm][$tgl_raw]) ? $data_lab[$nm][$tgl_raw] : 0) : 0;
            $radiologi = in_array('radiologi', $filter_jenis) ? (isset($data_radiologi[$nm][$tgl_raw]) ? $data_radiologi[$nm][$tgl_raw] : 0) : 0;
            $konsultasi_medik = in_array('konsultasi', $filter_jenis) ? (isset($data_konsultasi[$nm][$tgl_raw]) ? $data_konsultasi[$nm][$tgl_raw] : 0) : 0;

            $jumlah_pasien = $ralan + $ranap + $anastesi + $konsultasi_medik;
            $jumlah_bacaan = $lab + $radiologi;

            // Deteksi Minggu
            $dt = new DateTime($tgl_raw);
            $day_of_week = (int) $dt->format('w');
            $is_sunday = ($day_of_week == 0);

            $label_status = implode('+', array_map('strtoupper', $filter_jenis));

            $data[] = [
                'tanggal' => date('d/m/Y', strtotime($tgl_raw)),
                'tgl_sort' => $tgl_raw,
                'nik' => $nik,
                'nama_dokter' => $nm,
                'jumlah_pasien' => $jumlah_pasien,
                'jumlah_bacaan' => $jumlah_bacaan,
                'status_lanjut' => $label_status,
                'is_sunday' => $is_sunday
            ];
        }
    }

    echo json_encode($data);
    exit;
}

// Ambil data setting rumah sakit
$setting = mysqli_fetch_array(mysqli_query($conn, "SELECT nama_instansi, alamat_instansi, kabupaten, propinsi, kontak, email, logo FROM setting LIMIT 1"));
?>
<?php
$pageTitle = 'Tunjangan Susila Dokter - RSUD MERAUKE';
$extraHead = '
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: Arial, sans-serif; background: #f8f9fa; }
.container { max-width: 1400px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.page-header { text-align: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 3px solid #007bff; }
.page-header h2 { color: #007bff; font-size: 24px; margin-bottom: 10px; }
.filter-section { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 25px; }
.filter-row { display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 15px; }
.filter-row:last-child { margin-bottom: 0; }
.form-group { flex: 1; min-width: 180px; }
.form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; font-size: 14px; }
.form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
.dokter-dropdown-wrapper { position: relative; flex: 2; min-width: 280px; }
.dokter-toggle-btn { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; background: white; text-align: left; cursor: pointer; display: flex; justify-content: space-between; align-items: center; color: #333; }
.dokter-toggle-btn:hover { border-color: #007bff; }
.dokter-dropdown-panel { display: none; position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ddd; border-radius: 5px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 1000; max-height: 320px; overflow: hidden; flex-direction: column; }
.dokter-dropdown-panel.open { display: flex; }
.dokter-search-box { padding: 8px; border-bottom: 1px solid #eee; }
.dokter-search-box input { width: 100%; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; }
.dokter-actions { display: flex; gap: 5px; padding: 6px 8px; border-bottom: 1px solid #eee; }
.dokter-actions button { flex: 1; padding: 4px 8px; font-size: 12px; border: 1px solid #ddd; border-radius: 4px; cursor: pointer; background: #f8f9fa; }
.dokter-actions button:hover { background: #e9ecef; }
.dokter-list { overflow-y: auto; flex: 1; }
.dokter-item { display: flex; align-items: center; padding: 7px 12px; cursor: pointer; font-size: 13px; border-bottom: 1px solid #f5f5f5; }
.dokter-item:hover { background: #f0f7ff; }
.dokter-item.selected { background: #e8f4fd; }
.dokter-item input[type="checkbox"] { margin-right: 8px; width: auto; cursor: pointer; }
.jenis-data-group { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; padding: 10px; background: white; border: 1px solid #ddd; border-radius: 5px; }
.jenis-data-group label { display: flex; align-items: center; gap: 5px; font-size: 13px; font-weight: normal; cursor: pointer; padding: 4px 10px; border: 1px solid #ddd; border-radius: 20px; background: #f8f9fa; transition: all 0.2s; white-space: nowrap; }
.jenis-data-group label:hover { border-color: #007bff; background: #e8f4fd; }
.jenis-data-group input[type="checkbox"] { width: auto; margin: 0; cursor: pointer; }
.jenis-data-group label:has(input:checked) { background: #007bff; color: white; border-color: #007bff; }
.jenis-data-group label:has(input:checked) span { color: white; font-weight: bold; }
.btn { padding: 10px 25px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: bold; transition: all 0.3s; }
.btn-primary { background: #007bff; color: white; }
.btn-primary:hover { background: #0056b3; }
.btn-info { background: #17a2b8; color: white; }
.btn-info:hover { background: #138496; }
.btn-success { background: #28a745; color: white; }
.btn-success:hover { background: #218838; }
table.dataTable { width: 100% !important; border-collapse: collapse; }
table.dataTable thead th { background: white; color: black; padding: 12px 8px; text-align: center; border: 1px solid #000; font-size: 13px; font-weight: bold; }
table.dataTable tbody td { padding: 10px 8px; border: 1px solid #000; font-size: 13px; background: white; }
table.dataTable tbody tr:nth-child(even) { background-color: white; }
table.dataTable tbody tr:hover { background-color: white; }
table.dataTable tbody tr.row-sunday td { background-color: #ffcccc; color: #cc0000; font-weight: bold; }
table.dataTable tbody td.sunday-cell { background-color: #ffcccc; color: #cc0000; font-weight: bold; }
.dt-buttons { margin-bottom: 15px; }
.dt-button { background: #007bff !important; color: white !important; border: none !important; padding: 8px 15px !important; border-radius: 5px !important; margin-right: 5px !important; font-size: 13px !important; }
.dt-button:hover { background: #0056b3 !important; }
.custom-export-btn { background: #28a745 !important; color: white !important; border: none !important; padding: 8px 15px !important; border-radius: 5px !important; margin-right: 5px !important; font-size: 13px !important; cursor: pointer; }
.custom-export-btn:hover { background: #218838 !important; }
@media print { body * { visibility: hidden; } #printPagesContainer, #printPagesContainer * { visibility: visible; } #printPagesContainer { position: absolute; left: 0; top: 0; width: 100%; } .print-page { page-break-after: always; } .print-page:last-child { page-break-after: auto; } .no-print { display: none !important; } }
.print-header { display: none; text-align: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 3px solid #000; }
.print-header-content { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
.print-logo { width: 80px; height: auto; }
.print-title { flex: 1; text-align: center; }
.signature-section { margin-top: 50px; text-align: right; page-break-inside: avoid; }
</style>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
';
$rootPath = '../';
require_once 'layouts/header.php';
?>
    <div class="container">

        <div class="page-header no-print">
            <h2>LAPORAN USULAN TUNJANGAN KHUSUS DOKTER SPESIALIS DTPK</h2>
            <p>RSUD Merauke</p>
        </div>

        <!-- ==================== FILTER SECTION ==================== -->
        <div class="filter-section no-print">

            <!-- Baris 1: Tanggal + Tombol -->
            <div class="filter-row">
                <div class="form-group" style="flex:0 0 auto; min-width:180px;">
                    <label for="filterTanggalMulai">Tanggal Mulai:</label>
                    <input type="date" id="filterTanggalMulai" value="2026-01-01">
                </div>
                <div class="form-group" style="flex:0 0 auto; min-width:180px;">
                    <label for="filterTanggalSelesai">Tanggal Selesai:</label>
                    <input type="date" id="filterTanggalSelesai" value="2026-01-31">
                </div>
                <div class="form-group" style="flex:0 0 auto;">
                    <label>&nbsp;</label>
                    <button class="btn btn-primary" onclick="loadData()">🔍 Filter Data</button>
                </div>
                <div class="form-group" style="flex:0 0 auto;">
                    <label>&nbsp;</label>
                    <button class="btn btn-success" onclick="exportExcel()">📥 Export Excel</button>
                </div>
                <div class="form-group" style="flex:0 0 auto;">
                    <label>&nbsp;</label>
                    <button class="btn btn-info" onclick="printReport()">🖨️ Print Laporan</button>
                </div>
            </div>

            <!-- Baris 2: Pilih Dokter (multi checkbox) -->
            <div class="filter-row">
                <div class="dokter-dropdown-wrapper">
                    <label style="display:block; margin-bottom:5px; font-weight:bold; color:#333; font-size:14px;">Pilih
                        Dokter:</label>
                    <button type="button" class="dokter-toggle-btn" id="dokterToggleBtn"
                        onclick="toggleDokterDropdown()">
                        <span id="dokterToggleLabel">Memuat daftar dokter...</span>
                        <span>▼</span>
                    </button>
                    <div class="dokter-dropdown-panel" id="dokterDropdownPanel">
                        <div class="dokter-search-box">
                            <input type="text" id="dokterSearchInput" placeholder="Cari nama dokter..."
                                oninput="filterDokterList()">
                        </div>
                        <div class="dokter-actions">
                            <button onclick="selectAllDokter()">✔ Pilih Semua</button>
                            <button onclick="clearAllDokter()">✖ Hapus Semua</button>
                        </div>
                        <div class="dokter-list" id="dokterList">
                            <div style="padding:15px; text-align:center; color:#999;">Memuat...</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Baris 3: Jenis Data -->
            <div class="filter-row">
                <div class="form-group" style="flex: 1 1 100%;">
                    <label style="margin-bottom:8px; display:block;">Jenis Data:</label>
                    <div class="jenis-data-group">
                        <label>
                            <input type="checkbox" class="jenis-cb" value="ralan" checked>
                            <span>DPJP Ralan</span>
                        </label>
                        <label>
                            <input type="checkbox" class="jenis-cb" value="ranap" checked>
                            <span>DPJP Ranap</span>
                        </label>
                        <label>
                            <input type="checkbox" class="jenis-cb" value="anastesi" checked>
                            <span>Anestesi</span>
                        </label>
                        <label>
                            <input type="checkbox" class="jenis-cb" value="lab" checked>
                            <span>Lab PK</span>
                        </label>
                        <label>
                            <input type="checkbox" class="jenis-cb" value="radiologi" checked>
                            <span>Radiologi</span>
                        </label>
                        <label>
                            <input type="checkbox" class="jenis-cb" value="konsultasi" checked>
                            <span>Konsultasi Medik</span>
                        </label>
                    </div>
                </div>
            </div>

        </div>
        <!-- ==================== END FILTER SECTION ==================== -->

        <div id="printArea">
            <div class="print-header">
                <div class="print-header-content">
                    <img src="data:image/png;base64,<?= base64_encode($setting['logo']); ?>" alt="Logo"
                        class="print-logo">
                    <div class="print-title">
                        <h3>PEMERINTAH KABUPATEN MERAUKE</h3>
                        <h3>DINAS KESEHATAN</h3>
                        <h2>RUMAH SAKIT UMUM DAERAH KELAS C</h2>
                        <h2>MERAUKE</h2>
                        <p>Jalan Soekarno Wiryopranoto No. 1 - Kelurahan Karang Indah - Merauke</p>
                        <p>Pos-el: humasrsudmeraukegmail.com Kode Pos: 09614</p>
                    </div>
                    <img src="data:image/png;base64,<?= base64_encode($setting['logo']); ?>" alt="Logo"
                        class="print-logo">
                </div>
            </div>

            <div class="report-info">
                <table style="border: none;">
                    <tr>
                        <td style="width: 150px;"><strong>Kode Rumah Sakit</strong></td>
                        <td style="width: 20px;">:</td>
                        <td><span id="printKodeRS">9201012</span></td>
                    </tr>
                    <tr>
                        <td><strong>Nama Rumah Sakit</strong></td>
                        <td>:</td>
                        <td><span id="printNamaRS">RSUD Merauke</span></td>
                    </tr>
                    <tr>
                        <td><strong>Periode Usulan</strong></td>
                        <td>:</td>
                        <td><span id="printPeriode"></span></td>
                    </tr>
                </table>
            </div>

            <h4 style="text-align: center; margin: 20px 0; font-size: 14px;">
                DAFTAR NAMA USULAN TUNJANGAN KHUSUS DOKTER SPESIALIS DTPK
            </h4>

            <table id="tableLaporan" class="display" style="width:100%">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>NIK Dokter</th>
                        <th>Nama</th>
                        <th>Tanggal Pelayanan</th>
                        <th>Jumlah Pasien</th>
                        <th>Jumlah Bacaan Penunjang</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>

            <div class="signature-section">
                <div class="signature-box">
                    <p>Merauke, <span id="printTanggalTTD"></span></p>
                    <p>Direktur RSUD Merauke</p>
                    <div class="signature-space"></div>
                    <p class="signature-name">dr. DEWI WULANSARI.M.Sc</p>
                    <p>NIP.19720706 200501 2 010</p>
                </div>
            </div>
        </div>

    </div>

    <script>
        let dataTable;
        let allData = [];
        let dokterData = [];

        const bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];

        function formatTanggalIndo(tanggal) {
            const d = new Date(tanggal);
            return d.getDate() + ' ' + bulan[d.getMonth()] + ' ' + d.getFullYear();
        }

        function formatPeriode(tanggalMulai, tanggalSelesai) {
            const s = new Date(tanggalMulai),
                e = new Date(tanggalSelesai);
            if (s.getMonth() === e.getMonth() && s.getFullYear() === e.getFullYear()) {
                return bulan[s.getMonth()] + ' ' + s.getFullYear();
            }
            return bulan[s.getMonth()] + ' - ' + bulan[e.getMonth()] + ' ' + e.getFullYear();
        }

        // ================================================================
        // DOKTER DROPDOWN
        // ================================================================
        function loadDokterList() {
            $.ajax({
                url: window.location.href,
                method: 'GET',
                data: {
                    ajax: 'get_dokter_list'
                },
                dataType: 'json',
                success: function (list) {
                    dokterData = list;
                    renderDokterList(list);
                    updateDokterLabel();
                },
                error: function () {
                    $('#dokterList').html('<div style="padding:10px;color:red;">Gagal memuat daftar dokter</div>');
                }
            });
        }

        function renderDokterList(list) {
            const $list = $('#dokterList');
            $list.empty();

            if (list.length === 0) {
                $list.html('<div style="padding:10px;color:#999;text-align:center;">Tidak ada dokter ditemukan</div>');
                return;
            }

            list.forEach(function (d) {
                const checked = true;
                const $item = $(`
                <div class="dokter-item ${checked ? 'selected' : ''}" data-nama="${d.nm_dokter}">
                    <input type="checkbox" class="dokter-cb" value="${d.nm_dokter}" ${checked ? 'checked' : ''}>
                    ${d.nm_dokter}
                </div>
            `);

                $item.on('click', function (e) {
                    if (e.target.tagName !== 'INPUT') {
                        const cb = $(this).find('input[type="checkbox"]')[0];
                        cb.checked = !cb.checked;
                        $(cb).trigger('change');
                    }
                });

                $item.find('input').on('change', function () {
                    $(this).closest('.dokter-item').toggleClass('selected', this.checked);
                    updateDokterLabel();
                });

                $list.append($item);
            });

            updateDokterLabel();
        }

        function filterDokterList() {
            const keyword = $('#dokterSearchInput').val().toLowerCase();
            $('#dokterList .dokter-item').each(function () {
                const nama = $(this).data('nama').toLowerCase();
                $(this).toggle(nama.includes(keyword));
            });
        }

        function selectAllDokter() {
            $('#dokterList .dokter-item:visible input[type="checkbox"]').prop('checked', true).trigger('change');
            $('#dokterList .dokter-item:visible').addClass('selected');
            updateDokterLabel();
        }

        function clearAllDokter() {
            $('#dokterList .dokter-item:visible input[type="checkbox"]').prop('checked', false).trigger('change');
            $('#dokterList .dokter-item:visible').removeClass('selected');
            updateDokterLabel();
        }

        function getSelectedDokter() {
            const selected = [];
            $('#dokterList .dokter-cb:checked').each(function () {
                selected.push($(this).val());
            });
            return selected;
        }

        function updateDokterLabel() {
            const selected = getSelectedDokter();
            const total = $('#dokterList .dokter-cb').length;
            let label = '';
            if (selected.length === 0) {
                label = 'Tidak ada dokter dipilih';
            } else if (selected.length === total) {
                label = 'Semua Dokter (' + total + ')';
            } else if (selected.length === 1) {
                label = selected[0];
            } else {
                label = selected.length + ' Dokter dipilih';
            }
            $('#dokterToggleLabel').text(label);
        }

        function toggleDokterDropdown() {
            $('#dokterDropdownPanel').toggleClass('open');
        }

        $(document).on('click', function (e) {
            if (!$(e.target).closest('.dokter-dropdown-wrapper').length) {
                $('#dokterDropdownPanel').removeClass('open');
            }
        });

        // ================================================================
        // LOAD DATA
        // ================================================================
        function loadData() {
            const tanggalMulai = $('#filterTanggalMulai').val();
            const tanggalSelesai = $('#filterTanggalSelesai').val();
            const selectedDokter = getSelectedDokter();
            const selectedJenis = [];
            $('.jenis-cb:checked').each(function () {
                selectedJenis.push($(this).val());
            });

            if (selectedDokter.length === 0) {
                alert('Silakan pilih minimal satu dokter.');
                return;
            }

            if (selectedJenis.length === 0) {
                alert('Silakan pilih minimal satu jenis data.');
                return;
            }

            const params = {
                ajax: 'get_data',
                tanggal_mulai: tanggalMulai,
                tanggal_selesai: tanggalSelesai
            };

            selectedDokter.forEach(function (nm, i) {
                params['dokter[' + i + ']'] = nm;
            });

            selectedJenis.forEach(function (j, i) {
                params['jenis[' + i + ']'] = j;
            });

            $('#tableLaporan tbody').html(
                '<tr><td colspan="6" style="text-align:center;padding:20px;color:#666;">⏳ Memuat data...</td></tr>');

            $.ajax({
                url: window.location.href,
                method: 'GET',
                data: params,
                dataType: 'json',
                success: function (response) {
                    if (response && response.error) {
                        $('#tableLaporan tbody').html(
                            `<tr><td colspan="6" style="color:red;padding:15px;">
                            <strong>Error:</strong> ${response.error}
                        </td></tr>`
                        );
                        return;
                    }
                    allData = response;
                    renderTable(response);
                },
                error: function (xhr, status, error) {
                    $('#tableLaporan tbody').html(
                        `<tr><td colspan="6" style="color:red;padding:15px;"><strong>Error:</strong> ${error}</td></tr>`
                    );
                }
            });
        }

        // ================================================================
        // RENDER TABLE
        // ================================================================
        function renderTable(data) {
            if (dataTable) {
                dataTable.destroy();
            }

            data.sort((a, b) => {
                const namaCmp = a.nama_dokter.localeCompare(b.nama_dokter);
                return namaCmp !== 0 ? namaCmp : a.tgl_sort.localeCompare(b.tgl_sort);
            });

            const tbody = $('#tableLaporan tbody');
            tbody.empty();

            data.forEach((item, index) => {
                const isSunday = item.is_sunday;
                tbody.append(`
                <tr class="${isSunday ? 'row-sunday' : ''}">
                    <td style="text-align:center;">${index + 1}</td>
                    <td style="text-align:center;">${item.nik || '-'}</td>
                    <td>${item.nama_dokter}</td>
                    <td style="text-align:center;" class="${isSunday ? 'sunday-cell' : ''}">${item.tanggal}</td>
                    <td style="text-align:center;" class="${isSunday ? 'sunday-cell' : ''}">${item.jumlah_pasien}</td>
                    <td style="text-align:center;" class="${isSunday ? 'sunday-cell' : ''}">${item.jumlah_bacaan}</td>
                </tr>
            `);
            });

            dataTable = $('#tableLaporan').DataTable({
                dom: 'frtip',
                searching: false,
                ordering: false,
                pageLength: 50,
                order: [],
                language: {
                    lengthMenu: "Tampilkan _MENU_ data per halaman",
                    zeroRecords: "Data tidak ditemukan",
                    info: "Menampilkan halaman _PAGE_ dari _PAGES_",
                    infoEmpty: "Tidak ada data tersedia",
                    infoFiltered: "(difilter dari _MAX_ total data)",
                    paginate: {
                        first: "Pertama",
                        last: "Terakhir",
                        next: "Selanjutnya",
                        previous: "Sebelumnya"
                    }
                }
            });
        }

        // ================================================================
        // EXPORT EXCEL PER DOKTER
        // ================================================================
        function exportExcel() {
            const tanggalMulai = $('#filterTanggalMulai').val();
            const tanggalSelesai = $('#filterTanggalSelesai').val();
            const selectedDokter = getSelectedDokter();
            const selectedJenis = [];
            $('.jenis-cb:checked').each(function () {
                selectedJenis.push($(this).val());
            });

            if (selectedDokter.length === 0) {
                alert('Silakan pilih minimal satu dokter.');
                return;
            }

            if (selectedJenis.length === 0) {
                alert('Silakan pilih minimal satu jenis data.');
                return;
            }

            const params = {
                ajax: 'export_excel',
                tanggal_mulai: tanggalMulai,
                tanggal_selesai: tanggalSelesai
            };

            selectedDokter.forEach(function (nm, i) {
                params['dokter[' + i + ']'] = nm;
            });

            selectedJenis.forEach(function (j, i) {
                params['jenis[' + i + ']'] = j;
            });

            const queryString = new URLSearchParams(params).toString();
            window.location.href = window.location.href + '?' + queryString;
        }

        // ================================================================
        // PRINT
        // ================================================================
        function printReport() {
            if (allData.length === 0) {
                alert('Tidak ada data yang dapat diprint. Silakan filter data terlebih dahulu.');
                return;
            }

            const tanggalMulai = $('#filterTanggalMulai').val();
            const tanggalSelesai = $('#filterTanggalSelesai').val();
            const periodeText = formatPeriode(tanggalMulai, tanggalSelesai);
            const today = new Date();
            const ttdDate = formatTanggalIndo(today.toISOString().split('T')[0]);

            // Group data by doctor
            const groupedData = {};
            allData.forEach(item => {
                if (!groupedData[item.nama_dokter]) {
                    groupedData[item.nama_dokter] = [];
                }
                groupedData[item.nama_dokter].push(item);
            });

            // Prepare logo
            const logoSrc = `data:image/png;base64,<?= base64_encode($setting['logo']); ?>`;

            const $printContainer = $('<div id="printPagesContainer"></div>');

            Object.keys(groupedData).forEach(dokterName => {
                const docData = groupedData[dokterName].sort((a, b) => a.tgl_sort.localeCompare(b.tgl_sort));
                const nik = docData[0].nik || '-';

                let tbodyHtml = '';
                docData.forEach((item, index) => {
                    const isSunday = item.is_sunday;
                    const cssClass = isSunday ? 'class="row-sunday"' : '';
                    const cellClass = isSunday ? 'class="sunday-cell" style="text-align:center;"' : 'style="text-align:center;"';

                    tbodyHtml += `
                        <tr ${cssClass}>
                            <td style="text-align:center;">${index + 1}</td>
                            <td style="text-align:center;">${nik}</td>
                            <td>${dokterName}</td>
                            <td ${cellClass}>${item.tanggal}</td>
                            <td ${cellClass}>${item.jumlah_pasien}</td>
                            <td ${cellClass}>${item.jumlah_bacaan}</td>
                        </tr>
                    `;
                });

                const pageHtml = `
                    <div class="print-page">
                        <div class="print-header" style="display:block; text-align:center; margin-bottom:20px; padding-bottom:15px; border-bottom:3px solid #000;">
                            <div class="print-header-content" style="display:flex; align-items:center; justify-content:space-between;">
                                <img src="${logoSrc}" alt="Logo" class="print-logo" style="width:80px;">
                                <div class="print-title" style="flex:1; text-align:center;">
                                    <h3 style="font-size:16px; margin-bottom:5px; font-weight:bold;">PEMERINTAH KABUPATEN MERAUKE</h3>
                                    <h3 style="font-size:16px; margin-bottom:5px; font-weight:bold;">DINAS KESEHATAN</h3>
                                    <h2 style="font-size:18px; margin-bottom:5px; font-weight:bold;">RUMAH SAKIT UMUM DAERAH KELAS C</h2>
                                    <h2 style="font-size:18px; margin-bottom:5px; font-weight:bold;">MERAUKE</h2>
                                    <p style="font-size:12px; margin:2px 0;">Jalan Soekarno Wiryopranoto No. 1 - Kelurahan Karang Indah - Merauke</p>
                                    <p style="font-size:12px; margin:2px 0;">Pos-el: humasrsudmeraukegmail.com Kode Pos: 09614</p>
                                </div>
                                <img src="${logoSrc}" alt="Logo" class="print-logo" style="width:80px;">
                            </div>
                        </div>

                        <div class="report-info" style="margin:20px 0; font-size:13px;">
                            <table style="border:none; width:100%;">
                                <tr>
                                    <td style="width:150px; border:none; padding:3px 5px;"><strong>Kode Rumah Sakit</strong></td>
                                    <td style="width:20px; border:none; padding:3px 5px;">:</td>
                                    <td style="border:none; padding:3px 5px;">9201012</td>
                                </tr>
                                <tr>
                                    <td style="border:none; padding:3px 5px;"><strong>Nama Rumah Sakit</strong></td>
                                    <td style="border:none; padding:3px 5px;">:</td>
                                    <td style="border:none; padding:3px 5px;">RSUD Merauke</td>
                                </tr>
                                <tr>
                                    <td style="border:none; padding:3px 5px;"><strong>Periode Usulan</strong></td>
                                    <td style="border:none; padding:3px 5px;">:</td>
                                    <td style="border:none; padding:3px 5px;">${periodeText}</td>
                                </tr>
                                <tr>
                                    <td style="border:none; padding:3px 5px;"><strong>Nama Dokter</strong></td>
                                    <td style="border:none; padding:3px 5px;">:</td>
                                    <td style="border:none; padding:3px 5px;">${dokterName} (NIK: ${nik})</td>
                                </tr>
                            </table>
                        </div>

                        <h4 style="text-align:center; margin:20px 0; font-size:14px;">
                            DAFTAR NAMA USULAN TUNJANGAN KHUSUS DOKTER SPESIALIS DTPK
                        </h4>

                        <table class="dataTable" style="width:100%; border-collapse:collapse; margin-bottom:20px;">
                            <thead>
                                <tr>
                                    <th style="padding:12px 8px; border:1px solid #000; font-size:13px;">No</th>
                                    <th style="padding:12px 8px; border:1px solid #000; font-size:13px;">NIK Dokter</th>
                                    <th style="padding:12px 8px; border:1px solid #000; font-size:13px;">Nama</th>
                                    <th style="padding:12px 8px; border:1px solid #000; font-size:13px;">Tanggal Pelayanan</th>
                                    <th style="padding:12px 8px; border:1px solid #000; font-size:13px;">Jumlah Pasien</th>
                                    <th style="padding:12px 8px; border:1px solid #000; font-size:13px;">Jumlah Bacaan Penunjang</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${tbodyHtml}
                            </tbody>
                        </table>

                        <div class="signature-section" style="margin-top:50px; text-align:right; page-break-inside:avoid;">
                            <div class="signature-box" style="display:inline-block; text-align:center; min-width:200px;">
                                <p style="margin:5px 0; font-size:13px;">Merauke, ${ttdDate}</p>
                                <p style="margin:5px 0; font-size:13px;">Direktur RSUD Merauke</p>
                                <div class="signature-space" style="height:80px;"></div>
                                <p class="signature-name" style="margin:5px 0; font-size:13px; font-weight:bold; text-decoration:underline;">dr. DEWI WULANSARI.M.Sc</p>
                                <p style="margin:5px 0; font-size:13px;">NIP.19720706 200501 2 010</p>
                            </div>
                        </div>
                    </div>
                `;

                $printContainer.append(pageHtml);
            });

            $('#printPagesContainer').remove();
            $('body').append($printContainer);

            window.print();
        }

        // ================================================================
        // ON READY
        // ================================================================
        $(document).ready(function () {
            loadDokterList();
        });
    </script>
<?php require_once 'layouts/footer.php'; ?>