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
$grup_bangsal = $_POST['grup_bangsal'] ?? '';

$bulan_padded = str_pad($bulan, 2, '0', STR_PAD_LEFT);
$tgl_awal  = "$tahun-$bulan_padded-01 00:00:00";
$tgl_akhir = date("Y-m-t 23:59:59", strtotime($tgl_awal));

set_time_limit(120);

$base = "
    FROM reg_periksa
    JOIN pasien ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis
    JOIN penjab ON reg_periksa.kd_pj = penjab.kd_pj
    LEFT JOIN bridging_sep ON bridging_sep.no_rawat = reg_periksa.no_rawat
    LEFT JOIN rawat_inap_drpr ON rawat_inap_drpr.no_rawat = reg_periksa.no_rawat
        AND rawat_inap_drpr.kd_jenis_prw NOT IN ('RI01330','RI01331','RI01332','RI01337','RI00267','RI000276','RI00345','RI00751','RI01314','RI01315','RI01316','RI01317','RI01306','RI01307','RI01308','RI01309','RI00724','RI01918','RI01326','RI01327','RI01328','RI01329','RI00805','RI01373','RI01374','RI01375','RI01376','RI01365','RI01366','RI01367','RI01368','RI00778','RI01396','RI01385','RI01386','RI01387','RI01388')
    LEFT JOIN jns_perawatan_inap ON rawat_inap_drpr.kd_jenis_prw = jns_perawatan_inap.kd_jenis_prw
    LEFT JOIN dokter ON rawat_inap_drpr.kd_dokter = dokter.kd_dokter
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

$base_ids = "
    FROM reg_periksa
    LEFT JOIN rawat_inap_drpr ON rawat_inap_drpr.no_rawat = reg_periksa.no_rawat
        AND rawat_inap_drpr.kd_jenis_prw NOT IN ('RI01330','RI01331','RI01332','RI01337','RI00267','RI000276','RI00345','RI00751','RI01314','RI01315','RI01316','RI01317','RI01306','RI01307','RI01308','RI01309','RI00724','RI01918','RI01326','RI01327','RI01328','RI01329','RI00805','RI01373','RI01374','RI01375','RI01376','RI01365','RI01366','RI01367','RI01368','RI00778','RI01396','RI01385','RI01386','RI01387','RI01388')
    LEFT JOIN dokter ON rawat_inap_drpr.kd_dokter = dokter.kd_dokter
    LEFT JOIN kamar_inap ON kamar_inap.no_rawat = reg_periksa.no_rawat AND kamar_inap.stts_pulang != 'Pindah Kamar'
    LEFT JOIN kamar ON kamar_inap.kd_kamar = kamar.kd_kamar
    LEFT JOIN bangsal ON kamar.kd_bangsal = bangsal.kd_bangsal
    WHERE 1=1
    AND reg_periksa.status_lanjut = 'Ranap'
    AND reg_periksa.stts != 'Batal'
    AND reg_periksa.stts != 'Belum'
";

$base_ids .= " AND (
    CONCAT(kamar_inap.tgl_keluar, ' ', kamar_inap.jam_keluar)
    BETWEEN '$tgl_awal' AND '$tgl_akhir'
)";

if (!empty($kd_dokter)) {
    $dokter_filter = " AND (rawat_inap_drpr.kd_dokter = '$kd_dokter' OR EXISTS (
        SELECT 1 FROM dpjp_ranap WHERE dpjp_ranap.no_rawat = reg_periksa.no_rawat AND dpjp_ranap.kd_dokter = '$kd_dokter'
    ))";
    $base .= $dokter_filter;
    $base_ids .= $dokter_filter;
}

if (!empty($kd_pj)) {
    $base .= " AND reg_periksa.kd_pj = '$kd_pj'";
    $base_ids .= " AND reg_periksa.kd_pj = '$kd_pj'";
}

if (!empty($grup_bangsal)) {
    $base .= " AND bangsal.kd_bangsal IN (SELECT kd_bangsal FROM v_bangsal_grup WHERE grup_bangsal = '$grup_bangsal')";
    $base_ids .= " AND bangsal.kd_bangsal IN (SELECT kd_bangsal FROM v_bangsal_grup WHERE grup_bangsal = '$grup_bangsal')";
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

$rekap = [];
$processedNoRawat = [];

$offset = 0;
$batch = 500;

$data = [];
foreach ($rekap as $d) {
    if (!empty($kd_dokter) && $d['kd_dokter'] !== $kd_dokter) {
        continue;
    }
    $persen_dokter = $d['kolom_44'] > 0 ? round(($d['jumlah_dpjp'] / $d['kolom_44']) * 100, 2) : 0;
    $d['persen_dokter'] = $persen_dokter;
    $data[] = $d;
}

echo json_encode([
    "data" => $data
]);
mysqli_close($koneksi);
