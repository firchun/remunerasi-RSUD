<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/conf.php';
$koneksi = bukakoneksi();
$koneksi2 = bukakoneksi2();

// Get DataTables parameters
$draw   = $_POST['draw']   ?? 1;
$start  = $_POST['start']  ?? 0;
$length = $_POST['length'] ?? 25;
$search = $_POST['search']['value'] ?? '';

// Filters
$tgl1      = $_POST['tgl1']      ?? '';
$tgl2      = $_POST['tgl2']      ?? '';
$kd_dokter = $_POST['kd_dokter'] ?? '';
$nip       = $_POST['nip']       ?? '';
$kd_poli   = $_POST['kd_poli']   ?? '';
$kd_pj     = $_POST['kd_pj']     ?? '';
$tcari     = $_POST['tcari']     ?? '';
$status    = $_POST['status']    ?? '';

// Convert datetime-local → MYSQL
$tgl1_formatted = !empty($tgl1) ? str_replace("T", " ", $tgl1) . ":00" : "";
$tgl2_formatted = !empty($tgl2) ? str_replace("T", " ", $tgl2) . ":59" : "";

// ===============================
// BASE QUERY - ACUAN reg_periksa
// ===============================
$base = "
    FROM reg_periksa
    JOIN pasien ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis
    JOIN poliklinik ON reg_periksa.kd_poli = poliklinik.kd_poli
    JOIN penjab ON reg_periksa.kd_pj = penjab.kd_pj
    LEFT JOIN bridging_sep ON bridging_sep.no_rawat = reg_periksa.no_rawat
    LEFT JOIN rawat_jl_drpr ON rawat_jl_drpr.no_rawat = reg_periksa.no_rawat
    LEFT JOIN jns_perawatan ON rawat_jl_drpr.kd_jenis_prw = jns_perawatan.kd_jenis_prw
    LEFT JOIN dokter ON reg_periksa.kd_dokter = dokter.kd_dokter
    LEFT JOIN petugas ON rawat_jl_drpr.nip = petugas.nip
    WHERE 1=1
    AND reg_periksa.status_lanjut = 'Ralan'
    AND EXISTS (
        SELECT 1 FROM poliklinik p 
        WHERE p.kd_poli = reg_periksa.kd_poli
    )
    AND NOT (
    reg_periksa.kd_poli = 'IGDK'
    AND EXISTS (
        SELECT 1 
        FROM kamar_inap ki
        WHERE ki.no_rawat = reg_periksa.no_rawat
    )
)
";

// ===============================
// FILTERS
// ===============================

// Date filter - berdasarkan tanggal registrasi atau tindakan
if (!empty($tgl1) && !empty($tgl2)) {
    $base .= " AND (
        CONCAT(reg_periksa.tgl_registrasi,' ',reg_periksa.jam_reg) 
        BETWEEN '$tgl1_formatted' AND '$tgl2_formatted'
        OR CONCAT(rawat_jl_drpr.tgl_perawatan,' ',rawat_jl_drpr.jam_rawat) 
        BETWEEN '$tgl1_formatted' AND '$tgl2_formatted'
    )";
}

// Dokter
if (!empty($kd_dokter)) {
    $base .= " AND (reg_periksa.kd_dokter = '$kd_dokter' OR rawat_jl_drpr.kd_dokter = '$kd_dokter')";
}

// Petugas
if (!empty($nip)) {
    $base .= " AND rawat_jl_drpr.nip = '$nip'";
}

// Poli
if (!empty($kd_poli)) {
    $base .= " AND reg_periksa.kd_poli = '$kd_poli'";
}

// Cara Bayar
if (!empty($kd_pj)) {
    $base .= " AND reg_periksa.kd_pj = '$kd_pj'";
}

// Cari manual (tcari)
if (!empty($tcari)) {
    $base .= " AND (
        reg_periksa.no_rawat LIKE '%$tcari%' 
        OR pasien.nm_pasien LIKE '%$tcari%' 
        OR dokter.nm_dokter LIKE '%$tcari%' 
        OR reg_periksa.no_rkm_medis LIKE '%$tcari%'
        OR bridging_sep.no_sep LIKE '%$tcari%'
        OR poliklinik.nm_poli LIKE '%$tcari%'
    )";
}

// Search DataTables
if (!empty($search)) {
    $base .= " AND (
        reg_periksa.no_rawat LIKE '%$search%' 
        OR reg_periksa.no_rkm_medis LIKE '%$search%'
        OR pasien.nm_pasien LIKE '%$search%'
        OR dokter.nm_dokter LIKE '%$search%'
        OR poliklinik.nm_poli LIKE '%$search%'
        OR bridging_sep.no_sep LIKE '%$search%'
    )";
}

