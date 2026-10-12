<?php
require_once '../config/conf.php';
$koneksi = bukakoneksi();

header('Content-Type: application/json; charset=utf-8');

$jenis = $_POST['jenis'] ?? 'ranap';
$bulan = $_POST['bulan'] ?? '';
$tahun = $_POST['tahun'] ?? date('Y');

if (!preg_match('/^\d{4}$/', (string)$tahun)) {
    $tahun = date('Y');
}
if ($bulan !== '' && (!ctype_digit((string)$bulan) || (int)$bulan < 1 || (int)$bulan > 12)) {
    $bulan = '';
}
$tahun = mysqli_real_escape_string($koneksi, $tahun);
$bulan = mysqli_real_escape_string($koneksi, $bulan);

$where_tgl = " AND YEAR(r.tgl_perawatan) = '$tahun'";
if ($bulan !== '') {
    $where_tgl .= " AND MONTH(r.tgl_perawatan) = '$bulan'";
}

if ($jenis === 'ranap') {
    $sql = "SELECT rp.no_rawat,
                   rp.tgl_registrasi,
                   rp.no_rkm_medis,
                   p.nm_pasien,
                   bs.no_sep,
                   kk.nm_bangsal AS lokasi,
                   COUNT(*) AS jumlah_kantong,
                   IFNULL(SUM(j.total_byrdrpr), 0) AS total_biaya
            FROM rawat_inap_drpr r
            JOIN jns_perawatan_inap j ON r.kd_jenis_prw = j.kd_jenis_prw
            JOIN reg_periksa rp ON r.no_rawat = rp.no_rawat
            JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
            LEFT JOIN (
                SELECT no_rawat,
                       SUBSTRING_INDEX(GROUP_CONCAT(no_sep ORDER BY tglsep DESC SEPARATOR ','), ',', 1) AS no_sep
                FROM bridging_sep
                GROUP BY no_rawat
            ) bs ON bs.no_rawat = rp.no_rawat
            LEFT JOIN (
                SELECT ki.no_rawat, b.nm_bangsal
                FROM kamar_inap ki
                JOIN kamar km ON ki.kd_kamar = km.kd_kamar
                JOIN bangsal b ON km.kd_bangsal = b.kd_bangsal
                WHERE (ki.stts_pulang IS NULL OR ki.stts_pulang != 'Pindah Kamar')
            ) kk ON kk.no_rawat = rp.no_rawat
            WHERE j.kd_kategori = 'KP058'
              $where_tgl
            GROUP BY rp.no_rawat, rp.tgl_registrasi, rp.no_rkm_medis, p.nm_pasien, bs.no_sep, kk.nm_bangsal
            ORDER BY rp.no_rawat DESC";
} else {
    $sql = "SELECT rp.no_rawat,
                   rp.tgl_registrasi,
                   rp.no_rkm_medis,
                   p.nm_pasien,
                   bs.no_sep,
                   pl.nm_poli AS lokasi,
                   COUNT(*) AS jumlah_kantong,
                   IFNULL(SUM(j.total_byrdrpr), 0) AS total_biaya
            FROM rawat_jl_drpr r
            JOIN jns_perawatan j ON r.kd_jenis_prw = j.kd_jenis_prw
            JOIN reg_periksa rp ON r.no_rawat = rp.no_rawat
            JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
            LEFT JOIN (
                SELECT no_rawat,
                       SUBSTRING_INDEX(GROUP_CONCAT(no_sep ORDER BY tglsep DESC SEPARATOR ','), ',', 1) AS no_sep
                FROM bridging_sep
                GROUP BY no_rawat
            ) bs ON bs.no_rawat = rp.no_rawat
            LEFT JOIN poliklinik pl ON rp.kd_poli = pl.kd_poli
            WHERE j.kd_kategori = 'KP058'
              $where_tgl
            GROUP BY rp.no_rawat, rp.tgl_registrasi, rp.no_rkm_medis, p.nm_pasien, bs.no_sep, pl.nm_poli
            ORDER BY rp.no_rawat DESC";
}

$result = mysqli_query($koneksi, $sql);
if (!$result) {
    echo json_encode(['success' => false, 'message' => mysqli_error($koneksi)]);
    exit;
}

$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $row['jumlah_kantong'] = (int)$row['jumlah_kantong'];
    $row['total_biaya'] = (float)$row['total_biaya'];
    $data[] = $row;
}

$bulan_nama = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$periode = ($bulan !== '' ? $bulan_nama[(int)$bulan] . ' ' : 'Semua Bulan ') . $tahun;

echo json_encode([
    'success' => true,
    'jenis' => $jenis,
    'periode' => $periode,
    'total_pasien' => count($data),
    'total_kantong' => array_sum(array_column($data, 'jumlah_kantong')),
    'data' => $data
], JSON_UNESCAPED_UNICODE);

mysqli_close($koneksi);