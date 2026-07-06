<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/conf.php';
$koneksi = bukakoneksi();

$bulan   = $_POST['bulan'] ?? date('m');
$tahun   = $_POST['tahun'] ?? date('Y');
$kd_poli = $_POST['kd_poli'] ?? '';
$kd_pj   = $_POST['kd_pj'] ?? '';

$bulan_padded = str_pad($bulan, 2, '0', STR_PAD_LEFT);
$tgl_awal = "$tahun-$bulan_padded-01 00:00:00";
$tgl_akhir = date("Y-m-t 23:59:59", strtotime($tgl_awal));

$where = "WHERE rp.status_lanjut = 'Ralan'
  AND rp.stts != 'Batal'
  AND CONCAT(rp.tgl_registrasi,' ',rp.jam_reg) BETWEEN '$tgl_awal' AND '$tgl_akhir'";

if (!empty($kd_poli)) {
    $where .= " AND rp.kd_poli = '" . mysqli_real_escape_string($koneksi, $kd_poli) . "'";
}
if (!empty($kd_pj)) {
    $where .= " AND rp.kd_pj = '" . mysqli_real_escape_string($koneksi, $kd_pj) . "'";
}

$query = "
SELECT 
    rp.no_rawat,
    rp.no_rkm_medis,
    rp.tgl_registrasi,
    rp.stts,
    rp.kd_pj,
    pasien.nm_pasien,
    poliklinik.nm_poli,
    penjab.png_jawab,
    bridging_sep.no_sep,
    sk.no_surat AS no_surat_kontrol,
    (SELECT ro.no_resep FROM resep_obat ro WHERE ro.no_rawat = rp.no_rawat AND ro.status = 'ralan' AND ro.tgl_perawatan != '0000-00-00' LIMIT 1) AS no_resep,
    (SELECT 1 FROM rawat_jl_drpr rj WHERE rj.no_rawat = rp.no_rawat LIMIT 1) AS has_tindakan
FROM reg_periksa rp
JOIN pasien ON rp.no_rkm_medis = pasien.no_rkm_medis
JOIN poliklinik ON rp.kd_poli = poliklinik.kd_poli
JOIN penjab ON rp.kd_pj = penjab.kd_pj
LEFT JOIN bridging_sep ON bridging_sep.no_rawat = rp.no_rawat
LEFT JOIN bridging_surat_kontrol_bpjs sk ON sk.no_sep = bridging_sep.no_sep
$where
ORDER BY rp.tgl_registrasi DESC, rp.jam_reg DESC
";

$result = mysqli_query($koneksi, $query);

if (!$result) {
    echo json_encode([
        "success" => false,
        "error" => mysqli_error($koneksi)
    ]);
    exit;
}

$categories = [
    "tanpa_sep" => [
        "title" => "Pasien Tanpa SEP",
        "description" => "Jenis bayar BPJS tetapi tidak memiliki Nomor SEP",
        "count" => 0,
        "patients" => []
    ],
    "resep_tanpa_sep" => [
        "title" => "Resep Tanpa SEP",
        "description" => "Ada peresepan obat untuk pasien BPJS tetapi tidak ada SEP",
        "count" => 0,
        "patients" => []
    ],
    "tanpa_tindakan" => [
        "title" => "Tanpa Tindakan",
        "description" => "Pasien sudah dilayani/diperiksa tetapi tidak ada entry tindakan",
        "count" => 0,
        "patients" => []
    ],
    "resep_tanpa_tindakan" => [
        "title" => "Resep Tanpa Tindakan",
        "description" => "Ada peresepan obat tetapi tidak ada entry tindakan",
        "count" => 0,
        "patients" => []
    ],
    "tidak_dilayani" => [
        "title" => "Pasien Tidak Dilayani",
        "description" => "Status pendaftaran masih 'Belum' pada hari sebelumnya",
        "count" => 0,
        "patients" => []
    ],
    "status_salah" => [
        "title" => "Status Salah",
        "description" => "Bukan BPJS tetapi memiliki Nomor SEP bridging",
        "count" => 0,
        "patients" => []
    ],
    "tanpa_surat_kontrol" => [
        "title" => "Tanpa Surat Kontrol",
        "description" => "Pasien BPJS terlayani tetapi tidak memiliki Surat Kontrol",
        "count" => 0,
        "patients" => []
    ]
];

