<?php
/**
 * export_excel.php
 * Download Excel detail tagihan satu pasien — format mengikuti layout PDF
 * Parameter: ?no_rawat=xxxx
 *
 * Requires: composer require phpoffice/phpspreadsheet
 */

set_time_limit(120);
ini_set('memory_limit', '256M');

require_once '../config/conf.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

$no_rawat = $_GET['no_rawat'] ?? '';
if (empty($no_rawat)) {
    http_response_code(400);
    exit('Parameter no_rawat diperlukan.');
}

$koneksi = bukakoneksi();
$nr = mysqli_real_escape_string($koneksi, $no_rawat);

// ── Helpers ───────────────────────────────────────────────────────────────────
function safeF($v)
{
    return (float) ($v ?? 0);
}

// ── Fetch data pasien header ──────────────────────────────────────────────────
$hdr = mysqli_fetch_assoc(mysqli_query($koneksi, "
    SELECT p.nm_pasien, rp.no_rkm_medis, rp.tgl_registrasi, pj.png_jawab,
           dok.nm_dokter,
           IFNULL((SELECT bs.no_sep FROM bridging_sep bs
                   WHERE bs.no_rawat=rp.no_rawat AND bs.no_sep NOT IN ('','-') LIMIT 1),'-') AS no_sep,
           IFNULL(SUM(ki.lama),0) AS total_lama,
           IFNULL(SUM(ki.lama*ki.trf_kamar),0) AS total_kamar,
           IFNULL(MAX(ki.tgl_keluar),'') AS tgl_keluar
    FROM reg_periksa rp
    JOIN pasien p ON rp.no_rkm_medis=p.no_rkm_medis
    JOIN penjab pj ON rp.kd_pj=pj.kd_pj
    LEFT JOIN dokter dok ON rp.kd_dokter=dok.kd_dokter
    LEFT JOIN kamar_inap ki ON ki.no_rawat=rp.no_rawat
    WHERE rp.no_rawat='$nr'
    GROUP BY rp.no_rawat
"));
if (!$hdr) {
    http_response_code(404);
    exit('No rawat tidak ditemukan.');
}

// ── Riwayat kamar ─────────────────────────────────────────────────────────────
$kamar_rows = [];
$q = mysqli_query($koneksi, "
    SELECT b.nm_bangsal, ki.tgl_masuk, ki.jam_masuk, ki.tgl_keluar, ki.jam_keluar,
           ki.lama, ki.trf_kamar, ki.stts_pulang
    FROM kamar_inap ki
    JOIN kamar k ON ki.kd_kamar=k.kd_kamar
    JOIN bangsal b ON k.kd_bangsal=b.kd_bangsal
    WHERE ki.no_rawat='$nr'
    ORDER BY ki.tgl_masuk, ki.jam_masuk
");
while ($rw = mysqli_fetch_assoc($q))
    $kamar_rows[] = $rw;

// ── Tindakan rawat inap per ruangan ───────────────────────────────────────────
$tind_inap = [];
$q = mysqli_query($koneksi, "
    SELECT ji.nm_perawatan, COUNT(*) AS qty,
           ji.tarif_tindakandr, ji.tarif_tindakanpr, ji.material, ji.menejemen,
           ji.total_byrdrpr,
           IFNULL(b.nm_bangsal,'') AS nm_bangsal,
           ri.tgl_perawatan
    FROM rawat_inap_drpr ri
    JOIN jns_perawatan_inap ji ON ri.kd_jenis_prw=ji.kd_jenis_prw
    LEFT JOIN kamar_inap ki ON ki.no_rawat=ri.no_rawat
        AND ri.tgl_perawatan BETWEEN ki.tgl_masuk AND IF(ki.tgl_keluar='0000-00-00',CURDATE(),ki.tgl_keluar)
        AND ki.stts_pulang != 'Pindah Kamar'
    LEFT JOIN kamar k ON ki.kd_kamar=k.kd_kamar
    LEFT JOIN bangsal b ON k.kd_bangsal=b.kd_bangsal
    WHERE ri.no_rawat='$nr'
    GROUP BY ji.nm_perawatan, b.nm_bangsal
    ORDER BY b.nm_bangsal, ji.nm_perawatan
");
while ($rw = mysqli_fetch_assoc($q))
    $tind_inap[] = $rw;

// ── Tindakan rajal ────────────────────────────────────────────────────────────
$tind_rajal = [];
$q = mysqli_query($koneksi, "
    SELECT jj.nm_perawatan, COUNT(*) AS qty, jj.total_byrdrpr
    FROM rawat_jl_drpr rj
    JOIN jns_perawatan jj ON rj.kd_jenis_prw=jj.kd_jenis_prw
    WHERE rj.no_rawat='$nr'
    GROUP BY jj.nm_perawatan
    ORDER BY jj.nm_perawatan
");
while ($rw = mysqli_fetch_assoc($q))
    $tind_rajal[] = $rw;

// ── Operasi ───────────────────────────────────────────────────────────────────
$operasi = [];
$q = mysqli_query($koneksi, "
    SELECT pk.nm_perawatan, o.tgl_operasi, o.jenis_anasthesi,
           (IFNULL(o.biayaoperator1,0)+IFNULL(o.biayaoperator2,0)+IFNULL(o.biayaoperator3,0)) AS jasa_operator,
           (IFNULL(o.biayaasisten_operator1,0)+IFNULL(o.biayaasisten_operator2,0)+IFNULL(o.biayaasisten_operator3,0)) AS jasa_asisten,
           IFNULL(o.biayadokter_anestesi,0) AS jasa_anestesi,
           (IFNULL(o.biayaasisten_anestesi,0)+IFNULL(o.biayaasisten_anestesi2,0)) AS jasa_asisten_anestesi,
           (IFNULL(o.biayabidan,0)+IFNULL(o.biayabidan2,0)+IFNULL(o.biayabidan3,0)) AS jasa_bidan,
           (IFNULL(o.biaya_omloop,0)+IFNULL(o.biaya_omloop2,0)) AS jasa_onloop,
           IFNULL(o.biayadokter_anak,0) AS jasa_perina,
           (IFNULL(o.akomodasi,0)+IFNULL(o.bagian_rs,0)+IFNULL(o.biayasarpras,0)+IFNULL(o.biayasewaok,0)) AS jasa_sarana
    FROM operasi o
    LEFT JOIN paket_operasi pk ON pk.kode_paket=o.kode_paket
    WHERE o.no_rawat='$nr' AND o.status='Ranap'
");
while ($rw = mysqli_fetch_assoc($q))
    $operasi[] = $rw;

// ── Lab ───────────────────────────────────────────────────────────────────────
$lab = [];
$q = mysqli_query($koneksi, "
    SELECT jpl.nm_perawatan, COUNT(*) AS qty, jpl.total_byr
    FROM periksa_lab pl
    JOIN jns_perawatan_lab jpl ON pl.kd_jenis_prw=jpl.kd_jenis_prw
    WHERE pl.no_rawat='$nr'
    GROUP BY jpl.nm_perawatan
    ORDER BY jpl.nm_perawatan
");
while ($rw = mysqli_fetch_assoc($q))
    $lab[] = $rw;

// ── Radiologi ─────────────────────────────────────────────────────────────────
$radiologi = [];
$q = mysqli_query($koneksi, "
    SELECT jr.nm_perawatan, COUNT(*) AS qty, jr.total_byr
    FROM permintaan_radiologi pr
    JOIN permintaan_pemeriksaan_radiologi ppr ON pr.noorder=ppr.noorder
    JOIN jns_perawatan_radiologi jr ON ppr.kd_jenis_prw=jr.kd_jenis_prw
    WHERE pr.no_rawat='$nr'
    GROUP BY jr.nm_perawatan
");
while ($rw = mysqli_fetch_assoc($q))
    $radiologi[] = $rw;

// ── Farmasi ───────────────────────────────────────────────────────────────────
$obat = [];
$q = mysqli_query($koneksi, "
    SELECT db.nama_brng AS nama_obat, SUM(dpo.jml) AS qty,
           dpo.biaya_obat AS harga_jual, SUM(dpo.total) AS subtotal
    FROM detail_pemberian_obat dpo
    JOIN databarang db ON db.kode_brng = dpo.kode_brng
    WHERE dpo.no_rawat='$nr'
    GROUP BY dpo.kode_brng, dpo.biaya_obat
    ORDER BY db.nama_brng
");
while ($rw = mysqli_fetch_assoc($q))
    $obat[] = $rw;

$obat_pulang = [];
$q = mysqli_query($koneksi, "
    SELECT db.nama_brng AS nama_obat, SUM(rpo.jml_barang) AS qty,
           rpo.harga AS harga_jual, SUM(rpo.total) AS subtotal
    FROM resep_pulang rpo
    JOIN databarang db ON db.kode_brng = rpo.kode_brng
    WHERE rpo.no_rawat='$nr'
    GROUP BY rpo.kode_brng, rpo.harga
    ORDER BY db.nama_brng
");
while ($rw = mysqli_fetch_assoc($q))
    $obat_pulang[] = $rw;

mysqli_close($koneksi);

// ═══════════════════════════════════════════════════════════════════════════════
// BUILD SPREADSHEET
// ═══════════════════════════════════════════════════════════════════════════════
$spreadsheet = new Spreadsheet();
$ws = $spreadsheet->getActiveSheet();
$ws->setTitle('Tagihan');

// ── Column widths ─────────────────────────────────────────────────────────────
$ws->getColumnDimension('A')->setWidth(48);  // nama layanan
$ws->getColumnDimension('B')->setWidth(8);   // qty
$ws->getColumnDimension('C')->setWidth(8);   // satuan label
$ws->getColumnDimension('D')->setWidth(18);  // harga satuan
$ws->getColumnDimension('E')->setWidth(20);  // subtotal

// ── Colour palette ────────────────────────────────────────────────────────────
$C_NAVY = '1A2744';
$C_NAVY2 = '253560';
$C_NAVY3 = '3D5A9E';
$C_ACCENT = 'C8391A';
$C_HEADER_BG = 'F0EDE6';
$C_SUBTOTAL = 'E2DDD4';
$C_WHITE = 'FFFFFF';
$C_LIGHT = 'F8F6F1';

// ── Style helpers ─────────────────────────────────────────────────────────────
function applyStyle($ws, $range, array $style): void
{
    $ws->getStyle($range)->applyFromArray($style);
}

function sectionHead($ws, int $row, string $title, float $total, string $bg, string $lastCol = 'E'): void
{
    $ws->mergeCells("A{$row}:D{$row}");
    $ws->setCellValue("A{$row}", strtoupper($title));
    $ws->setCellValue("E{$row}", $total);
    $style = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 9, 'name' => 'Arial'],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
    ];
    applyStyle($ws, "A{$row}:{$lastCol}{$row}", $style);
    $ws->getStyle("E{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
    $ws->getStyle("E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $ws->getRowDimension($row)->setRowHeight(18);
}

function tableHeader($ws, int $row): void
{
    $ws->setCellValue("A{$row}", 'Nama Tindakan / Layanan');
    $ws->setCellValue("B{$row}", 'Qty');
    $ws->setCellValue("C{$row}", 'Sat');
    $ws->setCellValue("D{$row}", 'Harga Satuan (Rp)');
    $ws->setCellValue("E{$row}", 'Subtotal (Rp)');
    $style = [
        'font' => ['bold' => true, 'color' => ['rgb' => '4A4540'], 'size' => 8, 'name' => 'Arial'],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0EDE6']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D6D3C8']]],
    ];
    applyStyle($ws, "A{$row}:E{$row}", $style);
    $ws->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $ws->getRowDimension($row)->setRowHeight(14);
}

function dataRow($ws, int $row, string $label, $qty, string $sat, $harga, $subtotal, bool $alt = false): void
{
    $ws->setCellValue("A{$row}", $label);
    $ws->setCellValue("B{$row}", $qty);
    $ws->setCellValue("C{$row}", $sat);
    $ws->setCellValue("D{$row}", $harga);
    $ws->setCellValue("E{$row}", $subtotal);
    $fill = $alt ? 'F8F6F1' : 'FFFFFF';
    $style = [
        'font' => ['size' => 9, 'name' => 'Arial'],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $fill]],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'E0DDD5']]],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
    ];
    applyStyle($ws, "A{$row}:E{$row}", $style);
    foreach (['B', 'C'] as $c)
        $ws->getStyle("{$c}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    foreach (['D', 'E'] as $c) {
        $ws->getStyle("{$c}{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        $ws->getStyle("{$c}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }
    $ws->getRowDimension($row)->setRowHeight(14);
}

function subtotalRow($ws, int $row, string $label, float $total): void
{
    $ws->mergeCells("A{$row}:D{$row}");
    $ws->setCellValue("A{$row}", $label);
    $ws->setCellValue("E{$row}", $total);
    $style = [
        'font' => ['bold' => true, 'size' => 9, 'name' => 'Arial', 'color' => ['rgb' => '1A2744']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2DDD4']],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B0AA9E']]],
    ];
    applyStyle($ws, "A{$row}:E{$row}", $style);
    $ws->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $ws->getStyle("E{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
    $ws->getStyle("E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $ws->getRowDimension($row)->setRowHeight(15);
}

function blankRow($ws, int $row): void
{
    $ws->getRowDimension($row)->setRowHeight(6);
    applyStyle($ws, "A{$row}:E{$row}", [
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F4F3EF']],
    ]);
}

// ── ROW COUNTER ───────────────────────────────────────────────────────────────
$r = 1;

// ── HEADER SECTION ────────────────────────────────────────────────────────────






// Info pasien
$info = [
    ['Nama Pasien', $hdr['nm_pasien']],
    ['No. RM', $hdr['no_rkm_medis']],
    ['No. Rawat', $no_rawat],
    ['No. SEP', $hdr['no_sep']],
    ['Tgl Registrasi', $hdr['tgl_registrasi']],
    ['Jenis Bayar', $hdr['png_jawab']],
    ['Dokter Utama', $hdr['nm_dokter'] ?? '-'],
    ['Lama Rawat', $hdr['total_lama'] . ' hari'],
];
foreach ($info as $inf) {
    $ws->setCellValue("A{$r}", $inf[0]);
    $ws->setCellValue("B{$r}", ':');
    $ws->mergeCells("C{$r}:E{$r}");
    $ws->setCellValue("C{$r}", $inf[1]);
    applyStyle($ws, "A{$r}:E{$r}", ['font' => ['size' => 9, 'name' => 'Arial']]);
    $ws->getStyle("A{$r}")->getFont()->setBold(true);
    $ws->getStyle("A{$r}")->getFont()->getColor()->setRGB('4A4540');
    $ws->getRowDimension($r)->setRowHeight(13);
    $r++;
}

$r++; // spasi

// ── GRAND TOTAL TRACKER ───────────────────────────────────────────────────────
$grand_total = 0;

// ═══════════════════════════════════════════════════════════════════════════════
// PER RUANGAN
// ═══════════════════════════════════════════════════════════════════════════════
foreach ($kamar_rows as $kr) {
    $biaya_kamar = safeF($kr['lama']) * safeF($kr['trf_kamar']);
    // Tindakan inap di ruangan ini
    $ti_ruang = array_filter($tind_inap, fn($t) => ($t['nm_bangsal'] ?? '') === $kr['nm_bangsal']);
    $total_ti = (float) array_sum(array_map(fn($t) => safeF($t['total_byrdrpr']) * safeF($t['qty']), $ti_ruang));
    $total_section = $biaya_kamar + $total_ti;
    $grand_total += $total_section;

    // Section header
    $tgl_masuk = $kr['tgl_masuk'];
    $tgl_keluar = ($kr['tgl_keluar'] === '0000-00-00' || empty($kr['tgl_keluar'])) ? 'masih dirawat' : $kr['tgl_keluar'];
    sectionHead($ws, $r, $kr['nm_bangsal'] . " ({$tgl_masuk} → {$tgl_keluar})", $total_section, '253560');
    $r++;
    tableHeader($ws, $r);
    $r++;

    // Rawat inap
    dataRow($ws, $r, 'Rawat Inap — ' . $kr['nm_bangsal'], $kr['lama'], 'hari', safeF($kr['trf_kamar']), $biaya_kamar, false);
    $r++;

    // Tindakan
    $alt = false;
    foreach ($ti_ruang as $ti) {
        $sub = safeF($ti['total_byrdrpr']) * safeF($ti['qty']);
        dataRow($ws, $r, $ti['nm_perawatan'], $ti['qty'], 'tindakan', safeF($ti['total_byrdrpr']), $sub, $alt);
        $alt = !$alt;
        $r++;
    }
    subtotalRow($ws, $r, 'Subtotal  ' . strtoupper($kr['nm_bangsal']), $total_section);
    $r++;
    blankRow($ws, $r);
    $r++;
}

// ═══════════════════════════════════════════════════════════════════════════════
// TINDAKAN RAJAL
// ═══════════════════════════════════════════════════════════════════════════════
if (!empty($tind_rajal)) {
    $total_rajal = (float) array_sum(array_map(fn($t) => safeF($t['total_byrdrpr']) * safeF($t['qty']), $tind_rajal));
    $grand_total += $total_rajal;
    sectionHead($ws, $r, 'TINDAKAN RAWAT JALAN', $total_rajal, '1A2744');
    $r++;
    tableHeader($ws, $r);
    $r++;
    $alt = false;
    foreach ($tind_rajal as $tr) {
        $sub = safeF($tr['total_byrdrpr']) * safeF($tr['qty']);
        dataRow($ws, $r, $tr['nm_perawatan'], $tr['qty'], 'tindakan', safeF($tr['total_byrdrpr']), $sub, $alt);
        $alt = !$alt;
        $r++;
    }
    subtotalRow($ws, $r, 'Subtotal Tindakan Rawat Jalan', $total_rajal);
    $r++;
    blankRow($ws, $r);
    $r++;
}

// ═══════════════════════════════════════════════════════════════════════════════
// OPERASI
// ═══════════════════════════════════════════════════════════════════════════════
if (!empty($operasi)) {
    $total_op = 0;
    $op_items = [];
    foreach ($operasi as $o) {
        $nm = $o['nm_perawatan'] ?? 'Operasi';
        $tgl = $o['tgl_operasi'] ?? '';
        $map = [
            "Sarana / Sewa OK ($nm $tgl)" => $o['jasa_sarana'],
            "Operator ($nm)" => $o['jasa_operator'],
            "Asisten Operator ($nm)" => $o['jasa_asisten'],
            "Dr. Anestesi — " . $o['jenis_anasthesi'] => $o['jasa_anestesi'],
            "Asisten Anestesi ($nm)" => $o['jasa_asisten_anestesi'],
            "Bidan ($nm)" => $o['jasa_bidan'],
            "Onloop ($nm)" => $o['jasa_onloop'],
            "Perina / Dr. Anak ($nm)" => $o['jasa_perina'],
        ];
        foreach ($map as $lbl => $val) {
            if (safeF($val) > 0) {
                $op_items[] = ['lbl' => $lbl, 'val' => safeF($val)];
                $total_op += safeF($val);
            }
        }
    }
    $grand_total += $total_op;
    sectionHead($ws, $r, 'OPERASI', $total_op, '1A2744');
    $r++;
    $ws->setCellValue("A{$r}", 'Komponen');
    $ws->setCellValue("E{$r}", 'Subtotal (Rp)');
    applyStyle($ws, "A{$r}:E{$r}", [
        'font' => ['bold' => true, 'size' => 8, 'name' => 'Arial', 'color' => ['rgb' => '4A4540']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0EDE6']],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D6D3C8']]],
    ]);
    $ws->mergeCells("A{$r}:D{$r}");
    $ws->getStyle("E{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $ws->getRowDimension($r)->setRowHeight(14);
    $r++;
    $alt = false;
    foreach ($op_items as $oi) {
        $ws->mergeCells("A{$r}:D{$r}");
        $ws->setCellValue("A{$r}", $oi['lbl']);
        $ws->setCellValue("E{$r}", $oi['val']);
        $fill = $alt ? 'F8F6F1' : 'FFFFFF';
        applyStyle($ws, "A{$r}:E{$r}", [
            'font' => ['size' => 9, 'name' => 'Arial'],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $fill]],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'E0DDD5']]],
        ]);
        $ws->getStyle("E{$r}")->getNumberFormat()->setFormatCode('#,##0.00');
        $ws->getStyle("E{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $ws->getRowDimension($r)->setRowHeight(14);
        $alt = !$alt;
        $r++;
    }
    subtotalRow($ws, $r, 'Subtotal Operasi', $total_op);
    $r++;
    blankRow($ws, $r);
    $r++;
}

