<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_time_limit(120);

require_once '../config/conf.php';
$koneksi  = bukakoneksi();
$koneksi2 = bukakoneksi2();

// Get DataTables parameters
$draw   = $_POST['draw']   ?? 1;
$start  = $_POST['start']  ?? 0;
$length = $_POST['length'] ?? 25;
$search = $_POST['search']['value'] ?? '';

// Filters
$tgl1          = $_POST['tgl1']          ?? '';
$tgl2          = $_POST['tgl2']          ?? '';
$kd_bangsal    = $_POST['kd_bangsal']    ?? '';
$kd_dokter     = $_POST['kd_dokter']     ?? '';
$nip           = $_POST['nip']           ?? '';
$kd_pj         = $_POST['kd_pj']         ?? '';
$tcari         = $_POST['tcari']         ?? '';
$gedung        = $_POST['gedung']        ?? '';
$filter_sep = $_POST['filter_sep'] ?? 'semua';
$status_pulang = $_POST['status_pulang'] ?? 'belum_pulang';

// Convert datetime-local → MYSQL
$tgl1_format = !empty($tgl1) ? str_replace("T", " ", $tgl1) . ":00" : "";
$tgl2_format = !empty($tgl2) ? str_replace("T", " ", $tgl2) . ":59" : "";

// ===============================
// BASE QUERY - ACUAN reg_periksa
// ===============================

$base = "
    FROM reg_periksa
    JOIN pasien ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis
    JOIN penjab ON reg_periksa.kd_pj = penjab.kd_pj
    LEFT JOIN rawat_inap_drpr ON rawat_inap_drpr.no_rawat = reg_periksa.no_rawat
    LEFT JOIN rawat_jl_drpr ON rawat_jl_drpr.no_rawat = reg_periksa.no_rawat
    LEFT JOIN jns_perawatan_inap ON rawat_inap_drpr.kd_jenis_prw = jns_perawatan_inap.kd_jenis_prw
    LEFT JOIN jns_perawatan ON rawat_jl_drpr.kd_jenis_prw = jns_perawatan.kd_jenis_prw
    LEFT JOIN dokter ON reg_periksa.kd_dokter = dokter.kd_dokter
    LEFT JOIN petugas ON rawat_inap_drpr.nip = petugas.nip
    WHERE 1=1
    AND reg_periksa.status_lanjut = 'Ranap'
";

// ===============================
// FILTERS
// ===============================

// Date filter - berdasarkan tanggal registrasi atau tindakan
if (!empty($tgl1) && !empty($tgl2)) {
    $base .= " AND (
        CONCAT(reg_periksa.tgl_registrasi,' ',reg_periksa.jam_reg) 
        BETWEEN '$tgl1_format' AND '$tgl2_format'
        OR CONCAT(rawat_inap_drpr.tgl_perawatan,' ',rawat_inap_drpr.jam_rawat) 
        BETWEEN '$tgl1_format' AND '$tgl2_format'
    )";
}

// Bangsal - check di kamar_inap
if (!empty($kd_bangsal)) {
    $kd_bangsal_escaped = mysqli_real_escape_string($koneksi, $kd_bangsal);
    $base .= " AND EXISTS (
        SELECT 1 FROM kamar_inap ki
        JOIN kamar k ON ki.kd_kamar = k.kd_kamar
        WHERE ki.no_rawat = reg_periksa.no_rawat 
        AND k.kd_bangsal = '$kd_bangsal_escaped'
        LIMIT 1
    )";
}

// Dokter - bisa dari reg_periksa atau rawat_inap_drpr
if (!empty($kd_dokter)) {
    $kd_dokter_escaped = mysqli_real_escape_string($koneksi, $kd_dokter);
    $base .= " AND (reg_periksa.kd_dokter = '$kd_dokter_escaped' OR rawat_inap_drpr.kd_dokter = '$kd_dokter_escaped')";
}

// Petugas
if (!empty($nip)) {
    $nip_escaped = mysqli_real_escape_string($koneksi, $nip);
    $base .= " AND rawat_inap_drpr.nip = '$nip_escaped'";
}

// Penjab/Cara Bayar
if (!empty($kd_pj)) {
    $kd_pj_escaped = mysqli_real_escape_string($koneksi, $kd_pj);
    $base .= " AND reg_periksa.kd_pj = '$kd_pj_escaped'";
}

// gedung - Pencarian berdasarkan nama bangsal/gedung
if (!empty($gedung)) {
    $gedung_escaped = mysqli_real_escape_string($koneksi, $gedung);
    $base .= "
    AND EXISTS (
        SELECT 1
        FROM kamar_inap ki
        JOIN kamar km ON ki.kd_kamar = km.kd_kamar
        JOIN bangsal bs ON km.kd_bangsal = bs.kd_bangsal
        WHERE ki.no_rawat = reg_periksa.no_rawat
        AND bs.nm_bangsal LIKE '%$gedung_escaped%'
        AND ki.stts_pulang != 'Pindah Kamar'
    )";
}

// Filter Status Pulang
if ($status_pulang === 'belum_pulang') {
    $base .= "
    AND EXISTS (
        SELECT 1
        FROM kamar_inap ki
        WHERE ki.no_rawat = reg_periksa.no_rawat
        AND ki.stts_pulang = '-'
        AND ki.stts_pulang != 'Pindah Kamar'
        LIMIT 1
    )";
} elseif ($status_pulang === 'sudah_pulang') {
    $base .= "
    AND EXISTS (
        SELECT 1
        FROM kamar_inap ki
        WHERE ki.no_rawat = reg_periksa.no_rawat
        AND ki.stts_pulang != '-'
        AND ki.stts_pulang != 'Pindah Kamar'
        LIMIT 1
    )";
}


if ($filter_sep === 'ada') {
    $base .= " AND EXISTS (SELECT 1 FROM bridging_sep WHERE bridging_sep.no_rawat = reg_periksa.no_rawat AND no_sep != '' AND no_sep != '-') ";
} elseif ($filter_sep === 'tidak_ada') {
    $base .= " AND NOT EXISTS (SELECT 1 FROM bridging_sep WHERE bridging_sep.no_rawat = reg_periksa.no_rawat AND no_sep != '' AND no_sep != '-') ";
}

// Cari manual (Perbaikan tcari untuk SEP)
if (!empty($tcari)) {
    $tcari_escaped = mysqli_real_escape_string($koneksi, $tcari);
    $base .= " AND (
        reg_periksa.no_rawat LIKE '%$tcari_escaped%' 
        OR pasien.nm_pasien LIKE '%$tcari_escaped%' 
        OR dokter.nm_dokter LIKE '%$tcari_escaped%' 
        OR EXISTS (SELECT 1 FROM bridging_sep WHERE bridging_sep.no_rawat = reg_periksa.no_rawat AND no_sep LIKE '%$tcari_escaped%')
    )";
}

