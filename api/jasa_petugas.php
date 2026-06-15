<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/conf.php';
$koneksi = bukakoneksi();

// Get DataTables parameters
$draw = $_POST['draw'] ?? 1;
$start = $_POST['start'] ?? 0;
$length = $_POST['length'] ?? 25;
$search = $_POST['search']['value'] ?? '';

// Get filter parameters
$nama_petugas = $_POST['nama_petugas'] ?? '';
$bulan = $_POST['bulan'] ?? '';
$jenis_rawat = $_POST['jenis_rawat'] ?? '';

if (empty($nama_petugas) || empty($bulan) || empty($jenis_rawat)) {
  echo json_encode([
    "draw" => intval($draw),
    "recordsTotal" => 0,
    "recordsFiltered" => 0,
    "data" => [],
    "error" => "Parameter tidak lengkap"
  ]);
  exit;
}

// Escape input
$nama_petugas_escaped = mysqli_real_escape_string($koneksi, $nama_petugas);

// Parse bulan (format: YYYY-MM)
$bulan_parts = explode('-', $bulan);
$tahun = $bulan_parts[0];
$bln = $bulan_parts[1];
$tgl_awal = "$tahun-$bln-01 00:00:00";
$tgl_akhir = date("Y-m-t 23:59:59", strtotime($tgl_awal));

// ===================================
// CARI KODE PETUGAS/DOKTER
// ===================================
$petugas_info = null;
$kode_petugas = '';
$jenis_petugas = '';

