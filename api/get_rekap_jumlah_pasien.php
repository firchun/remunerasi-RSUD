<?php
require_once '../config/conf.php';
$koneksi = bukakoneksi();

$tahun = $_POST['tahun'] ?? date('Y');
$kd_pj_filter = $_POST['kd_pj'] ?? '';

$where_pj = '';
if (!empty($kd_pj_filter)) {
    $where_pj = " AND rp.kd_pj = '" . mysqli_real_escape_string($koneksi, $kd_pj_filter) . "'";
}

// Get all active payment types
$pj_query = "SELECT kd_pj, png_jawab FROM penjab WHERE status = '1' ORDER BY kd_pj";
$pj_result = mysqli_query($koneksi, $pj_query);
$payment_types = [];
while ($row = mysqli_fetch_assoc($pj_result)) {
    $payment_types[] = $row;
}

// Get all active polis
$poli_query = "SELECT kd_poli, nm_poli FROM poliklinik WHERE status = '1' ORDER BY nm_poli";
$poli_result = mysqli_query($koneksi, $poli_query);
$polis = [];
while ($row = mysqli_fetch_assoc($poli_result)) {
    $polis[] = $row;
}

// Get all room groups from v_bangsal_grup
$room_query = "SELECT DISTINCT grup_bangsal FROM v_bangsal_grup WHERE grup_bangsal IS NOT NULL ORDER BY grup_bangsal";
$room_result = mysqli_query($koneksi, $room_query);
$rooms = [];
while ($row = mysqli_fetch_assoc($room_result)) {
    $rooms[] = $row['grup_bangsal'];
}

// Ralan counts per month per poli per kd_pj
$ralan_sql = "
SELECT MONTH(rp.tgl_registrasi) AS bulan, rp.kd_poli, rp.kd_pj, COUNT(DISTINCT rp.no_rawat) AS jumlah
FROM reg_periksa rp
WHERE rp.status_lanjut = 'Ralan'
  AND YEAR(rp.tgl_registrasi) = '$tahun'
  AND NOT (
    rp.kd_poli = 'IGDK'
    AND EXISTS (SELECT 1 FROM kamar_inap ki WHERE ki.no_rawat = rp.no_rawat)
  )
  $where_pj
GROUP BY MONTH(rp.tgl_registrasi), rp.kd_poli, rp.kd_pj
";
$ralan_result = mysqli_query($koneksi, $ralan_sql);
$ralan_data = [];
while ($row = mysqli_fetch_assoc($ralan_result)) {
    $ralan_data[$row['bulan']][$row['kd_poli']][$row['kd_pj']] = (int)$row['jumlah'];
}

// Ranap counts per month per room group per kd_pj
$ranap_sql = "
SELECT MONTH(ki.tgl_masuk) AS bulan, vbg.grup_bangsal, rp.kd_pj, COUNT(DISTINCT rp.no_rawat) AS jumlah
FROM reg_periksa rp
JOIN kamar_inap ki ON rp.no_rawat = ki.no_rawat
JOIN kamar km ON ki.kd_kamar = km.kd_kamar
JOIN bangsal bs ON km.kd_bangsal = bs.kd_bangsal
JOIN v_bangsal_grup vbg ON bs.kd_bangsal = vbg.kd_bangsal
WHERE rp.status_lanjut = 'Ranap'
  AND YEAR(ki.tgl_masuk) = '$tahun'
  AND vbg.grup_bangsal IS NOT NULL
  AND (ki.stts_pulang IS NULL OR ki.stts_pulang != 'Pindah Kamar')
  $where_pj
GROUP BY MONTH(ki.tgl_masuk), vbg.grup_bangsal, rp.kd_pj
";
$ranap_result = mysqli_query($koneksi, $ranap_sql);
$ranap_data = [];
while ($row = mysqli_fetch_assoc($ranap_result)) {
    $ranap_data[$row['bulan']][$row['grup_bangsal']][$row['kd_pj']] = (int)$row['jumlah'];
}

// Initialize empty payment counts structure
function initPaymentCounts($payment_types) {
    $counts = [];
    foreach ($payment_types as $pt) {
        $counts[$pt['kd_pj']] = 0;
    }
    return $counts;
}

// Sum payment counts
function sumPaymentCounts($counts) {
    return array_sum($counts);
}

// Build flat data rows
$bulan_nama = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$flat_data = [];

