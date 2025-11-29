<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// contoh ; 2606R0011125V005190

require_once '../config/conf.php';
$koneksi = bukakoneksi();

$no_rawat = $_GET['no_rawat'] ?? '';

$query = "
    SELECT 
        periksa_lab.no_rawat,
        reg_periksa.no_rkm_medis,
        pasien.nm_pasien,
        bridging_sep.no_sep,
        poliklinik.nm_poli,
        penjab.png_jawab,
        dokter.nm_dokter,
        CONCAT(periksa_lab.tgl_periksa, ' ', periksa_lab.jam) AS waktu,

        -- Nama tindakan lab
        jns_perawatan_lab.kd_jenis_prw,
        jns_perawatan_lab.nm_perawatan,

        -- Biaya-biaya dari periksa_lab
        periksa_lab.bagian_rs,
        periksa_lab.bhp,
        periksa_lab.tarif_perujuk,
        periksa_lab.tarif_tindakan_dokter,
        periksa_lab.tarif_tindakan_petugas,
        periksa_lab.menejemen,
        periksa_lab.biaya

    FROM periksa_lab
    JOIN reg_periksa 
        ON reg_periksa.no_rawat = periksa_lab.no_rawat
    JOIN rawat_jl_drpr ON rawat_jl_drpr.no_rawat = reg_periksa.no_rawat
    JOIN dokter ON rawat_jl_drpr.kd_dokter = dokter.kd_dokter
    JOIN jns_perawatan_lab 
        ON jns_perawatan_lab.kd_jenis_prw = periksa_lab.kd_jenis_prw
    JOIN pasien 
        ON pasien.no_rkm_medis = reg_periksa.no_rkm_medis

    LEFT JOIN bridging_sep 
        ON bridging_sep.no_rawat = reg_periksa.no_rawat
    LEFT JOIN poliklinik 
        ON reg_periksa.kd_poli = poliklinik.kd_poli
    LEFT JOIN penjab 
        ON reg_periksa.kd_pj = penjab.kd_pj

    WHERE periksa_lab.no_rawat = '$no_rawat'
      AND periksa_lab.status = 'Ranap'

    ORDER BY periksa_lab.tgl_periksa, periksa_lab.jam
";

$result = mysqli_query($koneksi, $query);

$dataObat = [];
while ($row = mysqli_fetch_assoc($result)) {
    $dataObat[] = $row;
}

echo json_encode($dataObat);

mysqli_close($koneksi);