<?php

/**
 * Export Rawat Inap → Multi-Sheet XLSX
 *
 * Sheet layout:
 *   - Sheet per GEDUNG  → diambil dari prefix nm_bangsal
 *     (mis. "ICU 1" → "ICU", "RUSA I.1" → "RUSA I", "RUSA II.3" → "RUSA II")
 *   - Sheet IGD         → pasien yang pernah lewat IGD
 *
 * Format per sheet:
 *   Baris UTAMA  : no, no_sep, no_rawat, ..., kamar_terakhir, riwayat (ringkas)
 *   Sub-baris    : (kosong), (kosong), ..., nama_kamar, jasa_dokter_kamar, jasa_perawat_kamar, lama, biaya
 *
 * Requires: composer require phpoffice/phpspreadsheet
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
use PhpOffice\PhpSpreadsheet\Settings;
use PhpOffice\PhpSpreadsheet\Collection\MemoryCompact;

// Enable Cell Caching to save memory
if (class_exists(MemoryCompact::class)) {
  Settings::setCache(new MemoryCompact());
}

$koneksi = bukakoneksi();

// ─── Parameter filter ────────────────────────────────────────────────────────
$tgl1 = $_GET['tgl1'] ?? '';
$tgl2 = $_GET['tgl2'] ?? '';
$kd_bangsal = $_GET['kd_bangsal'] ?? '';
$kd_pj = $_GET['kd_pj'] ?? '';
$filter_sep = $_GET['filter_sep'] ?? 'semua';
$tcari = $_GET['tcari'] ?? '';
$gedung = $_GET['gedung'] ?? '';
$status_pulang = $_GET['status_pulang'] ?? 'semua';
$filter_bulan = $_GET['filter_bulan'] ?? '';
$filter_tahun = $_GET['filter_tahun'] ?? date('Y');

$tgl1_f = !empty($tgl1) ? str_replace("T", " ", $tgl1) . ":00" : "";
$tgl2_f = !empty($tgl2) ? str_replace("T", " ", $tgl2) . ":59" : "";

if (!empty($filter_bulan)) {
  $b = intval($filter_bulan);
  $y = intval($filter_tahun);
  $tgl1_f = sprintf("%04d-%02d-01 00:00:00", $y, $b);
  $tgl2_f = sprintf("%04d-%02d-%02d 23:59:59", $y, $b, cal_days_in_month(CAL_GREGORIAN, $b, $y));
}

// ─── WHERE ───────────────────────────────────────────────────────────────────
$where = "WHERE rp.status_lanjut = 'Ranap'";

if (!empty($tgl1_f) && !empty($tgl2_f)) {
  $where .= " AND (CONCAT(ki_filter.tgl_keluar,' ',ki_filter.jam_keluar) BETWEEN '$tgl1_f' AND '$tgl2_f')";
}
if (!empty($kd_bangsal)) {
  $kb = mysqli_real_escape_string($koneksi, $kd_bangsal);
  $where .= " AND EXISTS (
        SELECT 1 FROM kamar_inap ki2
        JOIN kamar k2 ON ki2.kd_kamar = k2.kd_kamar
        WHERE ki2.no_rawat = rp.no_rawat AND k2.kd_bangsal = '$kb' LIMIT 1)";
}
if (!empty($kd_pj)) {
  $kp = mysqli_real_escape_string($koneksi, $kd_pj);
  $where .= " AND rp.kd_pj = '$kp'";
}
if (!empty($gedung)) {
  $gd = mysqli_real_escape_string($koneksi, $gedung);
  $where .= " AND EXISTS (
        SELECT 1 FROM kamar_inap ki3
        JOIN kamar km3 ON ki3.kd_kamar = km3.kd_kamar
        JOIN bangsal bs3 ON km3.kd_bangsal = bs3.kd_bangsal
        WHERE ki3.no_rawat = rp.no_rawat
        AND bs3.nm_bangsal LIKE '%$gd%'
        AND ki3.stts_pulang != 'Pindah Kamar')";
}
if ($filter_sep === 'ada') {
  $where .= " AND EXISTS (SELECT 1 FROM bridging_sep bsep WHERE bsep.no_rawat = rp.no_rawat AND bsep.no_sep != '' AND bsep.no_sep != '-')";
} elseif ($filter_sep === 'tidak_ada') {
  $where .= " AND NOT EXISTS (SELECT 1 FROM bridging_sep bsep WHERE bsep.no_rawat = rp.no_rawat AND bsep.no_sep != '' AND bsep.no_sep != '-')";
}
if ($status_pulang === 'belum_pulang') {
  $where .= " AND EXISTS (SELECT 1 FROM kamar_inap ki4 WHERE ki4.no_rawat = rp.no_rawat AND ki4.stts_pulang = '-' AND ki4.stts_pulang != 'Pindah Kamar' LIMIT 1)";
} elseif ($status_pulang === 'sudah_pulang') {
  $where .= " AND EXISTS (SELECT 1 FROM kamar_inap ki4 WHERE ki4.no_rawat = rp.no_rawat AND ki4.stts_pulang != '-' AND ki4.stts_pulang != 'Pindah Kamar' LIMIT 1)";
}
if (!empty($tcari)) {
  $tc = mysqli_real_escape_string($koneksi, $tcari);
  $where .= " AND (rp.no_rawat LIKE '%$tc%' OR rp.no_rkm_medis LIKE '%$tc%')";
}

// ═══════════════════════════════════════════════════════════════════════════════
// KONFIGURASI GEDUNG
//
// Format: 'SQL_LIKE_PATTERN' => 'NAMA_SHEET'
// Pattern menggunakan SQL LIKE — langsung dicocokkan dengan nm_bangsal di DB.
// Urutan penting: pola LEBIH SPESIFIK harus di atas.
//
// Contoh:
//   nm_bangsal = "RUSA I.1"  → cocok 'RUSA I.%'   → sheet "RUSA I"
//   nm_bangsal = "RUSA II.3" → cocok 'RUSA II.%'  → sheet "RUSA II"
//   nm_bangsal = "ICU 1"     → cocok 'ICU%'        → sheet "ICU"
//   nm_bangsal = "IGD"       → cocok 'IGD%'         → sheet "IGD"
// ═══════════════════════════════════════════════════════════════════════════════
$GEDUNG_MAP = [
  // ── RUSA (spesifik dulu) ───────────────────────────────────────────────
  '%RUSA II%' => 'RUSA II',
  '%RUSA I%' => 'RUSA I',

  // ── ICU group ──────────────────────────────────────────────────────────
  '%NICU%' => 'URIP',
  '%PICU%' => 'PICU',
  '%ICU%' => 'ICU',

  // ── IGD ────────────────────────────────────────────────────────────────
  '%IGD%' => 'IGD',

  // ── Bangsal lain ───────────────────────────────────────────────────────
  '%CENDERAWASIH%' => 'CENDERAWASIH',
  '%KANGURU%' => 'KANGURU',
  '%KASUARI%' => 'KASUARI',
  '%KUSKUS%' => 'KUSKUS',
  '%MAMBRUK%' => 'MAMBRUK',
  '%URIP%' => 'URIP',
  '%BOHA%' => 'BOHA',
  '%MALEO%' => 'MALEO',
  '%PERINA%' => 'PERINA',
  '%KEBIDANAN%' => 'KEBIDANAN',
  '%VK%' => 'VK',
  '%VIP%' => 'VIP',
];

/**
 * Bangun SQL CASE WHEN dari $GEDUNG_MAP.
 * Hasilkan: CASE WHEN nm_bangsal LIKE 'X' THEN 'Y' ... ELSE nm_bangsal END
 * Digunakan langsung di query pre-fetch untuk grouping gedung.
 */
function buildGedungCaseSQL(array $gedungMap, string $col = 'b.nm_bangsal'): string
{
  $cases = '';
  foreach ($gedungMap as $pattern => $sheet) {
    $p = str_replace("'", "\'", $pattern);
    $s = str_replace("'", "\'", $sheet);
    $cases .= "WHEN {$col} LIKE '{$p}' THEN '{$s}' ";
  }
  return "CASE {$cases}ELSE {$col} END";
}


// ─── Style helpers ───────────────────────────────────────────────────────────
function styleHeader(): array
{
  return [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10, 'name' => 'Arial'],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E79']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B8CCE4']]],
  ];
}

