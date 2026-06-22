<?php
set_time_limit(120);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/conf.php';
$koneksi = bukakoneksi();

$draw = $_POST['draw'] ?? 1;
$start = $_POST['start'] ?? 0;
$length = $_POST['length'] ?? 25;
$search = $_POST['search']['value'] ?? '';

$bulan = $_POST['bulan'] ?? date('m');
$tahun = $_POST['tahun'] ?? date('Y');
$kd_dokter = $_POST['kd_dokter'] ?? '';
$kd_pj = $_POST['kd_pj'] ?? '';
$tcari = $_POST['tcari'] ?? '';
$filter_sep = $_POST['filter_sep'] ?? 'semua';
$grup_bangsal = $_POST['grup_bangsal'] ?? '';

$bulan_padded = str_pad($bulan, 2, '0', STR_PAD_LEFT);
$tgl_awal = "$tahun-$bulan_padded-01 00:00:00";
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
    WHERE 1=1
    AND reg_periksa.status_lanjut = 'Ranap'
    AND reg_periksa.stts != 'Batal'
    AND reg_periksa.stts != 'Belum'
";

$base .= " AND (
    CONCAT(kamar_inap.tgl_keluar, ' ', kamar_inap.jam_keluar)
    BETWEEN '$tgl_awal' AND '$tgl_akhir'
)";

if (!empty($kd_dokter)) {
    $base .= " AND reg_periksa.kd_dokter = '$kd_dokter'";
}

if (!empty($kd_pj)) {
    $base .= " AND reg_periksa.kd_pj = '$kd_pj'";
}

if (!empty($grup_bangsal)) {
    $base .= " AND bangsal.kd_bangsal IN (SELECT kd_bangsal FROM v_bangsal_grup WHERE grup_bangsal = '$grup_bangsal')";
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
    $base .= " AND (
        reg_periksa.no_rawat LIKE '%$tcari%'
        OR pasien.nm_pasien LIKE '%$tcari%'
        OR dokter.nm_dokter LIKE '%$tcari%'
        OR reg_periksa.no_rkm_medis LIKE '%$tcari%'
        OR bridging_sep.no_sep LIKE '%$tcari%'
        OR bangsal.nm_bangsal LIKE '%$tcari%'
    )";
}

if (!empty($search)) {
    $base .= " AND (
        reg_periksa.no_rawat LIKE '%$search%'
        OR reg_periksa.no_rkm_medis LIKE '%$search%'
        OR pasien.nm_pasien LIKE '%$search%'
        OR dokter.nm_dokter LIKE '%$search%'
        OR bangsal.nm_bangsal LIKE '%$search%'
        OR bridging_sep.no_sep LIKE '%$search%'
    )";
}

$query = "SELECT
    reg_periksa.no_rawat,
    reg_periksa.no_rkm_medis,
    reg_periksa.tgl_registrasi,
    pasien.nm_pasien,
    IFNULL(bridging_sep.no_sep, '-') AS no_sep,
    penjab.png_jawab,
    MIN(dokter.nm_dokter) AS nm_dokter,
    MIN(bangsal.nm_bangsal) AS nm_bangsal,
    MIN(kamar_inap.tgl_masuk) AS tgl_masuk,
    MIN(kamar_inap.stts_pulang) AS stts_pulang,

    IFNULL(SUM(jns_perawatan_inap.tarif_tindakandr), 0) AS total_tindakan_dr,
    IFNULL(SUM(jns_perawatan_inap.tarif_tindakanpr), 0) AS total_tindakan_pr,
    IFNULL(SUM(jns_perawatan_inap.menejemen), 0) AS total_menejemen_tindakan,
    IFNULL(SUM(jns_perawatan_inap.total_byrdrpr), 0) AS total_biaya_rawat

$base

GROUP BY
    reg_periksa.no_rawat,
    reg_periksa.no_rkm_medis,
    reg_periksa.tgl_registrasi,
    pasien.nm_pasien,
    bridging_sep.no_sep,
    penjab.png_jawab,
    reg_periksa.kd_dokter
