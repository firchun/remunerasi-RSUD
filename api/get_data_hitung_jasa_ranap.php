<?php
set_time_limit(300);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/conf.php';
$koneksi = bukakoneksi();

$draw   = $_POST['draw']   ?? 1;
$start  = $_POST['start']  ?? 0;
$length = $_POST['length'] ?? 25;
$search = $_POST['search']['value'] ?? '';

$bulan     = $_POST['bulan'] ?? date('m');
$tahun     = $_POST['tahun'] ?? date('Y');
$grup_bangsal = $_POST['grup_bangsal'] ?? '';
$kd_pj     = $_POST['kd_pj'] ?? '';
$tcari     = $_POST['tcari'] ?? '';
$filter_sep = $_POST['filter_sep'] ?? 'semua';
$status_pulang = $_POST['status_pulang'] ?? 'semua';

$bulan_padded = str_pad($bulan, 2, '0', STR_PAD_LEFT);
$tgl_awal  = "$tahun-$bulan_padded-01 00:00:00";
$tgl_akhir = date("Y-m-t 23:59:59", strtotime($tgl_awal));

function buildWhere($koneksi, $tgl_awal, $tgl_akhir, $grup_bangsal, $kd_pj, $status_pulang, $filter_sep, $tcari, $search) {
    $w = "
        AND reg_periksa.status_lanjut = 'Ranap'
        AND reg_periksa.stts != 'Batal'
        AND reg_periksa.stts != 'Belum'
        AND (
            CONCAT(kamar_inap.tgl_keluar, ' ', kamar_inap.jam_keluar)
            BETWEEN '$tgl_awal' AND '$tgl_akhir'
        )
    ";
    if (!empty($grup_bangsal)) {
        $w .= " AND bangsal.kd_bangsal IN (SELECT kd_bangsal FROM v_bangsal_grup WHERE grup_bangsal = '$grup_bangsal')";
    }
    if (!empty($kd_pj)) {
        $w .= " AND reg_periksa.kd_pj = '$kd_pj'";
    }
    if ($status_pulang === 'sudah_pulang') {
        $w .= " AND kamar_inap.stts_pulang != '-'";
    } elseif ($status_pulang === 'belum_pulang') {
        $w .= " AND kamar_inap.stts_pulang = '-'";
    }
    if ($filter_sep == 'ada') {
        $w .= " AND EXISTS (SELECT 1 FROM bridging_sep bs WHERE bs.no_rawat = reg_periksa.no_rawat AND bs.no_sep IS NOT NULL AND bs.no_sep != '' AND bs.no_sep != '-')";
    } elseif ($filter_sep == 'tidak_ada') {
        $w .= " AND NOT EXISTS (SELECT 1 FROM bridging_sep bs WHERE bs.no_rawat = reg_periksa.no_rawat AND bs.no_sep IS NOT NULL AND bs.no_sep != '' AND bs.no_sep != '-')";
    }
    if (!empty($tcari)) {
        $w .= " AND (
            reg_periksa.no_rawat LIKE '%$tcari%'
            OR pasien.nm_pasien LIKE '%$tcari%'
            OR dokter.nm_dokter LIKE '%$tcari%'
            OR reg_periksa.no_rkm_medis LIKE '%$tcari%'
            OR bridging_sep.no_sep LIKE '%$tcari%'
            OR bangsal.nm_bangsal LIKE '%$tcari%'
        )";
    }
    if (!empty($search)) {
        $w .= " AND (
            reg_periksa.no_rawat LIKE '%$search%'
            OR reg_periksa.no_rkm_medis LIKE '%$search%'
            OR pasien.nm_pasien LIKE '%$search%'
            OR dokter.nm_dokter LIKE '%$search%'
            OR bangsal.nm_bangsal LIKE '%$search%'
            OR bridging_sep.no_sep LIKE '%$search%'
        )";
    }
    return $w;
}

$where = buildWhere($koneksi, $tgl_awal, $tgl_akhir, $grup_bangsal, $kd_pj, $status_pulang, $filter_sep, $tcari, $search);

$count_query = "
SELECT COUNT(DISTINCT reg_periksa.no_rawat) AS total
FROM reg_periksa
JOIN pasien ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis
JOIN penjab ON reg_periksa.kd_pj = penjab.kd_pj
LEFT JOIN bridging_sep ON bridging_sep.no_rawat = reg_periksa.no_rawat
LEFT JOIN dokter ON reg_periksa.kd_dokter = dokter.kd_dokter
LEFT JOIN kamar_inap ON kamar_inap.no_rawat = reg_periksa.no_rawat AND kamar_inap.stts_pulang != 'Pindah Kamar'
LEFT JOIN kamar ON kamar_inap.kd_kamar = kamar.kd_kamar
LEFT JOIN bangsal ON kamar.kd_bangsal = bangsal.kd_bangsal
WHERE 1=1 $where
";
$c_total = mysqli_fetch_assoc(mysqli_query($koneksi, $count_query));
$total_records = $c_total['total'];

$filtered_records = $total_records;

