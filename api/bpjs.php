<?php
// Fitur BPJS dinonaktifkan (DB lokal inacbd tidak tersedia)
header('Content-Type: application/json');
$draw = $_POST['draw'] ?? 1;
echo json_encode([
  "draw" => intval($draw),
  "recordsTotal" => 0,
  "recordsFiltered" => 0,
  "data" => [],
  "info" => "Fitur BPJS tidak tersedia"
]);
exit;


// Get DataTables parameters
$draw   = $_POST['draw']   ?? 1;
$start  = $_POST['start']  ?? 0;
$length = $_POST['length'] ?? 25;
$search = $_POST['search']['value'] ?? '';

// Filters
$tgl1          = $_POST['tgl1']          ?? '';
$tgl2          = $_POST['tgl2']          ?? '';
$kd_pj         = $_POST['kd_pj']         ?? '';
$tcari         = $_POST['tcari']         ?? '';
$status_lanjut = $_POST['status_lanjut'] ?? 'semua';

// ===============================
// BASE QUERY - ACUAN DARI inacbd
// ===============================
$base = "FROM inacbd WHERE 1=1";

// Jika tcari diisi, filter berdasarkan no_sep
if (!empty($tcari)) {
  $tcari_escaped = mysqli_real_escape_string($koneksi2, $tcari);
  $base .= " AND inacbd.no_sep LIKE '%$tcari_escaped%'";
}

// ===============================
// MAIN QUERY
// ===============================
// Kita ambil data dari inacbd terlebih dahulu
$query = "
    SELECT 
        inacbd.no_sep,
        inacbd.total_bpjs
    $base
    LIMIT $start, $length
";

$count_total = mysqli_fetch_assoc(mysqli_query($koneksi2, "SELECT COUNT(*) as total FROM inacbd"))['total'];
$count_filtered = mysqli_fetch_assoc(mysqli_query($koneksi2, "SELECT COUNT(*) as total $base"))['total'];

$result = mysqli_query($koneksi2, $query);
$data = [];

while ($row = mysqli_fetch_assoc($result)) {
  $no_sep = mysqli_real_escape_string($koneksi, $row['no_sep']);

  // ===============================
  // CARI DATA DI SIMRS BERDASARKAN NO_SEP
  // ===============================
  // Kita cari no_rawat dulu dari bridging_sep di SIMRS
  $q_sep = mysqli_query($koneksi, "
        SELECT 
            reg_periksa.no_rawat,
            reg_periksa.no_rkm_medis,
            reg_periksa.tgl_registrasi,
            reg_periksa.status_lanjut,
            pasien.nm_pasien,
            penjab.png_jawab,
            dokter.nm_dokter,
            reg_periksa.kd_pj
        FROM bridging_sep
        JOIN reg_periksa ON bridging_sep.no_rawat = reg_periksa.no_rawat
        JOIN pasien ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis
        JOIN penjab ON reg_periksa.kd_pj = penjab.kd_pj
        LEFT JOIN dokter ON reg_periksa.kd_dokter = dokter.kd_dokter
        WHERE bridging_sep.no_sep = '$no_sep'
        LIMIT 1
    ");

  if ($q_sep && mysqli_num_rows($q_sep) > 0) {
    $r_simrs = mysqli_fetch_assoc($q_sep);

    // Filter Sisi PHP untuk parameter yang tidak ada di inacbd
    if ($kd_pj != '' && $r_simrs['kd_pj'] != $kd_pj) continue;
    if ($status_lanjut != 'semua' && $r_simrs['status_lanjut'] != $status_lanjut) continue;

    // Gabungkan data
    $row['no_rawat']       = $r_simrs['no_rawat'];
    $row['nm_pasien']      = $r_simrs['nm_pasien'];
    $row['no_rkm_medis']   = $r_simrs['no_rkm_medis'];
    $row['tgl_registrasi'] = $r_simrs['tgl_registrasi'];
    $row['status_lanjut']  = $r_simrs['status_lanjut'];
    $row['png_jawab']      = $r_simrs['png_jawab'];
    $row['nm_dokter']      = $r_simrs['nm_dokter'];
  } else {
    // Jika no_sep tidak ditemukan di bridging_sep SIMRS
    $row['no_rawat']       = "-";
    $row['nm_pasien']      = "Tidak ditemukan di SIMRS";
    $row['no_rkm_medis']   = "-";
    $row['tgl_registrasi'] = "-";
    $row['status_lanjut']  = "-";
    $row['png_jawab']      = "-";
    $row['nm_dokter']      = "-";
  }

  // Status & Format nominal
  $row['status_bpjs'] = ($row['total_bpjs'] > 0) ? 'Ada Data' : 'Total 0';
  $row['total_bpjs_formatted'] = number_format($row['total_bpjs'], 0, ',', '.');

  // Lokasi Ranap/Ralan
  if ($row['status_lanjut'] === 'Ranap') {
    $no_rawat_esc = mysqli_real_escape_string($koneksi, $row['no_rawat']);
    $bangsal_q = mysqli_query($koneksi, "
            SELECT bangsal.nm_bangsal
            FROM kamar_inap
            JOIN kamar ON kamar_inap.kd_kamar = kamar.kd_kamar
            JOIN bangsal ON kamar.kd_bangsal = bangsal.kd_bangsal
            WHERE kamar_inap.no_rawat = '$no_rawat_esc'
            ORDER BY kamar_inap.tgl_masuk DESC LIMIT 1
        ");
    $row['nm_bangsal'] = ($bangsal_q && mysqli_num_rows($bangsal_q) > 0)
      ? mysqli_fetch_assoc($bangsal_q)['nm_bangsal']
      : 'Belum Masuk Kamar';
  } else {
    $row['nm_bangsal'] = '-';
  }

  $data[] = $row;
}

// ===============================
// OUTPUT JSON
// ===============================
echo json_encode([
  "draw"            => intval($draw),
  "recordsTotal"    => intval($count_total),
  "recordsFiltered" => intval($count_filtered),
  "data"            => $data
], JSON_UNESCAPED_UNICODE);

mysqli_close($koneksi);
mysqli_close($koneksi2);