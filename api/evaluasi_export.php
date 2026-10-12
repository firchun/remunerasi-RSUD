<?php

/**
 * Export Evaluasi → Multi-Sheet XLSX
 *
 * Ralan : sheet per POLIKLINIK
 * Ranap : sheet per GRUP BANGSAL (dari v_bangsal_grup)
 *
 * Columns (ralan):
 *   No, No Rawat, Nama Pasien, No RM, Poli, Diagnosa, Nilai Tindakan,
 *   Harga Obat, No SEP, DPJP, Klaim BPJS, Penggunaan Obat, Anfrak BHP, Keterangan
 *
 * Columns (ranap):
 *   (sama) + Lama Perawatan, Tanggal Masuk, Tanggal Keluar
 */

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

$koneksi = bukakoneksi();

$bulanAwal    = intval($_GET['bulan_awal'] ?? 1);
$bulanAkhir   = intval($_GET['bulan_akhir'] ?? 3);
$tahun        = intval($_GET['tahun'] ?? date('Y'));
$jenisLayanan = $_GET['jenis_layanan'] ?? 'ralan';

if ($bulanAwal > $bulanAkhir || $bulanAwal < 1 || $bulanAkhir > 12) {
    die('Parameter bulan tidak valid.');
}

$bulanNama = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

// ── Headers ──────────────────────────────────────────────────────────────────
$headerRalan = ['No', 'No Rawat', 'Nama Pasien', 'No RM', 'Poli', 'Diagnosa', 'Nilai Tindakan', 'Harga Obat', 'No SEP', 'DPJP', 'Klaim BPJS', 'Penggunaan Obat', 'Anfrak BHP', 'Keterangan'];
$headerRanap = ['No', 'No Rawat', 'Nama Pasien', 'No RM', 'Poli/Ruangan', 'Diagnosa', 'Nilai Tindakan', 'Harga Obat', 'No SEP', 'DPJP', 'Klaim BPJS', 'Penggunaan Obat', 'Anfrak BHP', 'Keterangan', 'Lama Perawatan', 'Tanggal Masuk', 'Tanggal Keluar'];

$header   = ($jenisLayanan === 'ranap') ? $headerRanap : $headerRalan;
$lastCol  = Coordinate::stringFromColumnIndex(count($header));

// ── Style helpers ────────────────────────────────────────────────────────────
function headerStyle(): array {
    return [
        'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10, 'name' => 'Arial'],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E79']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B8CCE4']]],
    ];
}

function cellStyle(): array {
    return [
        'font'    => ['size' => 9, 'name' => 'Arial'],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9D9D9']]],
    ];
}

function altRowStyle(): array {
    return ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EBF3FF']]];
}

function safeSheetName(string $name): string {
    $n = preg_replace('/[\/\\\?\*\[\]\:\']+/', ' ', trim($name));
    return mb_substr($n, 0, 31);
}

function initSheet(Spreadsheet $spreadsheet, array &$wsMap, array &$rowMap, int &$sheetIdx, string $name, array $hdr, string $lastC): void {
    $safe = safeSheetName($name);
    if (!isset($wsMap[$safe])) {
        $ws = $spreadsheet->createSheet($sheetIdx++);
        $ws->setTitle($safe);
        $ws->fromArray([$hdr], null, 'A1');
        $ws->getStyle('A1:' . $lastC . '1')->applyFromArray(headerStyle());
        $ws->getRowDimension(1)->setRowHeight(30);
        $ws->freezePane('A2');
        $wsMap[$safe]  = $ws;
        $rowMap[$safe] = 2;
    }
}

function writeRow($ws, int &$rowNum, array $data, int $no, string $lastC): void {
    $ws->fromArray([$data], null, 'A' . $rowNum);
    $range = 'A' . $rowNum . ':' . $lastC . $rowNum;
    $ws->getStyle($range)->applyFromArray(cellStyle());
    if ($no % 2 === 0) {
        $ws->getStyle($range)->applyFromArray(altRowStyle());
    }
    $ws->getRowDimension($rowNum)->setRowHeight(16);
    $rowNum++;
}

