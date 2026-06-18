<?php
set_time_limit(600);
ini_set('memory_limit', '1024M');

require_once '../config/conf.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Settings;
use PhpOffice\PhpSpreadsheet\Collection\MemoryCompact;

if (class_exists(MemoryCompact::class)) {
    Settings::setCache(new MemoryCompact());
}

$koneksi = bukakoneksi();

$bulan     = $_GET['bulan'] ?? date('m');
$tahun     = $_GET['tahun'] ?? date('Y');
$kd_poli   = $_GET['kd_poli'] ?? '';
$kd_pj     = $_GET['kd_pj'] ?? '';
$tcari     = $_GET['tcari'] ?? '';
$filter_sep = $_GET['filter_sep'] ?? 'semua';

$bulan_padded = str_pad($bulan, 2, '0', STR_PAD_LEFT);
$tgl_awal  = "$tahun-$bulan_padded-01 00:00:00";
$tgl_akhir = date("Y-m-t 23:59:59", strtotime($tgl_awal));

$base = "
    FROM reg_periksa
    JOIN pasien ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis
    JOIN poliklinik ON reg_periksa.kd_poli = poliklinik.kd_poli
    JOIN penjab ON reg_periksa.kd_pj = penjab.kd_pj
    LEFT JOIN bridging_sep ON bridging_sep.no_rawat = reg_periksa.no_rawat
    LEFT JOIN rawat_jl_drpr ON rawat_jl_drpr.no_rawat = reg_periksa.no_rawat
    LEFT JOIN jns_perawatan ON rawat_jl_drpr.kd_jenis_prw = jns_perawatan.kd_jenis_prw
    LEFT JOIN dokter ON reg_periksa.kd_dokter = dokter.kd_dokter
    WHERE 1=1
    AND reg_periksa.status_lanjut = 'Ralan'
    AND reg_periksa.stts != 'Batal'
    AND reg_periksa.stts != 'Belum'
    AND NOT (
        reg_periksa.kd_poli = 'IGDK'
        AND EXISTS (
            SELECT 1 FROM kamar_inap ki WHERE ki.no_rawat = reg_periksa.no_rawat
        )
    )
";

$base .= " AND (
    CONCAT(reg_periksa.tgl_registrasi,' ',reg_periksa.jam_reg)
    BETWEEN '$tgl_awal' AND '$tgl_akhir'
    OR CONCAT(rawat_jl_drpr.tgl_perawatan,' ',rawat_jl_drpr.jam_rawat)
    BETWEEN '$tgl_awal' AND '$tgl_akhir'
)";

if (!empty($kd_poli)) {
    $base .= " AND reg_periksa.kd_poli = '$kd_poli'";
}

if (!empty($kd_pj)) {
    $base .= " AND reg_periksa.kd_pj = '$kd_pj'";
}

if ($filter_sep == 'ada') {
    $base .= " AND EXISTS (
        SELECT 1 FROM bridging_sep bs
        WHERE bs.no_rawat = reg_periksa.no_rawat
        AND bs.no_sep IS NOT NULL AND bs.no_sep != '' AND bs.no_sep != '-'
    )";
} elseif ($filter_sep == 'tidak_ada') {
    $base .= " AND NOT EXISTS (
        SELECT 1 FROM bridging_sep bs
        WHERE bs.no_rawat = reg_periksa.no_rawat
        AND bs.no_sep IS NOT NULL AND bs.no_sep != '' AND bs.no_sep != '-'
    )";
}

if (!empty($tcari)) {
    $c = mysqli_real_escape_string($koneksi, $tcari);
    $base .= " AND (
        reg_periksa.no_rawat LIKE '%$c%'
        OR pasien.nm_pasien LIKE '%$c%'
        OR dokter.nm_dokter LIKE '%$c%'
        OR reg_periksa.no_rkm_medis LIKE '%$c%'
        OR bridging_sep.no_sep LIKE '%$c%'
        OR poliklinik.nm_poli LIKE '%$c%'
    )";
}