// ===============================
// MAIN QUERY - ACUAN reg_periksa
// ===============================
$query = "
SELECT 
    reg_periksa.no_rawat,
    reg_periksa.no_rkm_medis,
    reg_periksa.tgl_registrasi,
    reg_periksa.jam_reg,
    pasien.nm_pasien,
    IFNULL(bridging_sep.no_sep, '-') AS no_sep,
    poliklinik.nm_poli,
    penjab.png_jawab,

    IFNULL(MIN(rawat_jl_drpr.tgl_perawatan), reg_periksa.tgl_registrasi) AS tgl_perawatan,
    IFNULL(MIN(rawat_jl_drpr.jam_rawat), reg_periksa.jam_reg) AS jam_rawat,
    IFNULL(MIN(CONCAT(rawat_jl_drpr.tgl_perawatan,' ',rawat_jl_drpr.jam_rawat)), 
           CONCAT(reg_periksa.tgl_registrasi,' ',reg_periksa.jam_reg)) AS waktu_perawatan,
    MIN(dokter.nm_dokter) AS nm_dokter,
    MIN(petugas.nama) AS nama_petugas,

    IFNULL(SUM(jns_perawatan.material), 0) AS total_material,
    IFNULL(SUM(jns_perawatan.bhp), 0) AS total_bhp,
    IFNULL(SUM(jns_perawatan.tarif_tindakandr), 0) AS total_tindakan_dr,
    IFNULL(SUM(jns_perawatan.tarif_tindakanpr), 0) AS total_tindakan_pr,
    IFNULL(SUM(jns_perawatan.kso), 0) AS total_kso,
    IFNULL(SUM(jns_perawatan.menejemen), 0) AS total_menejemen,
    IFNULL(SUM(jns_perawatan.total_byrdrpr), 0) AS total_biaya_rawat,
    
    CASE 
        WHEN COUNT(rawat_jl_drpr.no_rawat) > 0 THEN 'Sudah Ada Tindakan'
        ELSE 'Belum Ada Tindakan'
    END AS status_tindakan
    
$base

GROUP BY 
    reg_periksa.no_rawat,
    reg_periksa.no_rkm_medis,
    reg_periksa.tgl_registrasi,
    reg_periksa.jam_reg,
    pasien.nm_pasien,
    bridging_sep.no_sep,
    poliklinik.nm_poli,
    penjab.png_jawab
ORDER BY reg_periksa.tgl_registrasi DESC, reg_periksa.jam_reg DESC
LIMIT $start, $length
";

// ===============================
// COUNT TOTAL (tanpa filter)
// ===============================
$count_query = "
SELECT COUNT(DISTINCT reg_periksa.no_rawat) AS total
FROM reg_periksa
JOIN poliklinik ON reg_periksa.kd_poli = poliklinik.kd_poli
WHERE reg_periksa.status_lanjut = 'Ralan'
AND reg_periksa.kd_poli != 'IGDK'
AND EXISTS (
    SELECT 1 FROM poliklinik p 
    WHERE p.kd_poli = reg_periksa.kd_poli
)
";

$c_total = mysqli_fetch_assoc(mysqli_query($koneksi, $count_query));
$total_records = $c_total['total'];

// ===============================
// COUNT FILTERED
// ===============================
$count_filtered_query = "
SELECT COUNT(DISTINCT reg_periksa.no_rawat) AS total
$base
";

$c_filtered = mysqli_fetch_assoc(mysqli_query($koneksi, $count_filtered_query));
$filtered_records = $c_filtered['total'];

// ===============================
// GET MAIN DATA
// ===============================
$result = mysqli_query($koneksi, $query);

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

$data = [];

