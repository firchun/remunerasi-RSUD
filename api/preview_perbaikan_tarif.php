<?php
require_once '../config/conf.php';
$koneksi = bukakoneksi();

header('Content-Type: application/json');

$type      = isset($_GET['type'])         ? $_GET['type']         : '';
$kd_jenis  = isset($_GET['kd_jenis_prw']) ? trim($_GET['kd_jenis_prw']) : '';
$tgl_mulai = isset($_GET['tgl_mulai'])    ? $_GET['tgl_mulai']    : '';
$tgl_akhir = isset($_GET['tgl_akhir'])    ? $_GET['tgl_akhir']    : '';

$valid_types = ['ralan', 'ranap', 'lab', 'operasi'];
if (!in_array($type, $valid_types) || !$kd_jenis || !$tgl_mulai || !$tgl_akhir) {
    echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap.', 'data' => []]);
    exit;
}

$kd_esc    = mysqli_real_escape_string($koneksi, $kd_jenis);
$mulai_esc = mysqli_real_escape_string($koneksi, $tgl_mulai);
$akhir_esc = mysqli_real_escape_string($koneksi, $tgl_akhir);

// -------------------------------------------------------
// Query berbeda per type
// -------------------------------------------------------
if ($type === 'ralan' || $type === 'ranap') {

    $tabel_trx = $type === 'ralan' ? 'rawat_jl_drpr'    : 'rawat_inap_drpr';
    $tabel_jns = $type === 'ralan' ? 'jns_perawatan'     : 'jns_perawatan_inap';

    $sql = "SELECT
                r.no_rawat,
                r.tgl_perawatan,
                r.kd_dokter,
                r.biaya_rawat        AS tarif_lama,
                j.total_byrdrpr      AS tarif_baru,
                r.material           AS material_lama,
                j.material           AS material_baru,
                r.menejemen          AS menejemen_lama,
                j.menejemen          AS menejemen_baru,
                r.tarif_tindakandr   AS tindakandr_lama,
                j.tarif_tindakandr   AS tindakandr_baru,
                r.tarif_tindakanpr   AS tindakanpr_lama,
                j.tarif_tindakanpr   AS tindakanpr_baru,
                (j.total_byrdrpr - r.biaya_rawat) AS selisih
            FROM `$tabel_trx` r
            JOIN `$tabel_jns` j ON j.kd_jenis_prw = r.kd_jenis_prw
            WHERE r.kd_jenis_prw = '$kd_esc'
              AND r.tgl_perawatan BETWEEN '$mulai_esc' AND '$akhir_esc'
            HAVING selisih <> 0
            ORDER BY r.tgl_perawatan ASC, r.no_rawat ASC";

    $result = mysqli_query($koneksi, $sql);
    if (!$result) {
        echo json_encode(['success' => false, 'message' => mysqli_error($koneksi), 'data' => []]);
        exit;
    }

    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = [
            'mode'            => 'ralan_ranap',
            'no_rawat'        => $row['no_rawat'],
            'tgl_perawatan'   => $row['tgl_perawatan'],
            'kd_dokter'       => $row['kd_dokter'],
            'tarif_lama'      => (float)$row['tarif_lama'],
            'tarif_baru'      => (float)$row['tarif_baru'],
            'selisih'         => (float)$row['selisih'],
            'material_lama'   => (float)$row['material_lama'],
            'material_baru'   => (float)$row['material_baru'],
            'menejemen_lama'  => (float)$row['menejemen_lama'],
            'menejemen_baru'  => (float)$row['menejemen_baru'],
            'tindakandr_lama' => (float)$row['tindakandr_lama'],
            'tindakandr_baru' => (float)$row['tindakandr_baru'],
            'tindakanpr_lama' => (float)$row['tindakanpr_lama'],
            'tindakanpr_baru' => (float)$row['tindakanpr_baru'],
        ];
    }

} elseif ($type === 'lab') {

    $sql = "SELECT
                r.no_rawat,
                r.tgl_periksa       AS tgl_perawatan,
                r.kd_dokter,
                r.biaya             AS tarif_lama,
                j.total_byr         AS tarif_baru,
                r.bagian_rs         AS bagian_rs_lama,
                j.bagian_rs         AS bagian_rs_baru,
                r.bhp               AS bhp_lama,
                j.bhp               AS bhp_baru,
                r.tarif_perujuk     AS perujuk_lama,
                j.tarif_perujuk     AS perujuk_baru,
                r.tarif_tindakan_dokter  AS tindakan_dr_lama,
                j.tarif_tindakan_dokter  AS tindakan_dr_baru,
                r.tarif_tindakan_petugas AS tindakan_pt_lama,
                j.tarif_tindakan_petugas AS tindakan_pt_baru,
                r.kso               AS kso_lama,
                j.kso               AS kso_baru,
                r.menejemen         AS menejemen_lama,
                j.menejemen         AS menejemen_baru,
                (j.total_byr - r.biaya) AS selisih
            FROM `periksa_lab` r
            JOIN `jns_perawatan_lab` j ON j.kd_jenis_prw = r.kd_jenis_prw
            WHERE r.kd_jenis_prw = '$kd_esc'
              AND r.tgl_periksa BETWEEN '$mulai_esc' AND '$akhir_esc'
            HAVING selisih <> 0
            ORDER BY r.tgl_periksa ASC, r.no_rawat ASC";

    $result = mysqli_query($koneksi, $sql);
    if (!$result) {
        echo json_encode(['success' => false, 'message' => mysqli_error($koneksi), 'data' => []]);
        exit;
    }

    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = [
            'mode'            => 'lab',
            'no_rawat'        => $row['no_rawat'],
            'tgl_perawatan'   => $row['tgl_perawatan'],
            'kd_dokter'       => $row['kd_dokter'],
            'tarif_lama'      => (float)$row['tarif_lama'],
            'tarif_baru'      => (float)$row['tarif_baru'],
            'selisih'         => (float)$row['selisih'],
            'bagian_rs_lama'  => (float)$row['bagian_rs_lama'],
            'bagian_rs_baru'  => (float)$row['bagian_rs_baru'],
            'bhp_lama'        => (float)$row['bhp_lama'],
            'bhp_baru'        => (float)$row['bhp_baru'],
            'perujuk_lama'    => (float)$row['perujuk_lama'],
            'perujuk_baru'    => (float)$row['perujuk_baru'],
            'tindakan_dr_lama'=> (float)$row['tindakan_dr_lama'],
            'tindakan_dr_baru'=> (float)$row['tindakan_dr_baru'],
            'tindakan_pt_lama'=> (float)$row['tindakan_pt_lama'],
            'tindakan_pt_baru'=> (float)$row['tindakan_pt_baru'],
            'kso_lama'        => (float)$row['kso_lama'],
            'kso_baru'        => (float)$row['kso_baru'],
            'menejemen_lama'  => (float)$row['menejemen_lama'],
            'menejemen_baru'  => (float)$row['menejemen_baru'],
        ];
    }

} elseif ($type === 'operasi') {

    // Hitung total lama dan baru dari jumlah semua kolom biaya
    $total_lama_expr = "r.biayaoperator1+r.biayaoperator2+r.biayaoperator3"
        . "+r.biayaasisten_operator1+r.biayaasisten_operator2+r.biayaasisten_operator3"
        . "+r.biayainstrumen+r.biayadokter_anak+r.biayaperawaat_resusitas"
        . "+r.biayadokter_anestesi+r.biayaasisten_anestesi+r.biayaasisten_anestesi2"
        . "+r.biayabidan+r.biayabidan2+r.biayabidan3+r.biayaperawat_luar"
        . "+r.biayaalat+r.biayasewaok+r.akomodasi+r.bagian_rs"
        . "+r.biaya_omloop+r.biaya_omloop2+r.biaya_omloop3+r.biaya_omloop4+r.biaya_omloop5"
        . "+r.biayasarpras+r.biaya_dokter_pjanak+r.biaya_dokter_umum";

    $total_baru_expr = "j.operator1+j.operator2+j.operator3"
        . "+j.asisten_operator1+j.asisten_operator2+j.asisten_operator3"
        . "+j.instrumen+j.dokter_anak+j.perawaat_resusitas"
        . "+j.dokter_anestesi+j.asisten_anestesi+j.asisten_anestesi2"
        . "+j.bidan+j.bidan2+j.bidan3+j.perawat_luar"
        . "+j.alat+j.sewa_ok+j.akomodasi+j.bagian_rs"
        . "+j.omloop+j.omloop2+j.omloop3+j.omloop4+j.omloop5"
        . "+j.sarpras+j.dokter_pjanak+j.dokter_umum";

    $sql = "SELECT
                r.no_rawat,
                DATE(r.tgl_operasi) AS tgl_perawatan,
                r.kode_paket,
                ($total_lama_expr)  AS tarif_lama,
                ($total_baru_expr)  AS tarif_baru,
                (($total_baru_expr) - ($total_lama_expr)) AS selisih
            FROM `operasi` r
            JOIN `paket_operasi` j ON j.kode_paket = r.kode_paket
            WHERE r.kode_paket = '$kd_esc'
              AND DATE(r.tgl_operasi) BETWEEN '$mulai_esc' AND '$akhir_esc'
            HAVING selisih <> 0
            ORDER BY r.tgl_operasi ASC, r.no_rawat ASC";

    $result = mysqli_query($koneksi, $sql);
    if (!$result) {
        echo json_encode(['success' => false, 'message' => mysqli_error($koneksi), 'data' => []]);
        exit;
    }

    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = [
            'mode'          => 'operasi',
            'no_rawat'      => $row['no_rawat'],
            'tgl_perawatan' => $row['tgl_perawatan'],
            'kode_paket'    => $row['kode_paket'],
            'tarif_lama'    => (float)$row['tarif_lama'],
            'tarif_baru'    => (float)$row['tarif_baru'],
            'selisih'       => (float)$row['selisih'],
        ];
    }
}

echo json_encode(['success' => true, 'total' => count($data), 'data' => $data]);
?>
