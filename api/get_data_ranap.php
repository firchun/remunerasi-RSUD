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
    LEFT JOIN operasi ON operasi.no_rawat = reg_periksa.no_rawat
    LEFT JOIN bridging_sep ON bridging_sep.no_rawat = reg_periksa.no_rawat
    LEFT JOIN rawat_inap_drpr ON rawat_inap_drpr.no_rawat = reg_periksa.no_rawat
    LEFT JOIN jns_perawatan_inap ON rawat_inap_drpr.kd_jenis_prw = jns_perawatan_inap.kd_jenis_prw
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

// gedung - Perbaikan untuk case-insensitive LIKE
if (!empty($gedung)) {
    $gedung_escaped = mysqli_real_escape_string($koneksi, $gedung);
    $base .= "
    AND EXISTS (
        SELECT 1
        FROM kamar_inap ki
        JOIN kamar km ON ki.kd_kamar = km.kd_kamar
        JOIN bangsal bs ON km.kd_bangsal = bs.kd_bangsal
        WHERE ki.no_rawat = reg_periksa.no_rawat
        AND LOWER(bs.nm_bangsal) LIKE LOWER('$gedung_escaped%')
        AND CONCAT(ki.tgl_masuk, ' ', ki.jam_masuk) = (
            SELECT MAX(CONCAT(ki2.tgl_masuk, ' ', ki2.jam_masuk))
            FROM kamar_inap ki2
            WHERE ki2.no_rawat = reg_periksa.no_rawat
        )
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
        LIMIT 1
    )";
} elseif ($status_pulang === 'sudah_pulang') {
    $base .= "
    AND EXISTS (
        SELECT 1
        FROM kamar_inap ki
        WHERE ki.no_rawat = reg_periksa.no_rawat
        AND ki.stts_pulang != '-'
        LIMIT 1
    )";
}

// Cari manual (tcari)
if (!empty($tcari)) {
    $tcari_escaped = mysqli_real_escape_string($koneksi, $tcari);
    $base .= " AND (
        reg_periksa.no_rawat LIKE '%$tcari_escaped%' 
        OR pasien.nm_pasien LIKE '%$tcari_escaped%' 
        OR dokter.nm_dokter LIKE '%$tcari_escaped%' 
        OR reg_periksa.no_rkm_medis LIKE '%$tcari_escaped%'
        OR bridging_sep.no_sep LIKE '%$tcari_escaped%'
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
    reg_periksa.jam_reg,
    pasien.nm_pasien,
    IFNULL(bridging_sep.no_sep, '-') AS no_sep,
    penjab.png_jawab,

    IFNULL(MIN(rawat_inap_drpr.tgl_perawatan), reg_periksa.tgl_registrasi) AS tgl_perawatan,
    IFNULL(MIN(rawat_inap_drpr.jam_rawat), reg_periksa.jam_reg) AS jam_rawat,
    IFNULL(MIN(CONCAT(rawat_inap_drpr.tgl_perawatan,' ',rawat_inap_drpr.jam_rawat)), 
           CONCAT(reg_periksa.tgl_registrasi,' ',reg_periksa.jam_reg)) AS waktu_perawatan,
    MIN(dokter.nm_dokter) AS nm_dokter,
    MIN(petugas.nama) AS nama_petugas,

    IFNULL(SUM(jns_perawatan_inap.material), 0) AS total_material,
    IFNULL(SUM(jns_perawatan_inap.bhp), 0) AS total_bhp,
    IFNULL(SUM(jns_perawatan_inap.tarif_tindakandr), 0) AS total_tindakan_dr,
    IFNULL(SUM(jns_perawatan_inap.tarif_tindakanpr), 0) AS total_tindakan_pr,
    IFNULL(SUM(jns_perawatan_inap.kso), 0) AS total_kso,
    IFNULL(SUM(jns_perawatan_inap.menejemen), 0) AS total_menejemen,
    IFNULL(SUM(jns_perawatan_inap.total_byrdrpr), 0) AS total_biaya_rawat,
    
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
    bridging_sep.no_sep,
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
    $riwayat_kamar_result = mysqli_query($koneksi, "
        SELECT 
            bangsal.nm_bangsal,
            kamar_inap.tgl_masuk,
            kamar_inap.jam_masuk,
            kamar_inap.tgl_keluar,
            kamar_inap.jam_keluar,
            kamar_inap.lama,
            kamar_inap.stts_pulang,
            kamar.kd_kamar,
            kamar_inap.trf_kamar
        FROM kamar_inap
        JOIN kamar ON kamar_inap.kd_kamar = kamar.kd_kamar
        JOIN bangsal ON kamar.kd_bangsal = bangsal.kd_bangsal
        WHERE kamar_inap.no_rawat = '$no_rawat'
        ORDER BY kamar_inap.tgl_masuk ASC, kamar_inap.jam_masuk ASC
    ");

    $riwayat_kamar = [];
    $riwayat_kamar_simple = [];

    if ($riwayat_kamar_result && mysqli_num_rows($riwayat_kamar_result) > 0) {
        while ($rk = mysqli_fetch_assoc($riwayat_kamar_result)) {
            // Format detail untuk setiap kamar
            $detail = [
                'nm_bangsal' => $rk['nm_bangsal'],
                'kd_kamar' => $rk['kd_kamar'],
                'tgl_masuk' => $rk['tgl_masuk'],
                'jam_masuk' => $rk['jam_masuk'],
                'tgl_keluar' => $rk['tgl_keluar'],
                'jam_keluar' => $rk['jam_keluar'],
                'lama' => $rk['lama'],
                'tarif' => $rk['trf_kamar'],
                'total_biaya' => $rk['lama'] * $rk['trf_kamar'],
                'stts_pulang' => $rk['stts_pulang']
            ];

            $riwayat_kamar[] = $detail;

            // Format sederhana untuk bullet points
            $riwayat_kamar_simple[] = $rk['nm_bangsal'];
        }
    }

    // Simpan riwayat dalam format array
    $row['riwayat_kamar'] = $riwayat_kamar;

    // Format bullet points untuk display
    $row['riwayat_kamar_text'] = !empty($riwayat_kamar_simple)
        ? implode("\n", array_map(function ($item) {
            return "• " . $item;
        }, $riwayat_kamar_simple))
        : "Belum ada riwayat kamar";

    // Format HTML untuk tooltip atau detail view
    $row['riwayat_kamar_html'] = !empty($riwayat_kamar_simple)
        ? "<ul><li>" . implode("</li><li>", $riwayat_kamar_simple) . "</li></ul>"
        : "<p>Belum ada riwayat kamar</p>";

    // Jumlah total perpindahan kamar
    $row['jumlah_perpindahan_kamar'] = count($riwayat_kamar);

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
            if (!empty($kode) && $kode !== '-') {
                $kode_users[] = "'" . mysqli_real_escape_string($koneksi, $kode) . "'";
                // Simpan mapping kode ke peran (untuk dicari nanti)
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