<?php
error_reporting(0);
ob_start();
set_time_limit(600);
ini_set('memory_limit', '1024M');

require_once '../config/conf.php';
require_once '../vendor/autoload.php';












$koneksi = bukakoneksi();

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










$no = 1;
$rekap = [];
$selisih = [];
$pasien_gagal_compare = [];
$used_seps = [];

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
        IFNULL(SUM(CASE WHEN jns_perawatan_inap.kd_kategori NOT IN ('KP050', 'KP049') THEN jns_perawatan_inap.tarif_tindakandr ELSE 0 END), 0) AS total_tindakan_dr,
        IFNULL(SUM(CASE WHEN jns_perawatan_inap.kd_kategori NOT IN ('KP050', 'KP049') THEN jns_perawatan_inap.tarif_tindakanpr ELSE 0 END), 0) AS total_tindakan_pr,
        IFNULL(SUM(CASE WHEN jns_perawatan_inap.kd_kategori = 'KP050' THEN jns_perawatan_inap.tarif_tindakandr ELSE 0 END), 0) AS total_dr_operasi_kategori,
        IFNULL(SUM(CASE WHEN jns_perawatan_inap.kd_kategori = 'KP050' THEN jns_perawatan_inap.tarif_tindakanpr ELSE 0 END), 0) AS total_pr_operasi_kategori,
        IFNULL(SUM(CASE WHEN jns_perawatan_inap.kd_kategori = 'KP049' THEN jns_perawatan_inap.tarif_tindakandr ELSE 0 END), 0) AS total_dr_anestesi_kategori,
        IFNULL(SUM(CASE WHEN jns_perawatan_inap.kd_kategori = 'KP049' THEN jns_perawatan_inap.tarif_tindakanpr ELSE 0 END), 0) AS total_pr_anestesi_kategori,
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

        $od = $operasiMap[$nr] ?? null;
        $row['jasa_operator'] = floatval($row['total_dr_operasi_kategori'] ?? 0) + floatval($od['paket_operator'] ?? 0);
        $row['jasa_asisten'] = floatval($row['total_pr_operasi_kategori'] ?? 0) + floatval($od['paket_asisten'] ?? 0);
        $row['jasa_dr_anestesi'] = floatval($row['total_dr_anestesi_kategori'] ?? 0) + floatval($od['paket_dr_anestesi'] ?? 0);
        $row['jasa_as_anestesi'] = floatval($row['total_pr_anestesi_kategori'] ?? 0) + floatval($od['paket_as_anestesi'] ?? 0);
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
        $pct = fn($v) => $tj > 0 ? round($v / $tj * 100, 2)  : '0';

        $total_bpjs = $bpjs_lookup[$row['no_sep']] ?? 0;
        $kolom_44 = $total_bpjs * 0.44;
        $tb44 = $kolom_44;
        $sisa_bpjs = $total_bpjs - $kolom_44;
        $jml_dpjp = $tb44 > 0 ? round($pct($row['total_tindakan_dr']) / 100 * $tb44) : 0;
        $jml_perawat = $tb44 > 0 ? round($pct($row['total_tindakan_pr']) / 100 * $tb44) : 0;
        $jml_operator = $tb44 > 0 ? round($pct($row['jasa_operator']) / 100 * $tb44) : 0;
        $jml_asisten = $tb44 > 0 ? round($pct($row['jasa_asisten']) / 100 * $tb44) : 0;
        $jml_dr_anestesi = $tb44 > 0 ? round($pct($row['jasa_dr_anestesi']) / 100 * $tb44) : 0;
        $jml_as_anestesi = $tb44 > 0 ? round($pct($row['jasa_as_anestesi']) / 100 * $tb44) : 0;
        $jml_dr_anak = $tb44 > 0 ? round($pct($row['jasa_dr_anak']) / 100 * $tb44) : 0;
        $jml_pr_resusitas = $tb44 > 0 ? round($pct($row['jasa_pr_resusitas']) / 100 * $tb44) : 0;
        $jml_bidan = $tb44 > 0 ? round($pct($row['jasa_bidan']) / 100 * $tb44) : 0;
        $jml_instrumen = $tb44 > 0 ? round($pct($row['jasa_instrumen']) / 100 * $tb44) : 0;
        $jml_omloop = $tb44 > 0 ? round($pct($row['jasa_omloop']) / 100 * $tb44) : 0;
        $jml_dr_pjanak = $tb44 > 0 ? round($pct($row['jasa_dr_pjanak']) / 100 * $tb44) : 0;
        $jml_dr_umum = $tb44 > 0 ? round($pct($row['jasa_dr_umum']) / 100 * $tb44) : 0;
        $jml_pr_luar = $tb44 > 0 ? round($pct($row['jasa_pr_luar']) / 100 * $tb44) : 0;
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
            $row['jasa_operator'],
            $row['jasa_asisten'],
            $row['jasa_dr_anestesi'],
            $row['jasa_as_anestesi'],
            $row['jasa_dr_anak'],
            $row['jasa_pr_resusitas'],
            $row['jasa_bidan'],
            $row['jasa_instrumen'],
            $row['jasa_omloop'],
            $row['jasa_dr_pjanak'],
            $row['jasa_dr_umum'],
            $row['jasa_pr_luar'],
            $row['jasa_operasi'],
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
            $pct($row['jasa_operator']),
            $jml_operator,
            $pct($row['jasa_asisten']),
            $jml_asisten,
            $pct($row['jasa_dr_anestesi']),
            $jml_dr_anestesi,
            $pct($row['jasa_as_anestesi']),
            $jml_as_anestesi,
            $pct($row['jasa_dr_anak']),
            $jml_dr_anak,
            $pct($row['jasa_pr_resusitas']),
            $jml_pr_resusitas,
            $pct($row['jasa_bidan']),
            $jml_bidan,
            $pct($row['jasa_instrumen']),
            $jml_instrumen,
            $pct($row['jasa_omloop']),
            $jml_omloop,
            $pct($row['jasa_dr_pjanak']),
            $jml_dr_pjanak,
            $pct($row['jasa_dr_umum']),
            $jml_dr_umum,
            $pct($row['jasa_pr_luar']),
            $jml_pr_luar,
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
            $rekap[$bangsalKey] = [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0];
        }
        $rekap[$bangsalKey][0]++;
        $rekap[$bangsalKey][1] += $total_bpjs;
        $rekap[$bangsalKey][2] += $kolom_44;
        $rekap[$bangsalKey][3] += $sisa_bpjs;
        $rekap[$bangsalKey][4] += $jml_dpjp;
        $rekap[$bangsalKey][5] += $jml_perawat;
        $rekap[$bangsalKey][6] += $jml_operator;
        $rekap[$bangsalKey][7] += $jml_asisten;
        $rekap[$bangsalKey][8] += $jml_dr_anestesi;
        $rekap[$bangsalKey][9] += $jml_as_anestesi;
        $rekap[$bangsalKey][10] += $jml_dr_anak;
        $rekap[$bangsalKey][11] += $jml_pr_resusitas;
        $rekap[$bangsalKey][12] += $jml_bidan;
        $rekap[$bangsalKey][13] += $jml_instrumen;
        $rekap[$bangsalKey][14] += $jml_omloop;
        $rekap[$bangsalKey][15] += $jml_dr_pjanak;
        $rekap[$bangsalKey][16] += $jml_dr_umum;
        $rekap[$bangsalKey][17] += $jml_pr_luar;
        $rekap[$bangsalKey][18] += $jml_farmasi;
        $rekap[$bangsalKey][19] += $jml_dokter_lab;
        $rekap[$bangsalKey][20] += $jml_analis_lab;
        $rekap[$bangsalKey][21] += $jml_dokter_rad;
        $rekap[$bangsalKey][22] += $jml_radiografer;
        $rekap[$bangsalKey][23] += $jml_non_medis;

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

        
        
        
        
        
        
        $no++;
    }
    unset($q, $ids, $id_q, $labMap, $radMap, $obatMap, $resepMap, $resepIds, $racikanSet, $nonRacikanSet);
    $offset += $batch;
}