// ═══════════════════════════════════════════════════════════════════════════════
// LABORATORIUM
// ═══════════════════════════════════════════════════════════════════════════════
if (!empty($lab)) {
    $total_lab = (float) array_sum(array_map(fn($l) => safeF($l['total_byr']) * safeF($l['qty']), $lab));
    $grand_total += $total_lab;
    sectionHead($ws, $r, 'LAB (' . ($hdr['tgl_registrasi']) . ')', $total_lab, '1A2744');
    $r++;
    tableHeader($ws, $r);
    $r++;
    $alt = false;
    foreach ($lab as $lb) {
        $sub = safeF($lb['total_byr']) * safeF($lb['qty']);
        dataRow($ws, $r, $lb['nm_perawatan'], $lb['qty'], 'item', safeF($lb['total_byr']), $sub, $alt);
        $alt = !$alt;
        $r++;
    }
    subtotalRow($ws, $r, 'Subtotal Lab', $total_lab);
    $r++;
    blankRow($ws, $r);
    $r++;
}

// ═══════════════════════════════════════════════════════════════════════════════
// RADIOLOGI
// ═══════════════════════════════════════════════════════════════════════════════
if (!empty($radiologi)) {
    $total_rad = (float) array_sum(array_map(fn($rd) => safeF($rd['total_byr']) * safeF($rd['qty']), $radiologi));
    $grand_total += $total_rad;
    sectionHead($ws, $r, 'RO (' . ($hdr['tgl_registrasi']) . ')', $total_rad, '1A2744');
    $r++;
    tableHeader($ws, $r);
    $r++;
    $alt = false;
    foreach ($radiologi as $rd) {
        $sub = safeF($rd['total_byr']) * safeF($rd['qty']);
        dataRow($ws, $r, $rd['nm_perawatan'], $rd['qty'], 'item', safeF($rd['total_byr']), $sub, $alt);
        $alt = !$alt;
        $r++;
    }
    subtotalRow($ws, $r, 'Subtotal Radiologi', $total_rad);
    $r++;
    blankRow($ws, $r);
    $r++;
}