$today = date('Y-m-d');

while ($row = mysqli_fetch_assoc($result)) {
    $patient_detail = [
        "no_rawat" => $row['no_rawat'],
        "no_rkm_medis" => $row['no_rkm_medis'],
        "nm_pasien" => $row['nm_pasien'],
        "nm_poli" => $row['nm_poli'],
        "tgl_registrasi" => $row['tgl_registrasi'],
        "png_jawab" => $row['png_jawab'],
        "no_sep" => $row['no_sep'] ?: '-',
        "no_resep" => $row['no_resep'] ?: '-',
        "no_surat_kontrol" => $row['no_surat_kontrol'] ?: '-',
        "stts" => $row['stts']
    ];

    // 1. Pasien Tanpa SEP (BPJS, stts bukan Batal/Belum, SEP kosong)
    $is_bpjs = (strpos($row['kd_pj'], 'BPJ') !== false || $row['kd_pj'] === 'BPJ');
    $no_sep_empty = (empty($row['no_sep']) || $row['no_sep'] === '-');
    
    if ($is_bpjs && $row['stts'] !== 'Belum' && $no_sep_empty) {
        $categories['tanpa_sep']['patients'][] = $patient_detail;
        $categories['tanpa_sep']['count']++;
    }

    // 2. Resep Tanpa SEP (BPJS, ada resep, SEP kosong)
    $has_resep = !empty($row['no_resep']);
    if ($is_bpjs && $has_resep && $no_sep_empty) {
        $categories['resep_tanpa_sep']['patients'][] = $patient_detail;
        $categories['resep_tanpa_sep']['count']++;
    }

    // 3. Tanpa Tindakan (stts = Sudah, tindakan kosong)
    if ($row['stts'] === 'Sudah' && empty($row['has_tindakan'])) {
        $categories['tanpa_tindakan']['patients'][] = $patient_detail;
        $categories['tanpa_tindakan']['count']++;
    }

    // 4. Resep Tanpa Tindakan (ada resep, tindakan kosong)
    if ($has_resep && empty($row['has_tindakan'])) {
        $categories['resep_tanpa_tindakan']['patients'][] = $patient_detail;
        $categories['resep_tanpa_tindakan']['count']++;
    }

    // 5. Tidak Dilayani (stts = Belum, tgl_registrasi < hari ini)
    if ($row['stts'] === 'Belum' && $row['tgl_registrasi'] < $today) {
        $categories['tidak_dilayani']['patients'][] = $patient_detail;
        $categories['tidak_dilayani']['count']++;
    }

    // 6. Status Salah (Bukan BPJS, ada SEP)
    if (!$is_bpjs && !$no_sep_empty) {
        $categories['status_salah']['patients'][] = $patient_detail;
        $categories['status_salah']['count']++;
    }

    // 7. Tanpa Surat Kontrol (BPJS, status = Sudah, tidak ada surat kontrol)
    $no_sk_empty = (empty($row['no_surat_kontrol']) || $row['no_surat_kontrol'] === '-');
    if ($is_bpjs && $row['stts'] === 'Sudah' && $no_sk_empty) {
        $categories['tanpa_surat_kontrol']['patients'][] = $patient_detail;
        $categories['tanpa_surat_kontrol']['count']++;
    }
}

echo json_encode([
    "success" => true,
    "periode" => date('F Y', strtotime($tgl_awal)),
    "data" => $categories
]);

mysqli_close($koneksi);
