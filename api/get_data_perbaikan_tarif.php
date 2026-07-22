<?php
require_once '../config/conf.php';
$koneksi = bukakoneksi();

header('Content-Type: application/json');

$type = isset($_GET['type']) ? $_GET['type'] : '';

switch($type) {
    case 'ralan':
        $table   = 'jns_perawatan';
        $kd_col  = 'kd_jenis_prw';
        $tarif   = 'total_byrdrpr';
        break;
    case 'ranap':
        $table   = 'jns_perawatan_inap';
        $kd_col  = 'kd_jenis_prw';
        $tarif   = 'total_byrdrpr';
        break;
    case 'lab':
        $table   = 'jns_perawatan_lab';
        $kd_col  = 'kd_jenis_prw';
        $tarif   = 'total_byr';
        break;
    case 'radiologi':
        $table   = 'jns_perawatan_radiologi';
        $kd_col  = 'kd_jenis_prw';
        $tarif   = 'total_byr';
        break;
    case 'operasi':
        // paket_operasi — tarif total = jumlah semua komponen
        $table   = 'paket_operasi';
        $kd_col  = 'kode_paket';
        $tarif   = '(operator1+operator2+operator3+asisten_operator1+asisten_operator2+asisten_operator3'
                 . '+instrumen+dokter_anak+perawaat_resusitas+dokter_anestesi+asisten_anestesi+asisten_anestesi2'
                 . '+bidan+bidan2+bidan3+perawat_luar+sewa_ok+alat+akomodasi+bagian_rs'
                 . '+omloop+omloop2+omloop3+omloop4+omloop5+sarpras+dokter_pjanak+dokter_umum)';
        break;
    default:
        echo json_encode(['data' => []]);
        exit;
}

$query = "SELECT $kd_col AS kd_jenis_prw, nm_perawatan, $tarif AS tarif FROM `$table` WHERE status = '1'";

$result = mysqli_query($koneksi, $query);

$data = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = [
            'kd_jenis_prw'  => $row['kd_jenis_prw'],
            'nm_perawatan'  => $row['nm_perawatan'],
            'total_byrdrpr' => $row['tarif'],
            'action'        => '<button class="btn-perbaiki" data-kd="' . htmlspecialchars($row['kd_jenis_prw']) . '" data-type="' . $type . '"> perbaiki</button>'
        ];
    }
}

echo json_encode(['data' => $data]);
?>
