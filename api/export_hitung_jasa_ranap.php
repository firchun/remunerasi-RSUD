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
$grup_bangsal = $_GET['grup_bangsal'] ?? '';
$kd_pj     = $_GET['kd_pj'] ?? '';
$tcari     = $_GET['tcari'] ?? '';
$filter_sep = $_GET['filter_sep'] ?? 'semua';
$status_pulang = $_GET['status_pulang'] ?? 'semua';

$bulan_padded = str_pad($bulan, 2, '0', STR_PAD_LEFT);
$tgl_awal  = "$tahun-$bulan_padded-01 00:00:00";
$tgl_akhir = date("Y-m-t 23:59:59", strtotime($tgl_awal));

$base = "
    FROM reg_periksa
    JOIN pasien ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis
    JOIN penjab ON reg_periksa.kd_pj = penjab.kd_pj
    LEFT JOIN bridging_sep ON bridging_sep.no_rawat = reg_periksa.no_rawat
    LEFT JOIN rawat_inap_drpr ON rawat_inap_drpr.no_rawat = reg_periksa.no_rawat
        AND rawat_inap_drpr.kd_jenis_prw NOT IN ('RI01330','RI01331','RI01332','RI01337','RI00267','RI000276','RI00345','RI00751','RI01314','RI01315','RI01316','RI01317','RI01306','RI01307','RI01308','RI01309','RI00724','RI01918','RI01326','RI01327','RI01328','RI01329','RI00805','RI01373','RI01374','RI01375','RI01376','RI01365','RI01366','RI01367','RI01368','RI00778','RI01396','RI01385','RI01386','RI01387','RI01388')
    LEFT JOIN jns_perawatan_inap ON rawat_inap_drpr.kd_jenis_prw = jns_perawatan_inap.kd_jenis_prw
    LEFT JOIN dokter ON reg_periksa.kd_dokter = dokter.kd_dokter
    LEFT JOIN kamar_inap ON kamar_inap.no_rawat = reg_periksa.no_rawat AND kamar_inap.stts_pulang != 'Pindah Kamar'
    LEFT JOIN kamar ON kamar_inap.kd_kamar = kamar.kd_kamar
    LEFT JOIN bangsal ON kamar.kd_bangsal = bangsal.kd_bangsal
    LEFT JOIN v_bangsal_grup ON bangsal.kd_bangsal = v_bangsal_grup.kd_bangsal
    WHERE 1=1
    AND reg_periksa.status_lanjut = 'Ranap'
    AND reg_periksa.stts != 'Batal'
    AND reg_periksa.stts != 'Belum'
";

$base .= " AND (
    CONCAT(kamar_inap.tgl_keluar, ' ', kamar_inap.jam_keluar)
    BETWEEN '$tgl_awal' AND '$tgl_akhir'
)";

if (!empty($grup_bangsal)) {
    $base .= " AND bangsal.kd_bangsal IN (SELECT kd_bangsal FROM v_bangsal_grup WHERE grup_bangsal = '$grup_bangsal')";
}

if (!empty($kd_pj)) {
    $base .= " AND reg_periksa.kd_pj = '$kd_pj'";
}

if ($status_pulang === 'sudah_pulang') {
    $base .= " AND kamar_inap.stts_pulang != '-'";
} elseif ($status_pulang === 'belum_pulang') {
    $base .= " AND kamar_inap.stts_pulang = '-'";
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
        OR bangsal.nm_bangsal LIKE '%$c%'
    )";
}

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
    'Sisa BPJS',
    'No.RM',
    'Pasien',
    'Bangsal',
    'DPJP',
    'Tgl Masuk',
    'Lama',
    'Status Pulang',
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
$rekap = [];
$selisih = [];
$pasien_gagal_compare = [];
$used_seps = [];

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

$offset = 0;
$batch = 200;

