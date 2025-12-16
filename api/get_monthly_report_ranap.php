<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/conf.php';
$koneksi = bukakoneksi();

// Get parameters
$bulan = $_POST['bulan'] ?? date('Y-m');
$kd_pj = $_POST['kd_pj'] ?? '';

// Parse bulan untuk filter
list($tahun, $bln) = explode('-', $bulan);
$tgl_awal = "$tahun-$bln-01 00:00:00";
$tgl_akhir = date("Y-m-t 23:59:59", strtotime($tgl_awal));

// Base WHERE condition
$where = "WHERE reg_periksa.status_lanjut = 'Ranap'
    AND CONCAT(reg_periksa.tgl_registrasi,' ',reg_periksa.jam_reg) 
    BETWEEN '$tgl_awal' AND '$tgl_akhir'
   ";

// Filter cara bayar jika dipilih
if (!empty($kd_pj)) {
  $where .= " AND reg_periksa.kd_pj = '$kd_pj'";
}

// Query utama - group by kamar
$query = "
SELECT 
    bangsal.kd_bangsal,
    bangsal.nm_bangsal,
    COUNT(DISTINCT reg_periksa.no_rawat) AS jumlah_kunjungan,
    
    -- TINDAKAN
    IFNULL(SUM(jns_perawatan.material), 0) AS total_material_tindakan,
    IFNULL(SUM(jns_perawatan.bhp), 0) AS total_bhp_tindakan,
    IFNULL(SUM(jns_perawatan.tarif_tindakandr), 0) AS total_dokter_tindakan,
    IFNULL(SUM(jns_perawatan.tarif_tindakanpr), 0) AS total_perawat_tindakan,
    IFNULL(SUM(jns_perawatan.kso), 0) AS total_kso_tindakan,
    IFNULL(SUM(jns_perawatan.menejemen), 0) AS total_menejemen_tindakan,
    IFNULL(SUM(jns_perawatan.total_byrdrpr), 0) AS total_tindakan,
    
    -- OBAT (dihitung dari detail_pemberian_obat)
    (SELECT IFNULL(SUM(dpo.total), 0)
     FROM detail_pemberian_obat dpo
     JOIN reg_periksa rp ON dpo.no_rawat = rp.no_rawat
     WHERE dpo.status = 'Ranap'
     AND rp.kd_poli = bangsal.kd_bangsal
     AND CONCAT(rp.tgl_registrasi,' ',rp.jam_reg) BETWEEN '$tgl_awal' AND '$tgl_akhir'
     " . (!empty($kd_pj) ? "AND rp.kd_pj = '$kd_pj'" : "") . "
    ) AS total_obat,
    
    -- RESEP RACIKAN
    (SELECT COUNT(DISTINCT ro.no_resep)
     FROM resep_obat ro
     JOIN resep_dokter_racikan rdr ON ro.no_resep = rdr.no_resep
     JOIN reg_periksa rp ON ro.no_rawat = rp.no_rawat
     WHERE ro.status = 'Ranap'
     AND rp.kd_poli = bangsal.kd_bangsal
     AND CONCAT(rp.tgl_registrasi,' ',rp.jam_reg) BETWEEN '$tgl_awal' AND '$tgl_akhir'
     " . (!empty($kd_pj) ? "AND rp.kd_pj = '$kd_pj'" : "") . "
    ) AS jumlah_resep_racikan,
    
    -- RESEP NON RACIKAN
    (SELECT COUNT(DISTINCT ro.no_resep)
     FROM resep_obat ro
     JOIN resep_dokter rd ON ro.no_resep = rd.no_resep
     LEFT JOIN resep_dokter_racikan rdr ON ro.no_resep = rdr.no_resep
     JOIN reg_periksa rp ON ro.no_rawat = rp.no_rawat
     WHERE ro.status = 'Ranap'
     AND rdr.no_resep IS NULL
     AND SUBSTRING(ro.no_resep, 1, 2) != 'OK'
     AND rp.kd_poli = bangsal.kd_bangsal
     AND CONCAT(rp.tgl_registrasi,' ',rp.jam_reg) BETWEEN '$tgl_awal' AND '$tgl_akhir'
     " . (!empty($kd_pj) ? "AND rp.kd_pj = '$kd_pj'" : "") . "
    ) AS jumlah_resep_non_racikan,
    
    -- RESEP OPERASI
    (SELECT COUNT(DISTINCT ro.no_resep)
     FROM resep_obat ro
     JOIN reg_periksa rp ON ro.no_rawat = rp.no_rawat
     WHERE ro.status = 'Ranap'
     AND SUBSTRING(ro.no_resep, 1, 2) = 'OK'
     AND rp.kd_poli = bangsal.kd_bangsal
     AND CONCAT(rp.tgl_registrasi,' ',rp.jam_reg) BETWEEN '$tgl_awal' AND '$tgl_akhir'
     " . (!empty($kd_pj) ? "AND rp.kd_pj = '$kd_pj'" : "") . "
    ) AS jumlah_resep_operasi,
    
    -- LAB
    (SELECT IFNULL(SUM(pl.bagian_rs), 0)
     FROM periksa_lab pl
     JOIN reg_periksa rp ON pl.no_rawat = rp.no_rawat
     WHERE pl.status = 'Ranap'
     AND rp.kd_poli = bangsal.kd_bangsal
     AND CONCAT(rp.tgl_registrasi,' ',rp.jam_reg) BETWEEN '$tgl_awal' AND '$tgl_akhir'
     " . (!empty($kd_pj) ? "AND rp.kd_pj = '$kd_pj'" : "") . "
    ) AS total_material_lab,
    
    (SELECT IFNULL(SUM(pl.tarif_tindakan_dokter), 0)
     FROM periksa_lab pl
     JOIN reg_periksa rp ON pl.no_rawat = rp.no_rawat
     WHERE pl.status = 'Ranap'
     AND rp.kd_poli = bangsal.kd_bangsal
     AND CONCAT(rp.tgl_registrasi,' ',rp.jam_reg) BETWEEN '$tgl_awal' AND '$tgl_akhir'
     " . (!empty($kd_pj) ? "AND rp.kd_pj = '$kd_pj'" : "") . "
    ) AS total_dokter_lab,
    
    (SELECT IFNULL(SUM(pl.tarif_tindakan_petugas), 0)
     FROM periksa_lab pl
     JOIN reg_periksa rp ON pl.no_rawat = rp.no_rawat
     WHERE pl.status = 'Ranap'
     AND rp.kd_poli = bangsal.kd_bangsal
     AND CONCAT(rp.tgl_registrasi,' ',rp.jam_reg) BETWEEN '$tgl_awal' AND '$tgl_akhir'
     " . (!empty($kd_pj) ? "AND rp.kd_pj = '$kd_pj'" : "") . "
    ) AS total_petugas_lab,
    
    (SELECT IFNULL(SUM(pl.menejemen), 0)
     FROM periksa_lab pl
     JOIN reg_periksa rp ON pl.no_rawat = rp.no_rawat
     WHERE pl.status = 'Ranap'
     AND rp.kd_poli = bangsal.kd_bangsal
     AND CONCAT(rp.tgl_registrasi,' ',rp.jam_reg) BETWEEN '$tgl_awal' AND '$tgl_akhir'
     " . (!empty($kd_pj) ? "AND rp.kd_pj = '$kd_pj'" : "") . "
    ) AS total_menejemen_lab,
    
    (SELECT IFNULL(SUM(pl.biaya), 0)
     FROM periksa_lab pl
     JOIN reg_periksa rp ON pl.no_rawat = rp.no_rawat
     WHERE pl.status = 'Ranap'
     AND rp.kd_poli = bangsal.kd_bangsal
     AND CONCAT(rp.tgl_registrasi,' ',rp.jam_reg) BETWEEN '$tgl_awal' AND '$tgl_akhir'
     " . (!empty($kd_pj) ? "AND rp.kd_pj = '$kd_pj'" : "") . "
    ) AS total_lab,
    
    -- RADIOLOGI
    (SELECT IFNULL(SUM(pr.bagian_rs), 0)
     FROM periksa_radiologi pr
     JOIN reg_periksa rp ON pr.no_rawat = rp.no_rawat
     WHERE pr.status = 'Ranap'
     AND rp.kd_poli = bangsal.kd_bangsal
     AND CONCAT(rp.tgl_registrasi,' ',rp.jam_reg) BETWEEN '$tgl_awal' AND '$tgl_akhir'
     " . (!empty($kd_pj) ? "AND rp.kd_pj = '$kd_pj'" : "") . "
    ) AS total_material_radiologi,
    
    (SELECT IFNULL(SUM(pr.tarif_tindakan_dokter), 0)
     FROM periksa_radiologi pr
     JOIN reg_periksa rp ON pr.no_rawat = rp.no_rawat
     WHERE pr.status = 'Ranap'
     AND rp.kd_poli = bangsal.kd_bangsal
     AND CONCAT(rp.tgl_registrasi,' ',rp.jam_reg) BETWEEN '$tgl_awal' AND '$tgl_akhir'
     " . (!empty($kd_pj) ? "AND rp.kd_pj = '$kd_pj'" : "") . "
    ) AS total_dokter_radiologi,
    
    (SELECT IFNULL(SUM(pr.tarif_tindakan_petugas), 0)
     FROM periksa_radiologi pr
     JOIN reg_periksa rp ON pr.no_rawat = rp.no_rawat
     WHERE pr.status = 'Ranap'
     AND rp.kd_poli = bangsal.kd_bangsal
     AND CONCAT(rp.tgl_registrasi,' ',rp.jam_reg) BETWEEN '$tgl_awal' AND '$tgl_akhir'
     " . (!empty($kd_pj) ? "AND rp.kd_pj = '$kd_pj'" : "") . "
    ) AS total_petugas_radiologi,
    
    (SELECT IFNULL(SUM(pr.menejemen), 0)
     FROM periksa_radiologi pr
     JOIN reg_periksa rp ON pr.no_rawat = rp.no_rawat
     WHERE pr.status = 'Ranap'
     AND rp.kd_poli = bangsal.kd_bangsal
     AND CONCAT(rp.tgl_registrasi,' ',rp.jam_reg) BETWEEN '$tgl_awal' AND '$tgl_akhir'
     " . (!empty($kd_pj) ? "AND rp.kd_pj = '$kd_pj'" : "") . "
    ) AS total_menejemen_radiologi,
    
    (SELECT IFNULL(SUM(pr.biaya), 0)
     FROM periksa_radiologi pr
     JOIN reg_periksa rp ON pr.no_rawat = rp.no_rawat
     WHERE pr.status = 'Ranap'
     AND rp.kd_poli = bangsal.kd_bangsal
     AND CONCAT(rp.tgl_registrasi,' ',rp.jam_reg) BETWEEN '$tgl_awal' AND '$tgl_akhir'
     " . (!empty($kd_pj) ? "AND rp.kd_pj = '$kd_pj'" : "") . "
    ) AS total_radiologi