// ─── helpers ───────────────────────────────────────────────────────────────────
function safeSheetName(string $name): string
{
    $n = preg_replace('/[\/\\\?\*\[\]\:\']+/', ' ', trim($name));
    return mb_substr($n, 0, 31);
}
function headerStyle(): array
{
    return [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10, 'name' => 'Arial'],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E79']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B8CCE4']]],
    ];
}
function cellStyle(): array
{
    return [
        'font' => ['size' => 9, 'name' => 'Arial'],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9D9D9']]],
    ];
}
function altRowStyle(): array
{
    return ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EBF3FF']]];
}

$headerRow = [
    'No',
    'No.Rawat',
    'No.SEP',
    'Total BPJS',
    '44%',
    'No.RM',
    'Pasien',
    'Poli',
    'Dokter',
    'Tgl',
    'Jasa Dokter',
    'Jasa Perawat',
    'Jasa Manajemen',
    'Total Jasa Tindakan',
    'Total Non Medis',
    'Jasa Farmasi',
    'Jasa Dokter Lab',
    'Jasa Petugas Lab',
    'Jasa Manajemen Lab',
    'Total Jasa Lab',
    'Jasa Dokter Rad',
    'Jasa Petugas Rad',
    'Jasa Manajemen Rad',
    'Total Jasa Rad',
    'TOTAL JASA',
    '%DPJP',
    'Jml DPJP',
    '%Perawat',
    'Jml Perawat',
    '%Farmasi',
    'Jml Farmasi',
    '%Dr Lab',
    'Jml Dr Lab',
    '%Analis Lab',
    'Jml Analis Lab',
    '%Dr Rad',
    'Jml Dr Rad',
    '%Radiografer',
    'Jml Radiografer',
    '%Non Medis',
    'Jml Non Medis',
];
$lastCol = Coordinate::stringFromColumnIndex(count($headerRow));

$spreadsheet = new Spreadsheet();
$spreadsheet->removeSheetByIndex(0);
$wsSheets = [];
$rowSheets = [];
$sheetIdx = 0;
$no = 1;
$rekap = []; // poli => [count, total_bpjs, jml_dpjp, jml_perawat, jml_farmasi, jml_dokter_lab, jml_analis_lab, jml_dokter_rad, jml_radiografer, jml_non_medis]

function getWs($spreadsheet, &$wsSheets, &$rowSheets, &$sheetIdx, $name, $header, $hStyle, $lastCol)
{
    $safe = safeSheetName($name);
    if (!isset($wsSheets[$safe])) {
        $ws = $spreadsheet->createSheet($sheetIdx++);
        $ws->setTitle($safe);
        $ws->fromArray([$header], null, 'A1');
        $ws->getStyle('A1:' . $lastCol . '1')->applyFromArray($hStyle);
        $ws->getRowDimension(1)->setRowHeight(28);
        $ws->freezePane('A2');
        $wsSheets[$safe] = $ws;
        $rowSheets[$safe] = 2;
    }
    return $wsSheets[$safe];
}

// ─── Fetch data in batches ────────────────────────────────────────────────────
$offset = 0;
$batch = 200;

