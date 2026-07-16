<?php
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
$kd_poli = $_POST['kd_poli'] ?? '';
$kd_pj = $_POST['kd_pj'] ?? '';
$tcari = $_POST['tcari'] ?? '';

$bulan_padded = str_pad($bulan, 2, '0', STR_PAD_LEFT);
$tgl_awal = "$tahun-$bulan_padded-01 00:00:00";
$tgl_akhir = date("Y-m-t 23:59:59", strtotime($tgl_awal));

$base = "
    FROM reg_periksa
    JOIN pasien ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis
    JOIN poliklinik ON reg_periksa.kd_poli = poliklinik.kd_poli
    JOIN penjab ON reg_periksa.kd_pj = penjab.kd_pj
        LEFT JOIN rawat_jl_drpr ON rawat_jl_drpr.no_rawat = reg_periksa.no_rawat
    LEFT JOIN jns_perawatan ON rawat_jl_drpr.kd_jenis_prw = jns_perawatan.kd_jenis_prw
    LEFT JOIN dokter ON reg_periksa.kd_dokter = dokter.kd_dokter
    WHERE 1=1
    AND reg_periksa.status_lanjut = 'Ralan'
    AND reg_periksa.stts != 'Batal'
    AND reg_periksa.kd_pj != 'BPJ'
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

if (!empty($kd_poli)) {
    $base .= " AND reg_periksa.kd_poli = '$kd_poli'";
}

if (!empty($kd_pj)) {
    $base .= " AND reg_periksa.kd_pj = '$kd_pj'";
}


if (!empty($tcari)) {
    $base .= " AND (
        reg_periksa.no_rawat LIKE '%$tcari%'
        OR pasien.nm_pasien LIKE '%$tcari%'
        OR dokter.nm_dokter LIKE '%$tcari%'
        OR reg_periksa.no_rkm_medis LIKE '%$tcari%'
        OR poliklinik.nm_poli LIKE '%$tcari%'
    )";
}

if (!empty($search)) {
    $base .= " AND (
        reg_periksa.no_rawat LIKE '%$search%'
        OR reg_periksa.no_rkm_medis LIKE '%$search%'
        OR pasien.nm_pasien LIKE '%$search%'
        OR dokter.nm_dokter LIKE '%$search%'
        OR poliklinik.nm_poli LIKE '%$search%'
    )";
}

$query = "SELECT
    reg_periksa.no_rawat,
    reg_periksa.no_rkm_medis,
    reg_periksa.tgl_registrasi,
    pasien.nm_pasien,
        poliklinik.nm_poli,
    penjab.png_jawab,
    MIN(dokter.nm_dokter) AS nm_dokter,

    IFNULL(SUM(jns_perawatan.material), 0) AS total_material,
    IFNULL(SUM(jns_perawatan.bhp), 0) AS total_bhp,
    IFNULL(SUM(jns_perawatan.tarif_tindakandr), 0) AS total_tindakan_dr,
    IFNULL(SUM(jns_perawatan.tarif_tindakanpr), 0) AS total_tindakan_pr,
    IFNULL(SUM(jns_perawatan.menejemen), 0) AS total_menejemen_tindakan,
    IFNULL(SUM(jns_perawatan.total_byrdrpr), 0) AS total_biaya_rawat

$base

GROUP BY
    reg_periksa.no_rawat,
    reg_periksa.no_rkm_medis,
    reg_periksa.tgl_registrasi,
    pasien.nm_pasien,
    poliklinik.nm_poli,
    penjab.png_jawab,
    reg_periksa.kd_dokter
ORDER BY reg_periksa.tgl_registrasi DESC, reg_periksa.jam_reg DESC
LIMIT $start, $length
";

$count_query = "
SELECT COUNT(DISTINCT reg_periksa.no_rawat) AS total
FROM reg_periksa
JOIN poliklinik ON reg_periksa.kd_poli = poliklinik.kd_poli
WHERE reg_periksa.status_lanjut = 'Ralan'
AND reg_periksa.kd_poli != 'IGDK'
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
        WHERE periksa_lab.no_rawat = '$no_rawat'
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
        WHERE t1.no_rawat = '$no_rawat' AND t1.status = 'ralan'
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
        WHERE no_rawat = '$no_rawat' AND status = 'Ralan'
    ");
    if ($obat_result && $od = mysqli_fetch_assoc($obat_result)) {
        $row['total_obat'] = floatval($od['total_obat']);
    } else {
        $row['total_obat'] = 0;
    }
    $row['total_obat_ppn'] = $row['total_obat'] * 1.11;

    $resep_result = mysqli_query($koneksi, "
        SELECT no_resep FROM resep_obat
        WHERE no_rawat = '$no_rawat' AND tgl_perawatan != '0000-00-00' AND status = 'ralan'
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
    $jasa_obat = 0;
    if ($total_racikan > 0)
        $jasa_obat = 25000;
    elseif ($total_non_racikan > 0)
        $jasa_obat = 15000;
    $jasa_operasi = $total_resep_operasi > 0 ? 35000 : 0;
    $row['jasa_farmasi'] = $jasa_obat + $jasa_operasi;
    $row['jasa_apoteker'] = 0.80 * $row['jasa_farmasi'];
    $row['jasa_non_apoteker'] = 0.20 * $row['jasa_farmasi'];

    $row['total_non_medis'] = $row['total_menejemen_tindakan'] + $row['total_menejemen_lab'] + $row['total_menejemen_radiologi'] + $row['jasa_non_apoteker'];

    $row['total_jasa'] = $row['jasa_tindakan'] + $row['jasa_farmasi'] + $row['jasa_lab'] + $row['jasa_radiologi'];

    $tj = $row['total_jasa'];
    $data[] = $row;
}

echo json_encode([
    "draw" => intval($draw),
    "recordsTotal" => intval($total_records),
    "recordsFiltered" => intval($filtered_records),
    "data" => $data
]);

mysqli_close($koneksi);
