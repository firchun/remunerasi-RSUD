<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/conf.php';
$koneksi = bukakoneksi();
$koneksi2 = bukakoneksi2();

$no_rawat = $_GET['no_rawat'] ?? '';

if (empty($no_rawat)) {
    echo json_encode(['error' => 'No rawat tidak ditemukan']);
    exit;
}

// ===============================
// GET INFO PASIEN (AMBIL SEKALI SAJA)
// ===============================
$info_query = mysqli_query($koneksi, "
    SELECT 
        reg_periksa.no_rawat,
        reg_periksa.no_rkm_medis,
        pasien.nm_pasien,
        IFNULL(bridging_sep.no_sep, '-') AS no_sep,
        penjab.png_jawab
    FROM reg_periksa
    JOIN pasien ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis
    LEFT JOIN bridging_sep ON bridging_sep.no_rawat = reg_periksa.no_rawat
    JOIN penjab ON reg_periksa.kd_pj = penjab.kd_pj
    WHERE reg_periksa.no_rawat = '$no_rawat'
    LIMIT 1
");

$info = mysqli_fetch_assoc($info_query);

if (!$info) {
    echo json_encode(['error' => 'Data pasien tidak ditemukan']);
    exit;
}

// ===============================
// GET INFO BANGSAL/RUANG
// ===============================
$bangsal_query = mysqli_query($koneksi, "
    SELECT bangsal.nm_bangsal, kamar.kd_kamar
    FROM kamar_inap
    JOIN kamar ON kamar_inap.kd_kamar = kamar.kd_kamar
    JOIN bangsal ON kamar.kd_bangsal = bangsal.kd_bangsal
    WHERE kamar_inap.no_rawat = '$no_rawat'
    LIMIT 1
");

$ruang = 'Ruang Terhapus';
if ($bangsal_query && mysqli_num_rows($bangsal_query) > 0) {
    $bangsal_data = mysqli_fetch_assoc($bangsal_query);
    $ruang = $bangsal_data['nm_bangsal'];
}

// ===============================
// QUERY DETAIL TINDAKAN RAWAT INAP (TANPA JOIN KE TABLE LAIN)
// ===============================
$tindakan_query = "
    SELECT 
        rawat_inap_drpr.no_rawat,
        rawat_inap_drpr.kd_jenis_prw,
        rawat_inap_drpr.kd_dokter,
        rawat_inap_drpr.nip,
        rawat_inap_drpr.tgl_perawatan,
        rawat_inap_drpr.jam_rawat,
        CONCAT(rawat_inap_drpr.tgl_perawatan, ' ', rawat_inap_drpr.jam_rawat) AS waktu,
        
        jns_perawatan_inap.nm_perawatan,
        jns_perawatan_inap.material,
        jns_perawatan_inap.bhp,
        jns_perawatan_inap.tarif_tindakandr,
        jns_perawatan_inap.tarif_tindakanpr,
        jns_perawatan_inap.kso,
        jns_perawatan_inap.menejemen,
        jns_perawatan_inap.total_byrdrpr,
        
        dokter.nm_dokter,
        petugas.nama AS nama_petugas
        
    FROM rawat_inap_drpr
    LEFT JOIN jns_perawatan_inap ON rawat_inap_drpr.kd_jenis_prw = jns_perawatan_inap.kd_jenis_prw
    LEFT JOIN dokter ON rawat_inap_drpr.kd_dokter = dokter.kd_dokter
    LEFT JOIN petugas ON rawat_inap_drpr.nip = petugas.nip
    
    WHERE rawat_inap_drpr.no_rawat = '$no_rawat'
    ORDER BY rawat_inap_drpr.tgl_perawatan, rawat_inap_drpr.jam_rawat
";

$result = mysqli_query($koneksi, $tindakan_query);

if (!$result) {
    echo json_encode(['error' => mysqli_error($koneksi)]);
    exit;
}

$dataDetailTindakan = [];
$total_tindakan = 0;
while ($row = mysqli_fetch_assoc($result)) {
    $dataDetailTindakan[] = $row;
    $total_tindakan += $row['total_byrdrpr'];
}

// ===============================
// GET DETAIL OBAT
// ===============================
$obat_query = mysqli_query($koneksi, "
    SELECT 
        databarang.kode_brng,
        databarang.nama_brng AS nama_obat,
        detail_pemberian_obat.jml AS jumlah,
        detail_pemberian_obat.biaya_obat,
        detail_pemberian_obat.embalase,
        detail_pemberian_obat.tuslah,
        detail_pemberian_obat.total,
        detail_pemberian_obat.tgl_perawatan,
        detail_pemberian_obat.jam
    FROM detail_pemberian_obat
    JOIN databarang ON databarang.kode_brng = detail_pemberian_obat.kode_brng
    WHERE detail_pemberian_obat.no_rawat = '$no_rawat'
      AND detail_pemberian_obat.status = 'Ranap'
    ORDER BY detail_pemberian_obat.tgl_perawatan, detail_pemberian_obat.jam
");

$dataObat = [];
$total_obat = 0;
while ($obat = mysqli_fetch_assoc($obat_query)) {
    $dataObat[] = $obat;
    $total_obat += $obat['total'];
}

// ===============================
// GET DETAIL LAB
// ===============================
$lab_query = mysqli_query($koneksi, "
    SELECT 
        periksa_lab.no_rawat,
        periksa_lab.nip,
        periksa_lab.kd_jenis_prw,
        jns_perawatan_lab.nm_perawatan,
        periksa_lab.tgl_periksa,
        periksa_lab.jam,
        periksa_lab.dokter_perujuk,
        periksa_lab.bagian_rs,
        periksa_lab.bhp,
        periksa_lab.tarif_perujuk,
        periksa_lab.tarif_tindakan_dokter,
        periksa_lab.tarif_tindakan_petugas,
        periksa_lab.kso,
        periksa_lab.menejemen,
        periksa_lab.biaya
    FROM periksa_lab
    LEFT JOIN jns_perawatan_lab ON periksa_lab.kd_jenis_prw = jns_perawatan_lab.kd_jenis_prw
    WHERE periksa_lab.no_rawat = '$no_rawat'
      AND periksa_lab.status = 'Ranap'
    ORDER BY periksa_lab.tgl_periksa, periksa_lab.jam
");

$dataLab = [];
$total_lab = 0;
while ($lab = mysqli_fetch_assoc($lab_query)) {
    $dataLab[] = $lab;
    $total_lab += $lab['biaya'];
}

// ===============================
// GET DETAIL RADIOLOGI
// ===============================
$rad_query = mysqli_query($koneksi, "
    SELECT 
        periksa_radiologi.no_rawat,
        periksa_radiologi.nip,
        periksa_radiologi.kd_jenis_prw,
        jns_perawatan_radiologi.nm_perawatan,
        periksa_radiologi.tgl_periksa,
        periksa_radiologi.jam,
        periksa_radiologi.dokter_perujuk,
        periksa_radiologi.bagian_rs,
        periksa_radiologi.bhp,
        periksa_radiologi.tarif_perujuk,
        periksa_radiologi.tarif_tindakan_dokter,
        periksa_radiologi.tarif_tindakan_petugas,
        periksa_radiologi.kso,
        periksa_radiologi.menejemen,
        periksa_radiologi.biaya
    FROM periksa_radiologi
    LEFT JOIN jns_perawatan_radiologi ON periksa_radiologi.kd_jenis_prw = jns_perawatan_radiologi.kd_jenis_prw
    WHERE periksa_radiologi.no_rawat = '$no_rawat'
      AND periksa_radiologi.status = 'Ranap'
    ORDER BY periksa_radiologi.tgl_periksa, periksa_radiologi.jam
");

$dataRadiologi = [];
$total_radiologi = 0;
while ($rad = mysqli_fetch_assoc($rad_query)) {
    $dataRadiologi[] = $rad;
    $total_radiologi += $rad['biaya'];
}



// ===============================
// HITUNG GRAND TOTAL
// ===============================
$grand_total = $total_tindakan + $total_obat + $total_lab + $total_radiologi;

// ===============================
// OUTPUT JSON
// ===============================
echo json_encode([
    'info' => [
        'no_rawat' => $no_rawat,
        'no_rkm_medis' => $info['no_rkm_medis'],
        'nm_pasien' => $info['nm_pasien'],
        'no_sep' => $info['no_sep'],
        'png_jawab' => $info['png_jawab'],
        'ruang' => $ruang
    ],
    'tindakan' => $dataDetailTindakan,
    'obat' => $dataObat,
    'lab' => $dataLab,
    'radiologi' => $dataRadiologi,
    'summary' => [
        'total_tindakan' => $total_tindakan,
        'total_obat' => $total_obat,
        'total_obat_ppn' => $total_obat * 1.11,
        'total_lab' => $total_lab,
        'total_radiologi' => $total_radiologi,
        'grand_total' => $grand_total,
    ]
], JSON_UNESCAPED_UNICODE);

mysqli_close($koneksi);
mysqli_close($koneksi2);