while ($row = mysqli_fetch_assoc($result)) {
    $no_rawat = $row['no_rawat'];
    $no_sep = $row['no_sep'];

    // ===============================
    // Get RESEP - RACIKAN & NON RACIKAN & OPERASI
    // ===============================
    $resep_result = mysqli_query($koneksi, "
        SELECT no_resep 
        FROM resep_obat 
        WHERE no_rawat = '$no_rawat' AND tgl_perawatan != '0000-00-00' AND status = 'ralan'
    ");

    $total_racikan = 0;
    $total_non_racikan = 0;
    $total_operasi = 0;

    if ($resep_result && mysqli_num_rows($resep_result) > 0) {
        while ($resep = mysqli_fetch_assoc($resep_result)) {
            $no_resep = $resep['no_resep'];

            // Cek apakah resep operasi (2 digit pertama = 'OK')
            $is_operasi = (substr($no_resep, 0, 2) === 'OK');

            // Cek apakah ada di resep_obat_racikan (RACIKAN)
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

            // Cek apakah ada di resep_dokter (NON RACIKAN)
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

            // Kategorikan resep ini
            if ($is_operasi) {
                // Jika operasi, hitung sebagai 1 resep operasi
                $total_operasi++;
            } else {
                // Jika bukan operasi, cek apakah racikan atau non racikan
                if ($ada_racikan) {
                    $total_racikan++;
                } else if ($ada_non_racikan) {
                    $total_non_racikan++;
                }
                // Jika tidak ada di kedua tabel, tidak dihitung
            }
        }
    }

    // Format output
    $row['jumlah_resep_racikan'] = $total_racikan;
    $row['jumlah_resep_non_racikan'] = $total_non_racikan;
    $row['jumlah_resep_operasi'] = $total_operasi;
    $row['total_resep'] = $total_racikan + $total_non_racikan + $total_operasi;
    $row['jumlah_resep_real'] = mysqli_num_rows($resep_result); // Total resep dari resep_obat

    // Hitung jasa farmasi
    $row['jasa_farmasi'] = ($total_racikan * 25000) + ($total_non_racikan * 15000) + ($total_operasi * 35000);
    $row['jasa_farmasi_format'] = number_format($row['jasa_farmasi'], 0, ',', '.');

    // Get LAB totals
    $lab_result = mysqli_query($koneksi, "
        SELECT 
            SUM(IFNULL(bagian_rs,0)) AS total_material_lab,
            SUM(IFNULL(tarif_tindakan_dokter,0)) AS total_dokter_lab,
            SUM(IFNULL(tarif_tindakan_petugas,0)) AS total_petugas_lab,
            SUM(IFNULL(menejemen,0)) AS total_menejemen_lab,
            SUM(IFNULL(biaya,0)) AS total_lab
        FROM periksa_lab
        WHERE no_rawat = '$no_rawat' AND status = 'Ralan'
    ");

    if ($lab_result && mysqli_num_rows($lab_result) > 0) {
        $lab_data = mysqli_fetch_assoc($lab_result);
        $row = array_merge($row, $lab_data);
    } else {
        $row['total_material_lab'] = 0;
        $row['total_dokter_lab'] = 0;
        $row['total_petugas_lab'] = 0;
        $row['total_menejemen_lab'] = 0;
        $row['total_lab'] = 0;
    }

    // Get RADIOLOGI totals
    $rad_result = mysqli_query($koneksi, "
        SELECT 
            SUM(IFNULL(bagian_rs,0)) AS total_material_radiologi,
            SUM(IFNULL(tarif_tindakan_dokter,0)) AS total_dokter_radiologi,
            SUM(IFNULL(tarif_tindakan_petugas,0)) AS total_petugas_radiologi,
            SUM(IFNULL(menejemen,0)) AS total_menejemen_radiologi,
            SUM(IFNULL(biaya,0)) AS total_radiologi
        FROM periksa_radiologi
        WHERE no_rawat = '$no_rawat' AND status = 'Ralan'
    ");

    if ($rad_result && mysqli_num_rows($rad_result) > 0) {
        $rad_data = mysqli_fetch_assoc($rad_result);
        $row = array_merge($row, $rad_data);
    } else {
        $row['total_material_radiologi'] = 0;
        $row['total_dokter_radiologi'] = 0;
        $row['total_petugas_radiologi'] = 0;
        $row['total_menejemen_radiologi'] = 0;
        $row['total_radiologi'] = 0;
    }

    // Get OBAT totals
    $obat_result = mysqli_query($koneksi, "
        SELECT 
            SUM(IFNULL(total,0)) AS total_obat
        FROM detail_pemberian_obat
        WHERE no_rawat = '$no_rawat' AND status = 'Ralan'
    ");

    if ($obat_result && mysqli_num_rows($obat_result) > 0) {
        $obat_data = mysqli_fetch_assoc($obat_result);
        $row['total_obat'] = $obat_data['total_obat'];
        $row['total_obat_dan_ppn'] = $obat_data['total_obat'] * 1.11;
    } else {
        $row['total_obat'] = 0;
        $row['total_obat_dan_ppn'] = 0;
    }

    // Get total BPJS dari koneksi2 - HANYA jika no_sep ada dan bukan '-'
    $row['total_bpjs'] = 0;
    if ($no_sep && $no_sep != '-') {
        $q2 = mysqli_query($koneksi2, "SELECT total_bpjs FROM inacbd WHERE no_sep = '$no_sep' LIMIT 1");

        if ($q2 && mysqli_num_rows($q2) > 0) {
            $r2 = mysqli_fetch_assoc($q2);
            $row['total_bpjs'] = $r2['total_bpjs'];
        }
    }

    // Calculate grand total
    $row['grand_total'] =
        ($row['total_biaya_rawat'] ?? 0) +
        ($row['total_obat'] ?? 0) +
        ($row['total_lab'] ?? 0) +
        ($row['total_radiologi'] ?? 0);

    $data[] = $row;
}

// RESPONSE
echo json_encode([
    "draw" => intval($draw),
    "recordsTotal" => intval($total_records),
    "recordsFiltered" => intval($filtered_records),
    "data" => $data
]);

mysqli_close($koneksi);
mysqli_close($koneksi2);
