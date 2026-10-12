<?php
require_once '../config/conf.php';
$koneksi = bukakoneksi();

header('Content-Type: application/json; charset=utf-8');

$jenis = $_GET['jenis'] ?? 'ranap';
$no_rawat = $_GET['no_rawat'] ?? '';

if (empty($no_rawat)) {
    echo json_encode(['success' => false, 'message' => 'no_rawat tidak ditemukan']);
    exit;
}

$info_sql = "SELECT rp.no_rawat,
                    rp.no_rkm_medis,
                    p.nm_pasien,
                    rp.tgl_registrasi,
                    rp.status_lanjut,
                    pl.nm_poli,
                    kk.nm_bangsal
             FROM reg_periksa rp
             JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
             LEFT JOIN poliklinik pl ON rp.kd_poli = pl.kd_poli
             LEFT JOIN (
                 SELECT ki.no_rawat, b.nm_bangsal
                 FROM kamar_inap ki
                 JOIN kamar km ON ki.kd_kamar = km.kd_kamar
                 JOIN bangsal b ON km.kd_bangsal = b.kd_bangsal
                 WHERE (ki.stts_pulang IS NULL OR ki.stts_pulang != 'Pindah Kamar')
             ) kk ON kk.no_rawat = rp.no_rawat
             WHERE rp.no_rawat = '" . mysqli_real_escape_string($koneksi, $no_rawat) . "'
             LIMIT 1";

$info = mysqli_fetch_assoc(mysqli_query($koneksi, $info_sql));

if (!$info) {
    echo json_encode(['success' => false, 'message' => 'Data pasien tidak ditemukan']);
    exit;
}

if ($jenis === 'ranap') {
    $rows_sql = "SELECT r.tgl_perawatan,
                        r.jam_rawat,
                        CONCAT(r.tgl_perawatan, ' ', r.jam_rawat) AS waktu,
                        j.nm_perawatan,
                        j.kd_jenis_prw,
                        d.nm_dokter,
                        pt.nama AS nm_petugas,
                        j.total_byrdrpr AS biaya
                 FROM rawat_inap_drpr r
                 JOIN jns_perawatan_inap j ON r.kd_jenis_prw = j.kd_jenis_prw
                 LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
                 LEFT JOIN petugas pt ON r.nip = pt.nip
                 WHERE r.no_rawat = '" . mysqli_real_escape_string($koneksi, $no_rawat) . "'
                   AND j.kd_kategori = 'KP058'
                 ORDER BY r.tgl_perawatan, r.jam_rawat";
} else {
    $rows_sql = "SELECT r.tgl_perawatan,
                        r.jam_rawat,
                        CONCAT(r.tgl_perawatan, ' ', r.jam_rawat) AS waktu,
                        j.nm_perawatan,
                        j.kd_jenis_prw,
                        d.nm_dokter,
                        pt.nama AS nm_petugas,
                        j.total_byrdrpr AS biaya
                 FROM rawat_jl_drpr r
                 JOIN jns_perawatan j ON r.kd_jenis_prw = j.kd_jenis_prw
                 LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
                 LEFT JOIN petugas pt ON r.nip = pt.nip
                 WHERE r.no_rawat = '" . mysqli_real_escape_string($koneksi, $no_rawat) . "'
                   AND j.kd_kategori = 'KP058'
                 ORDER BY r.tgl_perawatan, r.jam_rawat";
}

$result = mysqli_query($koneksi, $rows_sql);
if (!$result) {
    echo json_encode(['success' => false, 'message' => mysqli_error($koneksi)]);
    exit;
}

$rows = [];
$total_biaya = 0;
while ($row = mysqli_fetch_assoc($result)) {
    $row['biaya'] = (float)$row['biaya'];
    $total_biaya += $row['biaya'];
    $rows[] = $row;
}

$lokasi = $jenis === 'ranap'
    ? ($info['nm_bangsal'] ?? '-')
    : ($info['nm_poli'] ?? '-');

echo json_encode([
    'success' => true,
    'jenis' => $jenis,
    'info' => [
        'no_rawat' => $info['no_rawat'],
        'no_rkm_medis' => $info['no_rkm_medis'],
        'nm_pasien' => $info['nm_pasien'],
        'tgl_registrasi' => $info['tgl_registrasi'],
        'status_lanjut' => $info['status_lanjut'],
        'lokasi' => $lokasi
    ],
    'rows' => $rows,
    'summary' => [
        'jumlah_kantong' => count($rows),
        'total_biaya' => $total_biaya
    ]
], JSON_UNESCAPED_UNICODE);

mysqli_close($koneksi);