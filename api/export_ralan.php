<?php

/**
 * Export Rawat Jalan → Multi-Sheet XLSX (Memory Optimized)
 *
 * Sheet layout:
 *   1. Per-poliklinik  → Sheet "[Nama Poli]"
 */

set_time_limit(600);
ini_set('memory_limit', '1024M');

require_once '../config/conf.php';
require_once '../vendor/autoload.php';   // PhpSpreadsheet via Composer

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Settings;
use PhpOffice\PhpSpreadsheet\Collection\MemoryCompact;

// Enable Cell Caching to save memory
if (class_exists(MemoryCompact::class)) {
    Settings::setCache(new MemoryCompact());
}

$koneksi  = bukakoneksi();

// ─── Parameter filter ────────────────────────────────────────────────────────
$tgl1         = $_GET['tgl1']         ?? '';
$tgl2         = $_GET['tgl2']         ?? '';
$kd_poli      = $_GET['kd_poli']      ?? '';
$kd_pj        = $_GET['kd_pj']        ?? '';
$filter_sep   = $_GET['filter_sep']   ?? 'semua';
$tcari        = $_GET['tcari']        ?? '';
$filter_bulan = $_GET['filter_bulan'] ?? '';
$filter_tahun = $_GET['filter_tahun'] ?? date('Y');

$tgl1_f = !empty($tgl1) ? str_replace("T", " ", $tgl1) . ":00" : "";
$tgl2_f = !empty($tgl2) ? str_replace("T", " ", $tgl2) . ":59" : "";

if (!empty($filter_bulan)) {
  $b = intval($filter_bulan);
  $y = intval($filter_tahun);
  $range_awal  = sprintf("%04d-%02d-01 00:00:00", $y, $b);
  $range_akhir = sprintf("%04d-%02d-%02d 23:59:59", $y, $b, cal_days_in_month(CAL_GREGORIAN, $b, $y));
} elseif (!empty($tgl1_f) && !empty($tgl2_f)) {
  $range_awal  = $tgl1_f;
  $range_akhir = $tgl2_f;
} else {
  $range_awal = $range_akhir = '';
}

// ─── WHERE clause ────────────────────────────────────────────────────────────
$where = "WHERE rp.status_lanjut = 'Ralan'
    AND NOT (rp.kd_poli = 'IGDK' AND EXISTS (
        SELECT 1 FROM kamar_inap ki WHERE ki.no_rawat = rp.no_rawat
    ))
";
if (!empty($range_awal)) {
  $where .= " AND CONCAT(rp.tgl_registrasi,' ',rp.jam_reg) BETWEEN '$range_awal' AND '$range_akhir'";
}
if (!empty($kd_poli))  $where .= " AND rp.kd_poli = '" . mysqli_real_escape_string($koneksi, $kd_poli) . "'";
if (!empty($kd_pj))    $where .= " AND rp.kd_pj = '"   . mysqli_real_escape_string($koneksi, $kd_pj)   . "'";

if ($filter_sep === 'ada') {
  $where .= " AND EXISTS (SELECT 1 FROM bridging_sep bs WHERE bs.no_rawat=rp.no_rawat AND bs.no_sep IS NOT NULL AND bs.no_sep!='' AND bs.no_sep!='-')";
} elseif ($filter_sep === 'tidak_ada') {
  $where .= " AND NOT EXISTS (SELECT 1 FROM bridging_sep bs WHERE bs.no_rawat=rp.no_rawat AND bs.no_sep IS NOT NULL AND bs.no_sep!='' AND bs.no_sep!='-')";
}
if (!empty($tcari)) {
  $c = mysqli_real_escape_string($koneksi, $tcari);
  $where .= " AND (rp.no_rawat LIKE '%$c%' OR rp.no_rkm_medis LIKE '%$c%')";
}

// ─── Helpers ─────────────────────────────────────────────────────────────────
function safeSheetName(string $name): string {
  $n = preg_replace('/[\/\\\?\*\[\]\:\']+/', ' ', trim($name));
  return mb_substr($n, 0, 31);
}
function headerStyle(): array {
  return [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10, 'name' => 'Arial'],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E79']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B8CCE4']]],
  ];
}
function cellStyle(): array {
  return ['font' => ['size' => 9, 'name' => 'Arial'], 'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9D9D9']]]];
}
function altRowStyle(): array {
  return ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EBF3FF']]];
}