// ═══════════════════════════════════════════════════════════════════════════════
// FARMASI RAWAT INAP
// ═══════════════════════════════════════════════════════════════════════════════
if (!empty($obat)) {
    $total_obat = (float) array_sum(array_column($obat, 'subtotal'));
    $grand_total += $total_obat;
    sectionHead($ws, $r, 'FARMASI (RAWAT INAP)', $total_obat, '1A2744');
    $r++;
    tableHeader($ws, $r);
    $r++;
    $alt = false;
    foreach ($obat as $ob) {
        dataRow($ws, $r, $ob['nama_obat'], $ob['qty'], 'item', safeF($ob['harga_jual']), safeF($ob['subtotal']), $alt);
        $alt = !$alt;
        $r++;
    }
    subtotalRow($ws, $r, 'Subtotal Farmasi Ranap', $total_obat);
    $r++;
    blankRow($ws, $r);
    $r++;
}

// ═══════════════════════════════════════════════════════════════════════════════
// FARMASI OBAT PULANG
// ═══════════════════════════════════════════════════════════════════════════════
if (!empty($obat_pulang)) {
    $total_obat_plg = (float) array_sum(array_column($obat_pulang, 'subtotal'));
    $grand_total += $total_obat_plg;
    sectionHead($ws, $r, 'FARMASI (OBAT PULANG)', $total_obat_plg, '1A2744');
    $r++;
    tableHeader($ws, $r);
    $r++;
    $alt = false;
    foreach ($obat_pulang as $ob) {
        dataRow($ws, $r, $ob['nama_obat'], $ob['qty'], 'item', safeF($ob['harga_jual']), safeF($ob['subtotal']), $alt);
        $alt = !$alt;
        $r++;
    }
    subtotalRow($ws, $r, 'Subtotal Obat Pulang', $total_obat_plg);
    $r++;
    blankRow($ws, $r);
    $r++;
}

