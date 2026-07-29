<?php
require_once '../config/conf.php';
$koneksi = bukakoneksi();

$jenis = $_POST['jenis'] ?? '';
$tahun = $_POST['tahun'] ?? date('Y');
$bulan = $_POST['bulan'] ?? '';

$bulan_nama = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

if ($jenis === 'per_poli') {
    $label_title = 'Poliklinik';
    $label_select = 'pl.nm_poli AS label';
    $label_join = 'LEFT JOIN poliklinik pl ON rp.kd_poli = pl.kd_poli';
    $label_group = 'rp.kd_poli, pl.nm_poli';
    $where_type = "AND rp.status_lanjut = 'Ralan' AND NOT (rp.kd_poli = 'IGDK' AND EXISTS (SELECT 1 FROM kamar_inap ki WHERE ki.no_rawat = rp.no_rawat))";
    $date_col = 'rp.tgl_registrasi';
} elseif ($jenis === 'per_ruangan') {
    $label_title = 'Ruangan';
    $label_select = 'vbg.grup_bangsal AS label';
    $label_join = 'JOIN kamar_inap ki ON rp.no_rawat = ki.no_rawat JOIN kamar km ON ki.kd_kamar = km.kd_kamar JOIN bangsal bs ON km.kd_bangsal = bs.kd_bangsal JOIN v_bangsal_grup vbg ON bs.kd_bangsal = vbg.kd_bangsal';
    $label_group = 'vbg.grup_bangsal';
    $where_type = "AND rp.status_lanjut = 'Ranap' AND vbg.grup_bangsal IS NOT NULL AND (ki.stts_pulang IS NULL OR ki.stts_pulang != 'Pindah Kamar')";
    $date_col = 'ki.tgl_masuk';
} elseif ($jenis === 'per_dokter') {
    $label_title = 'Dokter';
    $label_select = "COALESCE(d.nm_dokter, 'Tidak Diketahui') AS label";
    $label_join = 'LEFT JOIN dokter d ON rp.kd_dokter = d.kd_dokter';
    $label_group = 'rp.kd_dokter, d.nm_dokter';
    $where_type = "AND rp.status_lanjut IN ('Ralan','Ranap')";
    $date_col = 'rp.tgl_registrasi';
} else {
    echo json_encode(['success' => false, 'message' => 'Jenis rekap tidak valid']);
    exit;
}

$where_date = "AND YEAR($date_col) = '$tahun'";
if (!empty($bulan)) {
    $bulan_pad = str_pad($bulan, 2, '0', STR_PAD_LEFT);
    $where_date .= " AND MONTH($date_col) = '$bulan_pad'";
    $periode_text = $bulan_nama[(int)$bulan] . ' ' . $tahun;
} else {
    $periode_text = 'Tahun ' . $tahun;
}

$columns = [];
$headerRows = [];
$flat_data = [];