for ($b = 1; $b <= 12; $b++) {
    $has_content = false;

    // --- RAWAT JALAN ---
    $total_ralan_payments = initPaymentCounts($payment_types);
    $ralan_has_data = false;

    foreach ($polis as $poli) {
        // Get payment counts for this poli/month
        $poli_payments = initPaymentCounts($payment_types);
        if (isset($ralan_data[$b][$poli['kd_poli']])) {
            foreach ($ralan_data[$b][$poli['kd_poli']] as $kdpj => $jml) {
                if (isset($poli_payments[$kdpj])) {
                    $poli_payments[$kdpj] = $jml;
                    $total_ralan_payments[$kdpj] += $jml;
                }
            }
        }
        $poli_total = sumPaymentCounts($poli_payments);

        $row = ['bulan' => '', 'pelayanan' => $poli['nm_poli'], 'rowType' => 'detail'];
        foreach ($payment_types as $pt) {
            $row['pt_' . $pt['kd_pj']] = $poli_payments[$pt['kd_pj']];
        }
        $row['total'] = $poli_total;
        $ralan_rows[] = $row;

        if ($poli_total > 0) $ralan_has_data = true;
    }

    if ($ralan_has_data) {
        $subtotal_row = ['bulan' => $b === 1 ? $bulan_nama[$b] : $bulan_nama[$b], 'pelayanan' => 'RAWAT JALAN', 'rowType' => 'subtotal-ralan'];
        foreach ($payment_types as $pt) {
            $subtotal_row['pt_' . $pt['kd_pj']] = $total_ralan_payments[$pt['kd_pj']];
        }
        $subtotal_row['total'] = sumPaymentCounts($total_ralan_payments);
        $flat_data[] = $subtotal_row;
        $flat_data = array_merge($flat_data, $ralan_rows);
        $has_content = true;
    }
    $ralan_rows = [];

    // --- RAWAT INAP ---
    $total_ranap_payments = initPaymentCounts($payment_types);
    $ranap_has_data = false;
    $ranap_rows = [];

    foreach ($rooms as $room) {
        $room_payments = initPaymentCounts($payment_types);
        if (isset($ranap_data[$b][$room])) {
            foreach ($ranap_data[$b][$room] as $kdpj => $jml) {
                if (isset($room_payments[$kdpj])) {
                    $room_payments[$kdpj] = $jml;
                    $total_ranap_payments[$kdpj] += $jml;
                }
            }
        }
        $room_total = sumPaymentCounts($room_payments);

        $row = ['bulan' => '', 'pelayanan' => $room, 'rowType' => 'detail'];
        foreach ($payment_types as $pt) {
            $row['pt_' . $pt['kd_pj']] = $room_payments[$pt['kd_pj']];
        }
        $row['total'] = $room_total;
        $ranap_rows[] = $row;

        if ($room_total > 0) $ranap_has_data = true;
    }

    if ($ranap_has_data) {
        $subtotal_row = ['bulan' => '', 'pelayanan' => 'RAWAT INAP', 'rowType' => 'subtotal-ranap'];
        foreach ($payment_types as $pt) {
            $subtotal_row['pt_' . $pt['kd_pj']] = $total_ranap_payments[$pt['kd_pj']];
        }
        $subtotal_row['total'] = sumPaymentCounts($total_ranap_payments);
        $flat_data[] = $subtotal_row;
        $flat_data = array_merge($flat_data, $ranap_rows);
        $has_content = true;
    }

    // --- TOTAL ---
    if ($has_content) {
        $grand_total_payments = [];
        foreach ($payment_types as $pt) {
            $grand_total_payments[$pt['kd_pj']] = $total_ralan_payments[$pt['kd_pj']] + $total_ranap_payments[$pt['kd_pj']];
        }
        $total_row = ['bulan' => '', 'pelayanan' => 'TOTAL', 'rowType' => 'grand-total'];
        foreach ($payment_types as $pt) {
            $total_row['pt_' . $pt['kd_pj']] = $grand_total_payments[$pt['kd_pj']];
        }
        $total_row['total'] = sumPaymentCounts($grand_total_payments);
        $flat_data[] = $total_row;
    }
}

echo json_encode([
    'success' => true,
    'periode' => $tahun,
    'payment_types' => $payment_types,
    'data' => $flat_data
]);

mysqli_close($koneksi);