ORDER BY reg_periksa.tgl_registrasi DESC, reg_periksa.jam_reg DESC
LIMIT $start, $length
";

$count_query = "
SELECT COUNT(DISTINCT reg_periksa.no_rawat) AS total
FROM reg_periksa
JOIN kamar_inap ON kamar_inap.no_rawat = reg_periksa.no_rawat AND kamar_inap.stts_pulang != 'Pindah Kamar'
JOIN kamar ON kamar_inap.kd_kamar = kamar.kd_kamar
JOIN bangsal ON kamar.kd_bangsal = bangsal.kd_bangsal
WHERE reg_periksa.status_lanjut = 'Ranap'
";

$c_total = mysqli_fetch_assoc(mysqli_query($koneksi, $count_query));
$total_records = $c_total['total'];

$count_filtered_query = "
SELECT COUNT(DISTINCT reg_periksa.no_rawat) AS total
$base
";
$c_filtered = mysqli_fetch_assoc(mysqli_query($koneksi, $count_filtered_query));
$filtered_records = $c_filtered['total'];

$result = mysqli_query($koneksi, $query);
if (!$result) {
    echo json_encode([
        "draw" => intval($draw),
        "recordsTotal" => 0,
        "recordsFiltered" => 0,
        "data" => [],
        "error" => mysqli_error($koneksi)
    ]);
    exit;
}

