<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(180);

require_once '../config/conf.php';
$koneksi = bukakoneksi();

$filter_type = $_POST['filter_type'] ?? 'bulan';
$kd_pj = $_POST['kd_pj'] ?? '';
$gedung = $_POST['gedung'] ?? '';

if ($filter_type === 'tahun') {
  $tahun = $_POST['tahun'] ?? date('Y');
  $tgl_awal = "$tahun-01-01 00:00:00";
  $tgl_akhir = "$tahun-12-31 23:59:59";
  $periode_label = $tahun;
} else {
  $bulan = $_POST['bulan'] ?? date('Y-m');
  list($tahun, $bln) = explode('-', $bulan);
  $tgl_awal = "$tahun-$bln-01 00:00:00";
  $tgl_akhir = date("Y-m-t 23:59:59", strtotime($tgl_awal));
  $periode_label = date('F Y', strtotime($tgl_awal));
}

// SQL untuk mapping gedung
$sqlGedung = "CASE
    WHEN bs.nm_bangsal LIKE '%PICU%' THEN 'PICU'
    WHEN bs.nm_bangsal LIKE '%BOHA%' THEN 'BOHA'
    WHEN bs.nm_bangsal LIKE '%KANGURU%' THEN 'KANGURU'
    WHEN bs.nm_bangsal LIKE '%MALEO%' THEN 'MALEO'
    WHEN bs.nm_bangsal LIKE '%CENDERAWASIH%' THEN 'CENDERAWASIH'
    WHEN bs.nm_bangsal LIKE '%KUSKUS%' THEN 'KUSKUS'
    WHEN bs.nm_bangsal LIKE '%KASUARI%' THEN 'KASUARI'
    WHEN bs.nm_bangsal LIKE '%MAMBRUK%' THEN 'MAMBRUK'
    WHEN bs.nm_bangsal LIKE '%ICU%' THEN 'ICU'
    WHEN bs.nm_bangsal LIKE '%RUSA I.%' THEN 'RUSA I'
    WHEN bs.nm_bangsal LIKE '%RUSA II.%' THEN 'RUSA II'
    WHEN bs.nm_bangsal LIKE '%URIP%' THEN 'URIP'
    ELSE 'LAIN-LAIN'
END";

$esc_kd_pj = mysqli_real_escape_string($koneksi, $kd_pj);
$esc_gedung = mysqli_real_escape_string($koneksi, $gedung);

$where_kd_pj = !empty($kd_pj) ? " AND rp.kd_pj = '$esc_kd_pj'" : '';
$where_gedung = !empty($gedung) ? " AND bs.nm_bangsal LIKE '%$esc_gedung%'" : '';

// Daftar pasangan (nama_gedung, no_rawat) untuk periode & filter terpilih.
// Satu no_rawat bisa masuk lebih dari satu gedung, dihitung penuh di masing-masing.
$base = "SELECT
    $sqlGedung AS nama_gedung,
    rp.no_rawat
FROM reg_periksa rp
INNER JOIN kamar_inap ki ON rp.no_rawat = ki.no_rawat
INNER JOIN kamar km ON ki.kd_kamar = km.kd_kamar
INNER JOIN bangsal bs ON km.kd_bangsal = bs.kd_bangsal
WHERE rp.status_lanjut = 'Ranap'
/* Filter berdasarkan tgl keluar kamar, bukan tgl registrasi awal */
AND ki.tgl_keluar BETWEEN '$tgl_awal' AND '$tgl_akhir'
$where_kd_pj
$where_gedung
GROUP BY nama_gedung, rp.no_rawat";