// ═══════════════════════════════════════════════════════════════════════════════
// GRAND TOTAL
// ═══════════════════════════════════════════════════════════════════════════════
$ws->mergeCells("A{$r}:D{$r}");
$ws->setCellValue("A{$r}", 'TOTAL TAGIHAN');
$ws->setCellValue("E{$r}", $grand_total);
applyStyle($ws, "A{$r}:E{$r}", [
    'font' => ['bold' => true, 'size' => 12, 'name' => 'Arial', 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1A2744']],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => 'C8391A']]],
]);
$ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$ws->getStyle("E{$r}")->getNumberFormat()->setFormatCode('#,##0.00');
$ws->getStyle("E{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$ws->getRowDimension($r)->setRowHeight(22);
$r++;

// Terbilang (angka ke teks sederhana – cukup tampilkan nominal)
$ws->mergeCells("A{$r}:E{$r}");
$ws->setCellValue("A{$r}", 'Terbilang: Rp ' . number_format($grand_total, 2, ',', '.'));
applyStyle($ws, "A{$r}:E{$r}", [
    'font' => ['size' => 9, 'name' => 'Arial', 'italic' => true, 'color' => ['rgb' => '6B6860']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EBF0FA']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D6D3C8']]],
]);
$ws->getRowDimension($r)->setRowHeight(14);
$r++;

// ── Print settings ────────────────────────────────────────────────────────────
$ws->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT);
$ws->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
$ws->getPageSetup()->setFitToWidth(1);
$ws->getPageSetup()->setFitToHeight(0);
$ws->getPageMargins()->setTop(0.5)->setBottom(0.5)->setLeft(0.5)->setRight(0.5);
$ws->getHeaderFooter()->setOddHeader('&C&B&14 RSUD MERAUKE — Detail Tagihan');
$ws->getHeaderFooter()->setOddFooter('&L' . $hdr['nm_pasien'] . ' / ' . $no_rawat . '&RHalaman &P dari &N');

// ── Freeze panes ─────────────────────────────────────────────────────────────
$ws->freezePane('A14');

// ── Output ────────────────────────────────────────────────────────────────────
$filename = 'tagihan_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $no_rawat) . '_' . date('Ymd') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
(new Xlsx($spreadsheet))->save('php://output');
$spreadsheet->disconnectWorksheets();
unset($spreadsheet);
exit;