FROM reg_periksa
JOIN bangsal ON reg_periksa.kd_poli = bangsal.kd_bangsal
LEFT JOIN rawat_inap_drpr ON rawat_inap_drpr.no_rawat = reg_periksa.no_rawat
LEFT JOIN jns_perawatan ON rawat_inap_drpr.kd_jenis_prw = jns_perawatan.kd_jenis_prw

$where

GROUP BY 
    bangsal.kd_bangsal,
    bangsal.nm_bangsal
    
ORDER BY bangsal.nm_bangsal ASC
";

$result = mysqli_query($koneksi, $query);

if (!$result) {
  echo json_encode([
    "error" => mysqli_error($koneksi),
    "query" => $query
  ]);
  exit;
}

$data = [];

while ($row = mysqli_fetch_assoc($result)) {
  // Hitung jasa farmasi
  $racikan = (int)$row['jumlah_resep_racikan'];
  $non_racikan = (int)$row['jumlah_resep_non_racikan'];
  $operasi = (int)$row['jumlah_resep_operasi'];

  $row['jasa_farmasi'] = ($racikan * 25000) + ($non_racikan * 15000) + ($operasi * 35000);

  // Total obat + PPN 11%
  $row['total_obat_ppn'] = $row['total_obat'] * 1.11;

  // Grand total
  $row['grand_total'] =
    $row['total_tindakan'] +
    $row['total_obat_ppn'] +
    $row['jasa_farmasi'] +
    $row['total_lab'] +
    $row['total_radiologi'];

  $data[] = $row;
}

echo json_encode([
  "success" => true,
  "periode" => date('F Y', strtotime($tgl_awal)),
  "data" => $data
]);

mysqli_close($koneksi);
