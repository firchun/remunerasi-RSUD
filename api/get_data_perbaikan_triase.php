<?php
require_once '../config/conf.php';
$koneksi = bukakoneksi();

$tgl_awal  = $_POST['tgl_awal'] ?? '';
$tgl_akhir = $_POST['tgl_akhir'] ?? '';
$filter_status = $_POST['filter_status'] ?? '';
$tcari = $_POST['tcari'] ?? '';

$where = "WHERE dti.no_rawat IS NOT NULL";

if (!empty($tgl_awal)) {
    $where .= " AND dti.tgl_kunjungan >= '$tgl_awal 00:00:00'";
}
if (!empty($tgl_akhir)) {
    $where .= " AND dti.tgl_kunjungan <= '$tgl_akhir 23:59:59'";
}
if (!empty($tcari)) {
    $cari = mysqli_real_escape_string($koneksi, $tcari);
    $where .= " AND (dti.no_rawat LIKE '%$cari%' OR p.nm_pasien LIKE '%$cari%' OR rp.no_rkm_medis LIKE '%$cari%')";
}

$skala_join = "
LEFT JOIN (SELECT no_rawat, 1 AS ada FROM data_triase_igddetail_skala1 GROUP BY no_rawat) s1 ON dti.no_rawat = s1.no_rawat
LEFT JOIN (SELECT no_rawat, 1 AS ada FROM data_triase_igddetail_skala2 GROUP BY no_rawat) s2 ON dti.no_rawat = s2.no_rawat
LEFT JOIN (SELECT no_rawat, 1 AS ada FROM data_triase_igddetail_skala3 GROUP BY no_rawat) s3 ON dti.no_rawat = s3.no_rawat
LEFT JOIN (SELECT no_rawat, 1 AS ada FROM data_triase_igddetail_skala4 GROUP BY no_rawat) s4 ON dti.no_rawat = s4.no_rawat
LEFT JOIN (SELECT no_rawat, 1 AS ada FROM data_triase_igddetail_skala5 GROUP BY no_rawat) s5 ON dti.no_rawat = s5.no_rawat
";

$filter_where = '';
if ($filter_status === 'sesuai_gd') {
    $filter_where = " AND (s1.ada=1 OR s2.ada=1)";
} elseif ($filter_status === 'urgent') {
    $filter_where = " AND s1.ada IS NULL AND s2.ada IS NULL AND s3.ada=1";
} elseif ($filter_status === 'tidak_sesuai_gd') {
    $filter_where = " AND s1.ada IS NULL AND s2.ada IS NULL AND s3.ada IS NULL AND (s4.ada=1 OR s5.ada=1)";
} elseif ($filter_status === 'bpjs') {
    $filter_where = " AND rp.kd_pj IN ('BPJ','BPK')";
} elseif ($filter_status === 'bpjs_non_gd') {
    $filter_where = " AND rp.kd_pj IN ('BPJ','BPK') AND s1.ada IS NULL AND s2.ada IS NULL AND s3.ada IS NULL AND (s4.ada=1 OR s5.ada=1)";
} elseif ($filter_status === 'tanpa_skala') {
    $filter_where = " AND s1.ada IS NULL AND s2.ada IS NULL AND s3.ada IS NULL AND s4.ada IS NULL AND s5.ada IS NULL";
} elseif ($filter_status === 'all') {
    // no filter
} elseif ($filter_status === 'discrepancy' || $filter_status === 'bpjs_discrepancy') {
    $filter_where = " AND dti.no_rawat IS NOT NULL";
}

$sql = "
SELECT
    dti.no_rawat,
    dti.tgl_kunjungan,
    dti.cara_masuk,
    dti.alasan_kedatangan,
    dti.kode_kasus,
    mk.macam_kasus,
    dti.tekanan_darah,
    dti.nadi,
    dti.pernapasan,
    dti.suhu,
    dti.saturasi_o2,
    dti.nyeri,
    rp.no_rkm_medis,
    p.nm_pasien,
    rp.kd_pj,
    pj.png_jawab,
    rp.kd_poli,
    bs.no_sep,
    COALESCE(s1.ada, 0) AS skala1,
    COALESCE(s2.ada, 0) AS skala2,
    COALESCE(s3.ada, 0) AS skala3,
    COALESCE(s4.ada, 0) AS skala4,
    COALESCE(s5.ada, 0) AS skala5
FROM data_triase_igd dti
JOIN reg_periksa rp ON dti.no_rawat = rp.no_rawat
LEFT JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
LEFT JOIN penjab pj ON rp.kd_pj = pj.kd_pj
LEFT JOIN master_triase_macam_kasus mk ON dti.kode_kasus = mk.kode_kasus
LEFT JOIN bridging_sep bs ON dti.no_rawat = bs.no_rawat
$skala_join
$where $filter_where
ORDER BY dti.tgl_kunjungan DESC
LIMIT 500
";