$data_rekap = [];
foreach ($rekap as $bangsal => $v) {
    $data_rekap[] = [
        'nm_bangsal' => $bangsal,
        'jumlah_pasien' => $v[0],
        'total_bpjs' => $v[1],
        'kolom_44' => $v[2],
        'sisa_bpjs' => $v[3],
        'jml_dpjp' => $v[4],
        'jml_perawat' => $v[5],
        'jml_operator' => $v[6],
        'jml_asisten' => $v[7],
        'jml_dr_anestesi' => $v[8],
        'jml_as_anestesi' => $v[9],
        'jml_dr_anak' => $v[10],
        'jml_pr_resusitas' => $v[11],
        'jml_bidan' => $v[12],
        'jml_instrumen' => $v[13],
        'jml_omloop' => $v[14],
        'jml_dr_pjanak' => $v[15],
        'jml_dr_umum' => $v[16],
        'jml_pr_luar' => $v[17],
        'jml_farmasi' => $v[18],
        'jml_dokter_lab' => $v[19],
        'jml_analis_lab' => $v[20],
        'jml_dokter_radiologi' => $v[21],
        'jml_radiografer' => $v[22],
        'jml_non_medis' => $v[23]
    ];
}

ob_clean();
header('Content-Type: application/json');
echo json_encode(['data' => $data_rekap]);
mysqli_close($koneksi);