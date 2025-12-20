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
    reg_periksa.kd_dokter AS kd_dokter_utama,

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
    penjab.png_jawab,
    reg_periksa.kd_dokter
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
    // Get DOKTER TAMBAHAN & JASA
    // ===============================
    $kd_utama = $row['kd_dokter_utama'];
    $dr_tambahan_query = mysqli_query($koneksi, "
        SELECT 
            dokter.nm_dokter,
            SUM(rawat_jl_drpr.tarif_tindakandr) AS jasa_dr
        FROM rawat_jl_drpr
        JOIN dokter ON rawat_jl_drpr.kd_dokter = dokter.kd_dokter
        WHERE rawat_jl_drpr.no_rawat = '$no_rawat'
        AND rawat_jl_drpr.kd_dokter != '$kd_utama'
        GROUP BY rawat_jl_drpr.kd_dokter
    ");

    $list_dr_tambahan = [];
    $total_jasa_dr_tambahan = 0;

    if ($dr_tambahan_query && mysqli_num_rows($dr_tambahan_query) > 0) {
        while ($dr_add = mysqli_fetch_assoc($dr_tambahan_query)) {
            $nama_dr = $dr_add['nm_dokter'];
            $jasa = $dr_add['jasa_dr'];
            $total_jasa_dr_tambahan += $jasa;

            $list_dr_tambahan[] = "- " . $nama_dr . " (" . number_format($jasa, 0, ',', '.') . ")";
        }
    }

    // Masukkan ke dalam array $row
    $row['dokter_tambahan'] = !empty($list_dr_tambahan)
        ? "<ul><li>" . implode("</li><li>", $list_dr_tambahan) . "</li></ul>"
        : '-';

    $row['total_jasa_dr_tambahan'] = $total_jasa_dr_tambahan;
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

    $jasa_obat_umum = 0;
    $jasa_operasi = 0;

    if ($total_racikan > 0) {
        $jasa_obat_umum = 25000;
    } else if ($total_non_racikan > 0) {
        $jasa_obat_umum = 15000;
    }

    if ($total_operasi > 0) {
        $jasa_operasi = 35000;
    }

    $row['jasa_farmasi'] = $jasa_obat_umum + $jasa_operasi;

    // ===============================
    // FORMAT OUTPUT
    // ===============================
    $row['daftar_resep_racikan'] = !empty($resep_racikan_list)
        ? "<ul><li>- " . implode("</li><li>- ", $resep_racikan_list) . "</li></ul>"
        : '-';

    $row['daftar_resep_non_racikan'] = !empty($resep_non_racikan_list)
        ? "<ul><li>- " . implode("</li><li>- ", $resep_non_racikan_list) . "</li></ul>"
        : '-';

    $row['daftar_resep_operasi'] = !empty($resep_operasi_list)
        ? "<ul><li>- " . implode("</li><li>- ", $resep_operasi_list) . "</li></ul>"
        : '-';

    $row['jumlah_resep_racikan'] = $total_racikan;
    $row['jumlah_resep_non_racikan'] = $total_non_racikan;
    $row['jumlah_resep_operasi'] = $total_operasi;

    // Total resep secara fisik
    $row['jumlah_resep_real'] = $resep_result ? mysqli_num_rows($resep_result) : 0;

    $row['jasa_farmasi_format'] = "Rp " . number_format($row['jasa_farmasi'], 0, ',', '.');


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
        WHERE no_rawat = '$no_rawat' AND operasi.status = 'Ralan'
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
        WHERE no_rawat = '$no_rawat' AND operasi.status = 'Ralan'
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

    // Get RADIOLOGI totals
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
    WHERE t1.no_rawat = '$no_rawat'  AND t1.status = 'ralan'
    
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
    // ===============================
    // Get DOKTER RADIOLOGI
    // ===============================
    // 1. Cek apakah ada Order/Permintaan Radiologi
    $cek_permintaan_rad = mysqli_query($koneksi, "
        SELECT no_rawat FROM permintaan_radiologi 
        WHERE no_rawat = '$no_rawat' LIMIT 1
    ");
    $ada_order = (mysqli_num_rows($cek_permintaan_rad) > 0);

    // 2. Cari Hasil Pemeriksaan & Nama Dokter
    $dr_radiologi_query = mysqli_query($koneksi, "
        SELECT dokter.nm_dokter 
        FROM periksa_radiologi 
        JOIN dokter ON periksa_radiologi.kd_dokter = dokter.kd_dokter 
        WHERE periksa_radiologi.no_rawat = '$no_rawat' 
        LIMIT 1
    ");

    if ($dr_radiologi_query && mysqli_num_rows($dr_radiologi_query) > 0) {
        // JIKA SUDAH DIPERIKSA: Tampilkan Nama Dokter
        $dr_rad_data = mysqli_fetch_assoc($dr_radiologi_query);
        $row['nm_dokter_radiologi'] = $dr_rad_data['nm_dokter'];
    } elseif ($ada_order) {
        // JIKA ADA ORDER TAPI BELUM ADA HASIL: Tampilkan (belum ada hasil)
        $row['nm_dokter_radiologi'] = '(belum ada hasil)';
    } else {
        // JIKA TIDAK ADA PERMINTAAN SAMA SEKALI: Tampilkan -
        $row['nm_dokter_radiologi'] = '-';
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
        // $row['total_obat_dan_ppn'] = $obat_data['total_obat'] * 1.11;
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