$result = mysqli_query($koneksi, $sql);
if (!$result) {
    echo json_encode(['error' => mysqli_error($koneksi)]);
    exit;
}

// ─── Helper: parse vital sign values ─────────────────────────────────────
function parseSistolik($td)
{
    $td_clean = str_replace(',', '.', $td);
    if (!empty($td_clean) && $td_clean !== '-' && $td_clean !== '0') {
        $parts = explode('/', $td_clean);
        return intval(trim($parts[0]));
    }
    return 0;
}
function parseNumeric($val)
{
    $v = str_replace(',', '.', $val);
    return floatval($v);
}
function parseNyeri($val)
{
    return intval(preg_replace('/[^0-9].*/', '', $val));
}

// ─── Hitung expected skala dari vital sign ──────────────────────────────
function hitungSkalaDariVitalSign($td, $nadi, $rr, $suhu, $spo2, $nyeri)
{
    $sistolik = parseSistolik($td);
    $nadi_val = intval(parseNumeric($nadi));
    $rr_val = intval(parseNumeric($rr));
    $suhu_val = parseNumeric($suhu);
    $spo2_val = intval(parseNumeric($spo2));
    $nyeri_val = parseNyeri($nyeri);

    // Skala 1
    $s1 = false;
    if ($suhu_val > 0 && ($suhu_val <= 35.0 || $suhu_val >= 39.1)) $s1 = true;
    if ($nyeri_val >= 9 && $nyeri_val <= 10) $s1 = true;
    if ($sistolik > 0 && ($sistolik <= 90 || $sistolik >= 220)) $s1 = true;
    if ($nadi_val > 0 && ($nadi_val <= 40 || $nadi_val >= 131)) $s1 = true;
    if ($spo2_val > 0 && $spo2_val <= 91) $s1 = true;
    if ($rr_val > 0 && ($rr_val <= 8 || $rr_val >= 25)) $s1 = true;
    if ($s1) return 1;

    // Skala 2
    $s2 = false;
    if ($suhu_val > 0 && ($suhu_val <= 35.0 || $suhu_val >= 38.1)) $s2 = true;
    if ($nyeri_val >= 7 && $nyeri_val <= 10) $s2 = true;
    if ($sistolik > 0 && ($sistolik <= 100 || $sistolik >= 220)) $s2 = true;
    if ($nadi_val > 0 && ($nadi_val <= 50 || $nadi_val >= 111)) $s2 = true;
    if ($spo2_val > 0 && $spo2_val <= 93) $s2 = true;
    if ($rr_val > 0 && ($rr_val <= 8 || $rr_val >= 21)) $s2 = true;
    if ($s2) return 2;

    // Skala 3
    $s3 = false;
    if ($suhu_val > 0 && ($suhu_val <= 36.0 || $suhu_val >= 38.1)) $s3 = true;
    if ($sistolik > 0 && ($sistolik <= 110 || $sistolik >= 220)) $s3 = true;
    if ($nadi_val > 0 && ($nadi_val <= 50 || $nadi_val >= 91)) $s3 = true;
    if ($spo2_val > 0 && $spo2_val <= 95) $s3 = true;
    if ($rr_val > 0 && ($rr_val <= 11 || $rr_val >= 21)) $s3 = true;
    if ($s3) return 3;

    return 5;
}

// ─── Cek apakah minimal 1 vital sign mendukung actual_skala ─────────────
function isSupportedByVitalSigns($actual_skala, $td, $nadi, $rr, $suhu, $spo2, $nyeri)
{
    if ($actual_skala == 0) return false;

    $sistolik = parseSistolik($td);
    $nadi_val = intval(parseNumeric($nadi));
    $rr_val = intval(parseNumeric($rr));
    $suhu_val = parseNumeric($suhu);
    $spo2_val = intval(parseNumeric($spo2));
    $nyeri_val = parseNyeri($nyeri);

    if ($actual_skala == 1) {
        if ($suhu_val > 0 && ($suhu_val <= 35.0 || $suhu_val >= 39.1)) return true;
        if ($nyeri_val >= 9 && $nyeri_val <= 10) return true;
        if ($sistolik > 0 && ($sistolik <= 90 || $sistolik >= 220)) return true;
        if ($nadi_val > 0 && ($nadi_val <= 40 || $nadi_val >= 131)) return true;
        if ($spo2_val > 0 && $spo2_val <= 91) return true;
        if ($rr_val > 0 && ($rr_val <= 8 || $rr_val >= 25)) return true;
        return false;
    }
    if ($actual_skala == 2) {
        if ($suhu_val > 0 && ($suhu_val <= 35.0 || $suhu_val >= 38.1)) return true;
        if ($nyeri_val >= 7 && $nyeri_val <= 10) return true;
        if ($sistolik > 0 && ($sistolik <= 100 || $sistolik >= 220)) return true;
        if ($nadi_val > 0 && ($nadi_val <= 50 || $nadi_val >= 111)) return true;
        if ($spo2_val > 0 && $spo2_val <= 93) return true;
        if ($rr_val > 0 && ($rr_val <= 8 || $rr_val >= 21)) return true;
        return false;
    }
    if ($actual_skala == 3 || $actual_skala == 4) {
        if ($suhu_val > 0 && ($suhu_val <= 36.0 || $suhu_val >= 38.1)) return true;
        if ($sistolik > 0 && ($sistolik <= 110 || $sistolik >= 220)) return true;
        if ($nadi_val > 0 && ($nadi_val <= 50 || $nadi_val >= 91)) return true;
        if ($spo2_val > 0 && $spo2_val <= 95) return true;
        if ($rr_val > 0 && ($rr_val <= 11 || $rr_val >= 21)) return true;
        return false;
    }
    // Skala 5 = semua normal, always supported
    return true;
}

