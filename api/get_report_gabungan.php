<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once '../config/conf.php';
    $koneksi = bukakoneksi();

    $bulan_awal  = $_POST['bulan_awal'] ?? date('Y-m');
    $bulan_akhir = $_POST['bulan_akhir'] ?? date('Y-m');
    $kd_pj   = $_POST['kd_pj'] ?? '';
    $jenis   = $_POST['jenis'] ?? 'ralan';
    $kd_poli = $_POST['kd_poli'] ?? '';
    $kd_bangsal = $_POST['kd_bangsal'] ?? '';
    $tgl_awal  = $bulan_awal . '-01 00:00:00';
    $tgl_akhir = date("Y-m-t 23:59:59", strtotime($bulan_akhir . '-01'));

    $pj_filter = !empty($kd_pj) ? "AND rp.kd_pj = '" . mysqli_real_escape_string($koneksi, $kd_pj) . "'" : '';

    $poli_filter = '';
    if (!empty($kd_poli)) {
        $poli_vals = explode(',', $kd_poli);
        $poli_escaped = array_map(function ($v) use ($koneksi) {
            return "'" . mysqli_real_escape_string($koneksi, trim($v)) . "'";
        }, $poli_vals);
        $poli_filter = 'AND rp.kd_poli IN (' . implode(',', $poli_escaped) . ')';
    }

    $bangsal_filter = '';
    if (!empty($kd_bangsal)) {
        $bangsal_vals = explode(',', $kd_bangsal);
        $bangsal_escaped = array_map(function ($v) use ($koneksi) {
            return "'" . mysqli_real_escape_string($koneksi, trim($v)) . "'";
        }, $bangsal_vals);
        $bangsal_filter = 'AND km.kd_bangsal IN (' . implode(',', $bangsal_escaped) . ')';
    }

    if ($jenis === 'ranap') {
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

        $query = "SELECT $sqlGedung AS nama_gedung, rp.no_rawat, DATE_FORMAT(ki.tgl_keluar, '%Y-%m') AS bulan
            FROM reg_periksa rp
            INNER JOIN kamar_inap ki ON rp.no_rawat = ki.no_rawat
            INNER JOIN kamar km ON ki.kd_kamar = km.kd_kamar
            INNER JOIN bangsal bs ON km.kd_bangsal = bs.kd_bangsal
            WHERE rp.status_lanjut = 'Ranap'
            AND ki.tgl_keluar BETWEEN '$tgl_awal' AND '$tgl_akhir'
            $pj_filter
            $bangsal_filter
            GROUP BY bulan, nama_gedung, rp.no_rawat";

        $result = mysqli_query($koneksi, $query);
        if (!$result) throw new Exception('Query ranap gagal: ' . mysqli_error($koneksi));

        $gedung_data = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $bulan = $row['bulan'];
            $nama_gedung = $row['nama_gedung'];
            $key = $bulan . '|' . $nama_gedung;
            $no_rawat = mysqli_real_escape_string($koneksi, $row['no_rawat']);

            if (!isset($gedung_data[$key])) {
                $gedung_data[$key] = [
                    'bulan' => $bulan,
                    'nama_unit' => $nama_gedung,
                    'jumlah_kunjungan' => 0,
                    'total_biaya_kamar' => 0,
                    'total_material' => 0,
                    'total_bhp' => 0,
                    'total_tindakan_dr' => 0,
                    'total_tindakan_pr' => 0,
                    'total_kso' => 0,
                    'total_menejemen' => 0,
                    'total_tindakan' => 0,
                    'jumlah_resep_racikan' => 0,
                    'jumlah_resep_non_racikan' => 0,
                    'jumlah_resep_operasi' => 0,
                    'total_obat' => 0,
                    'total_jasa_farmasi' => 0,
                    'total_material_lab' => 0,
                    'total_dokter_lab' => 0,
                    'total_petugas_lab' => 0,
                    'total_menejemen_lab' => 0,
                    'total_lab' => 0,
                    'total_material_radiologi' => 0,
                    'total_dokter_radiologi' => 0,
                    'total_petugas_radiologi' => 0,
                    'total_menejemen_radiologi' => 0,
                    'total_radiologi' => 0,
                    'total_operasi' => 0,
                    'grand_total' => 0
                ];
            }

            $gedung_data[$key]['jumlah_kunjungan']++;

            $kamar_result = mysqli_query($koneksi, "SELECT SUM(lama * trf_kamar) AS total FROM kamar_inap WHERE no_rawat = '$no_rawat'");
            if ($kamar_result && $kr = mysqli_fetch_assoc($kamar_result))
                $gedung_data[$key]['total_biaya_kamar'] += floatval($kr['total'] ?? 0);

            $ti = mysqli_query($koneksi, "SELECT
                SUM(jns.material) AS m, SUM(jns.bhp) AS b, SUM(jns.tarif_tindakandr) AS dr,
                SUM(jns.tarif_tindakanpr) AS pr, SUM(jns.kso) AS k, SUM(jns.menejemen) AS mn,
                SUM(jns.total_byrdrpr) AS tot
                FROM rawat_inap_drpr drpr JOIN jns_perawatan_inap jns ON drpr.kd_jenis_prw=jns.kd_jenis_prw
                WHERE drpr.no_rawat = '$no_rawat'");
            if ($ti && $d = mysqli_fetch_assoc($ti)) {
                $g = &$gedung_data[$key];
                $g['total_material'] += floatval($d['m'] ?? 0);
                $g['total_bhp'] += floatval($d['b'] ?? 0);
                $g['total_tindakan_dr'] += floatval($d['dr'] ?? 0);
                $g['total_tindakan_pr'] += floatval($d['pr'] ?? 0);
                $g['total_kso'] += floatval($d['k'] ?? 0);
                $g['total_menejemen'] += floatval($d['mn'] ?? 0);
                $g['total_tindakan'] += floatval($d['tot'] ?? 0);
            }

            $resep_result = mysqli_query($koneksi, "SELECT no_resep FROM resep_obat WHERE no_rawat = '$no_rawat' AND tgl_perawatan != '0000-00-00' AND status = 'ranap'");
            if ($resep_result) while ($rs = mysqli_fetch_assoc($resep_result)) {
                $nr = mysqli_real_escape_string($koneksi, $rs['no_resep']);
                if (substr($rs['no_resep'], 0, 2) === 'OK') {
                    $gedung_data[$key]['jumlah_resep_operasi']++;
                } else {
                    $cr = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS ada FROM resep_dokter_racikan WHERE no_resep='$nr' LIMIT 1"));
                    $cn = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS ada FROM resep_dokter WHERE no_resep='$nr' LIMIT 1"));
                    if ($cr && $cr['ada'] > 0) $gedung_data[$key]['jumlah_resep_racikan']++;
                    elseif ($cn && $cn['ada'] > 0) $gedung_data[$key]['jumlah_resep_non_racikan']++;
                }
            }
            $gedung_data[$key]['total_jasa_farmasi'] =
                $gedung_data[$key]['jumlah_resep_racikan'] * 25000 +
                $gedung_data[$key]['jumlah_resep_non_racikan'] * 15000 +
                $gedung_data[$key]['jumlah_resep_operasi'] * 35000;

            $ob = mysqli_query($koneksi, "SELECT SUM(IFNULL(total,0)) AS tot FROM detail_pemberian_obat WHERE no_rawat='$no_rawat'");
            if ($ob && $od = mysqli_fetch_assoc($ob)) $gedung_data[$key]['total_obat'] += floatval($od['tot'] ?? 0);

            $lb = mysqli_query($koneksi, "SELECT
                SUM(IFNULL(jns.bagian_rs,0)) AS m, SUM(IFNULL(jns.tarif_tindakan_dokter,0)) AS dr,
                SUM(IFNULL(jns.tarif_tindakan_petugas,0)) AS pr, SUM(IFNULL(jns.menejemen,0)) AS mn,
                SUM(IFNULL(jns.total_byr,0)) AS tot
                FROM periksa_lab pl JOIN jns_perawatan_lab jns ON pl.kd_jenis_prw=jns.kd_jenis_prw WHERE pl.no_rawat='$no_rawat'");
            if ($lb && $ld = mysqli_fetch_assoc($lb)) {
                $gedung_data[$key]['total_material_lab'] += floatval($ld['m'] ?? 0);
                $gedung_data[$key]['total_dokter_lab'] += floatval($ld['dr'] ?? 0);
                $gedung_data[$key]['total_petugas_lab'] += floatval($ld['pr'] ?? 0);
                $gedung_data[$key]['total_menejemen_lab'] += floatval($ld['mn'] ?? 0);
                $gedung_data[$key]['total_lab'] += floatval($ld['tot'] ?? 0);
            }

            $rd = mysqli_query($koneksi, "SELECT
                COALESCE(SUM(t2.bagian_rs),0) AS m, COALESCE(SUM(t2.tarif_tindakan_dokter),0) AS dr,
                COALESCE(SUM(t2.tarif_tindakan_petugas),0) AS pr, COALESCE(SUM(t2.menejemen),0) AS mn,
                COALESCE(SUM(t2.total_byr),0) AS tot
                FROM periksa_radiologi t1
                JOIN permintaan_pemeriksaan_radiologi t3 ON t1.noorder=t3.noorder
                JOIN jns_perawatan_radiologi t2 ON t3.kd_jenis_prw=t2.kd_jenis_prw
                WHERE t1.no_rawat='$no_rawat' AND t1.status='ranap'");
            if ($rd && $rrd = mysqli_fetch_assoc($rd)) {
                $gedung_data[$key]['total_material_radiologi'] += floatval($rrd['m'] ?? 0);
                $gedung_data[$key]['total_dokter_radiologi'] += floatval($rrd['dr'] ?? 0);
                $gedung_data[$key]['total_petugas_radiologi'] += floatval($rrd['pr'] ?? 0);
                $gedung_data[$key]['total_menejemen_radiologi'] += floatval($rrd['mn'] ?? 0);
                $gedung_data[$key]['total_radiologi'] += floatval($rrd['tot'] ?? 0);
            }

            $op = mysqli_query($koneksi, "SELECT
                (IFNULL(SUM(biayaoperator1),0)+IFNULL(SUM(biayaoperator2),0)+IFNULL(SUM(biayaoperator3),0)+
                 IFNULL(SUM(biayaasisten_operator1),0)+IFNULL(SUM(biayaasisten_operator2),0)+IFNULL(SUM(biayaasisten_operator3),0)+
                 IFNULL(SUM(biayainstrumen),0)+IFNULL(SUM(biayadokter_anak),0)+IFNULL(SUM(biayaperawaat_resusitas),0)+
                 IFNULL(SUM(biayadokter_anestesi),0)+IFNULL(SUM(biayaasisten_anestesi),0)+IFNULL(SUM(biayaasisten_anestesi2),0)+
                 IFNULL(SUM(biayabidan),0)+IFNULL(SUM(biayabidan2),0)+IFNULL(SUM(biayabidan3),0)+
                 IFNULL(SUM(biayaperawat_luar),0)+IFNULL(SUM(biaya_omloop),0)+IFNULL(SUM(biaya_omloop2),0)+
                 IFNULL(SUM(biaya_omloop3),0)+IFNULL(SUM(biaya_omloop4),0)+IFNULL(SUM(biaya_omloop5),0)+
                 IFNULL(SUM(biaya_dokter_pjanak),0)+IFNULL(SUM(biaya_dokter_umum),0)+
                 IFNULL(SUM(biayaalat),0)+IFNULL(SUM(biayasewaok),0)+
                 IFNULL(SUM(operasi.akomodasi),0)+IFNULL(SUM(operasi.bagian_rs),0)+IFNULL(SUM(biayasarpras),0)
                ) AS tot FROM operasi WHERE no_rawat='$no_rawat' AND operasi.status='Ranap'");
            if ($op && $odp = mysqli_fetch_assoc($op)) $gedung_data[$key]['total_operasi'] += floatval($odp['tot'] ?? 0);
        }

        foreach ($gedung_data as $k => $v) {
            $gedung_data[$k]['grand_total'] = $v['total_biaya_kamar'] + $v['total_tindakan'] + $v['total_obat'] + $v['total_lab'] + $v['total_radiologi'] + $v['total_operasi'];
        }
        $data = array_values($gedung_data);
    } else {
        $where = "WHERE rp.status_lanjut = 'Ralan'
            AND CONCAT(rp.tgl_registrasi,' ',rp.jam_reg) BETWEEN '$tgl_awal' AND '$tgl_akhir'
            AND NOT (rp.kd_poli = 'IGDK' AND EXISTS (SELECT 1 FROM kamar_inap ki WHERE ki.no_rawat = rp.no_rawat))
            $pj_filter
            $poli_filter";

        $query = "SELECT
            DATE_FORMAT(rp.tgl_registrasi, '%Y-%m') AS bulan,
            pl.kd_poli, pl.nm_poli,
            COUNT(DISTINCT rp.no_rawat) AS jumlah_kunjungan,
            IFNULL(SUM(jns_perawatan.material),0) AS total_material_tindakan,
            IFNULL(SUM(jns_perawatan.bhp),0) AS total_bhp_tindakan,
            IFNULL(SUM(jns_perawatan.tarif_tindakandr),0) AS total_dokter_tindakan,
            IFNULL(SUM(jns_perawatan.tarif_tindakanpr),0) AS total_perawat_tindakan,
            IFNULL(SUM(jns_perawatan.kso),0) AS total_kso_tindakan,
            IFNULL(SUM(jns_perawatan.menejemen),0) AS total_menejemen_tindakan,
            IFNULL(SUM(jns_perawatan.total_byrdrpr),0) AS total_tindakan,
            (SELECT IFNULL(SUM(dpo.total),0) FROM detail_pemberian_obat dpo JOIN reg_periksa rp2 ON dpo.no_rawat=rp2.no_rawat WHERE dpo.status='Ralan' AND rp2.kd_poli=pl.kd_poli AND DATE_FORMAT(rp2.tgl_registrasi, '%Y-%m') = DATE_FORMAT(rp.tgl_registrasi, '%Y-%m') $pj_filter) AS total_obat,
            (SELECT COUNT(DISTINCT ro.no_rawat) FROM resep_obat ro JOIN reg_periksa rp2 ON ro.no_rawat=rp2.no_rawat WHERE ro.status='ralan' AND rp2.kd_poli=pl.kd_poli AND DATE_FORMAT(rp2.tgl_registrasi, '%Y-%m') = DATE_FORMAT(rp.tgl_registrasi, '%Y-%m') $pj_filter) AS jumlah_pasien_resep,
            (SELECT IFNULL(SUM(pl.biaya),0) FROM periksa_lab pl JOIN reg_periksa rp2 ON pl.no_rawat=rp2.no_rawat WHERE pl.status='Ralan' AND rp2.kd_poli=pl.kd_poli AND DATE_FORMAT(rp2.tgl_registrasi, '%Y-%m') = DATE_FORMAT(rp.tgl_registrasi, '%Y-%m') $pj_filter) AS total_lab,
            (SELECT IFNULL(SUM(jns.total_byr),0) FROM permintaan_radiologi pm JOIN permintaan_pemeriksaan_radiologi pp ON pm.noorder=pp.noorder JOIN jns_perawatan_radiologi jns ON pp.kd_jenis_prw=jns.kd_jenis_prw JOIN reg_periksa rp2 ON pm.no_rawat=rp2.no_rawat WHERE pm.status='Ralan' AND rp2.kd_poli=pl.kd_poli AND DATE_FORMAT(rp2.tgl_registrasi, '%Y-%m') = DATE_FORMAT(rp.tgl_registrasi, '%Y-%m') $pj_filter) AS total_radiologi
            FROM reg_periksa rp
            JOIN poliklinik pl ON rp.kd_poli = pl.kd_poli
            LEFT JOIN rawat_jl_drpr ON rawat_jl_drpr.no_rawat = rp.no_rawat
            LEFT JOIN jns_perawatan ON rawat_jl_drpr.kd_jenis_prw = jns_perawatan.kd_jenis_prw
            $where
            GROUP BY bulan, pl.kd_poli, pl.nm_poli
            ORDER BY bulan, pl.nm_poli ASC";

        $result = mysqli_query($koneksi, $query);
        if (!$result) throw new Exception('Query ralan gagal: ' . mysqli_error($koneksi));

        $data = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $row['jenis_rawat'] = 'Rajal';
            $row['total_obat_ppn'] = $row['total_obat'] * 1.11;
            $row['jasa_farmasi'] = (int)$row['jumlah_pasien_resep'] * 15000;
            $row['grand_total'] = $row['total_tindakan'] + $row['total_obat_ppn'] + $row['jasa_farmasi'] + $row['total_lab'] + $row['total_radiologi'];
            $row['nama_unit'] = $row['nm_poli'];
            $data[] = $row;
        }

        $query_ranap = "SELECT
            DATE_FORMAT(ki.tgl_keluar, '%Y-%m') AS bulan,
            pl.kd_poli, pl.nm_poli, rp.no_rawat
            FROM reg_periksa rp
            JOIN poliklinik pl ON rp.kd_poli = pl.kd_poli
            INNER JOIN kamar_inap ki ON rp.no_rawat = ki.no_rawat
            WHERE rp.status_lanjut = 'Ranap'
            AND ki.tgl_keluar BETWEEN '$tgl_awal' AND '$tgl_akhir'
            $pj_filter
            $poli_filter
            GROUP BY bulan, pl.kd_poli, pl.nm_poli, rp.no_rawat
            ORDER BY pl.nm_poli, bulan";

        $result_ranap = mysqli_query($koneksi, $query_ranap);
        if (!$result_ranap) throw new Exception('Query ranap per poli gagal: ' . mysqli_error($koneksi));

        $poli_data = [];

        while ($row = mysqli_fetch_assoc($result_ranap)) {
            $bulan = $row['bulan'];
            $nm_poli = $row['nm_poli'];
            $key = $bulan . '|' . $nm_poli;
            $no_rawat = mysqli_real_escape_string($koneksi, $row['no_rawat']);

            if (!isset($poli_data[$key])) {
                $poli_data[$key] = [
                    'bulan' => $bulan,
                    'nama_unit' => $nm_poli,
                    'jumlah_kunjungan' => 0,
                    'total_tindakan' => 0,
                    'total_obat' => 0,
                    'jumlah_pasien_resep' => 0,
                    'total_lab' => 0,
                    'total_radiologi' => 0,
                ];
            }

            $poli_data[$key]['jumlah_kunjungan']++;

            $ti = mysqli_query($koneksi, "SELECT IFNULL(SUM(jns.total_byrdrpr),0) AS tot FROM rawat_inap_drpr drpr JOIN jns_perawatan_inap jns ON drpr.kd_jenis_prw=jns.kd_jenis_prw WHERE drpr.no_rawat='$no_rawat'");
            if ($ti && $d = mysqli_fetch_assoc($ti)) $poli_data[$key]['total_tindakan'] += floatval($d['tot']);

            $ob = mysqli_query($koneksi, "SELECT IFNULL(SUM(total),0) AS tot FROM detail_pemberian_obat WHERE no_rawat='$no_rawat' AND status='Ranap'");
            if ($ob && $od = mysqli_fetch_assoc($ob)) $poli_data[$key]['total_obat'] += floatval($od['tot']);

            $rs = mysqli_query($koneksi, "SELECT COUNT(DISTINCT no_rawat) AS cnt FROM resep_obat WHERE no_rawat='$no_rawat' AND status='ranap'");
            if ($rs && $rd = mysqli_fetch_assoc($rs)) $poli_data[$key]['jumlah_pasien_resep'] += intval($rd['cnt']);

            $lb = mysqli_query($koneksi, "SELECT IFNULL(SUM(biaya),0) AS tot FROM periksa_lab WHERE no_rawat='$no_rawat' AND status='Ranap'");
            if ($lb && $ld = mysqli_fetch_assoc($lb)) $poli_data[$key]['total_lab'] += floatval($ld['tot']);

            $rd2 = mysqli_query($koneksi, "SELECT COALESCE(SUM(t2.total_byr),0) AS tot FROM permintaan_radiologi t1 JOIN permintaan_pemeriksaan_radiologi t3 ON t1.noorder=t3.noorder JOIN jns_perawatan_radiologi t2 ON t3.kd_jenis_prw=t2.kd_jenis_prw WHERE t1.no_rawat='$no_rawat' AND t1.status='ranap'");
            if ($rd2 && $rrd = mysqli_fetch_assoc($rd2)) $poli_data[$key]['total_radiologi'] += floatval($rrd['tot']);
        }

        foreach ($data as $rajal) {
            $key = $rajal['bulan'] . '|' . $rajal['nama_unit'];
            if (!isset($poli_data[$key])) {
                $poli_data[$key] = [
                    'bulan' => $rajal['bulan'],
                    'nama_unit' => $rajal['nama_unit'],
                    'jumlah_kunjungan' => 0,
                    'total_tindakan' => 0,
                    'total_obat' => 0,
                    'jumlah_pasien_resep' => 0,
                    'total_lab' => 0,
                    'total_radiologi' => 0,
                ];
            }
        }

        foreach ($poli_data as $row) {
            $row['jenis_rawat'] = 'Ranap';
            $row['total_obat_ppn'] = $row['total_obat'] * 1.11;
            $row['jasa_farmasi'] = (int)$row['jumlah_pasien_resep'] * 15000;
            $row['grand_total'] = $row['total_tindakan'] + $row['total_obat_ppn'] + $row['jasa_farmasi'] + $row['total_lab'] + $row['total_radiologi'];
            $data[] = $row;
        }
    }

    $summary = [
        'total_kunjungan' => 0,
        'total_tindakan' => 0,
        'total_obat' => 0,
        'total_lab' => 0,
        'total_radiologi' => 0,
        'total_farmasi' => 0,
        'total_operasi' => 0,
        'total_biaya_kamar' => 0,
        'grand_total' => 0
    ];

    foreach ($data as $d) {
        $summary['total_kunjungan'] += intval($d['jumlah_kunjungan'] ?? 0);
        $summary['total_tindakan'] += floatval($d['total_tindakan'] ?? 0);
        $summary['total_obat'] += floatval($d['total_obat'] ?? 0);
        $summary['total_lab'] += floatval($d['total_lab'] ?? 0);
        $summary['total_radiologi'] += floatval($d['total_radiologi'] ?? 0);
        $summary['total_farmasi'] += floatval($d['total_jasa_farmasi'] ?? 0);
        $summary['total_operasi'] += floatval($d['total_operasi'] ?? 0);
        $summary['total_biaya_kamar'] += floatval($d['total_biaya_kamar'] ?? 0);
        $summary['grand_total'] += floatval($d['grand_total'] ?? 0);
    }

    $periode_awal = date('F Y', strtotime($tgl_awal));
    $periode_akhir = date('F Y', strtotime($tgl_akhir));
    $periode = $periode_awal === $periode_akhir ? $periode_awal : "$periode_awal - $periode_akhir";

    echo json_encode([
        'success' => true,
        'periode' => $periode,
        'jenis' => $jenis,
        'data' => $data,
        'summary' => $summary
    ]);

    mysqli_close($koneksi);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
