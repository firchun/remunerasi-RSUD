<?php
require_once '../config/conf.php';
require_once __DIR__ . '/rekap_kunjungan_ralan_query.php';

$koneksi = bukakoneksi();

$tahun = rekap_kunjungan_ralan_tahun($_POST['tahun'] ?? date('Y'));
$bulan = $_POST['bulan'] ?? '';
if ($bulan !== '' && (!ctype_digit((string)$bulan) || (int)$bulan < 1 || (int)$bulan > 12)) {
    $bulan = '';
}
$kd_poli = trim($_POST['kd_poli'] ?? '');
if ($kd_poli !== '') {
    $kd_poli = mysqli_real_escape_string($koneksi, $kd_poli);
}

$rows = rekap_kunjungan_ralan_rows($koneksi, $tahun);

$data = [];
foreach ($rows as $r) {
    if ($bulan !== '' && (int)$r['bulan'] !== (int)$bulan) {
        continue;
    }
    if ($kd_poli !== '' && $r['kd_poli'] !== $kd_poli) {
        continue;
    }
    $data[] = $r;
}

$bulan_nama = rekap_kunjungan_ralan_bulan_nama();
$total_kunjungan = 0;
$total_batal = 0;
$total_perminggu = 0;
foreach ($data as $r) {
    $total_kunjungan += $r['jumlah_kunjungan'];
    $total_batal += $r['batal_kunjungan'];
    $total_perminggu += $r['kunjungan_perminggu'];
}

echo json_encode([
    'success' => true,
    'periode' => 'Tahun ' . $tahun . ($bulan !== '' ? ' - ' . $bulan_nama[(int)$bulan] : ''),
    'total_kunjungan' => $total_kunjungan,
    'total_batal' => $total_batal,
    'total_perminggu' => $total_perminggu,
    'data' => $data,
]);

mysqli_close($koneksi);