function getSkalaNama($skala)
{
    $names = [
        1 => 'SKALA 1 - RESUSITASI',
        2 => 'SKALA 2 - EMERGENT',
        3 => 'SKALA 3 - URGENT',
        4 => 'SKALA 4 - SEMI URGENT',
        5 => 'SKALA 5 - NON URGENT',
    ];
    return $names[$skala] ?? 'TIDAK DIKETAHUI';
}

// ─── Process each row ────────────────────────────────────────────────────
$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $actual_skala = 0;
    if ($row['skala1']) $actual_skala = 1;
    elseif ($row['skala2']) $actual_skala = 2;
    elseif ($row['skala3']) $actual_skala = 3;
    elseif ($row['skala4']) $actual_skala = 4;
    elseif ($row['skala5']) $actual_skala = 5;

    $expected_skala = hitungSkalaDariVitalSign(
        $row['tekanan_darah'], $row['nadi'], $row['pernapasan'],
        $row['suhu'], $row['saturasi_o2'], $row['nyeri']
    );

    $is_supported = isSupportedByVitalSigns(
        $actual_skala,
        $row['tekanan_darah'], $row['nadi'], $row['pernapasan'],
        $row['suhu'], $row['saturasi_o2'], $row['nyeri']
    );

    $actual_gd = ($actual_skala == 1 || $actual_skala == 2);
    $expected_gd = ($expected_skala == 1 || $expected_skala == 2);
    $is_discrepancy = ($actual_skala > 0 && $expected_skala > 0 && $actual_skala != $expected_skala);
    $is_bpjs = in_array($row['kd_pj'], ['BPJ', 'BPK']);

    $row['actual_gd'] = $actual_gd;
    $row['expected_gd'] = $expected_gd;
    $row['actual_skala'] = $actual_skala;
    $row['actual_skala_nama'] = $actual_skala ? getSkalaNama($actual_skala) : 'TIDAK ADA';
    $row['expected_skala'] = $expected_skala;
    $row['expected_skala_nama'] = getSkalaNama($expected_skala);
    $row['is_discrepancy'] = $is_discrepancy;
    $row['is_supported'] = $is_supported;
    $row['is_bpjs'] = $is_bpjs;

    if ($actual_skala == 0) $row['actual_status'] = 'TANPA SKALA';
    elseif ($actual_gd) $row['actual_status'] = 'SESUAI GD';
    elseif ($actual_skala == 3) $row['actual_status'] = 'URGENT';
    else $row['actual_status'] = 'TIDAK SESUAI GD';

    $row['expected_status'] = $expected_gd ? 'GD' : 'NON GD';

    $row['bpjs_note'] = '';
    if ($is_bpjs && $actual_skala > 0) {
        if ($is_discrepancy) {
            if ($expected_gd && !$actual_gd) $row['bpjs_note'] = 'HARUSNYA GD';
            elseif (!$expected_gd && $actual_gd) $row['bpjs_note'] = 'OVER-TRIAGE';
            else $row['bpjs_note'] = 'SKALA TIDAK COCOK';
        } elseif (!$expected_gd) $row['bpjs_note'] = 'NON GD (SESUAI)';
        elseif ($expected_gd) $row['bpjs_note'] = 'GD (SESUAI)';
    }

    $data[] = $row;
}

// ─── In-memory filters ──────────────────────────────────────────────────
if ($filter_status === 'discrepancy') {
    $data = array_values(array_filter($data, fn($r) => $r['is_discrepancy']));
} elseif ($filter_status === 'bpjs_discrepancy') {
    $data = array_values(array_filter($data, fn($r) => $r['is_bpjs'] && $r['is_discrepancy']));
} elseif ($filter_status === '') {
    // Default: hanya tampilkan data yang TIDAK didukung oleh vital sign
    $data = array_values(array_filter($data, fn($r) => !$r['is_supported']));
}

echo json_encode([
    'success' => true,
    'data' => $data
]);

mysqli_close($koneksi);