$headerPoli = ['No','No.SEP','No.Rawat','No.RM','Nama Pasien','Jenis','Dokter','Poliklinik','Tgl Registrasi','Sarana (Tindakan)','Dokter (Tindakan)','Perawat (Tindakan)','Non-Medis (Tindakan)','Total Tindakan','Nama Operasi','Anastesi','Sarana (Op)','Operator (Op)','Asisten Op.','Dr.Anestesi (Op)','Asisten Anestesi (Op)','Bidan (Op)','Onloop (Op)','Perina (Op)','Total Operasi','Jml Racikan','Jml Non-Racikan','Jml Resep OK','Jasa Farmasi','Total Obat','Sarana (Lab)','Dokter (Lab)','Petugas (Lab)','Non-Medis (Lab)','Total Lab','Dokter Radiologi','Tindakan Radiologi','Sarana (Radiologi)','Dokter (Radiologi)','Petugas (Radiologi)','Non-Medis (Radiologi)','Total Radiologi', 'Total Bayar','Total BPJS'];
$lastCol = Coordinate::stringFromColumnIndex(count($headerPoli));

$spreadsheet = new Spreadsheet();
$spreadsheet->removeSheetByIndex(0);
$wsSheets = []; $rowSheets = []; $sheetIdx = 0;

function getWs($spreadsheet, &$wsSheets, &$rowSheets, &$sheetIdx, $name, $header, $hStyle, $lastCol) {
  $safe = safeSheetName($name);
  if (!isset($wsSheets[$safe])) {
    $ws = $spreadsheet->createSheet($sheetIdx++);
    $ws->setTitle($safe);
    $ws->fromArray([$header], null, 'A1');
    $ws->getStyle('A1:'.$lastCol.'1')->applyFromArray($hStyle);
    $ws->getRowDimension(1)->setRowHeight(28); $ws->freezePane('A2');
    $wsSheets[$safe] = $ws; $rowSheets[$safe] = 2;
  }
  return $wsSheets[$safe];
}

// ─── Fetch semua data per batch ──────────────────────────────────────────────
$offset = 0; $batch = 200; $no = 1;