// Agregasi per no_rawat lalu digabung ke daftar di atas dalam satu query.
$query = "
SELECT
    b.nama_gedung,
    COUNT(*) AS jumlah_kunjungan,
    COALESCE(SUM(k.total_biaya_kamar), 0) AS total_biaya_kamar,
    COALESCE(SUM(t.total_material), 0) AS total_material,
    COALESCE(SUM(t.total_bhp), 0) AS total_bhp,
    COALESCE(SUM(t.total_tindakan_dr), 0) AS total_tindakan_dr,
    COALESCE(SUM(t.total_tindakan_pr), 0) AS total_tindakan_pr,
    COALESCE(SUM(t.total_kso), 0) AS total_kso,
    COALESCE(SUM(t.total_menejemen), 0) AS total_menejemen,
    COALESCE(SUM(t.total_tindakan), 0) AS total_tindakan,
    COALESCE(SUM(rx.jumlah_resep_racikan), 0) AS jumlah_resep_racikan,
    COALESCE(SUM(rx.jumlah_resep_non_racikan), 0) AS jumlah_resep_non_racikan,
    COALESCE(SUM(rx.jumlah_resep_operasi), 0) AS jumlah_resep_operasi,
    COALESCE(SUM(ob.total_obat), 0) AS total_obat,
    COALESCE(SUM(l.total_material_lab), 0) AS total_material_lab,
    COALESCE(SUM(l.total_dokter_lab), 0) AS total_dokter_lab,
    COALESCE(SUM(l.total_petugas_lab), 0) AS total_petugas_lab,
    COALESCE(SUM(l.total_menejemen_lab), 0) AS total_menejemen_lab,
    COALESCE(SUM(l.total_lab), 0) AS total_lab,
    COALESCE(SUM(rd.total_material_radiologi), 0) AS total_material_radiologi,
    COALESCE(SUM(rd.total_dokter_radiologi), 0) AS total_dokter_radiologi,
    COALESCE(SUM(rd.total_petugas_radiologi), 0) AS total_petugas_radiologi,
    COALESCE(SUM(rd.total_menejemen_radiologi), 0) AS total_menejemen_radiologi,
    COALESCE(SUM(rd.total_radiologi), 0) AS total_radiologi,
    COALESCE(SUM(op.total_operasi), 0) AS total_operasi
