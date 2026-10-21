<?php
// Query builder bersama untuk menu Rekap Kunjungan Pasien Ralan
// (dipakai oleh api/get_rekap_kunjungan_pasien_ralan.php dan api/export_rekap_kunjungan_pasien_ralan.php)

function rekap_kunjungan_ralan_tahun($tahun)
{
    $tahun = (int)$tahun;
    if ($tahun < 2015 || $tahun > 2100) {
        $tahun = (int)date('Y');
    }
    return $tahun;
}

function rekap_kunjungan_ralan_bulan_nama()
{
    return [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];
}

function rekap_kunjungan_ralan_sql($tahun)
{
    $tahun = rekap_kunjungan_ralan_tahun($tahun);
    $tahun_berikutnya = $tahun + 1;

    return "
WITH kunjungan AS (
    SELECT
        rp.no_rkm_medis,
        rp.no_rawat,
        rp.tgl_registrasi,
        rp.kd_poli,
        rp.kd_dokter,
        rp.stts,
        LAG(rp.tgl_registrasi) OVER (
            PARTITION BY rp.no_rkm_medis
            ORDER BY rp.tgl_registrasi, rp.no_rawat
        ) AS kunjungan_sebelumnya
    FROM reg_periksa rp
    WHERE rp.kd_poli != 'IGDK'
      AND rp.status_lanjut = 'ralan'
      AND rp.kd_pj = 'BPJ'
      AND rp.tgl_registrasi >= '$tahun-01-01'
      AND rp.tgl_registrasi < '$tahun_berikutnya-01-01'
),
rekap AS (
    SELECT
        YEAR(tgl_registrasi) AS tahun,
        MONTH(tgl_registrasi) AS bulan,
        no_rkm_medis,
        kd_poli,
        kd_dokter,
        COUNT(*) AS jumlah_kunjungan,
        SUM(
            CASE
                WHEN stts = 'Batal' THEN 1
                ELSE 0
            END
        ) AS batal_kunjungan,
        SUM(
            CASE
                WHEN kunjungan_sebelumnya IS NOT NULL
                 AND DATEDIFF(
                     tgl_registrasi,
                     kunjungan_sebelumnya
                 ) >= 7
                THEN 1
                ELSE 0
            END
        ) AS kunjungan_perminggu,
        SUM(COUNT(*)) OVER (
            PARTITION BY no_rkm_medis, YEAR(tgl_registrasi), MONTH(tgl_registrasi)
        ) AS total_kunjungan_bulan
    FROM kunjungan
    GROUP BY
        YEAR(tgl_registrasi),
        MONTH(tgl_registrasi),
        no_rkm_medis,
        kd_poli,
        kd_dokter
)
SELECT
    r.tahun,
    r.bulan,
    r.no_rkm_medis,
    ps.nm_pasien,
    d.nm_dokter,
    r.jumlah_kunjungan,
    r.batal_kunjungan,
    r.kunjungan_perminggu,
    r.kd_poli,
    p.nm_poli
FROM rekap r
JOIN pasien ps
    ON ps.no_rkm_medis = r.no_rkm_medis
JOIN dokter d
    ON d.kd_dokter = r.kd_dokter
JOIN poliklinik p
    ON p.kd_poli = r.kd_poli
WHERE r.total_kunjungan_bulan > 2
ORDER BY
    r.tahun,
    r.bulan,
    r.jumlah_kunjungan DESC";
}

function rekap_kunjungan_ralan_rows($koneksi, $tahun)
{
    $rows = [];
    $result = mysqli_query($koneksi, rekap_kunjungan_ralan_sql($tahun));
    if (!$result) {
        return $rows;
    }
    while ($r = mysqli_fetch_assoc($result)) {
        $r['tahun'] = (int)$r['tahun'];
        $r['bulan'] = (int)$r['bulan'];
        $r['jumlah_kunjungan'] = (int)$r['jumlah_kunjungan'];
        $r['batal_kunjungan'] = (int)$r['batal_kunjungan'];
        $r['kunjungan_perminggu'] = (int)$r['kunjungan_perminggu'];
        $r['data_lebih'] = max(0, $r['jumlah_kunjungan'] - 2);
        $rows[] = $r;
    }
    return $rows;
}
