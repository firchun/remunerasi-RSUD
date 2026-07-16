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
$kd_dokter    = $_POST['kd_dokter'] ?? '';
$kd_pj        = $_POST['kd_pj'] ?? '';
$tcari     = $_POST['tcari'] ?? '';
$status_pulang = $_POST['status_pulang'] ?? 'semua';

$bulan_padded = str_pad($bulan, 2, '0', STR_PAD_LEFT);
$tgl_awal  = "$tahun-$bulan_padded-01 00:00:00";
$tgl_akhir = date("Y-m-t 23:59:59", strtotime($tgl_awal));

function buildWhere($koneksi, $tgl_awal, $tgl_akhir, $grup_bangsal, $kd_pj, $status_pulang, $tcari, $search) {
    global $kd_dokter;
    $w = "
        AND reg_periksa.status_lanjut = 'Ranap'
        AND reg_periksa.stts != 'Batal'
    AND reg_periksa.kd_pj != 'BPJ'
AND reg_periksa.stts != 'Belum'
        AND (
            CONCAT(kamar_inap.tgl_keluar, ' ', kamar_inap.jam_keluar)
            BETWEEN '$tgl_awal' AND '$tgl_akhir'
        )
    ";
    global $kd_dokter;
    if (!empty($kd_dokter)) {
        $kd_dokter_esc = mysqli_real_escape_string($koneksi, $kd_dokter);
        $w .= " AND (
            EXISTS (SELECT 1 FROM rawat_inap_drpr r WHERE r.no_rawat = reg_periksa.no_rawat AND r.kd_dokter = '$kd_dokter_esc') OR
            EXISTS (SELECT 1 FROM operasi o WHERE o.no_rawat = reg_periksa.no_rawat AND (o.operator1 = '$kd_dokter_esc' OR o.operator2 = '$kd_dokter_esc' OR o.operator3 = '$kd_dokter_esc' OR o.dokter_anestesi = '$kd_dokter_esc' OR o.dokter_anak = '$kd_dokter_esc' OR o.dokter_umum = '$kd_dokter_esc' OR o.dokter_pjanak = '$kd_dokter_esc')) OR
            EXISTS (SELECT 1 FROM periksa_lab pl JOIN jns_perawatan_lab jpl ON pl.kd_jenis_prw=jpl.kd_jenis_prw WHERE pl.no_rawat = reg_periksa.no_rawat AND pl.kd_dokter = '$kd_dokter_esc' AND pl.status='Ranap') OR
            EXISTS (SELECT 1 FROM periksa_radiologi pr JOIN jns_perawatan_radiologi jpr ON pr.kd_jenis_prw=jpr.kd_jenis_prw WHERE pr.no_rawat = reg_periksa.no_rawat AND pr.kd_dokter = '$kd_dokter_esc' AND pr.status='Ranap')
        )";
    }

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
        if (!empty($tcari)) {
        $w .= " AND (
            reg_periksa.no_rawat LIKE '%$tcari%'
            OR pasien.nm_pasien LIKE '%$tcari%'
            OR dokter.nm_dokter LIKE '%$tcari%'
            OR reg_periksa.no_rkm_medis LIKE '%$tcari%'
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
        )";
    }
    return $w;
}

$where = buildWhere($koneksi, $tgl_awal, $tgl_akhir, $grup_bangsal, $kd_pj, $status_pulang, $tcari, $search);

$count_query = "
SELECT COUNT(DISTINCT reg_periksa.no_rawat) AS total
FROM reg_periksa
JOIN pasien ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis
JOIN penjab ON reg_periksa.kd_pj = penjab.kd_pj
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
    pasien.nm_pasien,     bangsal.nm_bangsal, MIN(dokter.nm_dokter) AS nm_dokter,
    kamar_inap.tgl_masuk, kamar_inap.lama, kamar_inap.stts_pulang,
    penjab.png_jawab