while (true) {
    $id_q = mysqli_query($koneksi, "
        SELECT DISTINCT reg_periksa.no_rawat, poliklinik.nm_poli $base
        ORDER BY poliklinik.nm_poli, reg_periksa.tgl_registrasi DESC, reg_periksa.jam_reg DESC
        LIMIT $offset, $batch
    ");
    if (!$id_q || mysqli_num_rows($id_q) === 0) break;

    $ids = [];
    $poliMap = [];
    while ($r = mysqli_fetch_assoc($id_q)) {
        $ids[] = "'" . mysqli_real_escape_string($koneksi, $r['no_rawat']) . "'";
        $poliMap[$r['no_rawat']] = $r['nm_poli'];
    }
    $in = implode(',', $ids);

    $q = mysqli_query($koneksi, "SELECT
        reg_periksa.no_rawat,
        reg_periksa.no_rkm_medis,
        reg_periksa.tgl_registrasi,
        pasien.nm_pasien,
        IFNULL(bridging_sep.no_sep, '-') AS no_sep,
        poliklinik.nm_poli,
        MIN(dokter.nm_dokter) AS nm_dokter,
        IFNULL(SUM(jns_perawatan.tarif_tindakandr), 0) AS total_tindakan_dr,
        IFNULL(SUM(jns_perawatan.tarif_tindakanpr), 0) AS total_tindakan_pr,
        IFNULL(SUM(jns_perawatan.menejemen), 0) AS total_menejemen_tindakan,
        IFNULL(SUM(jns_perawatan.total_byrdrpr), 0) AS total_biaya_rawat
    $base
    AND reg_periksa.no_rawat IN ($in)
    GROUP BY
        reg_periksa.no_rawat,
        reg_periksa.no_rkm_medis,
        reg_periksa.tgl_registrasi,
        pasien.nm_pasien,
        bridging_sep.no_sep,
        poliklinik.nm_poli,
        reg_periksa.kd_dokter
    ORDER BY poliklinik.nm_poli, reg_periksa.tgl_registrasi DESC, reg_periksa.jam_reg DESC
    ");

    if (!$q) break;

    // ─── bulk lab ──────────────────────────────────────────────────────────────
    $labMap = [];
    $lr = mysqli_query($koneksi, "SELECT no_rawat,
        SUM(IFNULL(jns_perawatan_lab.tarif_tindakan_dokter,0)) AS total_dokter_lab,
        SUM(IFNULL(jns_perawatan_lab.tarif_tindakan_petugas,0)) AS total_petugas_lab,
        SUM(IFNULL(jns_perawatan_lab.menejemen,0)) AS total_menejemen_lab
        FROM periksa_lab JOIN jns_perawatan_lab ON periksa_lab.kd_jenis_prw = jns_perawatan_lab.kd_jenis_prw
        WHERE periksa_lab.no_rawat IN ($in) GROUP BY no_rawat");
    if ($lr) while ($l = mysqli_fetch_assoc($lr)) $labMap[$l['no_rawat']] = $l;

    // ─── bulk rad ──────────────────────────────────────────────────────────────
    $radMap = [];
    $rr = mysqli_query($koneksi, "SELECT t1.no_rawat,
        COALESCE(SUM(t2.tarif_tindakan_dokter),0) AS total_dokter_radiologi,
        COALESCE(SUM(t2.tarif_tindakan_petugas),0) AS total_petugas_radiologi,
        COALESCE(SUM(t2.menejemen),0) AS total_menejemen_radiologi
        FROM permintaan_radiologi t1
        JOIN permintaan_pemeriksaan_radiologi t3 ON t1.noorder = t3.noorder
        JOIN jns_perawatan_radiologi t2 ON t3.kd_jenis_prw = t2.kd_jenis_prw
        WHERE t1.no_rawat IN ($in) AND t1.status = 'ralan' GROUP BY t1.no_rawat");
    if ($rr) while ($r = mysqli_fetch_assoc($rr)) $radMap[$r['no_rawat']] = $r;

    // ─── bulk obat ─────────────────────────────────────────────────────────────
    $obatMap = [];
    $or = mysqli_query($koneksi, "SELECT no_rawat, SUM(IFNULL(total,0)) AS total_obat
        FROM detail_pemberian_obat WHERE no_rawat IN ($in) AND status = 'Ralan' GROUP BY no_rawat");
    if ($or) while ($o = mysqli_fetch_assoc($or)) $obatMap[$o['no_rawat']] = floatval($o['total_obat']);

    // ─── bulk resep ────────────────────────────────────────────────────────────
    $resepMap = [];
    $rsr = mysqli_query($koneksi, "SELECT no_rawat, no_resep FROM resep_obat
        WHERE no_rawat IN ($in) AND tgl_perawatan != '0000-00-00' AND status = 'ralan'");
    $resepIds = [];
    if ($rsr) while ($rs = mysqli_fetch_assoc($rsr)) {
        $resepMap[$rs['no_rawat']][] = $rs['no_resep'];
        $resepIds[] = "'" . mysqli_real_escape_string($koneksi, $rs['no_resep']) . "'";
    }

    $racikanSet = [];
    $nonRacikanSet = [];
    if (!empty($resepIds)) {
        $rin = implode(',', $resepIds);
        $crr = mysqli_query($koneksi, "SELECT DISTINCT no_resep FROM resep_dokter_racikan WHERE no_resep IN ($rin)");
        if ($crr) while ($c = mysqli_fetch_assoc($crr)) $racikanSet[$c['no_resep']] = true;
        $cnr = mysqli_query($koneksi, "SELECT DISTINCT no_resep FROM resep_dokter WHERE no_resep IN ($rin)");
        if ($cnr) while ($c = mysqli_fetch_assoc($cnr)) $nonRacikanSet[$c['no_resep']] = true;
    }

    // ─── bpjs lookup ───────────────────────────────────────────────────────────
    if (!isset($bpjs_lookup)) {
        $bpjs_lookup = [];
        $bpjs_result = mysqli_query($koneksi, "
            SELECT data FROM bpjs_verifikasi
            ORDER BY created_at DESC
        ");
        while ($brow = mysqli_fetch_assoc($bpjs_result)) {
            $drows = json_decode($brow['data'], true);
            if (is_array($drows)) {
                foreach ($drows as $r) {
                    if (!empty($r['no_sep'])) {
                        $bpjs_lookup[$r['no_sep']] = floatval($r['disetujui'] ?? 0);
                    }
                }
            }
        }
    }

    // ─── process rows ──────────────────────────────────────────────────────────
    mysqli_data_seek($q, 0);
    while ($row = mysqli_fetch_assoc($q)) {
        $nr = $row['no_rawat'];
        $row['jasa_tindakan'] = $row['total_tindakan_dr'] + $row['total_tindakan_pr'] + $row['total_menejemen_tindakan'];

        $ld = $labMap[$nr] ?? null;
        $row['total_dokter_lab'] = floatval($ld['total_dokter_lab'] ?? 0);
        $row['total_petugas_lab'] = floatval($ld['total_petugas_lab'] ?? 0);
        $row['total_menejemen_lab'] = floatval($ld['total_menejemen_lab'] ?? 0);
        $row['jasa_lab'] = $row['total_dokter_lab'] + $row['total_petugas_lab'] + $row['total_menejemen_lab'];

        $rd = $radMap[$nr] ?? null;
        $row['total_dokter_radiologi'] = floatval($rd['total_dokter_radiologi'] ?? 0);
        $row['total_petugas_radiologi'] = floatval($rd['total_petugas_radiologi'] ?? 0);
        $row['total_menejemen_radiologi'] = floatval($rd['total_menejemen_radiologi'] ?? 0);
        $row['jasa_radiologi'] = $row['total_dokter_radiologi'] + $row['total_petugas_radiologi'] + $row['total_menejemen_radiologi'];

        $row['total_obat'] = $obatMap[$nr] ?? 0;

        $reseps = $resepMap[$nr] ?? [];
        $total_racikan = 0;
        $total_non_racikan = 0;
        $total_resep_operasi = 0;
        foreach ($reseps as $no_resep) {
            if (substr($no_resep, 0, 2) === 'OK') {
                $total_resep_operasi++;
            } elseif (isset($racikanSet[$no_resep])) {
                $total_racikan++;
            } elseif (isset($nonRacikanSet[$no_resep])) {
                $total_non_racikan++;
            }
        }
        $jasa_obat = 0;
        if ($total_racikan > 0) $jasa_obat = 25000;
        elseif ($total_non_racikan > 0) $jasa_obat = 15000;
        $row['jasa_farmasi'] = $jasa_obat + ($total_resep_operasi > 0 ? 35000 : 0);

        $row['total_non_medis'] = $row['total_menejemen_tindakan'] + $row['total_menejemen_lab'] + $row['total_menejemen_radiologi'];
        $row['total_jasa'] = $row['jasa_tindakan'] + $row['jasa_farmasi'] + $row['jasa_lab'] + $row['jasa_radiologi'];

        $tj = $row['total_jasa'];
        $pct = fn($v) => $tj > 0 ? round($v / $tj * 100, 2)  : '0';

        $total_bpjs = $bpjs_lookup[$row['no_sep']] ?? 0;
        $kolom_44 = $total_bpjs * 0.44;
        $tb44 = $kolom_44;
        $jml_dpjp = $tb44 > 0 ? round($pct($row['total_tindakan_dr']) / 100 * $tb44) : 0;
        $jml_perawat = $tb44 > 0 ? round($pct($row['total_tindakan_pr']) / 100 * $tb44) : 0;
        $jml_farmasi = $tb44 > 0 ? round($pct($row['jasa_farmasi']) / 100 * $tb44) : 0;
        $jml_dokter_lab = $tb44 > 0 ? round($pct($row['total_dokter_lab']) / 100 * $tb44) : 0;
        $jml_analis_lab = $tb44 > 0 ? round($pct($row['total_petugas_lab']) / 100 * $tb44) : 0;
        $jml_dokter_rad = $tb44 > 0 ? round($pct($row['total_dokter_radiologi']) / 100 * $tb44) : 0;
        $jml_radiografer = $tb44 > 0 ? round($pct($row['total_petugas_radiologi']) / 100 * $tb44) : 0;
        $jml_non_medis = $tb44 > 0 ? round($pct($row['total_non_medis']) / 100 * $tb44) : 0;

        $finalRow = [
            $no,
            $row['no_rawat'],
            $row['no_sep'],
            $total_bpjs,
            $kolom_44,
            $row['no_rkm_medis'],
            $row['nm_pasien'],
            $row['nm_poli'],
            $row['nm_dokter'] ?: 'TANPA DOKTER',
            $row['tgl_registrasi'],
            $row['total_tindakan_dr'],
            $row['total_tindakan_pr'],
            $row['total_menejemen_tindakan'],
            $row['jasa_tindakan'],
            $row['total_non_medis'],
            $row['jasa_farmasi'],
            $row['total_dokter_lab'],
            $row['total_petugas_lab'],
            $row['total_menejemen_lab'],
            $row['jasa_lab'],
            $row['total_dokter_radiologi'],
            $row['total_petugas_radiologi'],
            $row['total_menejemen_radiologi'],
            $row['jasa_radiologi'],
            $row['total_jasa'],
            $pct($row['total_tindakan_dr']),
            $jml_dpjp,
            $pct($row['total_tindakan_pr']),
            $jml_perawat,
            $pct($row['jasa_farmasi']),
            $jml_farmasi,
            $pct($row['total_dokter_lab']),
            $jml_dokter_lab,
            $pct($row['total_petugas_lab']),
            $jml_analis_lab,
            $pct($row['total_dokter_radiologi']),
            $jml_dokter_rad,
            $pct($row['total_petugas_radiologi']),
            $jml_radiografer,
            $pct($row['total_non_medis']),
            $jml_non_medis,
        ];

        $poliKey = $row['nm_poli'] ?: 'TANPA POLI';
        if (!isset($rekap[$poliKey])) {
            $rekap[$poliKey] = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
        }
        $rekap[$poliKey][0]++;
        $rekap[$poliKey][1] += $total_bpjs;
        $rekap[$poliKey][2] += $kolom_44;
        $rekap[$poliKey][3] += $jml_dpjp;
        $rekap[$poliKey][4] += $jml_perawat;
        $rekap[$poliKey][5] += $jml_farmasi;
        $rekap[$poliKey][6] += $jml_dokter_lab;
        $rekap[$poliKey][7] += $jml_analis_lab;
        $rekap[$poliKey][8] += $jml_dokter_rad;
        $rekap[$poliKey][9] += $jml_radiografer;
        $rekap[$poliKey][10] += $jml_non_medis;

        $ws = getWs($spreadsheet, $wsSheets, $rowSheets, $sheetIdx, $poliKey, $headerRow, headerStyle(), $lastCol);
        $ws->fromArray([$finalRow], null, 'A' . $rowSheets[safeSheetName($poliKey)]);
        $range = 'A' . $rowSheets[safeSheetName($poliKey)] . ':' . $lastCol . $rowSheets[safeSheetName($poliKey)];
        $ws->getStyle($range)->applyFromArray(cellStyle());
        if ($rowSheets[safeSheetName($poliKey)] % 2 === 0) {
            $ws->getStyle($range)->applyFromArray(altRowStyle());
        }
        $rowSheets[safeSheetName($poliKey)]++;
        $no++;
    }
    unset($q, $ids, $id_q, $labMap, $radMap, $obatMap, $resepMap, $resepIds, $racikanSet, $nonRacikanSet);
    $offset += $batch;
}

// ─── Rekap Per Poli sheet ───────────────────────────────────────────────────
if (!empty($rekap)) {
    $rekapHeader = [
        'Poliklinik',
        'Jumlah Pasien',
        'Total BPJS',
        '44%',
        'Sisa BPJS',
        'Jml DPJP',
        'Jml Perawat',
        'Jml Farmasi',
        'Jml Dr Lab',
        'Jml Analis Lab',
        'Jml Dr Rad',
        'Jml Radiografer',
        'Jml Non Medis'
    ];
    $wsRekap = $spreadsheet->createSheet($sheetIdx++);
    $wsRekap->setTitle('Rekap Per Poli');
    $wsRekap->fromArray([$rekapHeader], null, 'A1');
    $lastRekapCol = Coordinate::stringFromColumnIndex(count($rekapHeader));
    $wsRekap->getStyle('A1:' . $lastRekapCol . '1')->applyFromArray(headerStyle());
    $wsRekap->getRowDimension(1)->setRowHeight(28);
    $wsRekap->freezePane('A2');

    $r = 2;
    foreach ($rekap as $poli => $v) {
        $wsRekap->fromArray([array_merge([$poli, $v[0], $v[1], $v[2]], [$v[1] - $v[2]], array_slice($v, 3))], null, 'A' . $r);
        $range = 'A' . $r . ':' . $lastRekapCol . $r;
        $wsRekap->getStyle($range)->applyFromArray(cellStyle());
        if ($r % 2 === 0) {
            $wsRekap->getStyle($range)->applyFromArray(altRowStyle());
        }
        $r++;
    }

    for ($c = 1; $c <= count($rekapHeader); $c++) {
        $wsRekap->getColumnDimensionByColumn($c)->setAutoSize(true);
    }
}

if ($sheetIdx === 0) {
    $ws = $spreadsheet->createSheet(0);
    $ws->setTitle('TIDAK ADA DATA');
    $ws->setCellValue('A1', 'Tidak ada data.');
} else {
    foreach ($wsSheets as $ws) {
        $lastR = $rowSheets[$ws->getTitle()] ?? $ws->getHighestRow();
        if ($lastR > 1) {
            $ws->setAutoFilter('A1:' . $lastCol . '1');
        }
        for ($c = 1; $c <= count($headerRow); $c++) {
            $ws->getColumnDimensionByColumn($c)->setAutoSize(true);
        }
    }
}

$spreadsheet->setActiveSheetIndex(0);
$filename = 'hitung_jasa_ralan_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

(new Xlsx($spreadsheet))->save('php://output');
$spreadsheet->disconnectWorksheets();
unset($spreadsheet);
mysqli_close($koneksi);
exit;