if ($jenis === 'per_dokter') {
    if (empty($bulan)) {
        $row1 = [['title' => 'Dokter', 'rowspan' => 2]];
        $row2 = [];
        $columns[] = ['data' => 'label', 'title' => 'Dokter', 'className' => 'text-left font-medium'];

        for ($m = 1; $m <= 12; $m++) {
            $row1[] = ['title' => $bulan_nama[$m], 'colspan' => 2];
            $row2[] = ['title' => 'Rajal'];
            $row2[] = ['title' => 'Ranap'];
            $columns[] = ['data' => 'm' . $m . '_ralan', 'title' => 'Rajal', 'className' => 'text-right'];
            $columns[] = ['data' => 'm' . $m . '_ranap', 'title' => 'Ranap', 'className' => 'text-right'];
        }

        $row1[] = ['title' => 'Total', 'colspan' => 2];
        $row2[] = ['title' => 'Rajal'];
        $row2[] = ['title' => 'Ranap'];
        $columns[] = ['data' => 'total_ralan', 'title' => 'Rajal', 'className' => 'text-right font-bold'];
        $columns[] = ['data' => 'total_ranap', 'title' => 'Ranap', 'className' => 'text-right font-bold'];

        $headerRows = [$row1, $row2];

        $select_months = [];
        for ($m = 1; $m <= 12; $m++) {
            $select_months[] = "COUNT(DISTINCT CASE WHEN MONTH(rp.tgl_registrasi) = $m AND rp.status_lanjut = 'Ralan' THEN rp.no_rawat END) AS m{$m}_ralan";
            $select_months[] = "COUNT(DISTINCT CASE WHEN MONTH(rp.tgl_registrasi) = $m AND rp.status_lanjut = 'Ranap' THEN rp.no_rawat END) AS m{$m}_ranap";
        }
        $select_months_str = implode(", ", $select_months);

        $sql = "SELECT COALESCE(d.nm_dokter, 'Tidak Diketahui') AS label,
                       $select_months_str,
                       COUNT(DISTINCT CASE WHEN rp.status_lanjut = 'Ralan' THEN rp.no_rawat END) AS total_ralan,
                       COUNT(DISTINCT CASE WHEN rp.status_lanjut = 'Ranap' THEN rp.no_rawat END) AS total_ranap
                FROM reg_periksa rp
                LEFT JOIN dokter d ON rp.kd_dokter = d.kd_dokter
                WHERE 1=1 $where_type $where_date
                GROUP BY rp.kd_dokter, d.nm_dokter
                ORDER BY label";
        $result = mysqli_query($koneksi, $sql);
        while ($row = mysqli_fetch_assoc($result)) {
            $rowData = ['label' => $row['label'] ?? 'Tidak Diketahui'];
            for ($m = 1; $m <= 12; $m++) {
                $rowData['m' . $m . '_ralan'] = (int)$row['m' . $m . '_ralan'];
                $rowData['m' . $m . '_ranap'] = (int)$row['m' . $m . '_ranap'];
            }
            $rowData['total_ralan'] = (int)$row['total_ralan'];
            $rowData['total_ranap'] = (int)$row['total_ranap'];
            $flat_data[] = $rowData;
        }
    } else {
        $m_int = (int)$bulan;
        $monthName = $bulan_nama[$m_int];

        $row1 = [
            ['title' => 'Dokter', 'rowspan' => 2],
            ['title' => $monthName, 'colspan' => 2],
            ['title' => 'Total', 'colspan' => 2]
        ];
        $row2 = [
            ['title' => 'Rajal'], ['title' => 'Ranap'],
            ['title' => 'Rajal'], ['title' => 'Ranap']
        ];
        $headerRows = [$row1, $row2];

        $columns = [
            ['data' => 'label', 'title' => 'Dokter', 'className' => 'text-left font-medium'],
            ['data' => 'ralan', 'title' => 'Rajal', 'className' => 'text-right'],
            ['data' => 'ranap', 'title' => 'Ranap', 'className' => 'text-right'],
            ['data' => 'total_ralan', 'title' => 'Rajal', 'className' => 'text-right font-bold'],
            ['data' => 'total_ranap', 'title' => 'Ranap', 'className' => 'text-right font-bold']
        ];

        $sql = "SELECT COALESCE(d.nm_dokter, 'Tidak Diketahui') AS label,
                       COUNT(DISTINCT CASE WHEN rp.status_lanjut = 'Ralan' THEN rp.no_rawat END) AS ralan,
                       COUNT(DISTINCT CASE WHEN rp.status_lanjut = 'Ranap' THEN rp.no_rawat END) AS ranap
                FROM reg_periksa rp
                LEFT JOIN dokter d ON rp.kd_dokter = d.kd_dokter
                WHERE 1=1 $where_type $where_date
                GROUP BY rp.kd_dokter, d.nm_dokter
                ORDER BY label";
        $result = mysqli_query($koneksi, $sql);
        while ($row = mysqli_fetch_assoc($result)) {
            $ralan = (int)$row['ralan'];
            $ranap = (int)$row['ranap'];
            $flat_data[] = [
                'label' => $row['label'] ?? 'Tidak Diketahui',
                'ralan' => $ralan,
                'ranap' => $ranap,
                'total_ralan' => $ralan,
                'total_ranap' => $ranap
            ];
        }
    }
} elseif (in_array($jenis, ['per_poli', 'per_ruangan']) && empty($bulan)) {
    // PIVOT MODE FOR ALL MONTHS
    $columns[] = ['data' => 'label', 'title' => $label_title, 'className' => 'text-left font-medium'];
    for ($m = 1; $m <= 12; $m++) {
        $columns[] = ['data' => 'm' . $m, 'title' => $bulan_nama[$m], 'className' => 'text-right'];
    }
    $columns[] = ['data' => 'total', 'title' => 'Total', 'className' => 'text-right font-bold'];

    $select_months = [];
    for ($m = 1; $m <= 12; $m++) {
        $select_months[] = "COUNT(DISTINCT CASE WHEN MONTH($date_col) = $m THEN rp.no_rawat END) AS m$m";
    }
    $select_months_str = implode(", ", $select_months);

    $sql = "SELECT $label_select, $select_months_str, COUNT(DISTINCT rp.no_rawat) AS total
            FROM reg_periksa rp $label_join
            WHERE 1=1 $where_type $where_date
            GROUP BY $label_group
            ORDER BY label";
    $result = mysqli_query($koneksi, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $rowData = ['label' => $row['label'] ?? 'Tidak Diketahui'];
        for ($m = 1; $m <= 12; $m++) {
            $rowData['m' . $m] = (int)$row['m' . $m];
        }
        $rowData['total'] = (int)$row['total'];
        $flat_data[] = $rowData;
    }
} else {
    // SINGLE MONTH MODE
    $columns[] = ['data' => 'label', 'title' => $label_title, 'className' => 'text-left font-medium'];
    $columns[] = ['data' => 'total', 'title' => 'Total', 'className' => 'text-right font-bold'];

    $sql = "SELECT $label_select, COUNT(DISTINCT rp.no_rawat) AS total
            FROM reg_periksa rp $label_join
            WHERE 1=1 $where_type $where_date
            GROUP BY $label_group
            ORDER BY label";
    $result = mysqli_query($koneksi, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $flat_data[] = [
            'label' => $row['label'] ?? 'Tidak Diketahui',
            'total' => (int)$row['total']
        ];
    }
}

echo json_encode([
    'success' => true,
    'jenis' => $jenis,
    'periode' => $periode_text,
    'columns' => $columns,
    'headerRows' => $headerRows,
    'data' => $flat_data
]);

mysqli_close($koneksi);
