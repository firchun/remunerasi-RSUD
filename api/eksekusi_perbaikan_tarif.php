<?php
require_once '../config/conf.php';
$koneksi = bukakoneksi();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method tidak valid.']);
    exit;
}

$type         = isset($_POST['type'])         ? $_POST['type']         : '';
$kd_jenis     = isset($_POST['kd_jenis_prw']) ? trim($_POST['kd_jenis_prw']) : '';
$no_rawat_arr = isset($_POST['no_rawat'])     ? (array)$_POST['no_rawat'] : [];

$valid_types = ['ralan', 'ranap', 'lab', 'operasi'];
if (!in_array($type, $valid_types) || !$kd_jenis || empty($no_rawat_arr)) {
    echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap atau tidak ada data dipilih.']);
    exit;
}

$kd_esc = mysqli_real_escape_string($koneksi, $kd_jenis);

// Build IN clause dari no_rawat yang dipilih
$placeholders = implode(',', array_map(function($no) use ($koneksi) {
    return "'" . mysqli_real_escape_string($koneksi, $no) . "'";
}, $no_rawat_arr));

// -------------------------------------------------------
// Query UPDATE berbeda per type
// -------------------------------------------------------
if ($type === 'ralan' || $type === 'ranap') {

    $tabel_trx = $type === 'ralan' ? 'rawat_jl_drpr'    : 'rawat_inap_drpr';
    $tabel_jns = $type === 'ralan' ? 'jns_perawatan'     : 'jns_perawatan_inap';

    $sql = "UPDATE `$tabel_trx` r
            JOIN `$tabel_jns` j ON j.kd_jenis_prw = r.kd_jenis_prw
            SET r.biaya_rawat        = j.total_byrdrpr,
                r.material           = j.material,
                r.menejemen          = j.menejemen,
                r.tarif_tindakandr   = j.tarif_tindakandr,
                r.tarif_tindakanpr   = j.tarif_tindakanpr
            WHERE r.kd_jenis_prw = '$kd_esc'
              AND r.no_rawat IN ($placeholders)";

} elseif ($type === 'lab') {

    $sql = "UPDATE `periksa_lab` r
            JOIN `jns_perawatan_lab` j ON j.kd_jenis_prw = r.kd_jenis_prw
            SET r.biaya                  = j.total_byr,
                r.bagian_rs              = j.bagian_rs,
                r.bhp                    = j.bhp,
                r.tarif_perujuk          = j.tarif_perujuk,
                r.tarif_tindakan_dokter  = j.tarif_tindakan_dokter,
                r.tarif_tindakan_petugas = j.tarif_tindakan_petugas,
                r.kso                    = j.kso,
                r.menejemen              = j.menejemen
            WHERE r.kd_jenis_prw = '$kd_esc'
              AND r.no_rawat IN ($placeholders)";

} elseif ($type === 'operasi') {

    $sql = "UPDATE `operasi` r
            JOIN `paket_operasi` j ON j.kode_paket = r.kode_paket
            SET r.biayaoperator1         = j.operator1,
                r.biayaoperator2         = j.operator2,
                r.biayaoperator3         = j.operator3,
                r.biayaasisten_operator1 = j.asisten_operator1,
                r.biayaasisten_operator2 = j.asisten_operator2,
                r.biayaasisten_operator3 = j.asisten_operator3,
                r.biayainstrumen         = j.instrumen,
                r.biayadokter_anak       = j.dokter_anak,
                r.biayaperawaat_resusitas= j.perawaat_resusitas,
                r.biayadokter_anestesi   = j.dokter_anestesi,
                r.biayaasisten_anestesi  = j.asisten_anestesi,
                r.biayaasisten_anestesi2 = j.asisten_anestesi2,
                r.biayabidan             = j.bidan,
                r.biayabidan2            = j.bidan2,
                r.biayabidan3            = j.bidan3,
                r.biayaperawat_luar      = j.perawat_luar,
                r.biayaalat              = j.alat,
                r.biayasewaok            = j.sewa_ok,
                r.akomodasi              = j.akomodasi,
                r.bagian_rs              = j.bagian_rs,
                r.biaya_omloop           = j.omloop,
                r.biaya_omloop2          = j.omloop2,
                r.biaya_omloop3          = j.omloop3,
                r.biaya_omloop4          = j.omloop4,
                r.biaya_omloop5          = j.omloop5,
                r.biayasarpras           = j.sarpras,
                r.biaya_dokter_pjanak    = j.dokter_pjanak,
                r.biaya_dokter_umum      = j.dokter_umum
            WHERE r.kode_paket = '$kd_esc'
              AND r.no_rawat IN ($placeholders)";
}

$result = mysqli_query($koneksi, $sql);

if (!$result) {
    echo json_encode(['success' => false, 'message' => mysqli_error($koneksi)]);
    exit;
}

$affected = mysqli_affected_rows($koneksi);
echo json_encode([
    'success'       => true,
    'affected_rows' => $affected,
    'message'       => "$affected data berhasil diperbarui."
]);
?>