FROM reg_periksa
JOIN pasien ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis
JOIN penjab ON reg_periksa.kd_pj = penjab.kd_pj
LEFT JOIN dokter ON reg_periksa.kd_dokter = dokter.kd_dokter
LEFT JOIN kamar_inap ON kamar_inap.no_rawat = reg_periksa.no_rawat AND kamar_inap.stts_pulang != 'Pindah Kamar'
LEFT JOIN kamar ON kamar_inap.kd_kamar = kamar.kd_kamar
LEFT JOIN bangsal ON kamar.kd_bangsal = bangsal.kd_bangsal
WHERE 1=1 $where
GROUP BY reg_periksa.no_rawat, reg_periksa.no_rkm_medis, reg_periksa.tgl_registrasi,
    pasien.nm_pasien, bangsal.nm_bangsal,
    kamar_inap.tgl_masuk, kamar_inap.lama, kamar_inap.stts_pulang, penjab.png_jawab
ORDER BY bangsal.nm_bangsal, kamar_inap.tgl_masuk DESC

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
    IFNULL(SUM(CASE WHEN jns.kd_kategori NOT IN ('KP050', 'KP049') THEN jns.tarif_tindakandr ELSE 0 END), 0) AS total_tindakan_dr,
    IFNULL(SUM(CASE WHEN jns.kd_kategori NOT IN ('KP050', 'KP049') THEN jns.tarif_tindakanpr ELSE 0 END), 0) AS total_tindakan_pr,
    IFNULL(SUM(CASE WHEN jns.kd_kategori = 'KP050' THEN jns.tarif_tindakandr ELSE 0 END), 0) AS total_dr_operasi_kategori,
    IFNULL(SUM(CASE WHEN jns.kd_kategori = 'KP050' THEN jns.tarif_tindakanpr ELSE 0 END), 0) AS total_pr_operasi_kategori,
    IFNULL(SUM(CASE WHEN jns.kd_kategori = 'KP049' THEN jns.tarif_tindakandr ELSE 0 END), 0) AS total_dr_anestesi_kategori,
    IFNULL(SUM(CASE WHEN jns.kd_kategori = 'KP049' THEN jns.tarif_tindakanpr ELSE 0 END), 0) AS total_pr_anestesi_kategori,
    IFNULL(SUM(jns.menejemen), 0) AS total_menejemen_tindakan,
    IFNULL(SUM(jns.total_byrdrpr), 0) AS total_biaya_rawat
    FROM rawat_inap_drpr drpr
    JOIN jns_perawatan_inap jns ON drpr.kd_jenis_prw = jns.kd_jenis_prw
    WHERE drpr.no_rawat IN ($in) AND drpr.kd_jenis_prw NOT IN ('RI01330','RI01331','RI01332','RI01337','RI00267','RI000276','RI00345','RI00751','RI01314','RI01315','RI01316','RI01317','RI01306','RI01307','RI01308','RI01309','RI00724','RI01918','RI01326','RI01327','RI01328','RI01329','RI00805','RI01373','RI01374','RI01375','RI01376','RI01365','RI01366','RI01367','RI01368','RI00778','RI01396','RI01385','RI01386','RI01387','RI01388') GROUP BY no_rawat");
if ($tq) while ($t = mysqli_fetch_assoc($tq)) $tindakanMap[$t['no_rawat']] = $t;