// ── Get paginated IDs ──────────────────────────────────────────────
$id_query = "
SELECT DISTINCT reg_periksa.no_rawat, reg_periksa.no_rkm_medis, reg_periksa.tgl_registrasi,
    pasien.nm_pasien, IFNULL(bridging_sep.no_sep, '-') AS no_sep,
    bangsal.nm_bangsal, MIN(dokter.nm_dokter) AS nm_dokter,
    kamar_inap.tgl_masuk, kamar_inap.lama, kamar_inap.stts_pulang,
    penjab.png_jawab
FROM reg_periksa
JOIN pasien ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis
JOIN penjab ON reg_periksa.kd_pj = penjab.kd_pj
LEFT JOIN bridging_sep ON bridging_sep.no_rawat = reg_periksa.no_rawat
LEFT JOIN dokter ON reg_periksa.kd_dokter = dokter.kd_dokter
LEFT JOIN kamar_inap ON kamar_inap.no_rawat = reg_periksa.no_rawat AND kamar_inap.stts_pulang != 'Pindah Kamar'
LEFT JOIN kamar ON kamar_inap.kd_kamar = kamar.kd_kamar
LEFT JOIN bangsal ON kamar.kd_bangsal = bangsal.kd_bangsal
WHERE 1=1 $where
GROUP BY reg_periksa.no_rawat, reg_periksa.no_rkm_medis, reg_periksa.tgl_registrasi,
    pasien.nm_pasien, bridging_sep.no_sep, bangsal.nm_bangsal,
    kamar_inap.tgl_masuk, kamar_inap.lama, kamar_inap.stts_pulang, penjab.png_jawab
ORDER BY bangsal.nm_bangsal, kamar_inap.tgl_masuk DESC
LIMIT $start, $length
";
$id_result = mysqli_query($koneksi, $id_query);
if (!$id_result) {
    echo json_encode(["draw" => intval($draw), "recordsTotal" => 0, "recordsFiltered" => 0, "data" => [], "error" => mysqli_error($koneksi)]);
    exit;
}

$ids = [];
$rows = [];
$poliMap = [];
while ($r = mysqli_fetch_assoc($id_result)) {
    $ids[] = "'" . mysqli_real_escape_string($koneksi, $r['no_rawat']) . "'";
    $rows[$r['no_rawat']] = $r;
}
$in = implode(',', $ids);

if (empty($ids)) {
    echo json_encode(["draw" => intval($draw), "recordsTotal" => intval($total_records), "recordsFiltered" => intval($filtered_records), "data" => []]);
    exit;
}