while (true) {
    $id_q = mysqli_query($koneksi, "
        SELECT DISTINCT reg_periksa.no_rawat, COALESCE(v_bangsal_grup.grup_bangsal, bangsal.nm_bangsal, 'TANPA BANGSAL') AS nm_bangsal $base
        ORDER BY COALESCE(v_bangsal_grup.grup_bangsal, bangsal.nm_bangsal), kamar_inap.tgl_masuk DESC
        LIMIT $offset, $batch
    ");
    if (!$id_q || mysqli_num_rows($id_q) === 0) break;

    $ids = [];
    $bangsalMap = [];
    while ($r = mysqli_fetch_assoc($id_q)) {
        $ids[] = "'" . mysqli_real_escape_string($koneksi, $r['no_rawat']) . "'";
        $bangsalMap[$r['no_rawat']] = $r['nm_bangsal'];
    }
    $in = implode(',', $ids);

    $q = mysqli_query($koneksi, "SELECT
        reg_periksa.no_rawat,
        reg_periksa.no_rkm_medis,
        reg_periksa.tgl_registrasi,
        pasien.nm_pasien,
        IFNULL(bridging_sep.no_sep, '-') AS no_sep,
        COALESCE(v_bangsal_grup.grup_bangsal, bangsal.nm_bangsal) AS nm_bangsal,
        MIN(dokter.nm_dokter) AS nm_dokter,
        kamar_inap.tgl_masuk,
        kamar_inap.lama,
        kamar_inap.stts_pulang,
        IFNULL(SUM(jns_perawatan_inap.tarif_tindakandr), 0) AS total_tindakan_dr,
        IFNULL(SUM(jns_perawatan_inap.tarif_tindakanpr), 0) AS total_tindakan_pr,
        IFNULL(SUM(jns_perawatan_inap.menejemen), 0) AS total_menejemen_tindakan,
        IFNULL(SUM(jns_perawatan_inap.total_byrdrpr), 0) AS total_biaya_rawat
    $base
    AND reg_periksa.no_rawat IN ($in)
    GROUP BY
        reg_periksa.no_rawat,
        reg_periksa.no_rkm_medis,
        reg_periksa.tgl_registrasi,
        pasien.nm_pasien,
        bridging_sep.no_sep,
        COALESCE(v_bangsal_grup.grup_bangsal, bangsal.nm_bangsal),
        reg_periksa.kd_dokter,
        kamar_inap.tgl_masuk,
        kamar_inap.lama,
        kamar_inap.stts_pulang
    ORDER BY COALESCE(v_bangsal_grup.grup_bangsal, bangsal.nm_bangsal), kamar_inap.tgl_masuk DESC
    ");

    if (!$q) break;

    $labMap = [];
    $lr = mysqli_query($koneksi, "SELECT no_rawat,
        SUM(IFNULL(jns_perawatan_lab.tarif_tindakan_dokter,0)) AS total_dokter_lab,
        SUM(IFNULL(jns_perawatan_lab.tarif_tindakan_petugas,0)) AS total_petugas_lab,
        SUM(IFNULL(jns_perawatan_lab.menejemen,0)) AS total_menejemen_lab
        FROM periksa_lab JOIN jns_perawatan_lab ON periksa_lab.kd_jenis_prw = jns_perawatan_lab.kd_jenis_prw
        WHERE periksa_lab.no_rawat IN ($in) AND periksa_lab.status = 'Ranap' GROUP BY no_rawat");
    if ($lr) while ($l = mysqli_fetch_assoc($lr)) $labMap[$l['no_rawat']] = $l;

    $radMap = [];
    $rr = mysqli_query($koneksi, "SELECT t1.no_rawat,
        COALESCE(SUM(t2.tarif_tindakan_dokter),0) AS total_dokter_radiologi,
        COALESCE(SUM(t2.tarif_tindakan_petugas),0) AS total_petugas_radiologi,
        COALESCE(SUM(t2.menejemen),0) AS total_menejemen_radiologi
        FROM permintaan_radiologi t1
        JOIN permintaan_pemeriksaan_radiologi t3 ON t1.noorder = t3.noorder
        JOIN jns_perawatan_radiologi t2 ON t3.kd_jenis_prw = t2.kd_jenis_prw
        WHERE t1.no_rawat IN ($in) GROUP BY t1.no_rawat");
    if ($rr) while ($r = mysqli_fetch_assoc($rr)) $radMap[$r['no_rawat']] = $r;

    $obatMap = [];
    $or = mysqli_query($koneksi, "SELECT no_rawat, SUM(IFNULL(total,0)) AS total_obat
        FROM detail_pemberian_obat WHERE no_rawat IN ($in) AND status = 'Ranap' GROUP BY no_rawat");
    if ($or) while ($o = mysqli_fetch_assoc($or)) $obatMap[$o['no_rawat']] = floatval($o['total_obat']);

    $resepMap = [];
    $rsr = mysqli_query($koneksi, "SELECT no_rawat, no_resep FROM resep_obat
        WHERE no_rawat IN ($in) AND tgl_perawatan != '0000-00-00' AND status = 'ranap'");
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

    if (!isset($bpjs_lookup)) {
        $bpjs_lookup = [];
        $bulan_int = (int)$bulan;
        $bpjs_result = mysqli_query($koneksi, "
            SELECT data FROM bpjs_verifikasi
            WHERE bulan = '$bulan_int' AND tahun = '$tahun' AND jenis = 'ranap'
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
        $sisa_bpjs = $total_bpjs - $kolom_44;
        $jml_dpjp = $tb44 > 0 ? round($pct($row['total_tindakan_dr']) / 100 * $tb44) : 0;
        $jml_perawat = $tb44 > 0 ? round($pct($row['total_tindakan_pr']) / 100 * $tb44) : 0;
        $jml_farmasi = $tb44 > 0 ? round($pct($row['jasa_farmasi']) / 100 * $tb44) : 0;
        $jml_dokter_lab = $tb44 > 0 ? round($pct($row['total_dokter_lab']) / 100 * $tb44) : 0;
        $jml_analis_lab = $tb44 > 0 ? round($pct($row['total_petugas_lab']) / 100 * $tb44) : 0;
        $jml_dokter_rad = $tb44 > 0 ? round($pct($row['total_dokter_radiologi']) / 100 * $tb44) : 0;
        $jml_radiografer = $tb44 > 0 ? round($pct($row['total_petugas_radiologi']) / 100 * $tb44) : 0;
        $jml_non_medis = $tb44 > 0 ? round($pct($row['total_non_medis']) / 100 * $tb44) : 0;

        $stts_pulang = '-';
        if ($row['stts_pulang'] === '-') {
            $stts_pulang = 'Belum Pulang';
        } elseif (!empty($row['stts_pulang'])) {
            $stts_pulang = 'Pulang (' . $row['stts_pulang'] . ')';
        }

        $finalRow = [
            $no,
            $row['no_rawat'],
            $row['no_sep'],
            $total_bpjs,
            $kolom_44,
            $sisa_bpjs,
            $row['no_rkm_medis'],
            $row['nm_pasien'],
            $row['nm_bangsal'] ?: 'TANPA BANGSAL',
            $row['nm_dokter'] ?: 'TANPA DPJP',
            $row['tgl_masuk'],
            $row['lama'],
            $stts_pulang,
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

        $bangsalKey = $row['nm_bangsal'] ?: 'TANPA BANGSAL';
        if (!isset($rekap[$bangsalKey])) {
            $rekap[$bangsalKey] = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
        }
        $rekap[$bangsalKey][0]++;
        $rekap[$bangsalKey][1] += $total_bpjs;
        $rekap[$bangsalKey][2] += $kolom_44;
        $rekap[$bangsalKey][3] += $sisa_bpjs;
        $rekap[$bangsalKey][4] += $jml_dpjp;
        $rekap[$bangsalKey][5] += $jml_perawat;
        $rekap[$bangsalKey][6] += $jml_farmasi;
        $rekap[$bangsalKey][7] += $jml_dokter_lab;
        $rekap[$bangsalKey][8] += $jml_analis_lab;
        $rekap[$bangsalKey][9] += $jml_dokter_rad;
        $rekap[$bangsalKey][10] += $jml_radiografer;
        $rekap[$bangsalKey][11] += $jml_non_medis;

        if (!isset($selisih[$bangsalKey])) {
            $selisih[$bangsalKey] = [
                'total' => 0, 
                'berhasil' => 0,
                'tidak_berhasil' => 0,
                'sisa_bpjs' => 0,
                'sisa_rs' => 0
            ];
        }
        $selisih[$bangsalKey]['total']++;
        $punya_sep = ($row['no_sep'] !== '-' && !empty($row['no_sep']));
        
        if ($punya_sep && isset($bpjs_lookup[$row['no_sep']])) {
            $selisih[$bangsalKey]['berhasil']++;
            $used_seps[$row['no_sep']] = true;
        } else if ($punya_sep && !isset($bpjs_lookup[$row['no_sep']])) {
            $selisih[$bangsalKey]['tidak_berhasil']++;
            $selisih[$bangsalKey]['sisa_bpjs'] += $total_bpjs;
            $selisih[$bangsalKey]['sisa_rs'] += $row['total_jasa'];
            $pasien_gagal_compare[] = $finalRow;
        }

        $ws = getWs($spreadsheet, $wsSheets, $rowSheets, $sheetIdx, $bangsalKey, $headerRow, headerStyle(), $lastCol);
        $ws->fromArray([$finalRow], null, 'A' . $rowSheets[safeSheetName($bangsalKey)]);
        $range = 'A' . $rowSheets[safeSheetName($bangsalKey)] . ':' . $lastCol . $rowSheets[safeSheetName($bangsalKey)];
        $ws->getStyle($range)->applyFromArray(cellStyle());
        if ($rowSheets[safeSheetName($bangsalKey)] % 2 === 0) {
            $ws->getStyle($range)->applyFromArray(altRowStyle());
        }
        $rowSheets[safeSheetName($bangsalKey)]++;
        $no++;
    }
    unset($q, $ids, $id_q, $labMap, $radMap, $obatMap, $resepMap, $resepIds, $racikanSet, $nonRacikanSet);
    $offset += $batch;
}

// ─── Fetch This Month's BPJS for Gagal Compare ─────────────────────────────
$bpjs_this_month = [];
$bulan_int = (int)$bulan;
$bpjs_res_month = mysqli_query($koneksi, "SELECT data FROM bpjs_verifikasi WHERE bulan = '$bulan_int' AND tahun = '$tahun' AND jenis = 'ranap' ORDER BY created_at DESC");
if ($bpjs_res_month) {
    while ($bm = mysqli_fetch_assoc($bpjs_res_month)) {
        $m_rows = json_decode($bm['data'], true);
        if (is_array($m_rows)) {
            foreach ($m_rows as $r) {
                if (!empty($r['no_sep'])) {
                    $bpjs_this_month[$r['no_sep']] = $r;
                }
            }
        }
    }
}

$sep_gagal_compare = [];
$no_sep_gagal = 1;
foreach ($bpjs_this_month as $sep => $r) {
    if (!isset($used_seps[$sep]) && floatval($r['disetujui'] ?? 0) > 0) {
        $sep_gagal_compare[] = [
            $no_sep_gagal++,
            $sep,
            floatval($r['disetujui'] ?? 0)
        ];
    }
}

if (!empty($rekap)) {
    $rekapHeader = [
        'Bangsal', 'Jumlah Pasien', 'Total BPJS', '44%', 'Sisa BPJS',
        'Jml DPJP', 'Jml Perawat', 'Jml Farmasi',
        'Jml Dr Lab', 'Jml Analis Lab', 'Jml Dr Rad',
        'Jml Radiografer', 'Jml Non Medis'
    ];
    $wsRekap = $spreadsheet->createSheet($sheetIdx++);
    $wsRekap->setTitle('Rekap Per Bangsal');
    $wsRekap->fromArray([$rekapHeader], null, 'A1');
    $lastRekapCol = Coordinate::stringFromColumnIndex(count($rekapHeader));
    $wsRekap->getStyle('A1:' . $lastRekapCol . '1')->applyFromArray(headerStyle());
    $wsRekap->getRowDimension(1)->setRowHeight(28);
    $wsRekap->freezePane('A2');

    $r = 2;
    foreach ($rekap as $bangsal => $v) {
        $wsRekap->fromArray([array_merge([$bangsal, $v[0], $v[1], $v[2]], [$v[3]], array_slice($v, 4))], null, 'A' . $r);
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

// ─── Selisih sheet ───────────────────────────────────────────────────────────
if (!empty($selisih)) {
    $selisihHeader = [
        'Bangsal',
        'Total Pasien',
        'Berhasil Di Compare',
        'Tidak Berhasil Di Compare',
        'Sisa BPJS',
        'Sisa Jasa RS'
    ];
    $wsSelisih = $spreadsheet->createSheet($sheetIdx++);
    $wsSelisih->setTitle('Selisih');
    $wsSelisih->fromArray([$selisihHeader], null, 'A1');
    $lastSelisihCol = Coordinate::stringFromColumnIndex(count($selisihHeader));
    $wsSelisih->getStyle('A1:' . $lastSelisihCol . '1')->applyFromArray(headerStyle());
    $wsSelisih->getRowDimension(1)->setRowHeight(28);
    $wsSelisih->freezePane('A2');

    $r = 2;
    foreach ($selisih as $bangsal => $v) {
        $rowSelisih = [
            $bangsal, 
            $v['total'], 
            $v['berhasil'],
            $v['tidak_berhasil'],
            $v['sisa_bpjs'],
            $v['sisa_rs']
        ];
        $wsSelisih->fromArray([$rowSelisih], null, 'A' . $r);
        $range = 'A' . $r . ':' . $lastSelisihCol . $r;
        $wsSelisih->getStyle($range)->applyFromArray(cellStyle());
        if ($r % 2 === 0) {
            $wsSelisih->getStyle($range)->applyFromArray(altRowStyle());
        }
        $r++;
    }

    for ($c = 1; $c <= count($selisihHeader); $c++) {
        $wsSelisih->getColumnDimensionByColumn($c)->setAutoSize(true);
    }
}

// ─── Daftar Pasien Gagal Kompare ─────────────────────────────────────────────
if (!empty($pasien_gagal_compare)) {
    $wsGagalPasien = $spreadsheet->createSheet($sheetIdx++);
    $wsGagalPasien->setTitle('Pasien Gagal Kompare');
    $wsGagalPasien->fromArray([$headerRow], null, 'A1');
    $wsGagalPasien->getStyle('A1:' . $lastCol . '1')->applyFromArray(headerStyle());
    $wsGagalPasien->getRowDimension(1)->setRowHeight(28);
    $wsGagalPasien->freezePane('A2');

    $r = 2;
    foreach ($pasien_gagal_compare as $rowGagal) {
        $wsGagalPasien->fromArray([$rowGagal], null, 'A' . $r);
        $range = 'A' . $r . ':' . $lastCol . $r;
        $wsGagalPasien->getStyle($range)->applyFromArray(cellStyle());
        if ($r % 2 === 0) {
            $wsGagalPasien->getStyle($range)->applyFromArray(altRowStyle());
        }
        $r++;
    }

    for ($c = 1; $c <= count($headerRow); $c++) {
        $wsGagalPasien->getColumnDimensionByColumn($c)->setAutoSize(true);
    }
}

// ─── Daftar SEP BPJS Gagal Kompare ───────────────────────────────────────────
if (!empty($sep_gagal_compare)) {
    $bpjsHeader = ['No.', 'No. SEP', 'Nominal BPJS'];
    $wsGagalSep = $spreadsheet->createSheet($sheetIdx++);
    $wsGagalSep->setTitle('Sisa BPJS');
    $wsGagalSep->fromArray([$bpjsHeader], null, 'A1');
    $lastBpjsCol = Coordinate::stringFromColumnIndex(count($bpjsHeader));
    $wsGagalSep->getStyle('A1:' . $lastBpjsCol . '1')->applyFromArray(headerStyle());
    $wsGagalSep->getRowDimension(1)->setRowHeight(28);
    $wsGagalSep->freezePane('A2');

    $r = 2;
    foreach ($sep_gagal_compare as $rowSep) {
        $wsGagalSep->fromArray([$rowSep], null, 'A' . $r);
        $range = 'A' . $r . ':' . $lastBpjsCol . $r;
        $wsGagalSep->getStyle($range)->applyFromArray(cellStyle());
        if ($r % 2 === 0) {
            $wsGagalSep->getStyle($range)->applyFromArray(altRowStyle());
        }
        $r++;
    }

    for ($c = 1; $c <= count($bpjsHeader); $c++) {
        $wsGagalSep->getColumnDimensionByColumn($c)->setAutoSize(true);
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
$filename = 'hitung_jasa_ranap_' . date('Ymd_His') . '.xlsx';
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
