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