// ── Batch: tindakan inap ────────────────────────────────────────────
$tindakanMap = [];
$tq = mysqli_query($koneksi, "SELECT no_rawat,
    IFNULL(SUM(jns.tarif_tindakandr), 0) AS total_tindakan_dr,
    IFNULL(SUM(jns.tarif_tindakanpr), 0) AS total_tindakan_pr,
    IFNULL(SUM(jns.menejemen), 0) AS total_menejemen_tindakan,
    IFNULL(SUM(jns.total_byrdrpr), 0) AS total_biaya_rawat
    FROM rawat_inap_drpr drpr
    JOIN jns_perawatan_inap jns ON drpr.kd_jenis_prw = jns.kd_jenis_prw
    WHERE drpr.no_rawat IN ($in) AND drpr.kd_jenis_prw NOT IN ('RI01330','RI01331','RI01332','RI01337','RI00267','RI000276','RI00345','RI00751','RI01314','RI01315','RI01316','RI01317','RI01306','RI01307','RI01308','RI01309','RI00724','RI01918','RI01326','RI01327','RI01328','RI01329','RI00805','RI01373','RI01374','RI01375','RI01376','RI01365','RI01366','RI01367','RI01368','RI00778','RI01396','RI01385','RI01386','RI01387','RI01388') GROUP BY no_rawat");
if ($tq) while ($t = mysqli_fetch_assoc($tq)) $tindakanMap[$t['no_rawat']] = $t;

// ── Batch: lab ──────────────────────────────────────────────────────
$labMap = [];
$lr = mysqli_query($koneksi, "SELECT no_rawat,
    SUM(IFNULL(jns_perawatan_lab.tarif_tindakan_dokter,0)) AS total_dokter_lab,
    SUM(IFNULL(jns_perawatan_lab.tarif_tindakan_petugas,0)) AS total_petugas_lab,
    SUM(IFNULL(jns_perawatan_lab.menejemen,0)) AS total_menejemen_lab
    FROM periksa_lab JOIN jns_perawatan_lab ON periksa_lab.kd_jenis_prw = jns_perawatan_lab.kd_jenis_prw
    WHERE periksa_lab.no_rawat IN ($in) AND periksa_lab.status = 'Ranap' GROUP BY no_rawat");
if ($lr) while ($l = mysqli_fetch_assoc($lr)) $labMap[$l['no_rawat']] = $l;

// ── Batch: rad ──────────────────────────────────────────────────────
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

// ── Batch: obat ─────────────────────────────────────────────────────
$obatMap = [];
$or = mysqli_query($koneksi, "SELECT no_rawat, SUM(IFNULL(total,0)) AS total_obat
    FROM detail_pemberian_obat WHERE no_rawat IN ($in) AND status = 'Ranap' GROUP BY no_rawat");
if ($or) while ($o = mysqli_fetch_assoc($or)) $obatMap[$o['no_rawat']] = floatval($o['total_obat']);

// ── Batch: resep ────────────────────────────────────────────────────
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

// ── Batch: BPJS lookup ──────────────────────────────────────────────
$bpjs_lookup = [];
$bulan_int = (int)$bulan;
$bpjs_result = mysqli_query($koneksi, "SELECT data FROM bpjs_verifikasi WHERE bulan = '$bulan_int' AND tahun = '$tahun' AND jenis = 'ranap' ORDER BY created_at DESC");
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

// ── Process rows ────────────────────────────────────────────────────
$data = [];
foreach ($rows as $nr => $row) {
    $td = $tindakanMap[$nr] ?? null;
    $row['total_tindakan_dr'] = floatval($td['total_tindakan_dr'] ?? 0);
    $row['total_tindakan_pr'] = floatval($td['total_tindakan_pr'] ?? 0);
    $row['total_menejemen_tindakan'] = floatval($td['total_menejemen_tindakan'] ?? 0);
    $row['total_biaya_rawat'] = floatval($td['total_biaya_rawat'] ?? 0);
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
    $row['total_obat_ppn'] = $row['total_obat'] * 1.11;

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
    $row['jumlah_resep_racikan'] = $total_racikan;
    $row['jumlah_resep_non_racikan'] = $total_non_racikan;
    $row['jumlah_resep_operasi'] = $total_resep_operasi;
    $jasa_obat = 0;
    if ($total_racikan > 0) $jasa_obat = 25000;
    elseif ($total_non_racikan > 0) $jasa_obat = 15000;
    $row['jasa_farmasi'] = $jasa_obat + ($total_resep_operasi > 0 ? 35000 : 0);

    $row['total_non_medis'] = $row['total_menejemen_tindakan'] + $row['total_menejemen_lab'] + $row['total_menejemen_radiologi'];
    $row['total_jasa'] = $row['jasa_tindakan'] + $row['jasa_farmasi'] + $row['jasa_lab'] + $row['jasa_radiologi'];

    $tj = $row['total_jasa'];
    $row['persen_dpjp'] = $tj > 0 ? round($row['total_tindakan_dr'] / $tj * 100, 2) : 0;
    $row['persen_perawat'] = $tj > 0 ? round($row['total_tindakan_pr'] / $tj * 100, 2) : 0;
    $row['persen_farmasi'] = $tj > 0 ? round($row['jasa_farmasi'] / $tj * 100, 2) : 0;
    $row['persen_dokter_lab'] = $tj > 0 ? round($row['total_dokter_lab'] / $tj * 100, 2) : 0;
    $row['persen_analis_lab'] = $tj > 0 ? round($row['total_petugas_lab'] / $tj * 100, 2) : 0;
    $row['persen_dokter_radiologi'] = $tj > 0 ? round($row['total_dokter_radiologi'] / $tj * 100, 2) : 0;
    $row['persen_radiografer'] = $tj > 0 ? round($row['total_petugas_radiologi'] / $tj * 100, 2) : 0;
    $row['persen_non_medis'] = $tj > 0 ? round($row['total_non_medis'] / $tj * 100, 2) : 0;

    $row['total_bpjs'] = $bpjs_lookup[$row['no_sep']] ?? 0;
    $row['kolom_44'] = $row['total_bpjs'] * 0.44;
    $tb44 = $row['kolom_44'];
    $row['sisa_bpjs'] = $row['total_bpjs'] - $row['kolom_44'];
    $row['jumlah_dpjp'] = $tb44 > 0 ? round($row['persen_dpjp'] / 100 * $tb44) : 0;
    $row['jumlah_perawat'] = $tb44 > 0 ? round($row['persen_perawat'] / 100 * $tb44) : 0;
    $row['jumlah_farmasi'] = $tb44 > 0 ? round($row['persen_farmasi'] / 100 * $tb44) : 0;
    $row['jumlah_dokter_lab'] = $tb44 > 0 ? round($row['persen_dokter_lab'] / 100 * $tb44) : 0;
    $row['jumlah_analis_lab'] = $tb44 > 0 ? round($row['persen_analis_lab'] / 100 * $tb44) : 0;
    $row['jumlah_dokter_radiologi'] = $tb44 > 0 ? round($row['persen_dokter_radiologi'] / 100 * $tb44) : 0;
    $row['jumlah_radiografer'] = $tb44 > 0 ? round($row['persen_radiografer'] / 100 * $tb44) : 0;
    $row['jumlah_non_medis'] = $tb44 > 0 ? round($row['persen_non_medis'] / 100 * $tb44) : 0;

    $data[] = $row;
}

echo json_encode([
    "draw" => intval($draw),
    "recordsTotal" => intval($total_records),
    "recordsFiltered" => intval($filtered_records),
    "data" => $data
]);

mysqli_close($koneksi);
