<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/conf.php';
$koneksi = bukakoneksi();

$bulan     = $_POST['bulan'] ?? date('m');
$tahun     = $_POST['tahun'] ?? date('Y');
$kd_dokter = $_POST['kd_dokter'] ?? '';
$kd_pj     = $_POST['kd_pj'] ?? '';
$filter_sep = $_POST['filter_sep'] ?? 'semua';

$bulan_padded = str_pad($bulan, 2, '0', STR_PAD_LEFT);
$tgl_awal  = "$tahun-$bulan_padded-01 00:00:00";
$tgl_akhir = date("Y-m-t 23:59:59", strtotime($tgl_awal));

set_time_limit(120);

$base = "
    FROM reg_periksa
    JOIN pasien ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis
    JOIN poliklinik ON reg_periksa.kd_poli = poliklinik.kd_poli
    JOIN penjab ON reg_periksa.kd_pj = penjab.kd_pj
    LEFT JOIN bridging_sep ON bridging_sep.no_rawat = reg_periksa.no_rawat
    LEFT JOIN rawat_jl_drpr ON rawat_jl_drpr.no_rawat = reg_periksa.no_rawat
    LEFT JOIN jns_perawatan ON rawat_jl_drpr.kd_jenis_prw = jns_perawatan.kd_jenis_prw
    LEFT JOIN dokter ON reg_periksa.kd_dokter = dokter.kd_dokter
    WHERE 1=1
    AND reg_periksa.status_lanjut = 'Ralan'
    AND reg_periksa.stts != 'Batal'
    AND reg_periksa.stts != 'Belum'
    AND NOT (
        reg_periksa.kd_poli = 'IGDK'
        AND EXISTS (
            SELECT 1 FROM kamar_inap ki WHERE ki.no_rawat = reg_periksa.no_rawat
        )
    )
";

$base .= " AND (
    CONCAT(reg_periksa.tgl_registrasi,' ',reg_periksa.jam_reg)
    BETWEEN '$tgl_awal' AND '$tgl_akhir'
    OR CONCAT(rawat_jl_drpr.tgl_perawatan,' ',rawat_jl_drpr.jam_rawat)
    BETWEEN '$tgl_awal' AND '$tgl_akhir'
)";

// ID query - tanpa JOIN ke rawat_jl_drpr/jns_perawatan (menghindari duplikasi dan DISTINCT mahal)
$base_ids = "
    FROM reg_periksa
    LEFT JOIN dokter ON reg_periksa.kd_dokter = dokter.kd_dokter
    WHERE 1=1
    AND reg_periksa.status_lanjut = 'Ralan'
    AND reg_periksa.stts != 'Batal'
    AND reg_periksa.stts != 'Belum'
    AND NOT (
        reg_periksa.kd_poli = 'IGDK'
        AND EXISTS (
            SELECT 1 FROM kamar_inap ki WHERE ki.no_rawat = reg_periksa.no_rawat
        )
    )
";

$base_ids .= " AND (
    CONCAT(reg_periksa.tgl_registrasi,' ',reg_periksa.jam_reg)
    BETWEEN '$tgl_awal' AND '$tgl_akhir'
    OR EXISTS (
        SELECT 1 FROM rawat_jl_drpr
        WHERE rawat_jl_drpr.no_rawat = reg_periksa.no_rawat
        AND CONCAT(rawat_jl_drpr.tgl_perawatan,' ',rawat_jl_drpr.jam_rawat)
        BETWEEN '$tgl_awal' AND '$tgl_akhir'
    )
)";

if (!empty($kd_dokter)) {
    $base .= " AND reg_periksa.kd_dokter = '$kd_dokter'";
    $base_ids .= " AND reg_periksa.kd_dokter = '$kd_dokter'";
}

if (!empty($kd_pj)) {
    $base .= " AND reg_periksa.kd_pj = '$kd_pj'";
    $base_ids .= " AND reg_periksa.kd_pj = '$kd_pj'";
}