function styleMainRow(): array
{
  return [
    'font' => ['size' => 9, 'name' => 'Arial', 'bold' => false],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D0D0D0']]],
  ];
}

function styleMainRowAlt(): array
{
  return [
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EBF3FF']],
  ];
}

function styleSubRow(): array
{
  return [
    'font' => ['size' => 8.5, 'name' => 'Arial', 'italic' => true, 'color' => ['rgb' => '4472C4']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F2F7FF']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'C0C0C0']]],
    'alignment' => ['indent' => 2],
  ];
}

/**
 * Tulis sheet dengan baris utama + sub-baris riwayat kamar.
 *
 * $mainHeader   : array kolom header utama
 * $subHeader    : array kolom sub-header (hanya untuk baris riwayat kamar)
 * $mainColCount : jumlah kolom utama (= count($mainHeader))
 * $subStartCol  : kolom mulai sub-baris (1-based, misal 10 = kolom J)
 * $data         : array of ['main'=>[...], 'subs'=>[[...],[...]] ]
 *
 * Sub-baris diindent mulai dari kolom $subStartCol, kolom sebelumnya kosong.
 * Baris utama dan sub-baris tidak di-merge, tapi di-indent secara visual.
 */
function fillSheetWithSubs(
  $ws,
  array $mainHeader,
  array $subColLabels,
  int $subStartCol,
  array $data
): void {
  $totalCols = count($mainHeader);
  $lastCol = Coordinate::stringFromColumnIndex($totalCols);

  // ── Header ──────────────────────────────────────────────────────────────
  $ws->fromArray([$mainHeader], null, 'A1');
  $ws->getStyle('A1:' . $lastCol . '1')->applyFromArray(styleHeader());
  $ws->getRowDimension(1)->setRowHeight(30);
  $ws->freezePane('A2');

  // Tulis sub-header label tipis di baris 1 sebagai tooltip-like (opsional, lewati)

  // ── Data rows ───────────────────────────────────────────────────────────
  $rowNum = 2;
  $mainIdx = 0;

  foreach ($data as $entry) {
    $mainRow = $entry['main'];
    $subs = $entry['subs'] ?? [];

    // Baris utama
    $ws->fromArray([$mainRow], null, 'A' . $rowNum);
    $range = 'A' . $rowNum . ':' . $lastCol . $rowNum;
    $ws->getStyle($range)->applyFromArray(styleMainRow());
    if ($mainIdx % 2 === 0) {
      $ws->getStyle($range)->applyFromArray(styleMainRowAlt());
    }
    $ws->getRowDimension($rowNum)->setRowHeight(16);
    $rowNum++;

    // Sub-baris riwayat kamar
    foreach ($subs as $sub) {
      // Isi kolom sebelum subStartCol dengan string kosong agar tidak inherit
      $emptyPrefix = array_fill(0, $subStartCol - 1, '');
      $fullSub = array_merge($emptyPrefix, $sub);

      $ws->fromArray([$fullSub], null, 'A' . $rowNum);
      $range = 'A' . $rowNum . ':' . $lastCol . $rowNum;
      $ws->getStyle($range)->applyFromArray(styleSubRow());
      $ws->getRowDimension($rowNum)->setRowHeight(14);
      $rowNum++;
    }

    $mainIdx++;
  }

  // ── Auto-width ──────────────────────────────────────────────────────────
  for ($c = 1; $c <= $totalCols; $c++) {
    $ws->getColumnDimensionByColumn($c)->setAutoSize(true);
  }

  // ── Auto-filter (hanya baris utama, bukan sub) ──────────────────────────
  if ($rowNum > 2) {
    $ws->setAutoFilter('A1:' . $lastCol . '1');
  }
}

// ─── Fetch semua data per batch ──────────────────────────────────────────────
// ─── Build Helpers ───────────────────────────────────────────────────────────
$spreadsheet = new Spreadsheet();
$spreadsheet->removeSheetByIndex(0);
$wsGedung = [];
$rowGedung = [];
$sheetIdx = 0;
$no = 1;

function safeSheetName(string $name): string
{
  $name = preg_replace('/[\/\\\?\*\[\]\:\']+/', ' ', $name);
  $name = trim($name);
  return mb_substr($name, 0, 31);
}

function getSheet($spreadsheet, &$wsGedung, &$rowGedung, &$sheetIdx, $name, $mainHeader)
{
  $safe = safeSheetName($name);
  if (!isset($wsGedung[$safe])) {
    $ws = $spreadsheet->createSheet($sheetIdx++);
    $ws->setTitle($safe);
    $ws->fromArray([$mainHeader], null, 'A1');
    $ws->getStyle('A1:' . Coordinate::stringFromColumnIndex(count($mainHeader)) . '1')->applyFromArray(styleHeader());
    $ws->getRowDimension(1)->setRowHeight(30);
    $ws->freezePane('A2');
    $wsGedung[$safe] = $ws;
    $rowGedung[$safe] = 2;
  }
  return $wsGedung[$safe];
}

function writeEntry($ws, &$rowNum, $entry, $subStartCol, $lastCol, $no)
{
  // No column removed - Excel format starts with No.SEP
  $ws->fromArray([$entry['main']], null, 'A' . $rowNum);
  $range = 'A' . $rowNum . ':' . $lastCol . $rowNum;
  $ws->getStyle($range)->applyFromArray(styleMainRow());
  if ($no % 2 === 0)
    $ws->getStyle($range)->applyFromArray(styleMainRowAlt());
  $ws->getRowDimension($rowNum)->setRowHeight(16);
  $rowNum++;

  foreach ($entry['subs'] as $sub) {
    $fullSub = array_merge(array_fill(0, $subStartCol - 1, ''), $sub);
    $ws->fromArray([$fullSub], null, 'A' . $rowNum);
    $ws->getStyle('A' . $rowNum . ':' . $lastCol . $rowNum)->applyFromArray(styleSubRow());
    $ws->getRowDimension($rowNum)->setRowHeight(14);
    $rowNum++;
  }
}

$mainHeader = [
  'No.SEP',
  'No.Rawat',
  'No.RM',
  'Nama Pasien',
  'Jenis Bayar',
  'Dokter Utama',
  'DPJP Lengkap',
  'Kamar Terakhir',
  'Riwayat Kamar',
  'Status Pulang',
  'Tgl Keluar',
  'Lama Inap (Hari)',
  'Biaya Kamar',
  // Tindakan Inap
  'Sarana Tindakan Inap',
  'Jasa Dr Tindakan Inap',
  'Jasa DPJP Utama',
  'Jasa DPJP 2',
  'Jasa DPJP 3',
  'DPJP 4',
  'Jasa Pr Tindakan Inap',
  'Manajemen Tindakan Inap',
  'Total Tindakan Ranap',
  // Tindakan Rajal
  'Jasa Dr Tindakan Rajal',
  'Jasa Pr Tindakan Rajal',
  'Total Tindakan Rajal',
  // Operasi
  'Nama Operasi',
  'Jenis Anestesi',
  'Sarana/Sewa OK',
  'Perina (Dr Anak)',
  'Onloop',
  'Bidan',
  'Dr Anestesi',
  'Asisten Anestesi',
  'Asisten Operator',
  'Operator',
  'Total Operasi',
  // Resep & Farmasi
  'Jml Resep Racikan',
  'Jml Resep Non-Racikan',
  'Jml Resep Operasi',
  'Jasa Farmasi',
  'Total Obat',
  'Jasa Farmasi Pulang',
  'Total Obat Pulang',
  // Lab
  'Sarana Lab',
  'Jasa Dr Lab',
  'Jasa Petugas Lab',
  'Manajemen Lab',
  'Total Lab',
  // Radiologi
  'Dokter Radiologi',
  'Tindakan Radiologi',
  'Sarana Radiologi',
  'Jasa Dr Radiologi',
  'Jasa Petugas Radiologi',
  'Manajemen Radiologi',
  'Total Radiologi',
  // Total
  'Total Bayar',
  // Sub-baris header (riwayat kamar)
  'Ruangan (Riwayat)',
  'Jasa Dokter / Ruangan',
  'Jasa Perawat / Ruangan',
  'Lama (Hari)',
  'Biaya Kamar',
  'Tgl Masuk',
  'Tgl Keluar',
  'Status',
  // Sub-baris per dokter
  'Dokter (Jasa)',
  'Jasa Dr (Tindakan)',
  'Jasa Pr (Tindakan)',
  // Totals (calculated)
  'Total Jasa',
  'Total Non Medis',
  'Total Farmasi',
  'Total Ns OK',
  // Percentage columns (headers for reference)
  'Jasa DPJP Utama %',
  'Jasa DPJP 2 %',
  'Jasa DPJP 3 %',
  'DPJP 4 %',
  'Ns Ranap %',
  'Jasa Farmasi %',
  'Jasa Operator %',
  'Jasa Asisten OK %',
  'Jasa Dokter Anestesi %',
  'Jasa Asisten Anestesi %',
  'Jasa Dokter Lab %',
  'Analis %',
  'Jasa Dokter Radiologi %',
  'Jasa Radiografer %',
  'Non Medis %',
  'Jasa Dokter IGD %',
  'Jasa Ns IGD %',
  'Jasa Dokter Poli %',
  'Jasa Ns Poli %',
  // VLOOKUP & Calculation
  'BPJS (Disetujui)',
  '0.44',
  'Jasa DPJP Utama',
  'Jasa DPJP 2',
  'Jasa DPJP 3',
  'DPJP 4',
  'Ns Ranap',
  'Jasa Farmasi',
  'Jasa Operator',
  'Jasa Asisten OK',
  'Jasa Dokter Anestesi',
  'Jasa Asisten Anestesi',
  'Jasa Dokter Lab',
  'Analis',
  'Jasa Dokter Radiologi',
  'Jasa Radiografer',
  'Non Medis',
  'Jasa Dokter IGD',
  'Jasa Ns IGD',
  'Jasa Dokter Poli',
  'Jasa Ns Poli',
  'Total SIMRS',
  'Selisih',
];
$lastCol = Coordinate::stringFromColumnIndex(count($mainHeader));

$anastesiHeader = [
  'No',
  'No. SEP',
  'No. Rawat',
  'No. RM',
  'Nama Pasien',
  'Tgl Perawatan',
  'Jam Perawatan',
  'Nama Operasi',
  'Nama Anastesi',
  'Dokter Operator',
  'Dokter Anestesi',
  'Jasa Dokter',
  'Jasa Perawat',
  'Manajemen',
  'Jasa RS',
  'Total Jasa'
];
$no_ana = 1;

$offset = 0;
$batch = 100;
while (true) {
  $id_result = mysqli_query($koneksi, "SELECT DISTINCT rp.no_rawat FROM reg_periksa rp LEFT JOIN kamar_inap ki_filter ON ki_filter.no_rawat=rp.no_rawat AND ki_filter.stts_pulang!='Pindah Kamar' $where ORDER BY ki_filter.tgl_keluar DESC, ki_filter.jam_keluar DESC LIMIT $offset, $batch");
  if (!$id_result || mysqli_num_rows($id_result) === 0)
    break;
  $ids = [];
  while ($r = mysqli_fetch_assoc($id_result)) {
    $ids[] = "'" . mysqli_real_escape_string($koneksi, $r['no_rawat']) . "'";
  }
  $in = implode(',', $ids);

  $riwayat_all = [];
  $gedungCaseSQL = buildGedungCaseSQL($GEDUNG_MAP);
  $q = mysqli_query($koneksi, "SELECT ki.no_rawat, b.nm_bangsal, ({$gedungCaseSQL}) AS nm_gedung, ki.kd_kamar, ki.tgl_masuk, ki.jam_masuk, ki.tgl_keluar, ki.jam_keluar, ki.lama, ki.trf_kamar, ki.stts_pulang FROM kamar_inap ki JOIN kamar k ON ki.kd_kamar=k.kd_kamar JOIN bangsal b ON k.kd_bangsal=b.kd_bangsal WHERE ki.no_rawat IN ($in) ORDER BY ki.no_rawat, ki.tgl_masuk ASC, ki.jam_masuk ASC");
  while ($rw = mysqli_fetch_assoc($q)) {
    $riwayat_all[$rw['no_rawat']][] = $rw;
  }

  $tindakan_inap_all = [];
  $q = mysqli_query($koneksi, "SELECT ri.no_rawat, ri.tgl_perawatan, ri.jam_rawat, ri.kd_dokter, ji.tarif_tindakandr, ji.tarif_tindakanpr FROM rawat_inap_drpr ri JOIN jns_perawatan_inap ji ON ri.kd_jenis_prw=ji.kd_jenis_prw WHERE ri.no_rawat IN ($in) ORDER BY ri.no_rawat, ri.tgl_perawatan, ri.jam_rawat");
  while ($ti = mysqli_fetch_assoc($q)) {
    $tindakan_inap_all[$ti['no_rawat']][] = $ti;
  }

  // Fetch Anestesi data for this batch
  $q_anastesi = mysqli_query($koneksi, "
    SELECT 
      rp.no_rawat,
      rp.no_rkm_medis,
      p.nm_pasien,
      IFNULL((SELECT GROUP_CONCAT(DISTINCT bs2.no_sep SEPARATOR ' | ') FROM bridging_sep bs2 WHERE bs2.no_rawat=rp.no_rawat AND bs2.no_sep!='-' AND bs2.no_sep!=''),'-') AS no_sep,
      ri.tgl_perawatan,
      ri.jam_rawat,
      ji.nm_perawatan AS nama_anastesi,
      IFNULL(lap_op.operasi_nama, '-') AS nama_operasi,
      IFNULL(lap_op.nama_operator, '-') AS dokter_operator,
      IFNULL(lap_op.nama_anastesi_op, '-') AS dokter_anestesi,
      ji.tarif_tindakandr AS jasa_dokter,
      ji.tarif_tindakanpr AS jasa_perawat,
      ji.menejemen AS management,
      ji.material AS jasa_rs
    FROM rawat_inap_drpr ri
    JOIN jns_perawatan_inap ji ON ri.kd_jenis_prw = ji.kd_jenis_prw
    JOIN reg_periksa rp ON ri.no_rawat = rp.no_rawat
    JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
    LEFT JOIN (
      SELECT o.no_rawat,
        GROUP_CONCAT(DISTINCT pk.nm_perawatan SEPARATOR '; ') AS operasi_nama,
        GROUP_CONCAT(DISTINCT d_op.nm_dokter SEPARATOR '; ') AS nama_operator,
        GROUP_CONCAT(DISTINCT d_an.nm_dokter SEPARATOR '; ') AS nama_anastesi_op
      FROM operasi o
      LEFT JOIN paket_operasi pk ON pk.kode_paket = o.kode_paket
      LEFT JOIN dokter d_op ON d_op.kd_dokter = o.operator1
      LEFT JOIN dokter d_an ON d_an.kd_dokter = o.dokter_anestesi
      WHERE o.status = 'Ranap' AND o.no_rawat IN ($in)
      GROUP BY o.no_rawat
    ) lap_op ON lap_op.no_rawat = ri.no_rawat
    WHERE ri.no_rawat IN ($in) AND ji.kd_kategori = 'KP049'
    ORDER BY ri.tgl_perawatan, ri.jam_rawat
  ");
  if ($q_anastesi) {
    $safeAna = safeSheetName('ANASTESI');
    $wsAna = getSheet($spreadsheet, $wsGedung, $rowGedung, $sheetIdx, 'ANASTESI', $anastesiHeader);
    while ($ana = mysqli_fetch_assoc($q_anastesi)) {
      $total_jasa = (float) $ana['jasa_dokter'] + (float) $ana['jasa_perawat'] + (float) $ana['management'] + (float) $ana['jasa_rs'];
      $row_ana = [
        $no_ana,
        $ana['no_sep'],
        $ana['no_rawat'],
        $ana['no_rkm_medis'],
        $ana['nm_pasien'],
        $ana['tgl_perawatan'],
        $ana['jam_rawat'],
        $ana['nama_operasi'],
        $ana['nama_anastesi'],
        $ana['dokter_operator'],
        $ana['dokter_anestesi'],
        $ana['jasa_dokter'],
        $ana['jasa_perawat'],
        $ana['management'],
        $ana['jasa_rs'],
        $total_jasa
      ];

      $rNum = $rowGedung[$safeAna];
      $wsAna->fromArray([$row_ana], null, 'A' . $rNum);
      $rangeAna = 'A' . $rNum . ':' . Coordinate::stringFromColumnIndex(count($anastesiHeader)) . $rNum;
      $wsAna->getStyle($rangeAna)->applyFromArray(styleMainRow());
      if ($no_ana % 2 === 0) {
        $wsAna->getStyle($rangeAna)->applyFromArray(styleMainRowAlt());
      }
      $wsAna->getRowDimension($rNum)->setRowHeight(16);
      $rowGedung[$safeAna]++;
      $no_ana++;
    }
  }

  $jasa_global_all = [];
  $q = mysqli_query($koneksi, "SELECT no_rawat, SUM(total_dr) AS total_dr, SUM(total_pr) AS total_pr FROM (SELECT ri.no_rawat, SUM(IFNULL(ji.tarif_tindakandr,0)) AS total_dr, SUM(IFNULL(ji.tarif_tindakanpr,0)) AS total_pr FROM rawat_inap_drpr ri JOIN jns_perawatan_inap ji ON ri.kd_jenis_prw=ji.kd_jenis_prw WHERE ri.no_rawat IN ($in) GROUP BY ri.no_rawat UNION ALL SELECT rj.no_rawat, SUM(IFNULL(jj.tarif_tindakandr,0)), SUM(IFNULL(jj.tarif_tindakanpr,0)) FROM rawat_jl_drpr rj JOIN jns_perawatan jj ON rj.kd_jenis_prw=jj.kd_jenis_prw WHERE rj.no_rawat IN ($in) GROUP BY rj.no_rawat) g GROUP BY no_rawat");
  while ($jg = mysqli_fetch_assoc($q)) {
    $jasa_global_all[$jg['no_rawat']] = $jg;
  }

  $dpjp_all = [];
  $q = mysqli_query($koneksi, "SELECT dr.no_rawat, d.kd_dokter, d.nm_dokter FROM dpjp_ranap dr JOIN dokter d ON dr.kd_dokter=d.kd_dokter WHERE dr.no_rawat IN ($in) ORDER BY dr.no_rawat, dr.kd_dokter");
  while ($dj = mysqli_fetch_assoc($q)) {
    $dpjp_all[$dj['no_rawat']][] = ['kd_dokter' => $dj['kd_dokter'], 'nm_dokter' => $dj['nm_dokter']];
  }

  // Pre-fetch jasa tindakan per dokter per no_rawat
  $jasa_per_dokter_all = [];
  $q = mysqli_query($koneksi, "
    SELECT ri.no_rawat, ri.kd_dokter, d.nm_dokter,
      SUM(IFNULL(ji.tarif_tindakandr,0)) AS total_dr,
      SUM(IFNULL(ji.tarif_tindakanpr,0)) AS total_pr
    FROM rawat_inap_drpr ri
    JOIN jns_perawatan_inap ji ON ri.kd_jenis_prw=ji.kd_jenis_prw
    JOIN dokter d ON ri.kd_dokter=d.kd_dokter
    WHERE ri.no_rawat IN ($in)
    GROUP BY ri.no_rawat, ri.kd_dokter
    ORDER BY ri.no_rawat, total_dr DESC
  ");
  while ($jpd = mysqli_fetch_assoc($q)) {
    $jasa_per_dokter_all[$jpd['no_rawat']][$jpd['kd_dokter']] = [
      'nm_dokter' => $jpd['nm_dokter'],
      'total_dr' => (float) $jpd['total_dr'],
      'total_pr' => (float) $jpd['total_pr'],
    ];
  }

  $poli_all = [];
  $q = mysqli_query($koneksi, "SELECT rp2.no_rawat, pl.nm_poli FROM reg_periksa rp2 JOIN poliklinik pl ON rp2.kd_poli=pl.kd_poli WHERE rp2.no_rawat IN ($in)");
  while ($pl = mysqli_fetch_assoc($q)) {
    $poli_all[$pl['no_rawat']] = $pl['nm_poli'];
  }

  $res = mysqli_query($koneksi, "
  SELECT
    rp.no_rawat, rp.no_rkm_medis, rp.tgl_registrasi, rp.jam_reg,
    rp.kd_dokter AS kd_dokter_utama,
    p.nm_pasien, pj.png_jawab, dok.nm_dokter,
    IFNULL((SELECT GROUP_CONCAT(DISTINCT bs2.no_sep SEPARATOR ' | ') FROM bridging_sep bs2 WHERE bs2.no_rawat=rp.no_rawat AND bs2.no_sep!='-' AND bs2.no_sep!=''),'-') AS no_sep,
    IFNULL(kamar_akhir.nm_bangsal,'Belum Masuk Kamar') AS ruang,
    IFNULL(kamar_akhir.stts_pulang,'-') AS status_pulang,
    IFNULL(kamar_akhir.tgl_keluar,'') AS tgl_keluar,
    IFNULL(kamar_agg.total_lama_inap,0) AS total_lama_inap,
    IFNULL(kamar_agg.total_biaya_kamar,0) AS total_biaya_kamar,
    -- Tindakan Inap Breakdown
    IFNULL(tind_inap.total_material,0) AS total_material,
    IFNULL(tind_inap.total_tindakan_dr,0) AS total_tindakan_dr,
    IFNULL(tind_inap.total_tindakan_pr,0) AS total_tindakan_pr,
    IFNULL(tind_inap.total_menejemen,0) AS total_menejemen,
    IFNULL(tind_inap.total_biaya_rawat,0) AS total_biaya_rawat,
    -- Tindakan Rajal Breakdown
    IFNULL(tind_rajal.total_rajal_dr,0) AS total_rajal_tindakan_dr,
    IFNULL(tind_rajal.total_rajal_pr,0) AS total_rajal_tindakan_pr,
    IFNULL(tind_rajal.total_rajal_biaya_rawat,0) AS total_rajal_biaya_rawat,
    -- Operasi
    IFNULL(op.nm_perawatan,'-') AS nm_perawatan,
    IFNULL(op.anastesi,'-') AS anastesi,
    IFNULL(op.total_jasa_sarana_rs,0) AS total_jasa_sarana_rs,
    IFNULL(op.total_perina_operasi,0) AS total_perina_operasi,
    IFNULL(op.total_onloop_operasi,0) AS total_onloop_operasi,
    IFNULL(op.total_bidan_operasi,0) AS total_bidan_operasi,
    IFNULL(op.total_dr_anestesi_operasi,0) AS total_dr_anestesi_operasi,
    IFNULL(op.total_asisten_anestesi_operasi,0) AS total_asisten_anestesi_operasi,
    IFNULL(op.total_asisten_operator_operasi,0) AS total_asisten_operator_operasi,
    IFNULL(op.total_operator_operasi,0) AS total_operator_operasi,
    IFNULL(op.total_operasi,0) AS total_operasi,
    -- Resep
    IFNULL(resep.total_racikan,0) AS jumlah_resep_racikan,
    IFNULL(resep.total_non_racikan,0) AS jumlah_resep_non_racikan,
    IFNULL(resep.total_operasi_resep,0) AS jumlah_resep_operasi,
    -- Obat
    IFNULL(obat.total_obat,0) AS total_obat,
    IFNULL(obat_plg.total_obat_pulang,0) AS total_obat_pulang,
    -- Lab Breakdown
    IFNULL(lab.total_material_lab,0) AS total_material_lab,
    IFNULL(lab.total_dokter_lab,0) AS total_dokter_lab,
    IFNULL(lab.total_petugas_lab,0) AS total_petugas_lab,
    IFNULL(lab.total_menejemen_lab,0) AS total_menejemen_lab,
    IFNULL(lab.total_lab,0) AS total_lab,
    -- Radiologi Breakdown
    IFNULL(dr_rad.nm_dokter, IF(rad_order.no_rawat IS NOT NULL,'(belum ada hasil)','-')) AS nm_dokter_radiologi,
    IFNULL(rad.tindakan_radiologi,'-') AS tindakan_radiologi,
    IFNULL(rad.total_material_radiologi,0) AS total_material_radiologi,
    IFNULL(rad.total_dokter_radiologi,0) AS total_dokter_radiologi,
    IFNULL(rad.total_petugas_radiologi,0) AS total_petugas_radiologi,
    IFNULL(rad.total_menejemen_radiologi,0) AS total_menejemen_radiologi,
    IFNULL(rad.total_radiologi,0) AS total_radiologi
  FROM reg_periksa rp
  JOIN pasien p ON rp.no_rkm_medis=p.no_rkm_medis
  JOIN penjab pj ON rp.kd_pj=pj.kd_pj
  LEFT JOIN dokter dok ON rp.kd_dokter=dok.kd_dokter
  LEFT JOIN (
    SELECT ka.no_rawat, ka.nm_bangsal, ka.stts_pulang, ka.tgl_keluar, ka.jam_keluar
    FROM (
      SELECT k1.no_rawat, b1.nm_bangsal, k1.stts_pulang, k1.tgl_keluar, k1.jam_keluar,
        CASE WHEN (k1.tgl_keluar='0000-00-00' OR k1.tgl_keluar='' OR k1.tgl_keluar IS NULL) THEN 0 ELSE 1 END AS urut
      FROM kamar_inap k1
      JOIN kamar km1 ON k1.kd_kamar=km1.kd_kamar
      JOIN bangsal b1 ON km1.kd_bangsal=b1.kd_bangsal
      WHERE k1.no_rawat IN ($in) AND k1.stts_pulang!='Pindah Kamar'
    ) ka
    INNER JOIN (
      SELECT no_rawat, MIN(urut) AS min_urut
      FROM (SELECT k2.no_rawat, CASE WHEN (k2.tgl_keluar='0000-00-00' OR k2.tgl_keluar='' OR k2.tgl_keluar IS NULL) THEN 0 ELSE 1 END AS urut FROM kamar_inap k2 WHERE k2.no_rawat IN ($in) AND k2.stts_pulang!='Pindah Kamar') x
      GROUP BY no_rawat
    ) prio ON prio.no_rawat=ka.no_rawat AND prio.min_urut=ka.urut
    GROUP BY ka.no_rawat
  ) kamar_akhir ON kamar_akhir.no_rawat=rp.no_rawat
  LEFT JOIN (SELECT no_rawat, SUM(lama) AS total_lama_inap, SUM(lama*trf_kamar) AS total_biaya_kamar FROM kamar_inap WHERE no_rawat IN ($in) GROUP BY no_rawat) kamar_agg ON kamar_agg.no_rawat=rp.no_rawat
  LEFT JOIN (
    SELECT ri.no_rawat,
      SUM(IFNULL(ji.material,0)) AS total_material,
      SUM(IFNULL(ji.tarif_tindakandr,0)) AS total_tindakan_dr,
      SUM(IFNULL(ji.tarif_tindakanpr,0)) AS total_tindakan_pr,
      SUM(IFNULL(ji.menejemen,0)) AS total_menejemen,
      SUM(IFNULL(ji.total_byrdrpr,0)) AS total_biaya_rawat
    FROM rawat_inap_drpr ri JOIN jns_perawatan_inap ji ON ri.kd_jenis_prw=ji.kd_jenis_prw
    WHERE ri.no_rawat IN ($in) GROUP BY ri.no_rawat
  ) tind_inap ON tind_inap.no_rawat=rp.no_rawat
  LEFT JOIN (
    SELECT rj.no_rawat,
      SUM(IFNULL(jj.tarif_tindakandr,0)) AS total_rajal_dr,
      SUM(IFNULL(jj.tarif_tindakanpr,0)) AS total_rajal_pr,
      SUM(IFNULL(jj.total_byrdrpr,0)) AS total_rajal_biaya_rawat
    FROM rawat_jl_drpr rj JOIN jns_perawatan jj ON rj.kd_jenis_prw=jj.kd_jenis_prw
    WHERE rj.no_rawat IN ($in) GROUP BY rj.no_rawat
  ) tind_rajal ON tind_rajal.no_rawat=rp.no_rawat
  LEFT JOIN (
    SELECT o.no_rawat,
      GROUP_CONCAT(DISTINCT pk.nm_perawatan SEPARATOR '; ') AS nm_perawatan,
      MAX(o.jenis_anasthesi) AS anastesi,
      SUM(IFNULL(o.akomodasi,0)+IFNULL(o.bagian_rs,0)+IFNULL(o.biayasarpras,0)+IFNULL(o.biayasewaok,0)) AS total_jasa_sarana_rs,
      SUM(IFNULL(o.biayadokter_anak,0)) AS total_perina_operasi,
      SUM(IFNULL(o.biaya_omloop,0)+IFNULL(o.biaya_omloop2,0)+IFNULL(o.biaya_omloop3,0)+IFNULL(o.biaya_omloop4,0)+IFNULL(o.biaya_omloop5,0)) AS total_onloop_operasi,
      SUM(IFNULL(o.biayabidan,0)+IFNULL(o.biayabidan2,0)+IFNULL(o.biayabidan3,0)) AS total_bidan_operasi,
      SUM(IFNULL(o.biayadokter_anestesi,0)) AS total_dr_anestesi_operasi,
      SUM(IFNULL(o.biayaasisten_anestesi,0)+IFNULL(o.biayaasisten_anestesi2,0)) AS total_asisten_anestesi_operasi,
      SUM(IFNULL(o.biayaasisten_operator1,0)+IFNULL(o.biayaasisten_operator2,0)+IFNULL(o.biayaasisten_operator3,0)) AS total_asisten_operator_operasi,
      SUM(IFNULL(o.biayaoperator1,0)+IFNULL(o.biayaoperator2,0)+IFNULL(o.biayaoperator3,0)) AS total_operator_operasi,
      SUM(IFNULL(o.biayaoperator1,0)+IFNULL(o.biayaoperator2,0)+IFNULL(o.biayaoperator3,0)+IFNULL(o.biayaasisten_operator1,0)+IFNULL(o.biayaasisten_operator2,0)+IFNULL(o.biayaasisten_operator3,0)+IFNULL(o.biayainstrumen,0)+IFNULL(o.biayadokter_anak,0)+IFNULL(o.biayaperawaat_resusitas,0)+IFNULL(o.biayadokter_anestesi,0)+IFNULL(o.biayaasisten_anestesi,0)+IFNULL(o.biayaasisten_anestesi2,0)+IFNULL(o.biayabidan,0)+IFNULL(o.biayabidan2,0)+IFNULL(o.biayabidan3,0)+IFNULL(o.biayaperawat_luar,0)+IFNULL(o.biaya_omloop,0)+IFNULL(o.biaya_omloop2,0)+IFNULL(o.biaya_omloop3,0)+IFNULL(o.biaya_omloop4,0)+IFNULL(o.biaya_omloop5,0)+IFNULL(o.biaya_dokter_pjanak,0)+IFNULL(o.biaya_dokter_umum,0)+IFNULL(o.biayaalat,0)+IFNULL(o.biayasewaok,0)+IFNULL(o.akomodasi,0)+IFNULL(o.bagian_rs,0)+IFNULL(o.biayasarpras,0)) AS total_operasi
    FROM operasi o LEFT JOIN paket_operasi pk ON pk.kode_paket=o.kode_paket
    WHERE o.no_rawat IN ($in) AND o.status='Ranap' GROUP BY o.no_rawat
  ) op ON op.no_rawat=rp.no_rawat
  LEFT JOIN (SELECT no_rawat, SUM(IFNULL(total,0)) AS total_obat FROM detail_pemberian_obat WHERE no_rawat IN ($in) GROUP BY no_rawat) obat ON obat.no_rawat=rp.no_rawat
  LEFT JOIN (SELECT no_rawat, SUM(IFNULL(total,0)) AS total_obat_pulang FROM resep_pulang WHERE no_rawat IN ($in) GROUP BY no_rawat) obat_plg ON obat_plg.no_rawat=rp.no_rawat
  LEFT JOIN (
    SELECT ro.no_rawat,
      SUM(CASE WHEN SUBSTR(ro.no_resep,1,2)!='OK' AND rdr.no_resep IS NOT NULL THEN 1 ELSE 0 END) AS total_racikan,
      SUM(CASE WHEN SUBSTR(ro.no_resep,1,2)!='OK' AND rd.no_resep IS NOT NULL AND rdr.no_resep IS NULL THEN 1 ELSE 0 END) AS total_non_racikan,
      SUM(CASE WHEN SUBSTR(ro.no_resep,1,2)='OK' THEN 1 ELSE 0 END) AS total_operasi_resep
    FROM resep_obat ro
    LEFT JOIN resep_dokter_racikan rdr ON rdr.no_resep=ro.no_resep
    LEFT JOIN resep_dokter rd ON rd.no_resep=ro.no_resep
    WHERE ro.no_rawat IN ($in) AND ro.tgl_perawatan!='0000-00-00' AND ro.status='ranap' GROUP BY ro.no_rawat
  ) resep ON resep.no_rawat=rp.no_rawat
  LEFT JOIN (
    SELECT pl.no_rawat,
      SUM(IFNULL(jpl.bagian_rs,0)) AS total_material_lab,
      SUM(IFNULL(jpl.tarif_tindakan_dokter,0)) AS total_dokter_lab,
      SUM(IFNULL(jpl.tarif_tindakan_petugas,0)) AS total_petugas_lab,
      SUM(IFNULL(jpl.menejemen,0)) AS total_menejemen_lab,
      SUM(IFNULL(jpl.total_byr,0)) AS total_lab
    FROM periksa_lab pl JOIN jns_perawatan_lab jpl ON pl.kd_jenis_prw=jpl.kd_jenis_prw
    WHERE pl.no_rawat IN ($in) GROUP BY pl.no_rawat
  ) lab ON lab.no_rawat=rp.no_rawat
  LEFT JOIN (
    SELECT pr.no_rawat,
      SUM(IFNULL(jr.bagian_rs,0)) AS total_material_radiologi,
      SUM(IFNULL(jr.tarif_tindakan_dokter,0)) AS total_dokter_radiologi,
      SUM(IFNULL(jr.tarif_tindakan_petugas,0)) AS total_petugas_radiologi,
      SUM(IFNULL(jr.menejemen,0)) AS total_menejemen_radiologi,
      SUM(IFNULL(jr.total_byr,0)) AS total_radiologi,
      MAX(jr.nm_perawatan) AS tindakan_radiologi
    FROM permintaan_radiologi pr
    JOIN permintaan_pemeriksaan_radiologi ppr ON pr.noorder=ppr.noorder
    JOIN jns_perawatan_radiologi jr ON ppr.kd_jenis_prw=jr.kd_jenis_prw
    WHERE pr.no_rawat IN ($in) GROUP BY pr.no_rawat
  ) rad ON rad.no_rawat=rp.no_rawat
  LEFT JOIN (SELECT pr2.no_rawat FROM permintaan_radiologi pr2 WHERE pr2.no_rawat IN ($in) GROUP BY pr2.no_rawat) rad_order ON rad_order.no_rawat=rp.no_rawat
  LEFT JOIN (SELECT prk.no_rawat, d2.nm_dokter FROM periksa_radiologi prk JOIN dokter d2 ON prk.kd_dokter=d2.kd_dokter WHERE prk.no_rawat IN ($in) GROUP BY prk.no_rawat) dr_rad ON dr_rad.no_rawat=rp.no_rawat
  WHERE rp.no_rawat IN ($in)
  ");
  if (!$res)
    break;
  while ($row = mysqli_fetch_assoc($res)) {
    $no_rawat = $row['no_rawat'];
    $rk_list = $riwayat_all[$no_rawat] ?? [];
    $ti_list = $tindakan_inap_all[$no_rawat] ?? [];
    $jg = $jasa_global_all[$no_rawat] ?? ['total_dr' => 0, 'total_pr' => 0];
    $nm_poli = $poli_all[$no_rawat] ?? 'POLI';
    $t_dr_t = 0;
    $t_pr_t = 0;
    $subBaris = [];
    $gedungSet = [];
    foreach ($rk_list as $rk) {
      $start_dt = $rk['tgl_masuk'] . ' ' . $rk['jam_masuk'];
      $end_dt = ($rk['tgl_keluar'] == '0000-00-00' || empty($rk['tgl_keluar'])) ? date('Y-m-d H:i:s') : $rk['tgl_keluar'] . ' ' . $rk['jam_keluar'];
      $dr_k = 0;
      $pr_k = 0;
      foreach ($ti_list as $ti) {
        $wt = $ti['tgl_perawatan'] . ' ' . $ti['jam_rawat'];
        if ($wt >= $start_dt && $wt <= $end_dt) {
          $dr_k += (float) $ti['tarif_tindakandr'];
          $pr_k += (float) $ti['tarif_tindakanpr'];
        }
      }
      $t_dr_t += $dr_k;
      $t_pr_t += $pr_k;
      $lama = (int) $rk['lama'];
      $gedungSet[] = $rk['nm_gedung'];
      $subBaris[] = ['↳ ' . $rk['nm_bangsal'], $dr_k, $pr_k, $lama, $lama * (float) $rk['trf_kamar'], $rk['tgl_masuk'], ($rk['tgl_keluar'] == '0000-00-00' ? '(masih dirawat)' : $rk['tgl_keluar']), $rk['stts_pulang']];
    }
    $dr_p = max(0, (float) $jg['total_dr'] - $t_dr_t);
    $pr_p = max(0, (float) $jg['total_pr'] - $t_pr_t);
    if ($dr_p > 0 || $pr_p > 0)
      array_unshift($subBaris, ['↳ ' . $nm_poli . ' (awal)', $dr_p, $pr_p, '', '', '', '', '']);
    $dpjp_l = $dpjp_all[$no_rawat] ?? [];
    $dpjp_o = [];
    $dpjp1 = '';
    $dpjp2 = '';
    $dpjp3 = '';
    $dpjp4 = '';
    $dok_u = $row['nm_dokter'] ?? '';
    $kd_dok_u = '';
    if ($dok_u) {
      $dpjp_o[] = $dok_u . ' (DPJP Utama)';
      $dpjp1 = $dok_u;
      $kd_dok_u = $row['kd_dokter_utama'] ?? '';
    }
    foreach ($dpjp_l as $idx => $dj) {
      if ($dj['nm_dokter'] !== $dok_u)
        $dpjp_o[] = $dj['nm_dokter'];
      if ($idx === 0 && !$dpjp1) {
        $dpjp1 = $dj['nm_dokter'];
      } elseif ($idx === 1) {
        $dpjp2 = $dj['nm_dokter'];
      } elseif ($idx === 2) {
        $dpjp3 = $dj['nm_dokter'];
      } elseif ($idx === 3) {
        $dpjp4 = $dj['nm_dokter'];
      }
    }

    // Sub-baris per dokter (dari rawat_inap_drpr)
    $jasa_per_dokter = $jasa_per_dokter_all[$no_rawat] ?? [];
    // Kumpulkan set kd_dokter dari DPJP list
    $dpjp_kd_set = [];
    foreach ($dpjp_l as $dj) {
      $dpjp_kd_set[$dj['kd_dokter']] = $dj['nm_dokter'];
    }
    // Tambahkan dokter utama langsung dari kd_dokter di main query
    $kd_dok_utama = $row['kd_dokter_utama'] ?? '';
    if ($kd_dok_utama && !isset($dpjp_kd_set[$kd_dok_utama])) {
      $dpjp_kd_set[$kd_dok_utama] = $dok_u;
    }
    // Semua dokter yang ada di jasa_per_dokter (meski tidak terdaftar di dpjp_ranap)
    foreach ($jasa_per_dokter as $kd => $jpd) {
      if (!isset($dpjp_kd_set[$kd]))
        $dpjp_kd_set[$kd] = $jpd['nm_dokter'];
    }

    foreach ($dpjp_kd_set as $kd_dr => $nm_dr) {
      $jdr = $jasa_per_dokter[$kd_dr]['total_dr'] ?? 0;
      $jpr = $jasa_per_dokter[$kd_dr]['total_pr'] ?? 0;
      if ($jdr > 0 || $jpr > 0) {
        $label = '└ ' . $nm_dr;
        // Format sub-baris: 8 kolom kamar kosong, lalu 3 kolom dokter
        $subBaris[] = ['', '', '', '', '', '', '', '', $label, $jdr, $jpr];
      }
    }
    // Hitung jasa farmasi
    $j = 0;
    if ($row['jumlah_resep_racikan'] > 0)
      $j += (int) $row['jumlah_resep_racikan'] * 25000;
    if ($row['jumlah_resep_non_racikan'] > 0)
      $j += (int) $row['jumlah_resep_non_racikan'] * 15000;
    if ($row['jumlah_resep_operasi'] > 0)
      $j += (int) $row['jumlah_resep_operasi'] * 35000;
    $jp = ($row['total_obat_pulang'] > 0) ? 15000 : 0;
    $t_bpjs = 0; // BPJS dinonaktifkan (DB koneksi2 dihapus)
    $t_bayar = (float) $row['total_biaya_rawat'] + (float) $row['total_rajal_biaya_rawat']
      + (float) $row['total_biaya_kamar'] + (float) $row['total_obat'] + (float) $row['total_obat_pulang']
      + (float) $row['total_lab'] + (float) $row['total_radiologi'] + (float) $row['total_operasi'] + $j + $jp;
    $gedungSet = array_unique(array_filter($gedungSet));
    $isIGD = (stripos($row['ruang'], 'IGD') !== false || stripos($nm_poli, 'IGD') !== false || in_array('IGD', $gedungSet));

    $mainRow = [
      $row['no_sep'],                                             // No.SEP
      $row['no_rawat'],                                           // No.Rawat
      $row['no_rkm_medis'],                                       // No.RM
      $row['nm_pasien'],                                          // Nama Pasien
      $row['png_jawab'],                                          // Jenis Bayar
      $dok_u,                                                     // Dokter Utama
      implode(' | ', $dpjp_o) ?: '-',                            // DPJP Lengkap
      $row['ruang'],                                              // Kamar Terakhir
      implode(' → ', array_column($rk_list, 'nm_bangsal')),      // Riwayat Kamar
      $row['status_pulang'],                                      // Status Pulang
      $row['tgl_keluar'],                                         // Tgl Keluar
      $row['total_lama_inap'],                                    // Lama Inap
      $row['total_biaya_kamar'],                                  // Biaya Kamar
      // Tindakan Inap
      $row['total_material'],                                     // Sarana Tindakan Inap
      $row['total_tindakan_dr'],                                  // Jasa Dr Tindakan Inap
      0,                                                          // Jasa DPJP Utama (placeholder - perlu distribusi)
      0,                                                          // Jasa DPJP 2 (placeholder)
      0,                                                          // Jasa DPJP 3 (placeholder)
      0,                                                          // DPJP 4 (placeholder)
      $row['total_tindakan_pr'],                                  // Jasa Pr Tindakan Inap
      $row['total_menejemen'],                                    // Manajemen Tindakan Inap
      $row['total_biaya_rawat'],                                  // Total Tindakan Ranap
      // Tindakan Rajal
      $row['total_rajal_tindakan_dr'],                            // Jasa Dr Tindakan Rajal
      $row['total_rajal_tindakan_pr'],                            // Jasa Pr Tindakan Rajal
      $row['total_rajal_biaya_rawat'],                            // Total Tindakan Rajal
      // Operasi
      $row['nm_perawatan'],                                       // Nama Operasi
      $row['anastesi'],                                           // Jenis Anestesi
      $row['total_jasa_sarana_rs'],                               // Sarana/Sewa OK
      $row['total_perina_operasi'],                               // Perina (Dr Anak)
      $row['total_onloop_operasi'],                               // Onloop
      $row['total_bidan_operasi'],                                // Bidan
      $row['total_dr_anestesi_operasi'],                          // Dr Anestesi
      $row['total_asisten_anestesi_operasi'],                     // Asisten Anestesi
      $row['total_asisten_operator_operasi'],                     // Asisten Operator
      $row['total_operator_operasi'],                             // Operator
      $row['total_operasi'],                                      // Total Operasi
      // Resep & Farmasi
      $row['jumlah_resep_racikan'],                               // Jml Resep Racikan
      $row['jumlah_resep_non_racikan'],                           // Jml Resep Non-Racikan
      $row['jumlah_resep_operasi'],                               // Jml Resep Operasi
      $j,                                                         // Jasa Farmasi
      $row['total_obat'],                                         // Total Obat
      $jp,                                                        // Jasa Farmasi Pulang
      $row['total_obat_pulang'],                                  // Total Obat Pulang
      // Lab
      $row['total_material_lab'],                                 // Sarana Lab
      $row['total_dokter_lab'],                                   // Jasa Dr Lab
      $row['total_petugas_lab'],                                  // Jasa Petugas Lab
      $row['total_menejemen_lab'],                                // Manajemen Lab
      $row['total_lab'],                                          // Total Lab
      // Radiologi
      $row['nm_dokter_radiologi'],                                // Dokter Radiologi
      $row['tindakan_radiologi'],                                 // Tindakan Radiologi
      $row['total_material_radiologi'],                           // Sarana Radiologi
      $row['total_dokter_radiologi'],                             // Jasa Dr Radiologi
      $row['total_petugas_radiologi'],                            // Jasa Petugas Radiologi
      $row['total_menejemen_radiologi'],                          // Manajemen Radiologi
      $row['total_radiologi'],                                    // Total Radiologi
      // Total
      round($t_bayar),                                            // Total Bayar
      // Total BPJS column removed to match Excel format (CM has VLOOKUP instead)
      // Additional calculated columns will be added via array_merge
    ];
    
    $entry = ['main' => $mainRow, 'subs' => $subBaris];
    
    // BP = Total Jasa = P+Q+R+S+T+U+W+AD+AF+AG+AH+AI+AN+AP
    // In our data: total_tindakan_dr + total_tindakan_pr + total_rajal_tindakan_dr + 
    //              onloop_operasi + dr_anestesi_operasi + asisten_anestesi + asisten_operator + 
    //              operator_operasi + jasa_farmasi + jp
    $bp_total = $row['total_tindakan_dr'] + $row['total_tindakan_pr'] 
      + $row['total_rajal_tindakan_dr'] + $row['total_rajal_tindakan_pr']
      + $row['total_onloop_operasi'] + $row['total_dr_anestesi_operasi'] 
      + $row['total_asisten_anestesi_operasi'] + $row['total_asisten_operator_operasi'] 
      + $row['total_operator_operasi'] + $j + $jp;
    $entry['bp_total'] = $bp_total;
    
    // BQ = Total Non Medis = U + AU + BB = total_menejemen + total_menejemen_lab + total_menejemen_radiologi
    $bq_non_medis = $row['total_menejemen'] + $row['total_menejemen_lab'] + $row['total_menejemen_radiologi'];
    
    // BR = Total Farmasi = AN + AP = jasa_farmasi + jp
    $br_farmasi = $j + $jp;
    
    // BS = Total Ns OK = AD + AH = onloop_operasi + asisten_operator_operasi
    $bs_ns_ok = $row['total_onloop_operasi'] + $row['total_asisten_operator_operasi'];
    
    $entry['bq_non_medis'] = $bq_non_medis;
    $entry['br_farmasi'] = $br_farmasi;
    $entry['bs_ns_ok'] = $bs_ns_ok;
    
    // Calculate percentages (for reference/display, actual formulas in Excel)
    $pct_dpjp1 = $bp_total > 0 ? ($row['total_tindakan_dr'] / $bp_total) : 0;
    $pct_dpjp2 = $bp_total > 0 ? ($row['total_tindakan_pr'] / $bp_total) : 0;
    $pct_dpjp3 = 0;
    $pct_dpjp4 = 0;
    $pct_ns_ranap = $bp_total > 0 ? ($row['total_tindakan_pr'] / $bp_total) : 0;
    $pct_farmasi = $bp_total > 0 ? ($br_farmasi / $bp_total) : 0;
    $pct_operator = $bp_total > 0 ? ($row['total_operator_operasi'] / $bp_total) : 0;
    $pct_asisten_ok = $bp_total > 0 ? ($bs_ns_ok / $bp_total) : 0;
    $pct_dr_anestesi = $bp_total > 0 ? ($row['total_dr_anestesi_operasi'] / $bp_total) : 0;
    $pct_asisten_anestesi = $bp_total > 0 ? ($row['total_asisten_anestesi_operasi'] / $bp_total) : 0;
    $pct_dr_lab = $bp_total > 0 ? ($row['total_dokter_lab'] / $bp_total) : 0;
    $pct_analis = $bp_total > 0 ? ($row['total_petugas_lab'] / $bp_total) : 0;
    $pct_dr_rad = $bp_total > 0 ? ($row['total_dokter_radiologi'] / $bp_total) : 0;
    $pct_radiografer = $bp_total > 0 ? ($row['total_petugas_radiologi'] / $bp_total) : 0;
    $pct_non_medis = $bp_total > 0 ? ($bq_non_medis / $bp_total) : 0;
    $pct_dr_igd = $bp_total > 0 ? ($row['total_rajal_tindakan_dr'] / $bp_total) : 0;
    $pct_ns_igd = $bp_total > 0 ? ($row['total_rajal_tindakan_pr'] / $bp_total) : 0;
    $pct_dr_poli = 0;
    $pct_ns_poli = 0;
    
    $entry['pct_dpjp1'] = $pct_dpjp1;
    $entry['pct_dpjp2'] = $pct_dpjp2;
    $entry['pct_dpjp3'] = $pct_dpjp3;
    $entry['pct_dpjp4'] = $pct_dpjp4;
    $entry['pct_ns_ranap'] = $pct_ns_ranap;
    $entry['pct_farmasi'] = $pct_farmasi;
    $entry['pct_operator'] = $pct_operator;
    $entry['pct_asisten_ok'] = $pct_asisten_ok;
    $entry['pct_dr_anestesi'] = $pct_dr_anestesi;
    $entry['pct_asisten_anestesi'] = $pct_asisten_anestesi;
    $entry['pct_dr_lab'] = $pct_dr_lab;
    $entry['pct_analis'] = $pct_analis;
    $entry['pct_dr_rad'] = $pct_dr_rad;
    $entry['pct_radiografer'] = $pct_radiografer;
    $entry['pct_non_medis'] = $pct_non_medis;
    $entry['pct_dr_igd'] = $pct_dr_igd;
    $entry['pct_ns_igd'] = $pct_ns_igd;
    $entry['pct_dr_poli'] = $pct_dr_poli;
    $entry['pct_ns_poli'] = $pct_ns_poli;
    
    $cm_bpjs = 0;
    $cn_ratio = 0.44;
    $cn_jasa = $cm_bpjs * $cn_ratio;
    
    $entry['cm_bpjs'] = $cm_bpjs;
    $entry['cn_ratio'] = $cn_ratio;
    $entry['cn_jasa'] = $cn_jasa;
    
    $co_dpjp1 = $cn_jasa * $pct_dpjp1;
    $cp_dpjp2 = $cn_jasa * $pct_dpjp2;
    $cq_dpjp3 = $cn_jasa * $pct_dpjp3;
    $cr_dpjp4 = $cn_jasa * $pct_dpjp4;
    $cs_ns_ranap = $cn_jasa * $pct_ns_ranap;
    $ct_farmasi = $cn_jasa * $pct_farmasi;
    $cu_operator = $cn_jasa * $pct_operator;
    $cv_asisten_ok = $cn_jasa * $pct_asisten_ok;
    $cw_dr_anestesi = $cn_jasa * $pct_dr_anestesi;
    $cx_asisten_anestesi = $cn_jasa * $pct_asisten_anestesi;
    $cy_dr_lab = $cn_jasa * $pct_dr_lab;
    $cz_analis = $cn_jasa * $pct_analis;
    $da_dr_rad = $cn_jasa * $pct_dr_rad;
    $db_radiografer = $cn_jasa * $pct_radiografer;
    $dc_non_medis = $cn_jasa * $pct_non_medis;
    $dd_dr_igd = $cn_jasa * $pct_dr_igd;
    $de_ns_igd = $cn_jasa * $pct_ns_igd;
    $df_dr_poli = $cn_jasa * $pct_dr_poli;
    $dg_ns_poli = $cn_jasa * $pct_ns_poli;
    
    $entry['co_dpjp1'] = $co_dpjp1;
    $entry['cp_dpjp2'] = $cp_dpjp2;
    $entry['cq_dpjp3'] = $cq_dpjp3;
    $entry['cr_dpjp4'] = $cr_dpjp4;
    $entry['cs_ns_ranap'] = $cs_ns_ranap;
    $entry['ct_farmasi'] = $ct_farmasi;
    $entry['cu_operator'] = $cu_operator;
    $entry['cv_asisten_ok'] = $cv_asisten_ok;
    $entry['cw_dr_anestesi'] = $cw_dr_anestesi;
    $entry['cx_asisten_anestesi'] = $cx_asisten_anestesi;
    $entry['cy_dr_lab'] = $cy_dr_lab;
    $entry['cz_analis'] = $cz_analis;
    $entry['da_dr_rad'] = $da_dr_rad;
    $entry['db_radiografer'] = $db_radiografer;
    $entry['dc_non_medis'] = $dc_non_medis;
    $entry['dd_dr_igd'] = $dd_dr_igd;
    $entry['de_ns_igd'] = $de_ns_igd;
    $entry['df_dr_poli'] = $df_dr_poli;
    $entry['dg_ns_poli'] = $dg_ns_poli;
    
    $dh_total_simrs = $co_dpjp1 + $cp_dpjp2 + $cq_dpjp3 + $cr_dpjp4 + $cs_ns_ranap
      + $ct_farmasi + $cu_operator + $cv_asisten_ok + $cw_dr_anestesi
      + $cx_asisten_anestesi + $cy_dr_lab + $cz_analis + $da_dr_rad
      + $db_radiografer + $dc_non_medis + $dd_dr_igd + $de_ns_igd
      + $df_dr_poli + $dg_ns_poli;
    $di_selisih = $cn_jasa - $dh_total_simrs;
    
    $entry['dh_total_simrs'] = $dh_total_simrs;
    $entry['di_selisih'] = $di_selisih;
    
    // Add calculated columns after mainRow
    $calculatedCols = [
      $bp_total,
      $bq_non_medis,
      $br_farmasi,
      $bs_ns_ok,
      $pct_dpjp1,
      $pct_dpjp2,
      $pct_dpjp3,
      $pct_dpjp4,
      $pct_ns_ranap,
      $pct_farmasi,
      $pct_operator,
      $pct_asisten_ok,
      $pct_dr_anestesi,
      $pct_asisten_anestesi,
      $pct_dr_lab,
      $pct_analis,
      $pct_dr_rad,
      $pct_radiografer,
      $pct_non_medis,
      $pct_dr_igd,
      $pct_ns_igd,
      $pct_dr_poli,
      $pct_ns_poli,
      $cm_bpjs,
      $cn_ratio,
      $co_dpjp1,
      $cp_dpjp2,
      $cq_dpjp3,
      $cr_dpjp4,
      $cs_ns_ranap,
      $ct_farmasi,
      $cu_operator,
      $cv_asisten_ok,
      $cw_dr_anestesi,
      $cx_asisten_anestesi,
      $cy_dr_lab,
      $cz_analis,
      $da_dr_rad,
      $db_radiografer,
      $dc_non_medis,
      $dd_dr_igd,
      $de_ns_igd,
      $df_dr_poli,
      $dg_ns_poli,
      $dh_total_simrs,
      $di_selisih,
    ];
    
    $mainRow = array_merge($mainRow, $calculatedCols);

    // Tentukan sheet tujuan: Ruangan / Gedung terakhir
    $lastRoom = !empty($rk_list) ? end($rk_list) : null;
    $targetGedung = $lastRoom ? $lastRoom['nm_gedung'] : '';

    if (!$targetGedung) {
      if ($isIGD)
        $targetGedung = 'IGD';
      else
        $targetGedung = 'BELUM MASUK KAMAR';
    }

    // Tulis ke satu sheet saja (tidak dobel)
    // subStartCol: 14 info + 4 dpjp + 6 inap + 3 rajal + 11 operasi + 7 farmasi + 5 lab + 7 radiologi + 2 total + 4 sub-totals + 20 pct + 2 cm/cn = 85
    // Sub-baris riwayat kamar mulai kolom ke-67 (BE)
    writeEntry(getSheet($spreadsheet, $wsGedung, $rowGedung, $sheetIdx, $targetGedung, $mainHeader), $rowGedung[safeSheetName($targetGedung)], $entry, 67, $lastCol, $no);

    $no++;
  }
  unset($riwayat_all, $tindakan_inap_all, $jasa_global_all, $dpjp_all, $poli_all, $res, $ids, $id_result);
  $offset += $batch;
}

if ($sheetIdx === 0) {
  $ws = $spreadsheet->createSheet(0);
  $ws->setTitle('TIDAK ADA DATA');
  $ws->setCellValue('A1', 'Tidak ada data.');
} else {
  foreach ($wsGedung as $ws) {
    for ($c = 1; $c <= count($mainHeader); $c++) {
      $ws->getColumnDimensionByColumn($c)->setAutoSize(true);
    }
  }
}
$spreadsheet->setActiveSheetIndex(0);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="export_ranap_' . date('Ymd_His') . '.xlsx"');
(new Xlsx($spreadsheet))->save('php://output');
$spreadsheet->disconnectWorksheets();
unset($spreadsheet);
mysqli_close($koneksi);
exit;