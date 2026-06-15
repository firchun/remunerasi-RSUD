<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../config/conf.php';

$conn = bukakoneksi();

$bulan = isset($_GET['bulan']) ? intval($_GET['bulan']) : null;
$tahun = isset($_GET['tahun']) ? intval($_GET['tahun']) : null;

if ($bulan && $tahun) {
    $filterTgl = "AND YEAR(rp.tgl_registrasi) = $tahun AND MONTH(rp.tgl_registrasi) = $bulan";
} else {
    $periode = 6;
    $filterTgl = "AND rp.tgl_registrasi >= DATE_SUB(CURDATE(), INTERVAL $periode MONTH)";
}

try {
    // 1. RAJAL - Kepatuhan per Poliklinik
    $rajal = query($conn, "
        SELECT p.nm_poli,
            COUNT(DISTINCT rp.no_rawat) AS jumlah_pasien,
            COUNT(rjd.kd_jenis_prw) AS jumlah_tindakan
        FROM reg_periksa rp
        JOIN poliklinik p ON rp.kd_poli = p.kd_poli
        LEFT JOIN rawat_jl_drpr rjd ON rjd.no_rawat = rp.no_rawat
        WHERE rp.status_lanjut = 'Ralan'
            AND rp.stts != 'Batal'
            AND NOT (rp.kd_poli = 'IGDK' AND EXISTS (SELECT 1 FROM kamar_inap ki WHERE ki.no_rawat = rp.no_rawat))
            $filterTgl
        GROUP BY p.nm_poli
        HAVING jumlah_pasien > 0
        ORDER BY jumlah_pasien DESC
    ");

    // 2. RANAP - Kepatuhan per Bangsal (grouped by ward name)
    $caseBangsal = "
        CASE 
            WHEN b.nm_bangsal LIKE '%KANGURU%' THEN 'KANGURU'
            WHEN b.nm_bangsal LIKE '%MAMBRUK%' THEN 'MAMBRUK'
            WHEN b.nm_bangsal LIKE '%RUSA%' THEN 'RUSA'
            WHEN b.nm_bangsal LIKE '%CENDERAWASIH%' THEN 'CENDERAWASIH'
            WHEN b.nm_bangsal LIKE '%KUSKUS%' THEN 'KUSKUS'
            WHEN b.nm_bangsal LIKE '%MALEO%' THEN 'MALEO'
            WHEN b.nm_bangsal LIKE '%URIP%' THEN 'URIP'
            WHEN b.nm_bangsal LIKE '%KASUARI%' THEN 'KASUARI'
            WHEN b.nm_bangsal LIKE '%ICU%' THEN 'ICU'
            WHEN b.nm_bangsal LIKE '%PICU%' THEN 'PICU'
            WHEN b.nm_bangsal LIKE '%BOHA%' THEN 'BOHA'
            WHEN b.nm_bangsal LIKE '%NICU%' THEN 'NICU'
            WHEN b.nm_bangsal LIKE '%ANAK%' THEN 'ANAK'
            WHEN b.nm_bangsal LIKE '%BEDAH%' THEN 'BEDAH'
            WHEN b.nm_bangsal LIKE '%VIP%' THEN 'VIP'
            WHEN b.nm_bangsal LIKE '%ALFA%' THEN 'VIP ALFA / OMEGA'
            WHEN b.nm_bangsal LIKE '%OMEGA%' THEN 'VIP ALFA / OMEGA'
            ELSE TRIM(SUBSTRING_INDEX(b.nm_bangsal, '.', 1))
        END
    ";
    $ranap = query($conn, "
        SELECT $caseBangsal AS nm_bangsal,
            COUNT(DISTINCT rp.no_rawat) AS jumlah_pasien,
            COUNT(rid.kd_jenis_prw) AS jumlah_tindakan
        FROM reg_periksa rp
        JOIN kamar_inap ki ON ki.no_rawat = rp.no_rawat
        JOIN kamar k ON k.kd_kamar = ki.kd_kamar
        JOIN bangsal b ON b.kd_bangsal = k.kd_bangsal
        LEFT JOIN rawat_inap_drpr rid ON rid.no_rawat = rp.no_rawat
        WHERE rp.status_lanjut = 'Ranap'
            AND (ki.stts_pulang IS NULL OR ki.stts_pulang != 'Pindah Kamar')
            $filterTgl
        GROUP BY $caseBangsal
        HAVING jumlah_pasien >= 5
        ORDER BY jumlah_pasien DESC
    ");

    // 3. RADIOLOGI - Permintaan vs Hasil (per bulan)
    $radiologi = query($conn, "
        SELECT DATE_FORMAT(tgl_permintaan, '%Y-%m') AS bulan,
            COUNT(*) AS permintaan,
            SUM(CASE WHEN tgl_hasil IS NOT NULL AND tgl_hasil != '0000-00-00' THEN 1 ELSE 0 END) AS hasil
        FROM permintaan_radiologi
        WHERE tgl_permintaan >= DATE_SUB(CURDATE(), INTERVAL $periode MONTH)
        GROUP BY bulan
        ORDER BY bulan
    ");

    // 4. LABORATORIUM - Permintaan vs Hasil (per bulan)
    $laboratorium = query($conn, "
        SELECT DATE_FORMAT(pl.tgl_permintaan, '%Y-%m') AS bulan,
            COUNT(DISTINCT pl.noorder) AS permintaan,
            COUNT(DISTINCT dpl.no_rawat, dpl.kd_jenis_prw, dpl.tgl_periksa, dpl.jam) AS hasil
        FROM permintaan_lab pl
        LEFT JOIN detail_periksa_lab dpl ON dpl.no_rawat = pl.no_rawat
        WHERE pl.tgl_permintaan >= DATE_SUB(CURDATE(), INTERVAL $periode MONTH)
        GROUP BY bulan
        ORDER BY bulan
    ");

    echo json_encode([
        'success' => true,
        'rajal'   => $rajal,
        'ranap'   => $ranap,
        'radiologi' => $radiologi,
        'laboratorium' => $laboratorium,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function query($conn, $sql) {
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        throw new Exception(mysqli_error($conn));
    }
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    return $rows;
}