while (true) {
  $id_q = mysqli_query($koneksi, "SELECT DISTINCT rp.no_rawat FROM reg_periksa rp LEFT JOIN bridging_sep bs ON bs.no_rawat=rp.no_rawat $where ORDER BY rp.tgl_registrasi DESC, rp.jam_reg DESC LIMIT $offset, $batch");
  if (!$id_q || mysqli_num_rows($id_q) === 0) break;
  $ids = []; while ($r = mysqli_fetch_assoc($id_q)) { $ids[] = "'" . mysqli_real_escape_string($koneksi, $r['no_rawat']) . "'"; }
  $in = implode(',', $ids);

  $q = mysqli_query($koneksi, "SELECT rp.no_rawat, rp.no_rkm_medis, rp.tgl_registrasi, rp.jam_reg, rp.kd_poli, p.nm_pasien, IFNULL(bs.no_sep,'-') AS no_sep, poli.nm_poli, pj.png_jawab, dok.nm_dokter, IFNULL(tind.total_material,0) AS total_material, IFNULL(tind.total_tindakan_dr,0) AS total_tindakan_dr, IFNULL(tind.total_tindakan_pr,0) AS total_tindakan_pr, IFNULL(tind.total_menejemen,0) AS total_menejemen, IFNULL(tind.total_biaya_rawat,0) AS total_biaya_rawat, IFNULL(lab.total_material_lab,0) AS total_material_lab, IFNULL(lab.total_dokter_lab,0) AS total_dokter_lab, IFNULL(lab.total_petugas_lab,0) AS total_petugas_lab, IFNULL(lab.total_menejemen_lab,0) AS total_menejemen_lab, IFNULL(lab.total_lab,0) AS total_lab, IFNULL(rad.total_material_radiologi,0) AS total_material_radiologi, IFNULL(rad.total_dokter_radiologi,0) AS total_dokter_radiologi, IFNULL(rad.total_petugas_radiologi,0) AS total_petugas_radiologi, IFNULL(rad.total_menejemen_radiologi,0) AS total_menejemen_radiologi, IFNULL(rad.total_radiologi,0) AS total_radiologi, IFNULL(rad.tindakan_radiologi,'-') AS tindakan_radiologi, IFNULL(op.total_operasi,0) AS total_operasi, IFNULL(op.total_jasa_sarana_rs,0) AS total_jasa_sarana_rs, IFNULL(op.total_operator_operasi,0) AS total_operator_operasi, IFNULL(op.total_asisten_operator_operasi,0) AS total_asisten_operator_operasi, IFNULL(op.total_dr_anestesi_operasi,0) AS total_dr_anestesi_operasi, IFNULL(op.total_asisten_anestesi_operasi,0) AS total_asisten_anestesi_operasi, IFNULL(op.total_bidan_operasi,0) AS total_bidan_operasi, IFNULL(op.total_onloop_operasi,0) AS total_onloop_operasi, IFNULL(op.total_perina_operasi,0) AS total_perina_operasi, IFNULL(op.nm_perawatan,'-') AS nm_perawatan, IFNULL(op.anastesi,'-') AS anastesi, IFNULL(obat.total_obat,0) AS total_obat, IFNULL(resep.total_racikan,0) AS jumlah_resep_racikan, IFNULL(resep.total_non_racikan,0) AS jumlah_resep_non_racikan, IFNULL(resep.total_operasi_resep,0) AS jumlah_resep_operasi, IFNULL(dr_rad.nm_dokter, IF(rad_order.no_rawat IS NOT NULL,'(belum ada hasil)','-')) AS nm_dokter_radiologi FROM reg_periksa rp JOIN pasien p ON rp.no_rkm_medis=p.no_rkm_medis JOIN poliklinik poli ON rp.kd_poli=poli.kd_poli JOIN penjab pj ON rp.kd_pj=pj.kd_pj LEFT JOIN bridging_sep bs ON bs.no_rawat=rp.no_rawat LEFT JOIN dokter dok ON rp.kd_dokter=dok.kd_dokter LEFT JOIN (SELECT rjd.no_rawat, SUM(IFNULL(jp.material,0)) AS total_material, SUM(IFNULL(jp.tarif_tindakandr,0)) AS total_tindakan_dr, SUM(IFNULL(jp.tarif_tindakanpr,0)) AS total_tindakan_pr, SUM(IFNULL(jp.menejemen,0)) AS total_menejemen, SUM(IFNULL(jp.total_byrdrpr,0)) AS total_biaya_rawat FROM rawat_jl_drpr rjd JOIN jns_perawatan jp ON rjd.kd_jenis_prw=jp.kd_jenis_prw WHERE rjd.no_rawat IN ($in) GROUP BY rjd.no_rawat) tind ON tind.no_rawat=rp.no_rawat LEFT JOIN (SELECT pl.no_rawat, SUM(IFNULL(jpl.bagian_rs,0)) AS total_material_lab, SUM(IFNULL(jpl.tarif_tindakan_dokter,0)) AS total_dokter_lab, SUM(IFNULL(jpl.tarif_tindakan_petugas,0)) AS total_petugas_lab, SUM(IFNULL(jpl.menejemen,0)) AS total_menejemen_lab, SUM(IFNULL(jpl.total_byr,0)) AS total_lab FROM periksa_lab pl JOIN jns_perawatan_lab jpl ON pl.kd_jenis_prw=jpl.kd_jenis_prw WHERE pl.no_rawat IN ($in) GROUP BY pl.no_rawat) lab ON lab.no_rawat=rp.no_rawat LEFT JOIN (SELECT pr.no_rawat, SUM(IFNULL(jr.bagian_rs,0)) AS total_material_radiologi, SUM(IFNULL(jr.tarif_tindakan_dokter,0)) AS total_dokter_radiologi, SUM(IFNULL(jr.tarif_tindakan_petugas,0)) AS total_petugas_radiologi, SUM(IFNULL(jr.menejemen,0)) AS total_menejemen_radiologi, SUM(IFNULL(jr.total_byr,0)) AS total_radiologi, MAX(jr.nm_perawatan) AS tindakan_radiologi FROM permintaan_radiologi pr JOIN permintaan_pemeriksaan_radiologi ppr ON pr.noorder=ppr.noorder JOIN jns_perawatan_radiologi jr ON ppr.kd_jenis_prw=jr.kd_jenis_prw WHERE pr.no_rawat IN ($in) AND pr.status='ralan' GROUP BY pr.no_rawat) rad ON rad.no_rawat=rp.no_rawat LEFT JOIN (SELECT o.no_rawat, SUM(IFNULL(o.biayaoperator1,0)+IFNULL(o.biayaoperator2,0)+IFNULL(o.biayaoperator3,0)+IFNULL(o.biayaasisten_operator1,0)+IFNULL(o.biayaasisten_operator2,0)+IFNULL(o.biayaasisten_operator3,0)+IFNULL(o.biayainstrumen,0)+IFNULL(o.biayadokter_anak,0)+IFNULL(o.biayaperawaat_resusitas,0)+IFNULL(o.biayadokter_anestesi,0)+IFNULL(o.biayaasisten_anestesi,0)+IFNULL(o.biayaasisten_anestesi2,0)+IFNULL(o.biayabidan,0)+IFNULL(o.biayabidan2,0)+IFNULL(o.biayabidan3,0)+IFNULL(o.biayaperawat_luar,0)+IFNULL(o.biaya_omloop,0)+IFNULL(o.biaya_omloop2,0)+IFNULL(o.biaya_omloop3,0)+IFNULL(o.biaya_omloop4,0)+IFNULL(o.biaya_omloop5,0)+IFNULL(o.biaya_dokter_pjanak,0)+IFNULL(o.biaya_dokter_umum,0)+IFNULL(o.biayaalat,0)+IFNULL(o.biayasewaok,0)+IFNULL(o.akomodasi,0)+IFNULL(o.bagian_rs,0)+IFNULL(o.biayasarpras,0)) AS total_operasi, SUM(IFNULL(o.akomodasi,0)+IFNULL(o.bagian_rs,0)+IFNULL(o.biayasarpras,0)+IFNULL(o.biayasewaok,0)) AS total_jasa_sarana_rs, SUM(IFNULL(o.biayaoperator1,0)+IFNULL(o.biayaoperator2,0)+IFNULL(o.biayaoperator3,0)) AS total_operator_operasi, SUM(IFNULL(o.biayaasisten_operator1,0)+IFNULL(o.biayaasisten_operator2,0)+IFNULL(o.biayaasisten_operator3,0)) AS total_asisten_operator_operasi, SUM(IFNULL(o.biayadokter_anestesi,0)) AS total_dr_anestesi_operasi, SUM(IFNULL(o.biayaasisten_anestesi,0)+IFNULL(o.biayaasisten_anestesi2,0)) AS total_asisten_anestesi_operasi, SUM(IFNULL(o.biayabidan,0)+IFNULL(o.biayabidan2,0)+IFNULL(o.biayabidan3,0)) AS total_bidan_operasi, SUM(IFNULL(o.biaya_omloop,0)+IFNULL(o.biaya_omloop2,0)+IFNULL(o.biaya_omloop3,0)+IFNULL(o.biaya_omloop4,0)+IFNULL(o.biaya_omloop5,0)) AS total_onloop_operasi, SUM(IFNULL(o.biayadokter_anak,0)) AS total_perina_operasi, GROUP_CONCAT(DISTINCT pk.nm_perawatan SEPARATOR '; ') AS nm_perawatan, MAX(o.jenis_anasthesi) AS anastesi FROM operasi o LEFT JOIN paket_operasi pk ON pk.kode_paket=o.kode_paket WHERE o.no_rawat IN ($in) AND o.status='Ralan' GROUP BY o.no_rawat) op ON op.no_rawat=rp.no_rawat LEFT JOIN (SELECT no_rawat, SUM(IFNULL(total,0)) AS total_obat FROM detail_pemberian_obat WHERE no_rawat IN ($in) AND status='Ralan' GROUP BY no_rawat) obat ON obat.no_rawat=rp.no_rawat LEFT JOIN (SELECT ro.no_rawat, SUM(CASE WHEN SUBSTR(ro.no_resep,1,2)!='OK' AND rdr.no_resep IS NOT NULL THEN 1 ELSE 0 END) AS total_racikan, SUM(CASE WHEN SUBSTR(ro.no_resep,1,2)!='OK' AND rd.no_resep IS NOT NULL AND rdr.no_resep IS NULL THEN 1 ELSE 0 END) AS total_non_racikan, SUM(CASE WHEN SUBSTR(ro.no_resep,1,2)='OK' THEN 1 ELSE 0 END) AS total_operasi_resep FROM resep_obat ro LEFT JOIN resep_dokter_racikan rdr ON rdr.no_resep=ro.no_resep LEFT JOIN resep_dokter rd ON rd.no_resep=ro.no_resep WHERE ro.no_rawat IN ($in) AND ro.tgl_perawatan!='0000-00-00' AND ro.status='ralan' GROUP BY ro.no_rawat) resep ON resep.no_rawat=rp.no_rawat LEFT JOIN (SELECT pr2.no_rawat FROM permintaan_radiologi pr2 WHERE pr2.no_rawat IN ($in) GROUP BY pr2.no_rawat) rad_order ON rad_order.no_rawat=rp.no_rawat LEFT JOIN (SELECT prk.no_rawat, d2.nm_dokter FROM periksa_radiologi prk JOIN dokter d2 ON prk.kd_dokter=d2.kd_dokter WHERE prk.no_rawat IN ($in) GROUP BY prk.no_rawat) dr_rad ON dr_rad.no_rawat=rp.no_rawat WHERE rp.no_rawat IN ($in)");
  if (!$q) break;
  while ($row = mysqli_fetch_assoc($q)) {
    $jasa = 0; if ($row['jumlah_resep_racikan'] > 0) $jasa += 25000; elseif ($row['jumlah_resep_non_racikan'] > 0) $jasa += 15000;
    if ($row['jumlah_resep_operasi'] > 0) $jasa += 35000;
    $total_bpjs = 0; // BPJS dinonaktifkan
    $total_bayar = (float)$row['total_biaya_rawat'] + (float)$row['total_obat'] + (float)$row['total_lab'] + (float)$row['total_radiologi'] + $jasa;
    $finalRow = [$no, $row['no_sep'], $row['no_rawat'], $row['no_rkm_medis'], $row['nm_pasien'], $row['png_jawab'], ($row['nm_dokter']?:'TANPA DOKTER'), ($row['nm_poli']?:'TANPA POLI'), $row['tgl_registrasi'].' '.$row['jam_reg'], $row['total_material'], $row['total_tindakan_dr'], $row['total_tindakan_pr'], $row['total_menejemen'], $row['total_biaya_rawat'], $row['nm_perawatan'], $row['anastesi'], $row['total_jasa_sarana_rs'], $row['total_operator_operasi'], $row['total_asisten_operator_operasi'], $row['total_dr_anestesi_operasi'], $row['total_asisten_anestesi_operasi'], $row['total_bidan_operasi'], $row['total_onloop_operasi'], $row['total_perina_operasi'], $row['total_operasi'], $row['jumlah_resep_racikan'], $row['jumlah_resep_non_racikan'], $row['jumlah_resep_operasi'], $jasa, $row['total_obat'], $row['total_material_lab'], $row['total_dokter_lab'], $row['total_petugas_lab'], $row['total_menejemen_lab'], $row['total_lab'], $row['nm_dokter_radiologi'], $row['tindakan_radiologi'], $row['total_material_radiologi'], $row['total_dokter_radiologi'], $row['total_petugas_radiologi'], $row['total_menejemen_radiologi'], $row['total_radiologi'], round($total_bayar), $total_bpjs];

    $poliKey = $row['nm_poli'] ?: 'TANPA POLI';
    $ws = getWs($spreadsheet, $wsSheets, $rowSheets, $sheetIdx, $poliKey, $headerPoli, headerStyle(), $lastCol);
    $ws->fromArray([$finalRow], null, 'A' . $rowSheets[safeSheetName($poliKey)]);
    $range = 'A' . $rowSheets[safeSheetName($poliKey)] . ':' . $lastCol . $rowSheets[safeSheetName($poliKey)];
    $ws->getStyle($range)->applyFromArray(cellStyle());
    if ($rowSheets[safeSheetName($poliKey)] % 2 === 0) $ws->getStyle($range)->applyFromArray(altRowStyle());
    $rowSheets[safeSheetName($poliKey)]++; $no++;
  }
  unset($q, $ids, $id_q);
  $offset += $batch;
}

if ($sheetIdx === 0) { $ws = $spreadsheet->createSheet(0); $ws->setTitle('TIDAK ADA DATA'); $ws->setCellValue('A1', 'Tidak ada data.'); }
else { 
  foreach ($wsSheets as $ws) { 
    $lastR = $rowSheets[$ws->getTitle()] ?? ($ws->getHighestRow());
    if ($lastR > 1) {
       $ws->setAutoFilter('A1:' . $lastCol . '1');
    }
    for ($c = 1; $c <= count($headerPoli); $c++) { 
       $ws->getColumnDimensionByColumn($c)->setAutoSize(true); 
    } 
  } 
}
$spreadsheet->setActiveSheetIndex(0);
$filename = 'export_ralan_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

(new Xlsx($spreadsheet))->save('php://output');
$spreadsheet->disconnectWorksheets(); unset($spreadsheet);
mysqli_close($koneksi);
exit;
