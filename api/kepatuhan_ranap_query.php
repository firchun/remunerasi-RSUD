<?php
// Query builder bersama untuk menu Kepatuhan Rawat Inap
// (dipakai oleh api/get_data_kepatuhan_ranap.php dan api/export_kepatuhan_ranap.php)

function kepatuhan_ranap_jenis_list()
{
    return [
        'cara_bayar' => 'Cara Bayar Tidak Sesuai',
        'lama_minus' => 'Lama Perawatan Minus',
        'tanggal' => 'Tanggal Keluar Sebelum Masuk',
        'tanpa_sep' => 'BPJS Tanpa SEP',
        'resume' => 'Belum Ada Resume',
    ];
}

function kepatuhan_ranap_jenis_union()
{
    return 'SELECT \'cara_bayar\' AS kode, \'Cara Bayar Tidak Sesuai\' AS jenis'
        . ' UNION ALL SELECT \'lama_minus\', \'Lama Perawatan Minus\''
        . ' UNION ALL SELECT \'tanggal\', \'Tanggal Keluar Sebelum Masuk\''
        . ' UNION ALL SELECT \'tanpa_sep\', \'BPJS Tanpa SEP\''
        . ' UNION ALL SELECT \'resume\', \'Belum Ada Resume\'';
}

/**
 * Bangun klausa FROM..WHERE untuk data pelanggaran.
 * Satu baris = satu pelanggaran (join dengan daftar jenis pelanggaran).
 */
function kepatuhan_ranap_from($koneksi, $tgl_awal, $tgl_akhir, $kd_bangsal = '', $status_pulang = 'semua', $jenis_pelanggaran = 'semua', $search = '')
{
    $jenis_list = kepatuhan_ranap_jenis_list();
    $jenis_union = kepatuhan_ranap_jenis_union();
    $esc = fn($v) => mysqli_real_escape_string($koneksi, $v);

    $ada_pulang = "EXISTS (
        SELECT 1 FROM kamar_inap kx
        WHERE kx.no_rawat = rp.no_rawat
          AND YEAR(kx.tgl_masuk) = YEAR(ki.tgl_masuk)
          AND MONTH(kx.tgl_masuk) = MONTH(ki.tgl_masuk)
          AND IFNULL(kx.stts_pulang, '-') NOT IN ('-', 'Pindah Kamar')
    )";

    $v_cara_bayar = "(bs.no_rawat IS NOT NULL AND rp.kd_pj != 'BPJ')";
    $v_lama = "(ki.lama < 0)";
    $v_tanggal = "(ki.tgl_keluar IS NOT NULL AND ki.tgl_keluar != '0000-00-00' AND ki.tgl_keluar < ki.tgl_masuk)";
    $v_tanpa_sep = "(rp.kd_pj = 'BPJ' AND bs.no_rawat IS NULL)";
    $v_resume = "(rpr.no_rawat IS NULL AND $ada_pulang)";

    $from = "
        FROM reg_periksa rp
        JOIN (
            SELECT kr.*,
                ROW_NUMBER() OVER (
                    PARTITION BY kr.no_rawat, YEAR(kr.tgl_masuk), MONTH(kr.tgl_masuk)
                    ORDER BY (kr.lama < 0 OR (kr.tgl_keluar IS NOT NULL AND kr.tgl_keluar != '0000-00-00' AND kr.tgl_keluar < kr.tgl_masuk)) DESC,
                             kr.tgl_masuk DESC, kr.jam_masuk DESC
                ) AS rn
            FROM kamar_inap kr
            WHERE kr.tgl_masuk BETWEEN '$tgl_awal' AND '$tgl_akhir'
        ) ki ON ki.rn = 1 AND ki.no_rawat = rp.no_rawat
        JOIN kamar k ON ki.kd_kamar = k.kd_kamar
        JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
        JOIN pasien ps ON rp.no_rkm_medis = ps.no_rkm_medis
        LEFT JOIN penjab pj ON rp.kd_pj = pj.kd_pj
        LEFT JOIN (
            SELECT no_rawat, MAX(no_sep) AS no_sep
            FROM bridging_sep
            WHERE no_sep IS NOT NULL AND no_sep != '' AND no_sep != '-'
            GROUP BY no_rawat
        ) bs ON bs.no_rawat = rp.no_rawat
        LEFT JOIN resume_pasien_ranap rpr ON rpr.no_rawat = rp.no_rawat
        CROSS JOIN ($jenis_union) jt
        WHERE rp.status_lanjut = 'Ranap'
          AND (
               (jt.kode = 'cara_bayar' AND $v_cara_bayar)
            OR (jt.kode = 'lama_minus' AND $v_lama)
            OR (jt.kode = 'tanggal' AND $v_tanggal)
            OR (jt.kode = 'tanpa_sep' AND $v_tanpa_sep)
            OR (jt.kode = 'resume' AND $v_resume)
          )
    ";

    if (!empty($kd_bangsal)) {
        $from .= " AND b.kd_bangsal = '" . $esc($kd_bangsal) . "'";
    }

    if ($status_pulang === 'belum_pulang') {
        $from .= " AND NOT ($ada_pulang)";
    } elseif ($status_pulang === 'sudah_pulang') {
        $from .= " AND ($ada_pulang)";
    }

    if ($jenis_pelanggaran !== 'semua' && isset($jenis_list[$jenis_pelanggaran])) {
        $from .= " AND jt.kode = '" . $esc($jenis_pelanggaran) . "'";
    }

    if (!empty($search)) {
        $s = $esc($search);
        $from .= " AND (
            ki.no_rawat LIKE '%$s%'
            OR ps.nm_pasien LIKE '%$s%'
            OR rp.no_rkm_medis LIKE '%$s%'
            OR b.nm_bangsal LIKE '%$s%'
            OR bs.no_sep LIKE '%$s%'
            OR pj.png_jawab LIKE '%$s%'
            OR jt.jenis LIKE '%$s%'
        )";
    }

    return $from;
}

function kepatuhan_ranap_select()
{
    return "
        SELECT
            ki.no_rawat,
            rp.no_rkm_medis,
            ps.nm_pasien,
            rp.kd_pj,
            pj.png_jawab,
            bs.no_sep,
            b.nm_bangsal,
            b.kd_bangsal,
            ki.kd_kamar,
            k.kelas,
            ki.tgl_masuk,
            ki.jam_masuk,
            ki.tgl_keluar,
            ki.jam_keluar,
            ki.lama,
            ki.stts_pulang,
            jt.kode AS jenis_kode,
            jt.jenis AS jenis_pelanggaran
    ";
}

function kepatuhan_ranap_keterangan($row)
{
    switch ($row['jenis_kode'] ?? '') {
        case 'cara_bayar':
            return 'Ada SEP tapi cara bayar ' . $row['kd_pj'] . ' (' . ($row['png_jawab'] ?: '-') . ')';
        case 'lama_minus':
            return 'Lama perawatan ' . $row['lama'] . ' hari';
        case 'tanggal':
            return 'Masuk ' . $row['tgl_masuk'] . ', keluar ' . $row['tgl_keluar'];
        case 'tanpa_sep':
            return 'Cara bayar BPJS, data SEP tidak ditemukan';
        case 'resume':
            return 'Status pulang ' . ($row['stts_pulang'] ?: '-') . ', resume pasien ranap belum diisi';
        default:
            return '';
    }
}