FROM (
    $base
) b
LEFT JOIN (
    SELECT no_rawat, SUM(lama * trf_kamar) AS total_biaya_kamar
    FROM kamar_inap
    GROUP BY no_rawat
) k ON k.no_rawat = b.no_rawat
LEFT JOIN (
    SELECT
        drpr.no_rawat,
        SUM(jns.material) AS total_material,
        SUM(jns.bhp) AS total_bhp,
        SUM(jns.tarif_tindakandr) AS total_tindakan_dr,
        SUM(jns.tarif_tindakanpr) AS total_tindakan_pr,
        SUM(jns.kso) AS total_kso,
        SUM(jns.menejemen) AS total_menejemen,
        SUM(jns.total_byrdrpr) AS total_tindakan
    FROM rawat_inap_drpr drpr
    JOIN jns_perawatan_inap jns ON drpr.kd_jenis_prw = jns.kd_jenis_prw
    GROUP BY drpr.no_rawat
) t ON t.no_rawat = b.no_rawat
LEFT JOIN (
    SELECT
        ro.no_rawat,
        /* BINARY: no_resep prefix 'OK' checked case-sensitive, sama dengan substr() === 'OK' di PHP
           (collation utf8_general_ci membuat perbandingan string jadi case-insensitive) */
        SUM(CASE WHEN LEFT(BINARY ro.no_resep, 2) = 'OK' THEN 1 ELSE 0 END) AS jumlah_resep_operasi,
        SUM(CASE WHEN LEFT(BINARY ro.no_resep, 2) <> 'OK'
                  AND EXISTS (SELECT 1 FROM resep_dokter_racikan rrac WHERE rrac.no_resep = ro.no_resep)
                 THEN 1 ELSE 0 END) AS jumlah_resep_racikan,
        SUM(CASE WHEN LEFT(BINARY ro.no_resep, 2) <> 'OK'
                  AND NOT EXISTS (SELECT 1 FROM resep_dokter_racikan rrac WHERE rrac.no_resep = ro.no_resep)
                  AND EXISTS (SELECT 1 FROM resep_dokter nd WHERE nd.no_resep = ro.no_resep)
                 THEN 1 ELSE 0 END) AS jumlah_resep_non_racikan
    FROM resep_obat ro
    WHERE ro.tgl_perawatan != '0000-00-00' AND ro.status = 'ranap'
    GROUP BY ro.no_rawat
) rx ON rx.no_rawat = b.no_rawat
LEFT JOIN (
    SELECT no_rawat, SUM(IFNULL(total, 0)) AS total_obat
    FROM detail_pemberian_obat
    GROUP BY no_rawat
) ob ON ob.no_rawat = b.no_rawat
LEFT JOIN (
    SELECT
        pl.no_rawat,
        SUM(IFNULL(jpl.bagian_rs, 0)) AS total_material_lab,
        SUM(IFNULL(jpl.tarif_tindakan_dokter, 0)) AS total_dokter_lab,
        SUM(IFNULL(jpl.tarif_tindakan_petugas, 0)) AS total_petugas_lab,
        SUM(IFNULL(jpl.menejemen, 0)) AS total_menejemen_lab,
        SUM(IFNULL(jpl.total_byr, 0)) AS total_lab
    FROM periksa_lab pl
    JOIN jns_perawatan_lab jpl ON pl.kd_jenis_prw = jpl.kd_jenis_prw
    GROUP BY pl.no_rawat
) l ON l.no_rawat = b.no_rawat
LEFT JOIN (
    SELECT
        t1.no_rawat,
        COALESCE(SUM(t2.bagian_rs), 0) AS total_material_radiologi,
        COALESCE(SUM(t2.tarif_tindakan_dokter), 0) AS total_dokter_radiologi,
        COALESCE(SUM(t2.tarif_tindakan_petugas), 0) AS total_petugas_radiologi,
        COALESCE(SUM(t2.menejemen), 0) AS total_menejemen_radiologi,
        COALESCE(SUM(t2.total_byr), 0) AS total_radiologi
    FROM permintaan_radiologi t1
    JOIN permintaan_pemeriksaan_radiologi t3 ON t1.noorder = t3.noorder
    JOIN jns_perawatan_radiologi t2 ON t3.kd_jenis_prw = t2.kd_jenis_prw
    WHERE t1.status = 'ranap'
    GROUP BY t1.no_rawat
) rd ON rd.no_rawat = b.no_rawat
LEFT JOIN (
    SELECT
        no_rawat,
        (
            IFNULL(SUM(biayaoperator1),0) + IFNULL(SUM(biayaoperator2),0) + IFNULL(SUM(biayaoperator3),0) +
            IFNULL(SUM(biayaasisten_operator1),0) + IFNULL(SUM(biayaasisten_operator2),0) + IFNULL(SUM(biayaasisten_operator3),0) +
            IFNULL(SUM(biayainstrumen),0) + IFNULL(SUM(biayadokter_anak),0) + IFNULL(SUM(biayaperawaat_resusitas),0) +
            IFNULL(SUM(biayadokter_anestesi),0) + IFNULL(SUM(biayaasisten_anestesi),0) + IFNULL(SUM(biayaasisten_anestesi2),0) +
            IFNULL(SUM(biayabidan),0) + IFNULL(SUM(biayabidan2),0) + IFNULL(SUM(biayabidan3),0) +
            IFNULL(SUM(biayaperawat_luar),0) + IFNULL(SUM(biaya_omloop),0) + IFNULL(SUM(biaya_omloop2),0) +
            IFNULL(SUM(biaya_omloop3),0) + IFNULL(SUM(biaya_omloop4),0) + IFNULL(SUM(biaya_omloop5),0) +
            IFNULL(SUM(biaya_dokter_pjanak),0) + IFNULL(SUM(biaya_dokter_umum),0) +
            IFNULL(SUM(biayaalat),0) + IFNULL(SUM(biayasewaok),0) +
            IFNULL(SUM(operasi.akomodasi),0) + IFNULL(SUM(operasi.bagian_rs),0) + IFNULL(SUM(biayasarpras),0)
        ) AS total_operasi
    FROM operasi
    WHERE status = 'Ranap'
    GROUP BY no_rawat
) op ON op.no_rawat = b.no_rawat
GROUP BY b.nama_gedung
ORDER BY b.nama_gedung ASC";

$result = mysqli_query($koneksi, $query);
if (!$result) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "metadata" => [
            "title" => "Query Gagal",
            "message" => mysqli_error($koneksi),
            "code" => 500
        ]
    ]);
    mysqli_close($koneksi);
    exit;
}

$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    foreach ($row as $key => $value) {
        if ($key !== 'nama_gedung') $row[$key] = (float) $value;
    }

    $row['total_jasa_farmasi'] = ($row['jumlah_resep_racikan'] * 25000)
        + ($row['jumlah_resep_non_racikan'] * 15000)
        + ($row['jumlah_resep_operasi'] * 35000);

    $row['grand_total'] =
        $row['total_biaya_kamar'] +
        $row['total_tindakan'] +
        $row['total_obat'] +
        // $row['total_jasa_farmasi'] +
        $row['total_lab'] +
        $row['total_radiologi'] +
        $row['total_operasi'];

    $data[] = $row;
}

echo json_encode([
    "success" => true,
    "periode" => $periode_label,
    "data" => $data
]);

mysqli_close($koneksi);
