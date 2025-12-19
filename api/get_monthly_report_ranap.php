<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(180);

require_once '../config/conf.php';
$koneksi = bukakoneksi();
$koneksi2 = bukakoneksi2();

$bulan = $_POST['bulan'] ?? date('Y-m');
$kd_pj = $_POST['kd_pj'] ?? '';
$gedung = $_POST['gedung'] ?? '';

list($tahun, $bln) = explode('-', $bulan);
$tgl_awal = "$tahun-$bln-01 00:00:00";
$tgl_akhir = date("Y-m-t 23:59:59", strtotime($tgl_awal));

// SQL untuk mapping gedung
$sqlGedung = "CASE 
    WHEN bs.nm_bangsal LIKE '%PICU%' THEN 'PICU'
    WHEN bs.nm_bangsal LIKE '%BOHA%' THEN 'BOHA'
    WHEN bs.nm_bangsal LIKE '%KANGURU%' THEN 'KANGURU'
    WHEN bs.nm_bangsal LIKE '%MALEO%' THEN 'MALEO'
    WHEN bs.nm_bangsal LIKE '%CENDERAWASIH%' THEN 'CENDERAWASIH'
    WHEN bs.nm_bangsal LIKE '%KUSKUS%' THEN 'KUSKUS'
    WHEN bs.nm_bangsal LIKE '%KASUARI%' THEN 'KASUARI'
    WHEN bs.nm_bangsal LIKE '%MAMBRUK%' THEN 'MAMBRUK'
    WHEN bs.nm_bangsal LIKE '%ICU%' THEN 'ICU'
    WHEN bs.nm_bangsal LIKE '%RUSA I.%' THEN 'RUSA I'
    WHEN bs.nm_bangsal LIKE '%RUSA II.%' THEN 'RUSA II'
    WHEN bs.nm_bangsal LIKE '%URIP%' THEN 'URIP'
    ELSE 'LAIN-LAIN'
END";

// Query utama untuk mendapatkan list no_rawat per gedung
// $query = "
// SELECT 
//     $sqlGedung AS nama_gedung,
//     rp.no_rawat
// FROM reg_periksa rp
// INNER JOIN kamar_inap ki ON rp.no_rawat = ki.no_rawat
// INNER JOIN kamar km ON ki.kd_kamar = km.kd_kamar
// INNER JOIN bangsal bs ON km.kd_bangsal = bs.kd_bangsal
// WHERE rp.status_lanjut = 'Ranap'
// AND CONCAT(rp.tgl_registrasi,' ',rp.jam_reg) BETWEEN '$tgl_awal' AND '$tgl_akhir'
// AND ki.stts_pulang != 'Pindah Kamar'
// " . (!empty($kd_pj) ? " AND rp.kd_pj = '" . mysqli_real_escape_string($koneksi, $kd_pj) . "'" : "") . "
// " . (!empty($gedung) ? " AND bs.nm_bangsal LIKE '%" . mysqli_real_escape_string($koneksi, $gedung) . "%'" : "") . "
// GROUP BY nama_gedung, rp.no_rawat
// ORDER BY nama_gedung ASC";
$query = "
SELECT 
    $sqlGedung AS nama_gedung,
    rp.no_rawat
FROM reg_periksa rp
INNER JOIN kamar_inap ki ON rp.no_rawat = ki.no_rawat
INNER JOIN kamar km ON ki.kd_kamar = km.kd_kamar
INNER JOIN bangsal bs ON km.kd_bangsal = bs.kd_bangsal
WHERE rp.status_lanjut = 'Ranap'
/* Filter berdasarkan tgl masuk kamar, bukan tgl registrasi awal */
AND ki.tgl_masuk BETWEEN '$tgl_awal' AND '$tgl_akhir'
" . (!empty($kd_pj) ? " AND rp.kd_pj = '" . mysqli_real_escape_string($koneksi, $kd_pj) . "'" : "") . "
" . (!empty($gedung) ? " AND bs.nm_bangsal LIKE '%" . mysqli_real_escape_string($koneksi, $gedung) . "%'" : "") . "
GROUP BY nama_gedung, rp.no_rawat";

$result = mysqli_query($koneksi, $query);

// Inisialisasi array untuk menyimpan data per gedung
$gedung_data = [];

