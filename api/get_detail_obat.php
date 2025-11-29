<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/conf.php';
$koneksi = bukakoneksi();

$no_rawat = $_GET['no_rawat'] ?? '';

$query = "
    SELECT 
        detail_pemberian_obat.no_rawat,
        reg_periksa.no_rkm_medis,
        pasien.nm_pasien,
        bridging_sep.no_sep,
        poliklinik.nm_poli,
        penjab.png_jawab,
        CONCAT(detail_pemberian_obat.tgl_perawatan, ' ', detail_pemberian_obat.jam) AS waktu,

        detail_pemberian_obat.kode_brng,
        databarang.nama_brng,
        detail_pemberian_obat.jml,
        detail_pemberian_obat.biaya_obat,
        detail_pemberian_obat.embalase,
        detail_pemberian_obat.tuslah

    FROM detail_pemberian_obat
    JOIN databarang 
        ON databarang.kode_brng = detail_pemberian_obat.kode_brng
    JOIN reg_periksa 
        ON reg_periksa.no_rawat = detail_pemberian_obat.no_rawat
    JOIN pasien 
        ON pasien.no_rkm_medis = reg_periksa.no_rkm_medis
    LEFT JOIN bridging_sep 
        ON bridging_sep.no_rawat = reg_periksa.no_rawat
    LEFT JOIN poliklinik 
        ON reg_periksa.kd_poli = poliklinik.kd_poli
    LEFT JOIN penjab 
        ON reg_periksa.kd_pj = penjab.kd_pj

    WHERE detail_pemberian_obat.no_rawat = '$no_rawat'
      AND detail_pemberian_obat.status = 'Ralan'

    ORDER BY detail_pemberian_obat.tgl_perawatan, detail_pemberian_obat.jam
";

$result = mysqli_query($koneksi, $query);

$dataObat = [];
while ($row = mysqli_fetch_assoc($result)) {
    $dataObat[] = $row;
}

echo json_encode($dataObat);

mysqli_close($koneksi);