// ══════════════════════════════════════════════════════════════════════════════
// BUILD SPREADSHEET
// ══════════════════════════════════════════════════════════════════════════════
$spreadsheet = new Spreadsheet();
$spreadsheet->removeSheetByIndex(0);
$sheetIdx  = 0;
$wsMap     = [];   // safeSheetName => Worksheet
$rowMap    = [];   // safeSheetName => next row number
$globalNo  = [];   // safeSheetName => counter per sheet

for ($m = $bulanAwal; $m <= $bulanAkhir; $m++) {
    $awal   = sprintf('%04d-%02d-01 00:00:00', $tahun, $m);
    $akhir  = sprintf('%04d-%02d-%02d 23:59:59', $tahun, $m, cal_days_in_month(CAL_GREGORIAN, $m, $tahun));
    $prefix = $bulanNama[$m];

    // ── BPJS lookup (per bulan, sesuai jenis pelayanan) ────────────────────
    $bpjs_lookup = [];
    $bulan_int   = (int) $m;
    $bpjs_jenis  = ($jenisLayanan === 'ranap') ? 'ranap' : 'ralan';
    $bpjs_result = mysqli_query($koneksi, "
        SELECT data FROM bpjs_verifikasi
        WHERE bulan = '$bulan_int' AND tahun = '$tahun' AND jenis = '$bpjs_jenis'
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

    if ($jenisLayanan === 'ralan') {
        // ════════════════════════════════════════════════════════════════════
        // RALAN — sheet per POLIKLINIK
        // ════════════════════════════════════════════════════════════════════
        $offset = 0;
        $batch  = 300;
        while (true) {
            $idsQ = mysqli_query($koneksi, "
                SELECT DISTINCT rp.no_rawat
                FROM reg_periksa rp
                WHERE rp.status_lanjut = 'Ralan'
                  AND NOT (rp.kd_poli = 'IGDK' AND EXISTS (SELECT 1 FROM kamar_inap ki WHERE ki.no_rawat = rp.no_rawat))
                  AND CONCAT(rp.tgl_registrasi,' ',rp.jam_reg) BETWEEN '$awal' AND '$akhir'
                ORDER BY rp.tgl_registrasi ASC, rp.jam_reg ASC
                LIMIT $offset, $batch
            ");
            if (!$idsQ || mysqli_num_rows($idsQ) === 0) break;

            $ids = [];
            while ($r = mysqli_fetch_assoc($idsQ)) {
                $ids[] = "'" . mysqli_real_escape_string($koneksi, $r['no_rawat']) . "'";
            }
            $in = implode(',', $ids);

            $q = mysqli_query($koneksi, "
                SELECT
                    rp.no_rawat,
                    p.nm_pasien,
                    rp.no_rkm_medis,
                    IFNULL(pl.nm_poli, '-') AS nm_poli,
                    IFNULL((SELECT GROUP_CONCAT(DISTINCT bs2.no_sep SEPARATOR ' | ')
                            FROM bridging_sep bs2 WHERE bs2.no_rawat=rp.no_rawat AND bs2.no_sep!='-' AND bs2.no_sep!=''), '-') AS no_sep,
                    IFNULL(dok.nm_dokter, '-') AS nm_dokter
                FROM reg_periksa rp
                JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                LEFT JOIN poliklinik pl ON rp.kd_poli = pl.kd_poli
                LEFT JOIN dokter dok ON rp.kd_dokter = dok.kd_dokter
                WHERE rp.no_rawat IN ($in)
            ");
            if (!$q) break;

            // ── Bulk pre-fetch: diagnosa, nilai tindakan, harga obat (ralan) ──
            $diagnosaMap = [];
            $dq = mysqli_query($koneksi, "
                SELECT dp.no_rawat, GROUP_CONCAT(DISTINCT py.nm_penyakit ORDER BY dp.prioritas SEPARATOR ' + ') AS diagnosa
                FROM diagnosa_pasien dp
                LEFT JOIN penyakit py ON dp.kd_penyakit = py.kd_penyakit
                WHERE dp.no_rawat IN ($in) AND dp.status = 'Ralan'
                GROUP BY dp.no_rawat
            ");
            if ($dq) while ($dr = mysqli_fetch_assoc($dq)) $diagnosaMap[$dr['no_rawat']] = $dr['diagnosa'];

            $tindakanMap = [];
            $tq = mysqli_query($koneksi, "
                SELECT rj.no_rawat, SUM(IFNULL(jp.total_byrdrpr,0)) AS nilai
                FROM rawat_jl_drpr rj
                JOIN jns_perawatan jp ON rj.kd_jenis_prw = jp.kd_jenis_prw
                WHERE rj.no_rawat IN ($in)
                GROUP BY rj.no_rawat
            ");
            if ($tq) while ($tr = mysqli_fetch_assoc($tq)) $tindakanMap[$tr['no_rawat']] = floatval($tr['nilai']);

            $obatMap = [];
            $oq = mysqli_query($koneksi, "
                SELECT ro.no_rawat, SUM(IFNULL(rd.jml,0) * IFNULL(db.ralan,0)) AS harga
                FROM resep_obat ro
                JOIN resep_dokter rd ON rd.no_resep = ro.no_resep
                JOIN databarang db ON db.kode_brng = rd.kode_brng
                WHERE ro.no_rawat IN ($in) AND ro.tgl_perawatan != '0000-00-00' AND ro.status = 'ralan'
                GROUP BY ro.no_rawat
            ");
            if ($oq) while ($or_ = mysqli_fetch_assoc($oq)) $obatMap[$or_['no_rawat']] = floatval($or_['harga']);

            while ($row = mysqli_fetch_assoc($q)) {
                $sheetKey = $prefix . ' - ' . $row['nm_poli'];
                initSheet($spreadsheet, $wsMap, $rowMap, $sheetIdx, $sheetKey, $header, $lastCol);

                if (!isset($globalNo[$sheetKey])) $globalNo[$sheetKey] = 1;
                $no = $globalNo[$sheetKey]++;

                $klaimBpjs = $bpjs_lookup[$row['no_sep']] ?? 0;

                $dataRow = [
                    $no,
                    $row['no_rawat'],
                    $row['nm_pasien'],
                    $row['no_rkm_medis'],
                    $row['nm_poli'],
                    $diagnosaMap[$row['no_rawat']] ?? '',
                    $tindakanMap[$row['no_rawat']] ?? 0,
                    $obatMap[$row['no_rawat']] ?? 0,
                    $row['no_sep'],
                    $row['nm_dokter'],
                    $klaimBpjs,
                    '',
                    '',
                    '',
                ];

                $safeKey = safeSheetName($sheetKey);
                writeRow($wsMap[$safeKey], $rowMap[$safeKey], $dataRow, $no, $lastCol);
            }
            unset($q, $ids, $idsQ, $diagnosaMap, $tindakanMap, $obatMap);
            $offset += $batch;
        }
    } else {
        // ════════════════════════════════════════════════════════════════════
        // RANAP — sheet per GRUP BANGSAL (v_bangsal_grup)
        // ════════════════════════════════════════════════════════════════════
        $offset = 0;
        $batch  = 300;
        while (true) {
            $idsQ = mysqli_query($koneksi, "
                SELECT DISTINCT rp.no_rawat
                FROM reg_periksa rp
                LEFT JOIN kamar_inap ki_f ON ki_f.no_rawat=rp.no_rawat AND ki_f.stts_pulang!='Pindah Kamar'
                WHERE rp.status_lanjut = 'Ranap'
                  AND (CONCAT(ki_f.tgl_keluar,' ',ki_f.jam_keluar) BETWEEN '$awal' AND '$akhir')
                ORDER BY ki_f.tgl_keluar ASC, ki_f.jam_keluar ASC
                LIMIT $offset, $batch
            ");
            if (!$idsQ || mysqli_num_rows($idsQ) === 0) break;

            $ids = [];
            while ($r = mysqli_fetch_assoc($idsQ)) {
                $ids[] = "'" . mysqli_real_escape_string($koneksi, $r['no_rawat']) . "'";
            }
            $in = implode(',', $ids);

            // Pre-fetch: kamar terakhir + aggregate lama per no_rawat
            $lastRoom = [];
            $kiRows = mysqli_query($koneksi, "
                SELECT ki2.no_rawat, ki2.kd_kamar, ki2.lama,
                       CONCAT(ki2.tgl_keluar,' ',ki2.jam_keluar) AS tgl_keluar_dt,
                       CONCAT(ki2.tgl_masuk,' ',ki2.jam_masuk) AS tgl_masuk_dt
                FROM kamar_inap ki2
                WHERE ki2.no_rawat IN ($in) AND ki2.stts_pulang != 'Pindah Kamar'
                ORDER BY ki2.no_rawat, ki2.tgl_keluar DESC, ki2.jam_keluar DESC
            ");
            while ($kr = mysqli_fetch_assoc($kiRows)) {
                $nr = $kr['no_rawat'];
                if (!isset($lastRoom[$nr])) {
                    $lastRoom[$nr] = [
                        'kd_kamar'       => $kr['kd_kamar'],
                        'total_lama'     => 0,
                        'tgl_masuk'      => $kr['tgl_masuk_dt'],
                        'tgl_keluar'     => ($kr['tgl_keluar_dt'] && strpos($kr['tgl_keluar_dt'], '0000') === false) ? $kr['tgl_keluar_dt'] : '',
                    ];
                }
                $lastRoom[$nr]['total_lama'] += (int) $kr['lama'];
                if ($kr['tgl_masuk_dt'] < $lastRoom[$nr]['tgl_masuk']) {
                    $lastRoom[$nr]['tgl_masuk'] = $kr['tgl_masuk_dt'];
                }
            }
            unset($kiRows);

            // Pre-fetch: resolve kd_kamar → nama bangsal + grup_bangsal (1 query)
            $bangsalMap = [];
            $kamarIds = array_unique(array_column($lastRoom, 'kd_kamar'));
            if (!empty($kamarIds)) {
                $kamarIn = implode(',', array_map(function($k) use ($koneksi) {
                    return "'" . mysqli_real_escape_string($koneksi, $k) . "'";
                }, $kamarIds));
                $bQ = mysqli_query($koneksi, "SELECT k3.kd_kamar, b.nm_bangsal, COALESCE(vbg.grup_bangsal, b.nm_bangsal) AS grup_bangsal FROM kamar k3 JOIN bangsal b ON k3.kd_bangsal=b.kd_bangsal LEFT JOIN v_bangsal_grup vbg ON b.kd_bangsal=vbg.kd_bangsal WHERE k3.kd_kamar IN ($kamarIn)");
                while ($bR = mysqli_fetch_assoc($bQ)) {
                    $bangsalMap[$bR['kd_kamar']] = ['nm_bangsal' => $bR['nm_bangsal'], 'grup_bangsal' => $bR['grup_bangsal']];
                }
            }

            $q = mysqli_query($koneksi, "
                SELECT
                    rp.no_rawat,
                    p.nm_pasien,
                    rp.no_rkm_medis,
                    IFNULL(dok.nm_dokter, '-') AS nm_dokter,
                    IFNULL((SELECT GROUP_CONCAT(DISTINCT bs2.no_sep SEPARATOR ' | ')
                            FROM bridging_sep bs2 WHERE bs2.no_rawat=rp.no_rawat AND bs2.no_sep!='-' AND bs2.no_sep!=''), '-') AS no_sep
                FROM reg_periksa rp
                JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                LEFT JOIN dokter dok ON rp.kd_dokter = dok.kd_dokter
                WHERE rp.no_rawat IN ($in)
            ");
            if (!$q) break;

            // ── Bulk pre-fetch: diagnosa, nilai tindakan, harga obat (ranap) ──
            $diagnosaMap = [];
            $dq = mysqli_query($koneksi, "
                SELECT dp.no_rawat, GROUP_CONCAT(DISTINCT py.nm_penyakit ORDER BY dp.prioritas SEPARATOR ' + ') AS diagnosa
                FROM diagnosa_pasien dp
                LEFT JOIN penyakit py ON dp.kd_penyakit = py.kd_penyakit
                WHERE dp.no_rawat IN ($in) AND dp.status = 'Ranap'
                GROUP BY dp.no_rawat
            ");
            if ($dq) while ($dr = mysqli_fetch_assoc($dq)) $diagnosaMap[$dr['no_rawat']] = $dr['diagnosa'];

            $tindakanMap = [];
            $tq = mysqli_query($koneksi, "
                SELECT ri.no_rawat, SUM(IFNULL(ji.total_byrdrpr,0)) AS nilai
                FROM rawat_inap_drpr ri
                JOIN jns_perawatan_inap ji ON ri.kd_jenis_prw = ji.kd_jenis_prw
                WHERE ri.no_rawat IN ($in)
                GROUP BY ri.no_rawat
            ");
            if ($tq) while ($tr = mysqli_fetch_assoc($tq)) $tindakanMap[$tr['no_rawat']] = floatval($tr['nilai']);

            $obatMap = [];
            $oq = mysqli_query($koneksi, "
                SELECT ro.no_rawat, SUM(IFNULL(rd.jml,0) * IFNULL(db.ralan,0)) AS harga
                FROM resep_obat ro
                JOIN resep_dokter rd ON rd.no_resep = ro.no_resep
                JOIN databarang db ON db.kode_brng = rd.kode_brng
                WHERE ro.no_rawat IN ($in) AND ro.tgl_perawatan != '0000-00-00' AND ro.status = 'ranap'
                GROUP BY ro.no_rawat
            ");
            if ($oq) while ($or_ = mysqli_fetch_assoc($oq)) $obatMap[$or_['no_rawat']] = floatval($or_['harga']);

            while ($row = mysqli_fetch_assoc($q)) {
                $nr = $row['no_rawat'];
                $lr = $lastRoom[$nr] ?? null;

                $grupBangsal = '-';
                $nmBangsal   = '-';
                if ($lr && isset($bangsalMap[$lr['kd_kamar']])) {
                    $grupBangsal = $bangsalMap[$lr['kd_kamar']]['grup_bangsal'];
                    $nmBangsal   = $bangsalMap[$lr['kd_kamar']]['nm_bangsal'];
                }

                $sheetKey = $prefix . ' - ' . $grupBangsal;
                initSheet($spreadsheet, $wsMap, $rowMap, $sheetIdx, $sheetKey, $header, $lastCol);

                if (!isset($globalNo[$sheetKey])) $globalNo[$sheetKey] = 1;
                $no = $globalNo[$sheetKey]++;

                $lama = ($lr && $lr['total_lama'] > 0) ? $lr['total_lama'] . ' hari' : '-';
                $tglMasuk  = ($lr && $lr['tgl_masuk'])  ? $lr['tgl_masuk']  : '-';
                $tglKeluar = ($lr && $lr['tgl_keluar']) ? $lr['tgl_keluar'] : '-';

                $klaimBpjs = $bpjs_lookup[$row['no_sep']] ?? 0;

                $dataRow = [
                    $no,
                    $row['no_rawat'],
                    $row['nm_pasien'],
                    $row['no_rkm_medis'],
                    $nmBangsal,
                    $diagnosaMap[$nr] ?? '',
                    $tindakanMap[$nr] ?? 0,
                    $obatMap[$nr] ?? 0,
                    $row['no_sep'],
                    $row['nm_dokter'],
                    $klaimBpjs,
                    '',
                    '',
                    '',
                    $lama,
                    $tglMasuk,
                    $tglKeluar,
                ];

                $safeKey = safeSheetName($sheetKey);
                writeRow($wsMap[$safeKey], $rowMap[$safeKey], $dataRow, $no, $lastCol);
            }
            unset($q, $ids, $idsQ, $diagnosaMap, $tindakanMap, $obatMap, $lastRoom, $bangsalMap);
            $offset += $batch;
        }
    }
}

// ── Auto-width & auto-filter per sheet ───────────────────────────────────────
foreach ($wsMap as $safe => $ws) {
    for ($c = 1; $c <= count($header); $c++) {
        $ws->getColumnDimensionByColumn($c)->setAutoSize(true);
    }
    $lastRow = $rowMap[$safe] ?? 2;
    if ($lastRow > 2) {
        $ws->setAutoFilter('A1:' . $lastCol . '1');
    }
}

// ── Output ──────────────────────────────────────────────────────────────────
if ($sheetIdx === 0) {
    $ws = $spreadsheet->createSheet(0);
    $ws->setTitle('TIDAK ADA DATA');
    $ws->setCellValue('A1', 'Tidak ada data untuk periode ini.');
}

$spreadsheet->setActiveSheetIndex(0);
$filename = 'evaluasi_' . $jenisLayanan . '_' . $tahun . '_' . date('Ymd_His') . '.xlsx';
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