while ($row = mysqli_fetch_assoc($result)) {
  $nama_gedung = $row['nama_gedung'];
  $no_rawat = mysqli_real_escape_string($koneksi, $row['no_rawat']);

  // Inisialisasi gedung jika belum ada
  if (!isset($gedung_data[$nama_gedung])) {
    $gedung_data[$nama_gedung] = [
      'nama_gedung' => $nama_gedung,
      'jumlah_kunjungan' => 0,
      'total_biaya_kamar' => 0,
      'total_material' => 0,
      'total_bhp' => 0,
      'total_tindakan_dr' => 0,
      'total_tindakan_pr' => 0,
      'total_kso' => 0,
      'total_menejemen' => 0,
      'total_tindakan' => 0,
      'jumlah_resep_racikan' => 0,
      'jumlah_resep_non_racikan' => 0,
      'jumlah_resep_operasi' => 0,
      'total_obat' => 0,
      'total_jasa_farmasi' => 0,
      'total_material_lab' => 0,
      'total_dokter_lab' => 0,
      'total_petugas_lab' => 0,
      'total_menejemen_lab' => 0,
      'total_lab' => 0,
      'total_material_radiologi' => 0,
      'total_dokter_radiologi' => 0,
      'total_petugas_radiologi' => 0,
      'total_menejemen_radiologi' => 0,
      'total_radiologi' => 0,
      'total_operasi' => 0,
      'grand_total' => 0
    ];
  }

  // Increment jumlah kunjungan
  $gedung_data[$nama_gedung]['jumlah_kunjungan']++;

  // ===============================
  // 1. BIAYA KAMAR
  // ===============================
  $kamar_result = mysqli_query($koneksi, "
        SELECT 
            SUM(kamar_inap.lama * kamar_inap.trf_kamar) AS total_biaya_kamar
        FROM kamar_inap
        WHERE kamar_inap.no_rawat = '$no_rawat'
    ");
  if ($kamar_result && mysqli_num_rows($kamar_result) > 0) {
    $kamar_data = mysqli_fetch_assoc($kamar_result);
    $gedung_data[$nama_gedung]['total_biaya_kamar'] += floatval($kamar_data['total_biaya_kamar'] ?? 0);
  }

  // ===============================
  // 2. TINDAKAN RANAP
  // ===============================
  $tindakan_inap = mysqli_query($koneksi, "
        SELECT 
            SUM(jns.material) AS total_material,
            SUM(jns.bhp) AS total_bhp,
            SUM(jns.tarif_tindakandr) AS total_tindakan_dr,
            SUM(jns.tarif_tindakanpr) AS total_tindakan_pr,
            SUM(jns.kso) AS total_kso,
            SUM(jns.menejemen) AS total_menejemen,
            SUM(jns.total_byrdrpr) AS total_biaya_rawat
        FROM rawat_inap_drpr drpr
        JOIN jns_perawatan_inap jns ON drpr.kd_jenis_prw = jns.kd_jenis_prw
        WHERE drpr.no_rawat = '$no_rawat'
    ");
  if ($tindakan_inap && mysqli_num_rows($tindakan_inap) > 0) {
    $data_ti = mysqli_fetch_assoc($tindakan_inap);
    $gedung_data[$nama_gedung]['total_material'] += floatval($data_ti['total_material'] ?? 0);
    $gedung_data[$nama_gedung]['total_bhp'] += floatval($data_ti['total_bhp'] ?? 0);
    $gedung_data[$nama_gedung]['total_tindakan_dr'] += floatval($data_ti['total_tindakan_dr'] ?? 0);
    $gedung_data[$nama_gedung]['total_tindakan_pr'] += floatval($data_ti['total_tindakan_pr'] ?? 0);
    $gedung_data[$nama_gedung]['total_kso'] += floatval($data_ti['total_kso'] ?? 0);
    $gedung_data[$nama_gedung]['total_menejemen'] += floatval($data_ti['total_menejemen'] ?? 0);
    $gedung_data[$nama_gedung]['total_tindakan'] += floatval($data_ti['total_biaya_rawat'] ?? 0);
  }

  // ===============================
  // 3. RESEP (RACIKAN, NON RACIKAN, OPERASI)
  // ===============================
  $resep_result = mysqli_query($koneksi, "
        SELECT no_resep 
        FROM resep_obat 
        WHERE no_rawat = '$no_rawat' AND tgl_perawatan != '0000-00-00' AND status = 'ranap'
    ");

  if ($resep_result && mysqli_num_rows($resep_result) > 0) {
    while ($resep = mysqli_fetch_assoc($resep_result)) {
      $no_resep = mysqli_real_escape_string($koneksi, $resep['no_resep']);
      $is_operasi = (substr($resep['no_resep'], 0, 2) === 'OK');

      if ($is_operasi) {
        $gedung_data[$nama_gedung]['jumlah_resep_operasi']++;
      } else {
        // Cek apakah racikan
        $cek_racikan = mysqli_query($koneksi, "
                    SELECT COUNT(*) as ada 
                    FROM resep_dokter_racikan 
                    WHERE no_resep = '$no_resep'
                    LIMIT 1
                ");
        $ada_racikan = false;
        if ($cek_racikan && mysqli_num_rows($cek_racikan) > 0) {
          $data_racikan = mysqli_fetch_assoc($cek_racikan);
          $ada_racikan = ($data_racikan['ada'] > 0);
        }

        // Cek apakah non racikan
        $cek_non_racikan = mysqli_query($koneksi, "
                    SELECT COUNT(*) as ada 
                    FROM resep_dokter 
                    WHERE no_resep = '$no_resep'
                    LIMIT 1
                ");
        $ada_non_racikan = false;
        if ($cek_non_racikan && mysqli_num_rows($cek_non_racikan) > 0) {
          $data_non_racikan = mysqli_fetch_assoc($cek_non_racikan);
          $ada_non_racikan = ($data_non_racikan['ada'] > 0);
        }

        if ($ada_racikan) {
          $gedung_data[$nama_gedung]['jumlah_resep_racikan']++;
        } else if ($ada_non_racikan) {
          $gedung_data[$nama_gedung]['jumlah_resep_non_racikan']++;
        }
      }
    }
  }

  // Hitung jasa farmasi
  $jasa_racikan = $gedung_data[$nama_gedung]['jumlah_resep_racikan'] * 25000;
  $jasa_non_racikan = $gedung_data[$nama_gedung]['jumlah_resep_non_racikan'] * 15000;
  $jasa_operasi = $gedung_data[$nama_gedung]['jumlah_resep_operasi'] * 35000;
  $gedung_data[$nama_gedung]['total_jasa_farmasi'] = $jasa_racikan + $jasa_non_racikan + $jasa_operasi;

  // ===============================
  // 4. OBAT
  // ===============================
  $obat_result = mysqli_query($koneksi, "
        SELECT SUM(IFNULL(total,0)) AS total_obat
        FROM detail_pemberian_obat
        WHERE no_rawat = '$no_rawat'
    ");
  if ($obat_result && mysqli_num_rows($obat_result) > 0) {
    $obat_data = mysqli_fetch_assoc($obat_result);
    $gedung_data[$nama_gedung]['total_obat'] += floatval($obat_data['total_obat'] ?? 0);
  }

  // ===============================
  // 5. LABORATORIUM
  // ===============================
  $lab_result = mysqli_query($koneksi, "
        SELECT 
            SUM(IFNULL(jns_perawatan_lab.bagian_rs,0)) AS total_material_lab,
            SUM(IFNULL(jns_perawatan_lab.tarif_tindakan_dokter,0)) AS total_dokter_lab,
            SUM(IFNULL(jns_perawatan_lab.tarif_tindakan_petugas,0)) AS total_petugas_lab,
            SUM(IFNULL(jns_perawatan_lab.menejemen,0)) AS total_menejemen_lab,
            SUM(IFNULL(jns_perawatan_lab.total_byr,0)) AS total_lab
        FROM periksa_lab
        JOIN jns_perawatan_lab ON periksa_lab.kd_jenis_prw = jns_perawatan_lab.kd_jenis_prw
        WHERE periksa_lab.no_rawat = '$no_rawat'
    ");
  if ($lab_result && mysqli_num_rows($lab_result) > 0) {
    $lab_data = mysqli_fetch_assoc($lab_result);
    $gedung_data[$nama_gedung]['total_material_lab'] += floatval($lab_data['total_material_lab'] ?? 0);
    $gedung_data[$nama_gedung]['total_dokter_lab'] += floatval($lab_data['total_dokter_lab'] ?? 0);
    $gedung_data[$nama_gedung]['total_petugas_lab'] += floatval($lab_data['total_petugas_lab'] ?? 0);
    $gedung_data[$nama_gedung]['total_menejemen_lab'] += floatval($lab_data['total_menejemen_lab'] ?? 0);
    $gedung_data[$nama_gedung]['total_lab'] += floatval($lab_data['total_lab'] ?? 0);
  }

  // ===============================
  // 6. RADIOLOGI
  // ===============================
  $rad_result = mysqli_query($koneksi, "
        SELECT 
            COALESCE(SUM(t2.bagian_rs), 0) AS total_material_radiologi,
            COALESCE(SUM(t2.tarif_tindakan_dokter), 0) AS total_dokter_radiologi,
            COALESCE(SUM(t2.tarif_tindakan_petugas), 0) AS total_petugas_radiologi,
            COALESCE(SUM(t2.menejemen), 0) AS total_menejemen_radiologi,
            COALESCE(SUM(t2.total_byr), 0) AS total_radiologi
        FROM permintaan_radiologi t1
        JOIN permintaan_pemeriksaan_radiologi t3 ON t1.noorder = t3.noorder
        JOIN jns_perawatan_radiologi t2 ON t3.kd_jenis_prw = t2.kd_jenis_prw
        WHERE t1.no_rawat = '$no_rawat' AND t1.status = 'ranap'
    ");
  if ($rad_result && mysqli_num_rows($rad_result) > 0) {
    $rad_data = mysqli_fetch_assoc($rad_result);
    $gedung_data[$nama_gedung]['total_material_radiologi'] += floatval($rad_data['total_material_radiologi'] ?? 0);
    $gedung_data[$nama_gedung]['total_dokter_radiologi'] += floatval($rad_data['total_dokter_radiologi'] ?? 0);
    $gedung_data[$nama_gedung]['total_petugas_radiologi'] += floatval($rad_data['total_petugas_radiologi'] ?? 0);
    $gedung_data[$nama_gedung]['total_menejemen_radiologi'] += floatval($rad_data['total_menejemen_radiologi'] ?? 0);
    $gedung_data[$nama_gedung]['total_radiologi'] += floatval($rad_data['total_radiologi'] ?? 0);
  }

  // ===============================
  // 7. OPERASI
  // ===============================
  $operasi_result = mysqli_query($koneksi, "
        SELECT 
            (
                IFNULL(SUM(biayaoperator1),0) + IFNULL(SUM(biayaoperator2),0) + IFNULL(SUM(biayaoperator3),0) +
                IFNULL(SUM(biayaasisten_operator1),0) + IFNULL(SUM(biayaasisten_operator2),0) + IFNULL(SUM(biayaasisten_operator3),0) +
                IFNULL(SUM(biayainstrumen),0) + IFNULL(SUM(biayadokter_anak),0) + IFNULL(SUM(biayaperawaat_resusitas),0) +
                IFNULL(SUM(biayadokter_anestesi),0) + IFNULL(SUM(biayaasisten_anestesi),0) + IFNULL(SUM(biayaasisten_anestesi2),0) +
                IFNULL(SUM(biayabidan),0) + IFNULL(SUM(biayabidan2),0) + IFNULL(SUM(biayabidan3),0) +
                IFNULL(SUM(biayaperawat_luar),0) + IFNULL(SUM(biaya_omloop),0) + IFNULL(SUM(biaya_omloop2),0) +
                IFNULL(SUM(biaya_omloop3),0) + IFNULL(SUM(biaya_omloop4),0) + IFNULL(SUM(biaya_omloop5),0) +
                IFNULL(SUM(biaya_dokter_pjanak),0) + IFNULL(SUM(biaya_dokter_umum),0) +
                IFNULL(SUM(biayaalat),0) + IFNULL(SUM(biayasewaok),0) +
                IFNULL(SUM(operasi.akomodasi),0) + IFNULL(SUM(operasi.bagian_rs),0) + IFNULL(SUM(biayasarpras),0)
            ) AS total_operasi
        FROM operasi
        WHERE no_rawat = '$no_rawat' AND operasi.status = 'Ranap'
    ");
  if ($operasi_result && mysqli_num_rows($operasi_result) > 0) {
    $operasi_data = mysqli_fetch_assoc($operasi_result);
    $gedung_data[$nama_gedung]['total_operasi'] += floatval($operasi_data['total_operasi'] ?? 0);
  }
}

// Hitung grand total untuk setiap gedung
foreach ($gedung_data as $key => $value) {
  $gedung_data[$key]['grand_total'] =
    $value['total_biaya_kamar'] +
    $value['total_tindakan'] +
    $value['total_obat'] +
    $value['total_jasa_farmasi'] +
    $value['total_lab'] +
    $value['total_radiologi'] +
    $value['total_operasi'];
}

// Convert ke array biasa
$data = array_values($gedung_data);

echo json_encode([
  "success" => true,
  "periode" => date('F Y', strtotime($tgl_awal)),
  "data" => $data
]);

mysqli_close($koneksi);
mysqli_close($koneksi2);