// Cari di tabel dokter
$q_dokter = mysqli_query($koneksi, "
    SELECT kd_dokter as kode, nm_dokter as nama, 'Dokter' as jenis
    FROM dokter 
    WHERE nm_dokter LIKE '%$nama_petugas_escaped%' 
    LIMIT 1
");

if ($q_dokter && mysqli_num_rows($q_dokter) > 0) {
  $petugas_info = mysqli_fetch_assoc($q_dokter);
  $kode_petugas = $petugas_info['kode'];
  $jenis_petugas = 'dokter';
} else {
  // Cari di tabel petugas
  $q_petugas = mysqli_query($koneksi, "
        SELECT nip as kode, nama, 'Petugas' as jenis
        FROM petugas 
        WHERE nama LIKE '%$nama_petugas_escaped%' 
        LIMIT 1
    ");

  if ($q_petugas && mysqli_num_rows($q_petugas) > 0) {
    $petugas_info = mysqli_fetch_assoc($q_petugas);
    $kode_petugas = $petugas_info['kode'];
    $jenis_petugas = 'petugas';
  }
}

if (!$petugas_info) {
  echo json_encode([
    "draw" => intval($draw),
    "recordsTotal" => 0,
    "recordsFiltered" => 0,
    "data" => [],
    "error" => "Petugas/Dokter tidak ditemukan"
  ]);
  exit;
}

$kode_escaped = mysqli_real_escape_string($koneksi, $kode_petugas);

// ===================================
// BUILD QUERY BERDASARKAN JENIS RAWAT
// ===================================

if ($jenis_rawat === 'ralan') {
  // RAWAT JALAN
  $base = "
        FROM reg_periksa
        JOIN pasien ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis
        LEFT JOIN bridging_sep ON bridging_sep.no_rawat = reg_periksa.no_rawat
        WHERE reg_periksa.status_lanjut = 'Ralan'
        AND CONCAT(reg_periksa.tgl_registrasi, ' ', reg_periksa.jam_reg) 
            BETWEEN '$tgl_awal' AND '$tgl_akhir'
        AND EXISTS (
            SELECT 1 FROM rawat_jl_drpr 
            WHERE rawat_jl_drpr.no_rawat = reg_periksa.no_rawat
    ";

  if ($jenis_petugas === 'dokter') {
    $base .= " AND rawat_jl_drpr.kd_dokter = '$kode_escaped'";
  } else {
    $base .= " AND rawat_jl_drpr.nip = '$kode_escaped'";
  }

  $base .= " )";

  $kolom_jasa = ($jenis_petugas === 'dokter') ? 'tarif_tindakandr' : 'tarif_tindakanpr';
  $kolom_kode = ($jenis_petugas === 'dokter') ? 'kd_dokter' : 'nip';
} else {
  // RAWAT INAP
  $base = "
        FROM reg_periksa
        JOIN pasien ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis
        LEFT JOIN bridging_sep ON bridging_sep.no_rawat = reg_periksa.no_rawat
        WHERE reg_periksa.status_lanjut = 'Ranap'
        AND CONCAT(reg_periksa.tgl_registrasi, ' ', reg_periksa.jam_reg) 
            BETWEEN '$tgl_awal' AND '$tgl_akhir'
        AND EXISTS (
            SELECT 1 FROM rawat_inap_drpr 
            WHERE rawat_inap_drpr.no_rawat = reg_periksa.no_rawat
    ";

  if ($jenis_petugas === 'dokter') {
    $base .= " AND rawat_inap_drpr.kd_dokter = '$kode_escaped'";
  } else {
    $base .= " AND rawat_inap_drpr.nip = '$kode_escaped'";
  }

  $base .= " )";

  $kolom_jasa = ($jenis_petugas === 'dokter') ? 'tarif_tindakandr' : 'tarif_tindakanpr';
  $kolom_kode = ($jenis_petugas === 'dokter') ? 'kd_dokter' : 'nip';
}

// Search filter
if (!empty($search)) {
  $search_escaped = mysqli_real_escape_string($koneksi, $search);
  $base .= " AND (
        reg_periksa.no_rawat LIKE '%$search_escaped%' 
        OR pasien.nm_pasien LIKE '%$search_escaped%'
        OR bridging_sep.no_sep LIKE '%$search_escaped%'
    )";
}

// ===================================
// COUNT QUERIES
// ===================================
$count_query = "SELECT COUNT(DISTINCT reg_periksa.no_rawat) as total $base";
$count_result = mysqli_query($koneksi, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total'];

// ===================================
// MAIN QUERY
// ===================================
$main_query = "
    SELECT 
        reg_periksa.no_rawat,
        reg_periksa.tgl_registrasi,
        reg_periksa.jam_reg,
        pasien.nm_pasien,
        IFNULL(bridging_sep.no_sep, '-') as no_sep
    $base
    ORDER BY reg_periksa.tgl_registrasi DESC, reg_periksa.jam_reg DESC
    LIMIT $start, $length
";

$result = mysqli_query($koneksi, $main_query);

if (!$result) {
  echo json_encode([
    "draw" => intval($draw),
    "recordsTotal" => 0,
    "recordsFiltered" => 0,
    "data" => [],
    "error" => mysqli_error($koneksi)
  ]);
  exit;
}

// ===================================
// HITUNG GRAND TOTAL KESELURUHAN (TIDAK TERPENGARUH PAGINATION)
// ===================================
$tabel_tindakan = ($jenis_rawat === 'ralan') ? 'rawat_jl_drpr' : 'rawat_inap_drpr';
$tabel_jenis = ($jenis_rawat === 'ralan') ? 'jns_perawatan' : 'jns_perawatan_inap';

$grand_total_query = "
    SELECT SUM(jns.$kolom_jasa) as grand_total
    FROM $tabel_tindakan drpr
    JOIN $tabel_jenis jns ON drpr.kd_jenis_prw = jns.kd_jenis_prw
    JOIN reg_periksa ON reg_periksa.no_rawat = drpr.no_rawat
    WHERE drpr.$kolom_kode = '$kode_escaped'
    AND CONCAT(reg_periksa.tgl_registrasi, ' ', reg_periksa.jam_reg) 
        BETWEEN '$tgl_awal' AND '$tgl_akhir'
";

// Tambahkan kondisi status lanjut
if ($jenis_rawat === 'ralan') {
  $grand_total_query .= " AND reg_periksa.status_lanjut = 'Ralan'";
} else {
  $grand_total_query .= " AND reg_periksa.status_lanjut = 'Ranap'";
}

$grand_total_result = mysqli_query($koneksi, $grand_total_query);
$grand_total = 0;

if ($grand_total_result && mysqli_num_rows($grand_total_result) > 0) {
  $grand_total_data = mysqli_fetch_assoc($grand_total_result);
  $grand_total = (float)($grand_total_data['grand_total'] ?? 0);
}

// ===================================
// PROSES DATA PER HALAMAN (PAGINATION)
// ===================================
$data = [];

while ($row = mysqli_fetch_assoc($result)) {
  $no_rawat = mysqli_real_escape_string($koneksi, $row['no_rawat']);

  // ===================================
  // GET DETAIL TINDAKAN
  // ===================================
  $tindakan_query = "
        SELECT 
            drpr.tgl_perawatan,
            drpr.jam_rawat,
            jns.nm_perawatan,
            jns.$kolom_jasa as jasa
        FROM $tabel_tindakan drpr
        JOIN $tabel_jenis jns ON drpr.kd_jenis_prw = jns.kd_jenis_prw
        WHERE drpr.no_rawat = '$no_rawat'
        AND drpr.$kolom_kode = '$kode_escaped'
        ORDER BY drpr.tgl_perawatan, drpr.jam_rawat
    ";

  $tindakan_result = mysqli_query($koneksi, $tindakan_query);

  $daftar_tindakan = [];
  $daftar_jasa = [];
  $total_jasa = 0;

  if ($tindakan_result && mysqli_num_rows($tindakan_result) > 0) {
    while ($tindakan = mysqli_fetch_assoc($tindakan_result)) {
      $jasa_nilai = (float)$tindakan['jasa'];
      $total_jasa += $jasa_nilai;

      $daftar_tindakan[] = sprintf(
        '<div class="tindakan-group"><strong>%s %s</strong><br>%s<hr></div>',
        $tindakan['tgl_perawatan'],
        $tindakan['jam_rawat'],
        $tindakan['nm_perawatan']
      );

      $daftar_jasa[] = sprintf(
        '<div class="tindakan-group">Rp %s</div>',
        number_format($jasa_nilai, 0, ',', '.')
      );
    }
  }

  $row['daftar_tindakan'] = !empty($daftar_tindakan)
    ? implode('', $daftar_tindakan)
    : '-';

  $row['daftar_jasa'] = !empty($daftar_jasa)
    ? implode('', $daftar_jasa)
    : '-';

  $row['total_jasa'] = $total_jasa;

  $data[] = $row;
}

// ===================================
// OUTPUT
// ===================================
echo json_encode([
  "draw" => intval($draw),
  "recordsTotal" => intval($total_records),
  "recordsFiltered" => intval($total_records),
  "data" => $data,
  "petugas_info" => $petugas_info,
  "grand_total" => $grand_total
], JSON_UNESCAPED_UNICODE);

mysqli_close($koneksi);