$bpjs_lookup = [];
$bulan_int = (int)$bulan;
$bpjs_result = mysqli_query($koneksi, "
    SELECT data FROM bpjs_verifikasi
    WHERE bulan = '$bulan_int' AND tahun = '$tahun' AND jenis = 'ranap'
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

$data = [];

while ($row = mysqli_fetch_assoc($result)) {
    $no_rawat = $row['no_rawat'];

    $row['jasa_tindakan'] = $row['total_tindakan_dr'] + $row['total_tindakan_pr'] + $row['total_menejemen_tindakan'];

    $lab_result = mysqli_query($koneksi, "
        SELECT
            SUM(IFNULL(jns_perawatan_lab.bagian_rs,0)) AS total_material_lab,
            SUM(IFNULL(jns_perawatan_lab.tarif_tindakan_dokter,0)) AS total_dokter_lab,
            SUM(IFNULL(jns_perawatan_lab.tarif_tindakan_petugas,0)) AS total_petugas_lab,
            SUM(IFNULL(jns_perawatan_lab.menejemen,0)) AS total_menejemen_lab,
            SUM(IFNULL(jns_perawatan_lab.total_byr,0)) AS total_lab
        FROM periksa_lab
        JOIN jns_perawatan_lab ON periksa_lab.kd_jenis_prw = jns_perawatan_lab.kd_jenis_prw
        WHERE periksa_lab.no_rawat = '$no_rawat' AND periksa_lab.status = 'Ranap'
    ");
    if ($lab_result && $ld = mysqli_fetch_assoc($lab_result)) {
        $row['total_material_lab'] = floatval($ld['total_material_lab']);
        $row['total_dokter_lab'] = floatval($ld['total_dokter_lab']);
        $row['total_petugas_lab'] = floatval($ld['total_petugas_lab']);
        $row['total_menejemen_lab'] = floatval($ld['total_menejemen_lab']);
        $row['total_lab'] = floatval($ld['total_lab']);
    } else {
        $row['total_material_lab'] = 0;
        $row['total_dokter_lab'] = 0;
        $row['total_petugas_lab'] = 0;
        $row['total_menejemen_lab'] = 0;
        $row['total_lab'] = 0;
    }
    $row['jasa_lab'] = $row['total_dokter_lab'] + $row['total_petugas_lab'] + $row['total_menejemen_lab'];

    $rad_result = mysqli_query($koneksi, "
        SELECT
            COALESCE(SUM(t2.bagian_rs), 0) AS total_material_radiologi,
            COALESCE(SUM(t2.tarif_tindakan_dokter), 0) AS total_dokter_radiologi,
            COALESCE(SUM(t2.tarif_tindakan_petugas), 0) AS total_petugas_radiologi,
            COALESCE(SUM(t2.menejemen), 0) AS total_menejemen_radiologi,
            COALESCE(SUM(t2.total_byr), 0) AS total_radiologi
        FROM permintaan_radiologi t1
        JOIN permintaan_pemeriksaan_radiologi t3 ON t1.noorder = t3.noorder
        JOIN jns_perawatan_radiologi t2 ON t3.kd_jenis_prw = t2.kd_jenis_prw
        WHERE t1.no_rawat = '$no_rawat' AND t1.status = 'ranap'
    ");
    if ($rad_result && $rd = mysqli_fetch_assoc($rad_result)) {
        $row['total_material_radiologi'] = floatval($rd['total_material_radiologi']);
        $row['total_dokter_radiologi'] = floatval($rd['total_dokter_radiologi']);
        $row['total_petugas_radiologi'] = floatval($rd['total_petugas_radiologi']);
        $row['total_menejemen_radiologi'] = floatval($rd['total_menejemen_radiologi']);
        $row['total_radiologi'] = floatval($rd['total_radiologi']);
    } else {
        $row['total_material_radiologi'] = 0;
        $row['total_dokter_radiologi'] = 0;
        $row['total_petugas_radiologi'] = 0;
        $row['total_menejemen_radiologi'] = 0;
        $row['total_radiologi'] = 0;
    }
    $row['jasa_radiologi'] = $row['total_dokter_radiologi'] + $row['total_petugas_radiologi'] + $row['total_menejemen_radiologi'];

    $obat_result = mysqli_query($koneksi, "
        SELECT SUM(IFNULL(total,0)) AS total_obat
        FROM detail_pemberian_obat
        WHERE no_rawat = '$no_rawat' AND status = 'Ranap'
    ");
    if ($obat_result && $od = mysqli_fetch_assoc($obat_result)) {
        $row['total_obat'] = floatval($od['total_obat']);
    } else {
        $row['total_obat'] = 0;
    }
    $row['total_obat_ppn'] = $row['total_obat'] * 1.11;

    $resep_result = mysqli_query($koneksi, "
        SELECT no_resep FROM resep_obat
        WHERE no_rawat = '$no_rawat' AND tgl_perawatan != '0000-00-00' AND status = 'ranap'
    ");
    $total_racikan = 0;
    $total_non_racikan = 0;
    $total_resep_operasi = 0;
    if ($resep_result)
        while ($rs = mysqli_fetch_assoc($resep_result)) {
            $nr = mysqli_real_escape_string($koneksi, $rs['no_resep']);
            if (substr($rs['no_resep'], 0, 2) === 'OK') {
                $total_resep_operasi++;
            } else {
                $cr = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS ada FROM resep_dokter_racikan WHERE no_resep='$nr' LIMIT 1"));
                $cn = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS ada FROM resep_dokter WHERE no_resep='$nr' LIMIT 1"));
                if ($cr && $cr['ada'] > 0)
                    $total_racikan++;
                elseif ($cn && $cn['ada'] > 0)
                    $total_non_racikan++;
            }
        }
    $row['jumlah_resep_racikan'] = $total_racikan;
    $row['jumlah_resep_non_racikan'] = $total_non_racikan;
    $row['jumlah_resep_operasi'] = $total_resep_operasi;

    $jasa_obat = 0;
    if ($total_racikan > 0)
        $jasa_obat = 25000;
    elseif ($total_non_racikan > 0)
        $jasa_obat = 15000;
    $jasa_operasi = $total_resep_operasi > 0 ? 35000 : 0;
    $row['jasa_farmasi'] = $jasa_obat + $jasa_operasi;

    $row['total_non_medis'] = $row['total_menejemen_tindakan'] + $row['total_menejemen_lab'] + $row['total_menejemen_radiologi'];

    $row['total_jasa'] = $row['jasa_tindakan'] + $row['jasa_farmasi'] + $row['jasa_lab'] + $row['jasa_radiologi'];
    $row['nominal_rs'] = $row['jasa_tindakan'] + ($row['total_obat'] ?? 0) + $row['jasa_farmasi'] + $row['jasa_lab'] + $row['jasa_radiologi'];

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