// ── Batch: operasi ──────────────────────────────────────────────────────
$operasiMap = [];
$oq = mysqli_query($koneksi, "SELECT no_rawat,
    IFNULL(SUM(biayaoperator1 + biayaoperator2 + biayaoperator3), 0) AS paket_operator,
    IFNULL(SUM(biayaasisten_operator1 + biayaasisten_operator2 + biayaasisten_operator3), 0) AS paket_asisten,
    IFNULL(SUM(biayadokter_anestesi), 0) AS paket_dr_anestesi,
    IFNULL(SUM(biayaasisten_anestesi + biayaasisten_anestesi2), 0) AS paket_as_anestesi,
    IFNULL(SUM(biayadokter_anak), 0) AS paket_dr_anak,
    IFNULL(SUM(biayaperawaat_resusitas), 0) AS paket_pr_resusitas,
    IFNULL(SUM(biayabidan + biayabidan2 + biayabidan3), 0) AS paket_bidan,
    IFNULL(SUM(biayainstrumen), 0) AS paket_instrumen,
    IFNULL(SUM(biaya_omloop + biaya_omloop2 + biaya_omloop3 + biaya_omloop4 + biaya_omloop5), 0) AS paket_omloop,
    IFNULL(SUM(biaya_dokter_pjanak), 0) AS paket_dr_pjanak,
    IFNULL(SUM(biaya_dokter_umum), 0) AS paket_dr_umum,
    IFNULL(SUM(biayaperawat_luar), 0) AS paket_pr_luar
    FROM operasi
    WHERE no_rawat IN ($in) GROUP BY no_rawat");
if ($oq) while ($o = mysqli_fetch_assoc($oq)) $operasiMap[$o['no_rawat']] = $o;

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

// --- DOCTOR DISTRIBUTION BATCHES ---
$dpjpMap = [];
$q_dpjp = mysqli_query($koneksi, "SELECT r.no_rawat, r.kd_dokter, d.nm_dokter, SUM(j.tarif_tindakandr) as t_dr 
    FROM rawat_inap_drpr r JOIN jns_perawatan_inap j ON r.kd_jenis_prw = j.kd_jenis_prw JOIN dokter d ON r.kd_dokter = d.kd_dokter 
    WHERE r.no_rawat IN ($in) AND j.kd_kategori NOT IN ('KP050','KP049') GROUP BY r.no_rawat, r.kd_dokter");
if ($q_dpjp) while ($r = mysqli_fetch_assoc($q_dpjp)) $dpjpMap[$r['no_rawat']][] = $r;

$opKpMap = [];
$q_opkp = mysqli_query($koneksi, "SELECT r.no_rawat, r.kd_dokter, d.nm_dokter, SUM(j.tarif_tindakandr) as t_dr 
    FROM rawat_inap_drpr r JOIN jns_perawatan_inap j ON r.kd_jenis_prw = j.kd_jenis_prw JOIN dokter d ON r.kd_dokter = d.kd_dokter 
    WHERE r.no_rawat IN ($in) AND j.kd_kategori = 'KP050' GROUP BY r.no_rawat, r.kd_dokter");
if ($q_opkp) while ($r = mysqli_fetch_assoc($q_opkp)) $opKpMap[$r['no_rawat']][] = $r;

$anKpMap = [];
$q_ankp = mysqli_query($koneksi, "SELECT r.no_rawat, r.kd_dokter, d.nm_dokter, SUM(j.tarif_tindakandr) as t_dr 
    FROM rawat_inap_drpr r JOIN jns_perawatan_inap j ON r.kd_jenis_prw = j.kd_jenis_prw JOIN dokter d ON r.kd_dokter = d.kd_dokter 
    WHERE r.no_rawat IN ($in) AND j.kd_kategori = 'KP049' GROUP BY r.no_rawat, r.kd_dokter");
if ($q_ankp) while ($r = mysqli_fetch_assoc($q_ankp)) $anKpMap[$r['no_rawat']][] = $r;

$operasiDokterMap = [];
$q_op_doc = mysqli_query($koneksi, "
    SELECT o.no_rawat, 
           o.operator1, o.biayaoperator1, d1.nm_dokter as nm_op1,
           o.operator2, o.biayaoperator2, d2.nm_dokter as nm_op2,
           o.operator3, o.biayaoperator3, d3.nm_dokter as nm_op3,
           o.dokter_anestesi, o.biayadokter_anestesi, da.nm_dokter as nm_an,
           o.dokter_anak, o.biayadokter_anak, danak.nm_dokter as nm_anak,
           o.dokter_pjanak, o.biaya_dokter_pjanak, dpja.nm_dokter as nm_pja,
           o.dokter_umum, o.biaya_dokter_umum, du.nm_dokter as nm_umum
    FROM operasi o
    LEFT JOIN dokter d1 ON o.operator1 = d1.kd_dokter
    LEFT JOIN dokter d2 ON o.operator2 = d2.kd_dokter
    LEFT JOIN dokter d3 ON o.operator3 = d3.kd_dokter
    LEFT JOIN dokter da ON o.dokter_anestesi = da.kd_dokter
    LEFT JOIN dokter danak ON o.dokter_anak = danak.kd_dokter
    LEFT JOIN dokter dpja ON o.dokter_pjanak = dpja.kd_dokter
    LEFT JOIN dokter du ON o.dokter_umum = du.kd_dokter
    WHERE o.no_rawat IN ($in)
");
if ($q_op_doc) while ($r = mysqli_fetch_assoc($q_op_doc)) $operasiDokterMap[$r['no_rawat']][] = $r;

$labDokterMap = [];
$q_lab = mysqli_query($koneksi, "SELECT p.no_rawat, p.kd_dokter, d.nm_dokter, SUM(j.tarif_tindakan_dokter) as t_dr 
    FROM periksa_lab p JOIN jns_perawatan_lab j ON p.kd_jenis_prw = j.kd_jenis_prw JOIN dokter d ON p.kd_dokter = d.kd_dokter
    WHERE p.no_rawat IN ($in) AND p.status = 'Ranap' GROUP BY p.no_rawat, p.kd_dokter");
if ($q_lab) while ($r = mysqli_fetch_assoc($q_lab)) $labDokterMap[$r['no_rawat']][] = $r;

$radDokterMap = [];
$q_rad = mysqli_query($koneksi, "SELECT prk.no_rawat, prk.kd_dokter, d.nm_dokter, SUM(j.tarif_tindakan_dokter) as t_dr 
    FROM periksa_radiologi prk JOIN jns_perawatan_radiologi j ON prk.kd_jenis_prw = j.kd_jenis_prw JOIN dokter d ON prk.kd_dokter = d.kd_dokter
    WHERE prk.no_rawat IN ($in) AND prk.status = 'Ranap' GROUP BY prk.no_rawat, prk.kd_dokter");
if ($q_rad) while ($r = mysqli_fetch_assoc($q_rad)) $radDokterMap[$r['no_rawat']][] = $r;

// ── Batch: BPJS lookup ──────────────────────────────────────────────
$data = [];
foreach ($rows as $nr => $row) {
    $td = $tindakanMap[$nr] ?? null;
    $row['total_tindakan_dr'] = floatval($td['total_tindakan_dr'] ?? 0);
    $row['total_tindakan_pr'] = floatval($td['total_tindakan_pr'] ?? 0);
    $row['total_menejemen_tindakan'] = floatval($td['total_menejemen_tindakan'] ?? 0);
    $row['total_biaya_rawat'] = floatval($td['total_biaya_rawat'] ?? 0);
    $row['jasa_tindakan'] = $row['total_tindakan_dr'] + $row['total_tindakan_pr'] + $row['total_menejemen_tindakan'];

    $od = $operasiMap[$nr] ?? null;
    $row['jasa_operator'] = floatval($td['total_dr_operasi_kategori'] ?? 0) + floatval($od['paket_operator'] ?? 0);
    $row['jasa_asisten'] = floatval($td['total_pr_operasi_kategori'] ?? 0) + floatval($od['paket_asisten'] ?? 0);
    $row['jasa_dr_anestesi'] = floatval($td['total_dr_anestesi_kategori'] ?? 0) + floatval($od['paket_dr_anestesi'] ?? 0);
    $row['jasa_as_anestesi'] = floatval($td['total_pr_anestesi_kategori'] ?? 0) + floatval($od['paket_as_anestesi'] ?? 0);
    $row['jasa_dr_anak'] = floatval($od['paket_dr_anak'] ?? 0);
    $row['jasa_pr_resusitas'] = floatval($od['paket_pr_resusitas'] ?? 0);
    $row['jasa_bidan'] = floatval($od['paket_bidan'] ?? 0);
    $row['jasa_instrumen'] = floatval($od['paket_instrumen'] ?? 0);
    $row['jasa_omloop'] = floatval($od['paket_omloop'] ?? 0);
    $row['jasa_dr_pjanak'] = floatval($od['paket_dr_pjanak'] ?? 0);
    $row['jasa_dr_umum'] = floatval($od['paket_dr_umum'] ?? 0);
    $row['jasa_pr_luar'] = floatval($od['paket_pr_luar'] ?? 0);

    $row['jasa_operasi'] = $row['jasa_operator'] + $row['jasa_asisten'] + $row['jasa_dr_anestesi'] + $row['jasa_as_anestesi'] + $row['jasa_dr_anak'] + $row['jasa_pr_resusitas'] + $row['jasa_bidan'] + $row['jasa_instrumen'] + $row['jasa_omloop'] + $row['jasa_dr_pjanak'] + $row['jasa_dr_umum'] + $row['jasa_pr_luar'];

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
    $jasa_obat = 0;
    if ($total_racikan > 0) $jasa_obat = 25000;
    elseif ($total_non_racikan > 0) $jasa_obat = 15000;
    $row['jasa_farmasi'] = $jasa_obat + ($total_resep_operasi > 0 ? 35000 : 0);

    $row['total_non_medis'] = $row['total_menejemen_tindakan'] + $row['total_menejemen_lab'] + $row['total_menejemen_radiologi'];
    $row['total_jasa'] = $row['jasa_tindakan'] + $row['jasa_operasi'] + $row['jasa_farmasi'] + $row['jasa_lab'] + $row['jasa_radiologi'];

    $tj = $row['total_jasa'];
    $row['sisa_bpjs'] = $row['total_bpjs'] - $row['kolom_44'];
    // --- DOCTOR DISTRIBUTION ---
        $dokters = [];
    $addJasa = function($kd, $nm, $jasaBpjs, $jasaRs) use (&$dokters) {
        if ($jasaRs <= 0 || !$kd) return;
        if (!isset($dokters[$kd])) $dokters[$kd] = ['nm_dokter' => $nm, 'jasa' => 0, 'jasa_rs' => 0];
        $dokters[$kd]['jasa'] += $jasaBpjs;
        $dokters[$kd]['jasa_rs'] += $jasaRs;
    };

    if (isset($dpjpMap[$nr])) {
        foreach ($dpjpMap[$nr] as $d) {
            if ($row['total_tindakan_dr'] > 0) {
                $amt = ($d['t_dr'] / $row['total_tindakan_dr']) * $row['jumlah_dpjp'];
                $addJasa($d['kd_dokter'], $d['nm_dokter'], $amt, $d['t_dr']);
            }
        }
    }

    if ($row['jasa_operator'] > 0) {
        if (isset($opKpMap[$nr])) {
            foreach ($opKpMap[$nr] as $d) {
                $amt = ($d['t_dr'] / $row['jasa_operator']) * $row['jumlah_operator'];
                $addJasa($d['kd_dokter'], $d['nm_dokter'], $amt, $d['t_dr']);
            }
        }
        if (isset($operasiDokterMap[$nr])) {
            foreach ($operasiDokterMap[$nr] as $d) {
                if ($d['operator1']) $addJasa($d['operator1'], $d['nm_op1'], ($d['biayaoperator1'] / $row['jasa_operator']) * $row['jumlah_operator'], $d['biayaoperator1']);
                if ($d['operator2']) $addJasa($d['operator2'], $d['nm_op2'], ($d['biayaoperator2'] / $row['jasa_operator']) * $row['jumlah_operator'], $d['biayaoperator2']);
                if ($d['operator3']) $addJasa($d['operator3'], $d['nm_op3'], ($d['biayaoperator3'] / $row['jasa_operator']) * $row['jumlah_operator'], $d['biayaoperator3']);
            }
        }
    }

    if ($row['jasa_dr_anestesi'] > 0) {
        if (isset($anKpMap[$nr])) {
            foreach ($anKpMap[$nr] as $d) {
                $amt = ($d['t_dr'] / $row['jasa_dr_anestesi']) * $row['jumlah_dr_anestesi'];
                $addJasa($d['kd_dokter'], $d['nm_dokter'], $amt, $d['t_dr']);
            }
        }
        if (isset($operasiDokterMap[$nr])) {
            foreach ($operasiDokterMap[$nr] as $d) {
                if ($d['dokter_anestesi']) $addJasa($d['dokter_anestesi'], $d['nm_an'], ($d['biayadokter_anestesi'] / $row['jasa_dr_anestesi']) * $row['jumlah_dr_anestesi'], $d['biayadokter_anestesi']);
            }
        }
    }
    
    if (isset($operasiDokterMap[$nr])) {
        foreach ($operasiDokterMap[$nr] as $d) {
            if ($row['jasa_dr_anak'] > 0 && $d['dokter_anak']) $addJasa($d['dokter_anak'], $d['nm_anak'], ($d['biayadokter_anak'] / $row['jasa_dr_anak']) * $row['jumlah_dr_anak'], $d['biayadokter_anak']);
            if ($row['jasa_dr_pjanak'] > 0 && $d['dokter_pjanak']) $addJasa($d['dokter_pjanak'], $d['nm_pja'], ($d['biaya_dokter_pjanak'] / $row['jasa_dr_pjanak']) * $row['jumlah_dr_pjanak'], $d['biaya_dokter_pjanak']);
            if ($row['jasa_dr_umum'] > 0 && $d['dokter_umum']) $addJasa($d['dokter_umum'], $d['nm_umum'], ($d['biaya_dokter_umum'] / $row['jasa_dr_umum']) * $row['jumlah_dr_umum'], $d['biaya_dokter_umum']);
        }
    }

    if (isset($labDokterMap[$nr])) {
        foreach ($labDokterMap[$nr] as $d) {
            if ($row['total_dokter_lab'] > 0) {
                $amt = ($d['t_dr'] / $row['total_dokter_lab']) * $row['jumlah_dokter_lab'];
                $addJasa($d['kd_dokter'], $d['nm_dokter'], $amt, $d['t_dr']);
            }
        }
    }

    if (isset($radDokterMap[$nr])) {
        foreach ($radDokterMap[$nr] as $d) {
            if ($row['total_dokter_radiologi'] > 0) {
                $amt = ($d['t_dr'] / $row['total_dokter_radiologi']) * $row['jumlah_dokter_radiologi'];
                $addJasa($d['kd_dokter'], $d['nm_dokter'], $amt, $d['t_dr']);
            }
        }
    }

    foreach ($dokters as $kd => $d_info) {
        if ($d_info['jasa_rs'] > 0) {
            if (!empty($kd_dokter) && $kd !== $kd_dokter) continue;
            $d_row = $row;
            $d_row['kd_dokter'] = $kd;
            $d_row['nm_dokter'] = $d_info['nm_dokter'];
            $d_row['jumlah_dpjp'] = $d_info['jasa']; $d_row['total_tindakan_dr'] = $d_info['jasa_rs']; 
            $data[] = $d_row;
        }
    }
}

$total_records = count($data);
$filtered_records = $total_records;

// Apply DataTables Pagination
if ($length != -1) {
    $data = array_slice($data, $start, $length);
}

echo json_encode([
    "draw" => intval($draw),
    "recordsTotal" => intval($total_records),
    "recordsFiltered" => intval($filtered_records),
    "data" => $data
]);

mysqli_close($koneksi);