// Search DataTables
if (!empty($search)) {
    $search_escaped = mysqli_real_escape_string($koneksi, $search);
    $base .= " AND (
        reg_periksa.no_rawat LIKE '%$search_escaped%' 
        OR reg_periksa.no_rkm_medis LIKE '%$search_escaped%'
        OR pasien.nm_pasien LIKE '%$search_escaped%'
        OR dokter.nm_dokter LIKE '%$search_escaped%'
        OR bridging_sep.no_sep LIKE '%$search_escaped%'
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
    reg_periksa.kd_poli,
    reg_periksa.jam_reg,
    pasien.nm_pasien,
    -- Ambil daftar SEP sebagai string list (Gunakan Subquery)
    IFNULL((
        SELECT GROUP_CONCAT(DISTINCT no_sep SEPARATOR ' | ') 
        FROM bridging_sep 
        WHERE bridging_sep.no_rawat = reg_periksa.no_rawat 
        AND no_sep != '-' AND no_sep != ''
    ), '-') AS no_sep,
    penjab.png_jawab,

    IFNULL(MIN(rawat_inap_drpr.tgl_perawatan), reg_periksa.tgl_registrasi) AS tgl_perawatan,
    IFNULL(MIN(rawat_inap_drpr.jam_rawat), reg_periksa.jam_reg) AS jam_rawat,
    MIN(dokter.nm_dokter) AS nm_dokter,

    -- Pembagian biaya (Tetap gunakan SUM karena GROUP BY no_rawat)
    IFNULL(SUM(jns_perawatan_inap.total_byrdrpr), 0) AS total_biaya_rawat,

    -- Pembagian biaya (Tetap gunakan SUM karena GROUP BY no_rawat)
    IFNULL(SUM(jns_perawatan.total_byrdrpr), 0) AS total_rajal_biaya_rawat,
    
    CASE 
        WHEN COUNT(rawat_inap_drpr.no_rawat) > 0 THEN 'Sudah Ada Tindakan'
        ELSE 'Belum Ada Tindakan'
    END AS status_tindakan

$base

GROUP BY 
    reg_periksa.no_rawat,
    reg_periksa.no_rkm_medis,
    reg_periksa.tgl_registrasi,
    reg_periksa.jam_reg,
    pasien.nm_pasien,
    penjab.png_jawab
ORDER BY reg_periksa.tgl_registrasi DESC, reg_periksa.jam_reg DESC
LIMIT $start, $length
";

// ===============================
// COUNT TOTAL - OPTIMIZED
// ===============================
$count_total = mysqli_fetch_assoc(
    mysqli_query($koneksi, "
        SELECT COUNT(DISTINCT no_rawat) AS total
        FROM reg_periksa
        WHERE status_lanjut = 'Ranap'
    ")
)['total'];

// ===============================
// COUNT FILTERED - OPTIMIZED
// ===============================
$count_filtered_query = "
    SELECT COUNT(DISTINCT reg_periksa.no_rawat) AS total
    $base
";

$count_filtered = mysqli_fetch_assoc(
    mysqli_query($koneksi, $count_filtered_query)
)['total'];

// ===============================
// GET DATA
// ===============================
$result = mysqli_query($koneksi, $query);

if (!$result) {
    echo json_encode([
        "draw" => intval($draw),
        "recordsTotal" => 0,
        "recordsFiltered" => 0,
        "data" => [],
        "error" => mysqli_error($koneksi)
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = [];

while ($row = mysqli_fetch_assoc($result)) {
    $no_rawat = mysqli_real_escape_string($koneksi, $row['no_rawat']);
    $no_sep = $row['no_sep'];
    // ===============================
    // Get TOTAL TINDAKAN (Material, BHP, Jasa Dr, dll)
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
        $row['total_menejemen']   = $data_ti['total_menejemen'] ?? 0;
        $row['total_material']   = $data_ti['total_material'] ?? 0;
        $row['total_bhp']        = $data_ti['total_bhp'] ?? 0;
        $row['total_tindakan_dr'] = $data_ti['total_tindakan_dr'] ?? 0;
        $row['total_tindakan_pr'] = $data_ti['total_tindakan_pr'] ?? 0;
        $row['total_biaya_rawat'] = $data_ti['total_biaya_rawat'] ?? 0;

        $row['status_tindakan'] = ($row['total_biaya_rawat'] > 0) ? 'Sudah Ada Tindakan' : 'Belum Ada Tindakan';
    } else {
        $row['total_menejemen'] = 0;
        $row['total_material'] = 0;
        $row['total_bhp'] = 0;
        $row['total_biaya_rawat'] = 0;
        $row['status_tindakan'] = 'Belum Ada Tindakan';
    }
    // ===============================
    // Get TOTAL TINDAKAN RAJAL
    // ===============================
    $tindakan_rajal = mysqli_query($koneksi, "
        SELECT 
            SUM(jns_jl.material) AS total_rajal_material,
            SUM(jns_jl.bhp) AS total_rajal_bhp,
            SUM(jns_jl.tarif_tindakandr) AS total_rajal_tindakan_dr,
            SUM(jns_jl.tarif_tindakanpr) AS total_rajal_tindakan_pr,
            SUM(jns_jl.kso) AS total_rajal_kso,
            SUM(jns_jl.menejemen) AS total_rajal_menejemen,
            SUM(jns_jl.total_byrdrpr) AS total_rajal_biaya_rawat
        FROM rawat_jl_drpr drpr_jl
        JOIN jns_perawatan jns_jl ON drpr_jl.kd_jenis_prw = jns_jl.kd_jenis_prw
        WHERE drpr_jl.no_rawat = '$no_rawat'
    ");

    // PASTIKAN menggunakan variabel $tindakan_rajal, bukan $tindakan_inap
    if ($tindakan_rajal && mysqli_num_rows($tindakan_rajal) > 0) {
        $data_tr = mysqli_fetch_assoc($tindakan_rajal); // Beri nama berbeda, misal $data_tr
        $row['total_rajal_menejemen']   = $data_tr['total_rajal_menejemen'] ?? 0;
        $row['total_rajal_material']    = $data_tr['total_rajal_material'] ?? 0;
        $row['total_rajal_bhp']         = $data_tr['total_rajal_bhp'] ?? 0;
        $row['total_rajal_tindakan_dr'] = $data_tr['total_rajal_tindakan_dr'] ?? 0;
        $row['total_rajal_tindakan_pr'] = $data_tr['total_rajal_tindakan_pr'] ?? 0;
        $row['total_rajal_biaya_rawat'] = $data_tr['total_rajal_biaya_rawat'] ?? 0;

        $row['status_rajal_tindakan'] = ($row['total_rajal_biaya_rawat'] > 0) ? 'Sudah Ada Tindakan' : 'Tidak Ada Tindakan';
    } else {
        $row['total_rajal_menejemen']   = 0;
        $row['total_rajal_material']    = 0;
        $row['total_rajal_bhp']         = 0;
        $row['total_rajal_tindakan_dr'] = 0;
        $row['total_rajal_tindakan_pr'] = 0;
        $row['total_rajal_biaya_rawat'] = 0;
        $row['status_rajal_tindakan']   = 'Tidak Ada Tindakan';
    }
    // ===============================
    // Get RESEP - RACIKAN & NON RACIKAN & OPERASI
    // ===============================
    $resep_result = mysqli_query($koneksi, "
        SELECT no_resep 
        FROM resep_obat 
        WHERE no_rawat = '$no_rawat' AND tgl_perawatan != '0000-00-00' AND status = 'ranap'
    ");

    $total_racikan = 0;
    $total_non_racikan = 0;
    $total_operasi = 0;

    $resep_racikan_list = [];
    $resep_non_racikan_list = [];
    $resep_operasi_list = [];

    if ($resep_result && mysqli_num_rows($resep_result) > 0) {
        while ($resep = mysqli_fetch_assoc($resep_result)) {
            $no_resep = mysqli_real_escape_string($koneksi, $resep['no_resep']);

            $is_operasi = (substr($resep['no_resep'], 0, 2) === 'OK');

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

            if ($is_operasi) {
                $total_operasi++;
                $resep_operasi_list[] = $resep['no_resep'];
            } else {
                if ($ada_racikan) {
                    $total_racikan++;
                    $resep_racikan_list[] = $resep['no_resep'];
                } else if ($ada_non_racikan) {
                    $total_non_racikan++;
                    $resep_non_racikan_list[] = $resep['no_resep'];
                }
            }
        }
    }
    $row['daftar_resep_racikan'] = !empty($resep_racikan_list)
        ? "<ul><li>- " . implode("</li><li>- ", $resep_racikan_list) . "</li></ul>"
        : '-';

    $row['daftar_resep_non_racikan'] = !empty($resep_non_racikan_list)
        ? "<ul><li>- " . implode("</li><li>- ", $resep_non_racikan_list) . "</li></ul>"
        : '-';

    $row['daftar_resep_operasi'] = !empty($resep_operasi_list)
        ? "<ul><li>- " . implode("</li><li>- ", $resep_operasi_list) . "</li></ul>"
        : '-';

    // Format output
    $row['jumlah_resep_racikan'] = $total_racikan;
    $row['jumlah_resep_non_racikan'] = $total_non_racikan;
    $row['jumlah_resep_operasi'] = $total_operasi;
    $row['total_resep'] = $total_racikan + $total_non_racikan + $total_operasi;
    $row['jumlah_resep_real'] = $resep_result ? mysqli_num_rows($resep_result) : 0;

    // Hitung jasa farmasi
    $row['jasa_farmasi'] = ($total_racikan * 25000) + ($total_non_racikan * 15000) + ($total_operasi * 35000);
    $row['jasa_farmasi_format'] = number_format($row['jasa_farmasi'], 0, ',', '.');

    // ===============================
    // Get Bangsal/Ruang - Single query
    // ===============================
    $bangsal_result = mysqli_query($koneksi, "
        SELECT 
            bangsal.nm_bangsal,
            kamar_inap.stts_pulang,
            kamar_inap.tgl_keluar
        FROM kamar_inap
        JOIN kamar ON kamar_inap.kd_kamar = kamar.kd_kamar
        JOIN bangsal ON kamar.kd_bangsal = bangsal.kd_bangsal
        WHERE kamar_inap.no_rawat = '$no_rawat'
        AND kamar_inap.stts_pulang != 'Pindah Kamar'
        ORDER BY kamar_inap.tgl_masuk DESC
        LIMIT 1
    ");

    $row['ruang'] = 'Belum Masuk Kamar';
    $row['status_pulang'] = '-';
    $row['tgl_keluar'] = null;
    $row['status_pulang_label'] = 'Belum Masuk Kamar';

    if ($bangsal_result && mysqli_num_rows($bangsal_result) > 0) {
        $bangsal_data = mysqli_fetch_assoc($bangsal_result);
        $row['ruang'] = $bangsal_data['nm_bangsal'];
        $row['status_pulang'] = $bangsal_data['stts_pulang'];
        $row['tgl_keluar'] = $bangsal_data['tgl_keluar'];

        // Format status untuk display
        if ($bangsal_data['stts_pulang'] === '-') {
            $row['status_pulang_label'] = 'Belum Pulang';
        } else {
            $row['status_pulang_label'] = 'Sudah Pulang (' . $bangsal_data['stts_pulang'] . ')';
        }
    }

    // ===============================
    // Get RIWAYAT KAMAR - History perpindahan kamar
    // ===============================
    // $riwayat_kamar_result = mysqli_query($koneksi, "
    //     SELECT 
    //         bangsal.nm_bangsal,
    //         kamar_inap.tgl_masuk,
    //         kamar_inap.jam_masuk,
    //         kamar_inap.tgl_keluar,
    //         kamar_inap.jam_keluar
    //     FROM kamar_inap
    //     JOIN kamar ON kamar_inap.kd_kamar = kamar.kd_kamar
    //     JOIN bangsal ON kamar.kd_bangsal = bangsal.kd_bangsal
    //     WHERE kamar_inap.no_rawat = '$no_rawat'
    //     ORDER BY kamar_inap.tgl_masuk ASC, kamar_inap.jam_masuk ASC
    // ");

    // // Inisialisasi array untuk 3 kolom berbeda
    // $list_hanya_kamar = [];
    // $list_ringkasan_pr = [];
    // $list_ringkasan_dr = [];

    // if ($riwayat_kamar_result && mysqli_num_rows($riwayat_kamar_result) > 0) {
    //     while ($rk = mysqli_fetch_assoc($riwayat_kamar_result)) {
    //         $tgl_masuk = $rk['tgl_masuk'];
    //         $jam_masuk = $rk['jam_masuk'];
    //         $tgl_keluar = $rk['tgl_keluar'];
    //         $jam_keluar = $rk['jam_keluar'];
    //         $nm_bangsal = strtolower($rk['nm_bangsal']);

    //         // Penentuan batas waktu untuk filter tindakan
    //         $end_date_time = ($tgl_keluar == '0000-00-00' || empty($tgl_keluar))
    //             ? date('Y-m-d H:i:s')
    //             : "$tgl_keluar $jam_keluar";
    //         $start_date_time = "$tgl_masuk $jam_masuk";

    //         // Query Hitung Jasa per rentang waktu di kamar tersebut
    //         $sql_jasa = "
    //             SELECT 
    //                 SUM(jns.tarif_tindakandr) AS total_dr,
    //                 SUM(jns.tarif_tindakanpr) AS total_pr
    //             FROM rawat_inap_drpr drpr
    //             JOIN jns_perawatan_inap jns ON drpr.kd_jenis_prw = jns.kd_jenis_prw
    //             WHERE drpr.no_rawat = '$no_rawat'
    //             AND CONCAT(drpr.tgl_perawatan, ' ', drpr.jam_rawat) BETWEEN '$start_date_time' AND '$end_date_time'
    //         ";

    //         $jasa_res = mysqli_query($koneksi, $sql_jasa);
    //         $jasa_data = mysqli_fetch_assoc($jasa_res);

    //         $js_dr = $jasa_data['total_dr'] ?? 0;
    //         $js_pr = $jasa_data['total_pr'] ?? 0;

    //         // 1. Kolom Riwayat Kamar Saja
    //         $list_hanya_kamar[] = "<li>" . $nm_bangsal . "</li>";

    //         // 2. Kolom Riwayat Kamar Perawat: icu (100.000)
    //         $list_ringkasan_pr[] = "<li>" . $nm_bangsal . " (" . number_format($js_pr, 0, ',', '.') . ")</li>";

    //         // 3. Kolom Riwayat Kamar Dokter: icu (500.000)
    //         $list_ringkasan_dr[] = "<li>" . $nm_bangsal . " (" . number_format($js_dr, 0, ',', '.') . ")</li>";
    //     }
    // }

    // // Masukkan ke row DataTables dengan style list bersih
    // $row['col_hanya_kamar'] = !empty($list_hanya_kamar)
    //     ? "<ul style='list-style:none; padding:0; margin:0;'>" . implode("", $list_hanya_kamar) . "</ul>" : "-";

    // $row['col_tarif_pr_kamar'] = !empty($list_ringkasan_pr)
    //     ? "<ul style='list-style:none; padding:0; margin:0;'>" . implode("", $list_ringkasan_pr) . "</ul>" : "-";

    // $row['col_tarif_dr_kamar'] = !empty($list_ringkasan_dr)
    //     ? "<ul style='list-style:none; padding:0; margin:0;'>" . implode("", $list_ringkasan_dr) . "</ul>" : "-";
    // 1. Inisialisasi array kosong di setiap awal baris DataTables
    // --- [ 1. INISIALISASI ARRAY & VARIABEL AWAL ] ---
    // =========================================================================
    // =========================================================================
    // 1. HITUNG TOTAL JASA KESELURUHAN (GLOBAL)
    // =========================================================================
    $sql_total_global = "
        SELECT 
            SUM(total_dr) as total_dr, 
            SUM(total_pr) as total_pr 
        FROM (
            /* Ambil SEMUA dari Rawat Inap */
            SELECT SUM(jns.tarif_tindakandr) AS total_dr, SUM(jns.tarif_tindakanpr) AS total_pr
            FROM rawat_inap_drpr drpr
            JOIN jns_perawatan_inap jns ON drpr.kd_jenis_prw = jns.kd_jenis_prw
            WHERE drpr.no_rawat = '$no_rawat'
            UNION ALL
            /* Ambil SEMUA dari Rawat Jalan */
            SELECT SUM(jns.tarif_tindakandr) AS total_dr, SUM(jns.tarif_tindakanpr) AS total_pr
            FROM rawat_jl_drpr drjl
            JOIN jns_perawatan jns ON drjl.kd_jenis_prw = jns.kd_jenis_prw
            WHERE drjl.no_rawat = '$no_rawat'
        ) AS gabung_total";

    $res_total_global = mysqli_query($koneksi, $sql_total_global);
    $data_global = mysqli_fetch_assoc($res_total_global);
    $all_dr = (float)($data_global['total_dr'] ?? 0);
    $all_pr = (float)($data_global['total_pr'] ?? 0);

    // =========================================================================
    // 2. HITUNG JASA TIAP KAMAR (HANYA DARI TABEL RAWAT INAP)
    // =========================================================================
    $total_dr_kamar_saja = 0;
    $total_pr_kamar_saja = 0;
    $temp_kamar_html = "";
    $temp_dr_html = "";
    $temp_pr_html = "";

    $riwayat_kamar_result = mysqli_query($koneksi, "
        SELECT bangsal.nm_bangsal, ki.tgl_masuk, ki.jam_masuk, ki.tgl_keluar, ki.jam_keluar
        FROM kamar_inap ki
        JOIN kamar ON ki.kd_kamar = kamar.kd_kamar
        JOIN bangsal ON kamar.kd_bangsal = bangsal.kd_bangsal
        WHERE ki.no_rawat = '$no_rawat'
        ORDER BY ki.tgl_masuk ASC, ki.jam_masuk ASC
    ");

    while ($rk = mysqli_fetch_assoc($riwayat_kamar_result)) {
        $nm_bangsal = strtolower($rk['nm_bangsal']);
        $start_dt = $rk['tgl_masuk'] . ' ' . $rk['jam_masuk'];
        $end_dt   = ($rk['tgl_keluar'] == '0000-00-00' || empty($rk['tgl_keluar'])) ? date('Y-m-d H:i:s') : $rk['tgl_keluar'] . ' ' . $rk['jam_keluar'];

        // HANYA ambil dari rawat_inap_drpr untuk jasa kamar
        $sql_jasa_kamar = "
            SELECT 
                SUM(jns.tarif_tindakandr) AS total_dr, 
                SUM(jns.tarif_tindakanpr) AS total_pr
            FROM rawat_inap_drpr drpr 
            JOIN jns_perawatan_inap jns ON drpr.kd_jenis_prw = jns.kd_jenis_prw
            WHERE drpr.no_rawat = '$no_rawat' 
            AND CONCAT(drpr.tgl_perawatan, ' ', drpr.jam_rawat) BETWEEN '$start_dt' AND '$end_dt'";

        $res_kamar = mysqli_query($koneksi, $sql_jasa_kamar);
        $j_kamar = mysqli_fetch_assoc($res_kamar);

        $dr_kmr = (float)($j_kamar['total_dr'] ?? 0);
        $pr_kmr = (float)($j_kamar['total_pr'] ?? 0);

        $total_dr_kamar_saja += $dr_kmr;
        $total_pr_kamar_saja += $pr_kmr;

        $temp_kamar_html .= "<li>" . $nm_bangsal . "</li>";
        $temp_dr_html    .= "<li>" . $nm_bangsal . " (" . number_format($dr_kmr, 0, ',', '.') . ")</li>";
        $temp_pr_html    .= "<li>" . $nm_bangsal . " (" . number_format($pr_kmr, 0, ',', '.') . ")</li>";
    }

    // =========================================================================
    // 3. HITUNG FINAL POLI/IGD (TOTAL GLOBAL - TOTAL KAMAR)
    // =========================================================================
    // Karena rawat_jl_drpr tidak dihitung di loop kamar, 
    // maka nilai all_dr - total_dr_kamar_saja sudah termasuk SEMUA Rawat Jalan + Sisa Rawat Inap diluar jam kamar.
    $js_dr_awal_final = $all_dr - $total_dr_kamar_saja;
    $js_pr_awal_final = $all_pr - $total_pr_kamar_saja;

    $nm_poli = 'poli';
    $cari_poli = mysqli_query($koneksi, "SELECT nm_poli FROM poliklinik WHERE kd_poli = '" . $row['kd_poli'] . "' LIMIT 1");
    if ($poli_data = mysqli_fetch_assoc($cari_poli)) {
        $nm_poli = strtolower($poli_data['nm_poli']);
    }

    // Buat List Poli (Paling Atas)
    $list_poli_kamar = "<li>" . $nm_poli . "</li>" . $temp_kamar_html;
    $list_poli_dr    = "<li>" . $nm_poli . " (" . number_format(max(0, $js_dr_awal_final), 0, ',', '.') . ")</li>" . $temp_dr_html;
    $list_poli_pr    = "<li>" . $nm_poli . " (" . number_format(max(0, $js_pr_awal_final), 0, ',', '.') . ")</li>" . $temp_pr_html;

    // =========================================================================
    // 4. OUTPUT FINAL KE ARRAY DATATABLES
    // =========================================================================
    $row['col_hanya_kamar']    = "<ul style='list-style:none; padding:0; margin:0;'>" . $list_poli_kamar . "</ul>";
    $row['col_tarif_dr_kamar'] = "<ul style='list-style:none; padding:0; margin:0;'>" . $list_poli_dr . "</ul>";
    $row['col_tarif_pr_kamar'] = "<ul style='list-style:none; padding:0; margin:0;'>" . $list_poli_pr . "</ul>";
    // ===============================
    // Get KAMAR INAP - LAMA & BIAYA
    // ===============================
    $kamar_result = mysqli_query($koneksi, "
        SELECT 
            SUM(kamar_inap.lama) AS total_lama_inap,
            SUM(kamar_inap.lama * kamar_inap.trf_kamar) AS total_biaya_kamar
        FROM kamar_inap
        WHERE kamar_inap.no_rawat = '$no_rawat'
    ");

    if ($kamar_result && mysqli_num_rows($kamar_result) > 0) {
        $kamar_data = mysqli_fetch_assoc($kamar_result);
        $row['total_lama_inap'] = $kamar_data['total_lama_inap'] ?? 0;
        $row['total_biaya_kamar'] = $kamar_data['total_biaya_kamar'] ?? 0;
    } else {
        $row['total_lama_inap'] = 0;
        $row['total_biaya_kamar'] = 0;
    }
    // ===============================
    // Get LAB totals - Aggregated
    // ===============================
    $lab_result = mysqli_query($koneksi, "
        SELECT 
            SUM(IFNULL(jns_perawatan_lab.bagian_rs,0)) AS total_material_lab,
            SUM(IFNULL(jns_perawatan_lab.tarif_tindakan_dokter,0)) AS total_dokter_lab,
            SUM(IFNULL(jns_perawatan_lab.tarif_tindakan_petugas,0)) AS total_petugas_lab,
            SUM(IFNULL(jns_perawatan_lab.menejemen,0)) AS total_menejemen_lab,
            SUM(IFNULL(jns_perawatan_lab.total_byr,0)) AS total_lab,
            GROUP_CONCAT(
                CONCAT('- ', jns_perawatan_lab.nm_perawatan) 
                SEPARATOR '</li><li>'
            ) AS daftar_lab_mentah
        FROM periksa_lab
        JOIN jns_perawatan_lab ON periksa_lab.kd_jenis_prw = jns_perawatan_lab.kd_jenis_prw
        WHERE periksa_lab.no_rawat = '$no_rawat' 
    ");

    if ($lab_result && mysqli_num_rows($lab_result) > 0) {
        $lab_data = mysqli_fetch_assoc($lab_result);
        $row = array_merge($row, $lab_data);

        if (!empty($lab_data['daftar_lab_mentah'])) {
            $row['daftar_tindakan_lab'] = "<ul><li>" . $lab_data['daftar_lab_mentah'] . "</li></ul>";
        } else {
            $row['daftar_tindakan_lab'] = '-';
        }
    } else {
        $row['total_material_lab'] = 0;
        $row['total_dokter_lab'] = 0;
        $row['total_petugas_lab'] = 0;
        $row['total_menejemen_lab'] = 0;
        $row['total_lab'] = 0;
        $row['daftar_tindakan_lab'] = '-';
    }

    // ===============================
    // Get OPERASI totals
    // ===============================
    $operasi_result = mysqli_query($koneksi, "
        SELECT (
            IFNULL(SUM(biayaoperator1),0) +
            IFNULL(SUM(biayaoperator2),0) +
            IFNULL(SUM(biayaoperator3),0) +
            IFNULL(SUM(biayaasisten_operator1),0) +
            IFNULL(SUM(biayaasisten_operator2),0) +
            IFNULL(SUM(biayaasisten_operator3),0) +
            IFNULL(SUM(biayainstrumen),0) +
            IFNULL(SUM(biayadokter_anak),0) +
            IFNULL(SUM(biayaperawaat_resusitas),0) +
            IFNULL(SUM(biayadokter_anestesi),0) +
            IFNULL(SUM(biayaasisten_anestesi),0) +
            IFNULL(SUM(biayaasisten_anestesi2),0) +
            IFNULL(SUM(biayabidan),0) +
            IFNULL(SUM(biayabidan2),0) +
            IFNULL(SUM(biayabidan3),0) +
            IFNULL(SUM(biayaperawat_luar),0) +
            IFNULL(SUM(biaya_omloop),0) +
            IFNULL(SUM(biaya_omloop2),0) +
            IFNULL(SUM(biaya_omloop3),0) +
            IFNULL(SUM(biaya_omloop4),0) +
            IFNULL(SUM(biaya_omloop5),0) +
            IFNULL(SUM(biaya_dokter_pjanak),0) +
            IFNULL(SUM(biaya_dokter_umum),0) +
            IFNULL(SUM(biayaalat),0) +
            IFNULL(SUM(biayasewaok),0) +
            IFNULL(SUM(operasi.akomodasi),0) +
            IFNULL(SUM(operasi.bagian_rs),0) +
            IFNULL(SUM(biayasarpras),0)
        ) AS total_operasi,
         (
            IFNULL(SUM(operasi.akomodasi),0) +
            IFNULL(SUM(operasi.bagian_rs),0) +
            IFNULL(SUM(biayasarpras),0) +
            IFNULL(SUM(biayasewaok),0)
        ) AS total_jasa_sarana_rs,
        (
            IFNULL(SUM(biayaoperator1),0) +
            IFNULL(SUM(biayaoperator2),0) +
            IFNULL(SUM(biayaoperator3),0)
        ) AS total_operator_operasi,
        (
            IFNULL(SUM(biayaasisten_operator1),0) +
            IFNULL(SUM(biayaasisten_operator2),0) +
            IFNULL(SUM(biayaasisten_operator3),0) 
        ) AS total_asisten_operator_operasi,
        (
            IFNULL(SUM(biayadokter_anestesi),0) 
        ) AS total_dr_anestesi_operasi,
        (
            IFNULL(SUM(biayaasisten_anestesi),0) +
            IFNULL(SUM(biayaasisten_anestesi2),0) 
        ) AS total_asisten_anestesi_operasi,
        (
            IFNULL(SUM(biayabidan),0) +
            IFNULL(SUM(biayabidan2),0) +
            IFNULL(SUM(biayabidan3),0) 
        ) AS total_bidan_operasi,
        (
            IFNULL(SUM(biaya_omloop),0) +
            IFNULL(SUM(biaya_omloop2),0) +
            IFNULL(SUM(biaya_omloop3),0) +
            IFNULL(SUM(biaya_omloop4),0) +
            IFNULL(SUM(biaya_omloop5),0) 
        ) AS total_onloop_operasi,
        (
            IFNULL(SUM(biayadokter_anak),0) 
        ) AS total_perina_operasi,
            GROUP_CONCAT(DISTINCT paket_operasi.nm_perawatan SEPARATOR '; ') AS nm_perawatan, 
            MAX(operasi.jenis_anasthesi) AS anastesi
        FROM operasi
        LEFT JOIN 
            paket_operasi ON paket_operasi.kode_paket = operasi.kode_paket
        WHERE no_rawat = '$no_rawat' AND operasi.status = 'Ranap'
    ");

    if ($operasi_result && mysqli_num_rows($operasi_result) > 0) {
        $operasi_data = mysqli_fetch_assoc($operasi_result);
        $row = array_merge($row, $operasi_data);
    } else {
        $row['nm_perawatan'] = '-';
        $row['anastesi'] = '-';
        $row['total_jasa_sarana_rs'] = 0;
        $row['total_perina_operasi'] = 0;
        $row['total_onloop_operasi'] = 0;
        $row['total_bidan_operasi'] = 0;
        $row['total_dr_anestesi_operasi'] = 0;
        $row['total_asisten_anestesi_operasi'] = 0;
        $row['total_asisten_operator_operasi'] = 0;
        $row['total_operator_operasi'] = 0;
        $row['total_operasi'] = 0;
    }
    // ===============================
    // Get DAFTAR DPJP, TINDAKAN, & OPERASI
    // ===============================
    $utama_suffix = " (DPJP UTAMA)";
    $tindakan_suffix = " (TINDAKAN)";
    $operasi_suffix = " (OPERASI)";

    $dpjp_list_temp = [];
    $utama_item = null;

    // 1. Ambil dari dpjp_ranap (Data DPJP Resmi)
    $dpjp_result = mysqli_query($koneksi, "
        SELECT t2.nm_dokter FROM dpjp_ranap t1
        JOIN dokter t2 ON t1.kd_dokter = t2.kd_dokter
        WHERE t1.no_rawat = '$no_rawat'
    ");
    if ($dpjp_result) {
        while ($dpjp = mysqli_fetch_assoc($dpjp_result)) {
            $dpjp_list_temp[$dpjp['nm_dokter']] = $dpjp['nm_dokter'];
        }
    }

    // 2. Ambil dari rawat_inap_drpr (Tindakan di Kamar)
    $tindakan_dr_result = mysqli_query($koneksi, "
        SELECT DISTINCT t2.nm_dokter FROM rawat_inap_drpr t1
        JOIN dokter t2 ON t1.kd_dokter = t2.kd_dokter
        WHERE t1.no_rawat = '$no_rawat' AND t1.kd_dokter LIKE 'd%'
    ");
    if ($tindakan_dr_result) {
        while ($tdr = mysqli_fetch_assoc($tindakan_dr_result)) {
            $nama_dr = $tdr['nm_dokter'];
            // Masukkan hanya jika belum ada di list (agar tidak menimpa DPJP resmi)
            if (!isset($dpjp_list_temp[$nama_dr])) {
                $dpjp_list_temp[$nama_dr] = $nama_dr . $tindakan_suffix;
            }
        }
    }

    // 3. Ambil dari tabel operasi
    $op_dr_query = mysqli_query($koneksi, "
        SELECT 
            operator1, operator2, operator3, dokter_anestesi, dokter_anak, dokter_pjanak, dokter_umum
        FROM operasi WHERE no_rawat = '$no_rawat' AND status = 'Ranap' LIMIT 1
    ");
    if ($op_dr_query && mysqli_num_rows($op_dr_query) > 0) {
        $op_data_row = mysqli_fetch_assoc($op_dr_query);
        $op_kodes = array_filter([
            $op_data_row['operator1'],
            $op_data_row['operator2'],
            $op_data_row['operator3'],
            $op_data_row['dokter_anestesi'],
            $op_data_row['dokter_anak'],
            $op_data_row['dokter_pjanak'],
            $op_data_row['dokter_umum']
        ], function ($val) {
            return !empty($val) && $val !== '-' && strtolower(substr($val, 0, 1)) !== 'p';
        });

        if (!empty($op_kodes)) {
            $op_kodes_str = "'" . implode("','", array_map(function ($k) use ($koneksi) {
                return mysqli_real_escape_string($koneksi, $k);
            }, $op_kodes)) . "'";
            $lookup_op_dr = mysqli_query($koneksi, "SELECT nm_dokter FROM dokter WHERE kd_dokter IN ($op_kodes_str)");
            while ($odr = mysqli_fetch_assoc($lookup_op_dr)) {
                $nama_op_dr = $odr['nm_dokter'];
                // Masukkan hanya jika belum ada di list (DPJP & Tindakan lebih prioritas)
                if (!isset($dpjp_list_temp[$nama_op_dr])) {
                    $dpjp_list_temp[$nama_op_dr] = $nama_op_dr . $operasi_suffix;
                }
            }
        }
    }

    // 4. Tentukan DPJP UTAMA dari reg_periksa
    $dokter_reg_periksa = $row['nm_dokter'];
    if (!empty($dokter_reg_periksa) && $dokter_reg_periksa !== 'N/A') {
        if (isset($dpjp_list_temp[$dokter_reg_periksa])) {
            $utama_item = $dokter_reg_periksa . $utama_suffix;
            unset($dpjp_list_temp[$dokter_reg_periksa]);
        } else {
            $utama_item = $dokter_reg_periksa . $utama_suffix;
        }
    }

    // 5. Finalisasi List
    $dpjp_final_list = array_values($dpjp_list_temp);
    sort($dpjp_final_list);
    if ($utama_item !== null) {
        array_unshift($dpjp_final_list, $utama_item);
    }

    $row['daftar_dpjp'] = !empty($dpjp_final_list)
        ? "<ul><li>" . implode("</li><li>", $dpjp_final_list) . "</li></ul>"
        : '-';
    // ===============================
    // Get DAFTAR & JUMLAH PERAWAT DAN DOKTER dari rawat_inap_drpr
    // ===============================
    // Ambil data Perawat (NIP)
    $perawat_list = [];
    $perawat_query = mysqli_query($koneksi, "
    SELECT 
        rawat_inap_drpr.nip,
        petugas.nama,
        SUM(jns_perawatan_inap.tarif_tindakanpr) AS pendapatan_perawat
    FROM rawat_inap_drpr
    INNER JOIN petugas ON rawat_inap_drpr.nip = petugas.nip
    INNER JOIN jns_perawatan_inap ON rawat_inap_drpr.kd_jenis_prw = jns_perawatan_inap.kd_jenis_prw
    WHERE rawat_inap_drpr.no_rawat = '$no_rawat'
    GROUP BY rawat_inap_drpr.nip
");

    if ($perawat_query) {
        while ($p = mysqli_fetch_assoc($perawat_query)) {
            $nom = number_format($p['pendapatan_perawat'], 0, ',', '.');
            $perawat_list[] = "<li>" . $p['nama'] . " (" . $nom . ")</li>";
        }
    }

    // Ambil data Dokter (KD_DOKTER)
    $dokter_list = [];
    $dokter_query = mysqli_query($koneksi, "
    SELECT 
        rawat_inap_drpr.kd_dokter,
        dokter.nm_dokter,
        SUM(jns_perawatan_inap.tarif_tindakandr) AS pendapatan_dokter
    FROM rawat_inap_drpr
    INNER JOIN dokter ON rawat_inap_drpr.kd_dokter = dokter.kd_dokter
    INNER JOIN jns_perawatan_inap ON rawat_inap_drpr.kd_jenis_prw = jns_perawatan_inap.kd_jenis_prw
    WHERE rawat_inap_drpr.no_rawat = '$no_rawat'
    GROUP BY rawat_inap_drpr.kd_dokter
");

    if ($dokter_query) {
        while ($d = mysqli_fetch_assoc($dokter_query)) {
            $nom = number_format($d['pendapatan_dokter'], 0, ',', '.');
            $dokter_list[] = "<li>" . $d['nm_dokter'] . " (" . $nom . ")</li>";
        }
    }

    // Render menjadi UL List murni
    $row['daftar_perawat_tindakan'] = !empty($perawat_list)
        ? "<ul style='padding-left: 20px; margin: 0;'>" . implode("", $perawat_list) . "</ul>"
        : "-";

    $row['daftar_dokter_tindakan'] = !empty($dokter_list)
        ? "<ul style='padding-left: 20px; margin: 0;'>" . implode("", $dokter_list) . "</ul>"
        : "-";

    // ===============================
    // Get DAFTAR PETUGAS OPERASI
    // ===============================
    $operator_list = [];
    $petugas_operasi_result = mysqli_query($koneksi, "
        SELECT 
            operasi.operator1, operasi.operator2, operasi.operator3,
            operasi.asisten_operator1, operasi.asisten_operator2, operasi.asisten_operator3,
            operasi.dokter_anestesi, operasi.asisten_anestesi, operasi.asisten_anestesi2,
            operasi.bidan, operasi.bidan2, operasi.bidan3,
            operasi.perawat_luar, 
            operasi.omloop, operasi.omloop2, operasi.omloop3, operasi.omloop4, operasi.omloop5,
            operasi.dokter_anak, operasi.dokter_pjanak, operasi.dokter_umum,
            MAX(operasi.jenis_anasthesi) AS anastesi_label -- Tambahkan ini jika Anda mau label anestesi
        FROM operasi
        WHERE no_rawat = '$no_rawat' AND operasi.status = 'Ranap' 
        LIMIT 1
    ");

    if ($petugas_operasi_result && mysqli_num_rows($petugas_operasi_result) > 0) {
        $op_data = mysqli_fetch_assoc($petugas_operasi_result);

        // Definisikan peran dan mapping ke kolom di tabel 'operasi'
        $peran_map = [
            'operator1' => 'Operator 1',
            'operator2' => 'Operator 2',
            'operator3' => 'Operator 3',
            'asisten_operator1' => 'Asisten Operator 1',
            'asisten_operator2' => 'Asisten Operator 2',
            'asisten_operator3' => 'Asisten Operator 3',
            'dokter_anestesi' => 'Dokter Anestesi',
            'asisten_anestesi' => 'Asisten Anestesi 1',
            'asisten_anestesi2' => 'Asisten Anestesi 2',
            'bidan' => 'Bidan 1',
            'bidan2' => 'Bidan 2',
            'bidan3' => 'Bidan 3',
            'perawat_luar' => 'Perawat Luar',
            'omloop' => 'Perawat Onloop 1',
            'omloop2' => 'Perawat Onloop 2',
            'omloop3' => 'Perawat Onloop 3',
            'omloop4' => 'Perawat Onloop 4',
            'omloop5' => 'Perawat Onloop 5',
            'dokter_anak' => 'Dokter Anak',
            'dokter_pjanak' => 'Dokter PJ Anak',
            'dokter_umum' => 'Dokter Umum',
        ];

        $kode_users = [];
        $kode_peran = [];

        // Kumpulkan semua kode user yang tidak kosong
        foreach ($peran_map as $kolom => $peran) {
            $kode = $op_data[$kolom];
            if (!empty($kode) && $kode !== '-' && strtolower(substr($kode, 0, 1)) !== 'p') {
                $kode_users[] = "'" . mysqli_real_escape_string($koneksi, $kode) . "'";
                $kode_peran[$kode] = $peran;
            }
        }

        // Lakukan lookup nama hanya untuk kode user yang ada
        if (!empty($kode_users)) {
            $user_in = implode(",", $kode_users);
            $user_lookup_result = mysqli_query($koneksi, "
                SELECT nip, nama FROM petugas WHERE nip IN ($user_in)
                UNION
                SELECT kd_dokter AS nip, nm_dokter AS nama FROM dokter WHERE kd_dokter IN ($user_in)
            ");

            $nama_lookup = [];
            if ($user_lookup_result) {
                while ($user = mysqli_fetch_assoc($user_lookup_result)) {
                    $nama_lookup[$user['nip']] = $user['nama'];
                }
            }

            // Format output list
            $list_html = [];
            foreach ($kode_users as $kode_escaped) {
                // Hapus tanda kutip dari kode yang sudah di-escape untuk dicari
                $kode_nip = trim($kode_escaped, "'");

                $nama = $nama_lookup[$kode_nip] ?? 'N/A';
                $peran = $kode_peran[$kode_nip] ?? 'Peran Tidak Dikenal';

                $operator_list[] = "- " . $nama . " (" . $peran . ")";
            }
        }
    }

    // Gabungkan list operator menjadi satu string HTML untuk tampilan DataTables
    $row['daftar_petugas_operasi'] = !empty($operator_list)
        ? "<ul><li>" . implode("</li><li>", $operator_list) . "</li></ul>"
        : '-';

    // ===============================
    // Get RADIOLOGI totals - Aggregated
    // ===============================
    // $rad_result = mysqli_query($koneksi, "
    //     SELECT 
    //         SUM(IFNULL(bagian_rs,0)) AS total_material_radiologi,
    //         SUM(IFNULL(tarif_tindakan_dokter,0)) AS total_dokter_radiologi,
    //         SUM(IFNULL(tarif_tindakan_petugas,0)) AS total_petugas_radiologi,
    //         SUM(IFNULL(menejemen,0)) AS total_menejemen_radiologi,
    //         SUM(IFNULL(biaya,0)) AS total_radiologi
    //     FROM periksa_radiologi
    //     WHERE no_rawat = '$no_rawat' AND status = 'Ranap'
    // ");
    $rad_result = mysqli_query($koneksi, "
       SELECT 
        COALESCE(SUM(t2.bagian_rs), 0) AS total_material_radiologi,
        COALESCE(SUM(t2.tarif_tindakan_dokter), 0) AS total_dokter_radiologi,
        COALESCE(SUM(t2.tarif_tindakan_petugas), 0) AS total_petugas_radiologi,
        COALESCE(SUM(t2.menejemen), 0) AS total_menejemen_radiologi,
        COALESCE(SUM(t2.total_byr), 0) AS total_radiologi,
        max(t2.nm_perawatan) AS tindakan_radiologi
    FROM permintaan_radiologi t1
    JOIN permintaan_pemeriksaan_radiologi t3 
        ON t1.noorder = t3.noorder
    JOIN jns_perawatan_radiologi t2 
        ON t3.kd_jenis_prw = t2.kd_jenis_prw
    WHERE t1.no_rawat = '$no_rawat'  AND t1.status = 'ranap'
    
    ");

    if ($rad_result && mysqli_num_rows($rad_result) > 0) {
        $rad_data = mysqli_fetch_assoc($rad_result);
        $row = array_merge($row, $rad_data);
    } else {
        $row['tindakan_radiologi'] = '-';
        $row['total_material_radiologi'] = 0;
        $row['total_dokter_radiologi'] = 0;
        $row['total_petugas_radiologi'] = 0;
        $row['total_menejemen_radiologi'] = 0;
        $row['total_radiologi'] = 0;
    }

    // Get OBAT pulang total
    $obat_pulang_result = mysqli_query($koneksi, "
        SELECT 
            SUM(IFNULL(total,0)) AS total_obat_pulang
        FROM resep_pulang
        WHERE no_rawat = '$no_rawat'
    ");

    if ($obat_pulang_result && mysqli_num_rows($obat_pulang_result) > 0) {
        $obat_pulang_data = mysqli_fetch_assoc($obat_pulang_result);
        $row['total_obat_pulang'] = $obat_pulang_data['total_obat_pulang'];
    } else {
        $row['total_obat_pulang'] = 0;
    }
    // ===============================
    // Get OBAT totals - Aggregated
    // ===============================
    $obat_result = mysqli_query($koneksi, "
        SELECT 
            SUM(IFNULL(total,0)) AS total_obat
        FROM detail_pemberian_obat
        WHERE no_rawat = '$no_rawat'
    ");

    if ($obat_result && mysqli_num_rows($obat_result) > 0) {
        $obat_data = mysqli_fetch_assoc($obat_result);
        $row['total_obat'] = $obat_data['total_obat'];
    } else {
        $row['total_obat'] = 0;
        $row['total_obat_dan_ppn'] = 0;
    }

    // ===============================
    // Get total BPJS dari koneksi2
    // ===============================
    $row['total_bpjs'] = 0;
    if ($no_sep && $no_sep != '-') {
        $no_sep_escaped = mysqli_real_escape_string($koneksi2, $no_sep);
        $q2 = mysqli_query($koneksi2, "SELECT total_bpjs FROM inacbd WHERE no_sep = '$no_sep_escaped' LIMIT 1");

        if ($q2 && mysqli_num_rows($q2) > 0) {
            $r2 = mysqli_fetch_assoc($q2);
            $row['total_bpjs'] = $r2['total_bpjs'];
        }
    }

    // ===============================
    // Calculate grand total - TERMASUK BIAYA KAMAR & OPERASI
    // ===============================
    $row['grand_total'] =
        ($row['total_biaya_rawat'] ?? 0) +
        ($row['total_biaya_kamar'] ?? 0) +
        ($row['total_obat'] ?? 0) +
        ($row['total_lab'] ?? 0) +
        ($row['total_radiologi'] ?? 0) +
        ($row['total_operasi'] ?? 0);

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