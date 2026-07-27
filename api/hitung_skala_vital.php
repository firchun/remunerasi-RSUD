<?php
$td    = $_POST['td'] ?? '-';
$nadi  = $_POST['nadi'] ?? '-';
$rr    = $_POST['rr'] ?? '-';
$suhu  = $_POST['suhu'] ?? '-';
$spo2  = $_POST['spo2'] ?? '-';
$nyeri = $_POST['nyeri'] ?? '-';

function parseSistolik($td)
{
    $td_clean = str_replace(',', '.', $td);
    if (!empty($td_clean) && $td_clean !== '-' && $td_clean !== '0') {
        $parts = explode('/', $td_clean);
        return intval(trim($parts[0]));
    }
    return 0;
}
function parseNumeric($val) { return floatval(str_replace(',', '.', $val)); }
function parseNyeri($val) { return intval(preg_replace('/[^0-9].*/', '', $val)); }

function hitungSkalaDariVitalSign($td, $nadi, $rr, $suhu, $spo2, $nyeri)
{
    $sistolik = parseSistolik($td);
    $nadi_val = intval(parseNumeric($nadi));
    $rr_val = intval(parseNumeric($rr));
    $suhu_val = parseNumeric($suhu);
    $spo2_val = intval(parseNumeric($spo2));
    $nyeri_val = parseNyeri($nyeri);
    $s1 = false;
    if ($suhu_val > 0 && ($suhu_val <= 35.0 || $suhu_val >= 39.1)) $s1 = true;
    if ($nyeri_val >= 9 && $nyeri_val <= 10) $s1 = true;
    if ($sistolik > 0 && ($sistolik <= 90 || $sistolik >= 220)) $s1 = true;
    if ($nadi_val > 0 && ($nadi_val <= 40 || $nadi_val >= 131)) $s1 = true;
    if ($spo2_val > 0 && $spo2_val <= 91) $s1 = true;
    if ($rr_val > 0 && ($rr_val <= 8 || $rr_val >= 25)) $s1 = true;
    if ($s1) return 1;
    $s2 = false;
    if ($suhu_val > 0 && ($suhu_val <= 35.0 || $suhu_val >= 38.1)) $s2 = true;
    if ($nyeri_val >= 7 && $nyeri_val <= 10) $s2 = true;
    if ($sistolik > 0 && ($sistolik <= 100 || $sistolik >= 220)) $s2 = true;
    if ($nadi_val > 0 && ($nadi_val <= 50 || $nadi_val >= 111)) $s2 = true;
    if ($spo2_val > 0 && $spo2_val <= 93) $s2 = true;
    if ($rr_val > 0 && ($rr_val <= 8 || $rr_val >= 21)) $s2 = true;
    if ($s2) return 2;
    $s3 = false;
    if ($suhu_val > 0 && ($suhu_val <= 36.0 || $suhu_val >= 38.1)) $s3 = true;
    if ($sistolik > 0 && ($sistolik <= 110 || $sistolik >= 220)) $s3 = true;
    if ($nadi_val > 0 && ($nadi_val <= 50 || $nadi_val >= 91)) $s3 = true;
    if ($spo2_val > 0 && $spo2_val <= 95) $s3 = true;
    if ($rr_val > 0 && ($rr_val <= 11 || $rr_val >= 21)) $s3 = true;
    if ($s3) return 3;
    return 5;
}

$skala = hitungSkalaDariVitalSign($td, $nadi, $rr, $suhu, $spo2, $nyeri);

$names = [
    1 => 'SKALA 1 - RESUSITASI (Gawat Darurat)',
    2 => 'SKALA 2 - EMERGENT (Gawat Darurat)',
    3 => 'SKALA 3 - URGENT',
    4 => 'SKALA 4 - SEMI URGENT',
    5 => 'SKALA 5 - NON URGENT',
];

echo json_encode([
    'success' => true,
    'skala' => $skala,
    'skala_nama' => $names[$skala] ?? 'TIDAK DIKETAHUI',
]);
