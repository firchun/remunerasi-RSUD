<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/conf.php';
$koneksi = bukakoneksi();

$no_rawat = $_GET['no_rawat'] ?? '';

$query = "
    SELECT 
        rawat_jl_drpr.no_rawat,
        reg_periksa.no_rkm_medis,
        pasien.nm_pasien,
        bridging_sep.no_sep,
        dokter.nm_dokter,
        petugas.nama,
        poliklinik.nm_poli,
        penjab.png_jawab,
        jns_perawatan.nm_perawatan,
        jns_perawatan.material,
        jns_perawatan.bhp,
        jns_perawatan.tarif_tindakandr,
        jns_perawatan.tarif_tindakanpr,
        jns_perawatan.menejemen,
        jns_perawatan.total_byrdrpr,
        CONCAT(rawat_jl_drpr.tgl_perawatan, ' ', rawat_jl_drpr.jam_rawat) AS waktu
    FROM pasien
    JOIN reg_periksa ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis
    JOIN bridging_sep ON bridging_sep.no_rawat = reg_periksa.no_rawat
    JOIN rawat_jl_drpr ON rawat_jl_drpr.no_rawat = reg_periksa.no_rawat
    JOIN jns_perawatan ON rawat_jl_drpr.kd_jenis_prw = jns_perawatan.kd_jenis_prw
    JOIN dokter ON rawat_jl_drpr.kd_dokter = dokter.kd_dokter
    JOIN poliklinik ON reg_periksa.kd_poli = poliklinik.kd_poli
    JOIN penjab ON reg_periksa.kd_pj = penjab.kd_pj
    JOIN petugas ON rawat_jl_drpr.nip = petugas.nip
    LEFT JOIN detail_pemberian_obat 
        ON detail_pemberian_obat.no_rawat = reg_periksa.no_rawat 
        AND detail_pemberian_obat.status = 'Ralan'
    LEFT JOIN periksa_lab 
        ON periksa_lab.no_rawat = reg_periksa.no_rawat 
        AND periksa_lab.status = 'Ralan'
    LEFT JOIN periksa_radiologi 
        ON periksa_radiologi.no_rawat = reg_periksa.no_rawat 
        AND periksa_radiologi.status = 'Ralan'
    WHERE rawat_jl_drpr.no_rawat = '$no_rawat'
    ORDER BY rawat_jl_drpr.tgl_perawatan, rawat_jl_drpr.jam_rawat
";

$result = mysqli_query($koneksi, $query);

$dataDetailTindakan = [];
while ($row = mysqli_fetch_assoc($result)) {
    $dataDetailTindakan[] = $row;
}

echo json_encode($dataDetailTindakan);

mysqli_close($koneksi);