if ($filter_sep == 'ada') {
    $sep_cond = "EXISTS (
        SELECT 1 FROM bridging_sep bs
        WHERE bs.no_rawat = reg_periksa.no_rawat
        AND bs.no_sep IS NOT NULL AND bs.no_sep != '' AND bs.no_sep != '-'
    )";
    $base .= " AND $sep_cond";
    $base_ids .= " AND $sep_cond";
} elseif ($filter_sep == 'tidak_ada') {
    $sep_cond = "NOT EXISTS (
        SELECT 1 FROM bridging_sep bs
        WHERE bs.no_rawat = reg_periksa.no_rawat
        AND bs.no_sep IS NOT NULL AND bs.no_sep != '' AND bs.no_sep != '-'
    )";
    $base .= " AND $sep_cond";
    $base_ids .= " AND $sep_cond";
}

$rekap = []; // dokter => [count, total_bpjs, 44%, total_jasa_dokter, jml_dpjp]

$offset = 0;
$batch = 500;

// BPJS lookup
$bpjs_lookup = [];
$bulan_int = (int)$bulan;
$bpjs_result = mysqli_query($koneksi, "
    SELECT data FROM bpjs_verifikasi
    WHERE bulan = '$bulan_int' AND tahun = '$tahun' AND jenis = 'ralan'
    ORDER BY created_at DESC
");
while ($brow = mysqli_fetch_assoc($bpjs_result)) {
    $rows = json_decode($brow['data'], true);
    if (is_array($rows)) {
        foreach ($rows as $r) {
            if (!empty($r['no_sep'])) {
                $bpjs_lookup[$r['no_sep']] = floatval($r['disetujui'] ?? 0);
            }
        }
    }
}

while (true) {
    $id_q = mysqli_query($koneksi, "
        SELECT DISTINCT reg_periksa.no_rawat $base_ids
        LIMIT $offset, $batch
    ");
    if (!$id_q || mysqli_num_rows($id_q) === 0) break;

    $ids = [];
    while ($r = mysqli_fetch_assoc($id_q)) {
        $ids[] = "'" . mysqli_real_escape_string($koneksi, $r['no_rawat']) . "'";
    }
    $in = implode(',', $ids);

    $konsul_list = "'RJ00769','RJ00768','RJ00764','RJ00644','RJ00012','RJ00011','RJ00010','RJ00009','RJ000008','RJ000007','RJ000003'";

    $q = mysqli_query($koneksi, "SELECT
        reg_periksa.no_rawat,
        IFNULL(bridging_sep.no_sep, '-') AS no_sep,
        MIN(dokter.nm_dokter) AS nm_dokter,
        MIN(reg_periksa.kd_pj) AS kd_pj,
        MIN(reg_periksa.kd_dokter) AS kd_dokter,
        IFNULL(SUM(jns_perawatan.tarif_tindakandr), 0) AS total_tindakan_dr,
        IFNULL(SUM(jns_perawatan.tarif_tindakanpr), 0) AS total_tindakan_pr,
        IFNULL(SUM(jns_perawatan.menejemen), 0) AS total_menejemen_tindakan,
        COUNT(CASE WHEN rawat_jl_drpr.kd_jenis_prw IN ($konsul_list) THEN 1 END) AS jumlah_konsul,
        COUNT(CASE WHEN rawat_jl_drpr.kd_jenis_prw NOT IN ($konsul_list) THEN 1 END) AS jumlah_tindakan_lain
    $base
    AND reg_periksa.no_rawat IN ($in)
    GROUP BY
        reg_periksa.no_rawat,
        bridging_sep.no_sep,
        reg_periksa.kd_dokter
    ");

    if (!$q) break;

    $labMap = [];
    $lr = mysqli_query($koneksi, "SELECT no_rawat,
        SUM(IFNULL(jns_perawatan_lab.tarif_tindakan_dokter,0)) AS total_dokter_lab,
        SUM(IFNULL(jns_perawatan_lab.tarif_tindakan_petugas,0)) AS total_petugas_lab,
        SUM(IFNULL(jns_perawatan_lab.menejemen,0)) AS total_menejemen_lab
        FROM periksa_lab JOIN jns_perawatan_lab ON periksa_lab.kd_jenis_prw = jns_perawatan_lab.kd_jenis_prw
        WHERE periksa_lab.no_rawat IN ($in) GROUP BY no_rawat");
    if ($lr) while ($l = mysqli_fetch_assoc($lr)) $labMap[$l['no_rawat']] = $l;

    $radMap = [];
    $rr = mysqli_query($koneksi, "SELECT t1.no_rawat,
        COALESCE(SUM(t2.tarif_tindakan_dokter),0) AS total_dokter_radiologi,
        COALESCE(SUM(t2.tarif_tindakan_petugas),0) AS total_petugas_radiologi,
        COALESCE(SUM(t2.menejemen),0) AS total_menejemen_radiologi
        FROM permintaan_radiologi t1
        JOIN permintaan_pemeriksaan_radiologi t3 ON t1.noorder = t3.noorder
        JOIN jns_perawatan_radiologi t2 ON t3.kd_jenis_prw = t2.kd_jenis_prw
        WHERE t1.no_rawat IN ($in) AND t1.status = 'ralan' GROUP BY t1.no_rawat");
    if ($rr) while ($r = mysqli_fetch_assoc($rr)) $radMap[$r['no_rawat']] = $r;

    $resepMap = [];
    $rsr = mysqli_query($koneksi, "SELECT no_rawat, no_resep FROM resep_obat
        WHERE no_rawat IN ($in) AND tgl_perawatan != '0000-00-00' AND status = 'ralan'");
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
        $jml_dpjp = $tb44 > 0 ? round($pct($row['total_tindakan_dr']) / 100 * $tb44) : 0;

        $dokterKey = $row['nm_dokter'] ?: 'TANPA DOKTER';
        if (!isset($rekap[$dokterKey])) {
            $rekap[$dokterKey] = [
                'nm_dokter' => $dokterKey,
                'kd_dokter' => $row['kd_dokter'],
                'jumlah_pasien' => 0,
                'jumlah_pasien_bpjs' => 0,
                'jumlah_pasien_non_bpjs' => 0,
                'jumlah_pasien_klaim_bpjs' => 0,
                'jumlah_konsul' => 0,
                'jumlah_tindakan_lain' => 0,
                'total_bpjs' => 0,
                'kolom_44' => 0,
                'total_tindakan_dr' => 0,
                'jumlah_dpjp' => 0,
            ];
        }
        $is_bpjs = strpos($row['kd_pj'], 'BPJ') !== false;
        $rekap[$dokterKey]['jumlah_pasien']++;
        if ($is_bpjs) {
            $rekap[$dokterKey]['jumlah_pasien_bpjs']++;
        } else {
            $rekap[$dokterKey]['jumlah_pasien_non_bpjs']++;
        }
        if ($total_bpjs > 0) {
            $rekap[$dokterKey]['jumlah_pasien_klaim_bpjs']++;
        }
        $rekap[$dokterKey]['jumlah_konsul'] += intval($row['jumlah_konsul']);
        $rekap[$dokterKey]['jumlah_tindakan_lain'] += intval($row['jumlah_tindakan_lain']);
        $rekap[$dokterKey]['total_bpjs'] += $total_bpjs;
        $rekap[$dokterKey]['kolom_44'] += $kolom_44;
        $rekap[$dokterKey]['total_tindakan_dr'] += $row['total_tindakan_dr'];
        $rekap[$dokterKey]['jumlah_dpjp'] += $jml_dpjp;
    }
    $offset += $batch;
}

$data = [];
foreach ($rekap as $d) {
    $persen_dokter = $d['kolom_44'] > 0 ? round(($d['jumlah_dpjp'] / $d['kolom_44']) * 100, 2) : 0;
    $d['persen_dokter'] = $persen_dokter;
    $data[] = $d;
}

echo json_encode([
    "data" => $data
]);
mysqli_close($koneksi);
