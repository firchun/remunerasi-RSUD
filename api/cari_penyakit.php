<?php
require_once '../config/conf.php';
$koneksi = bukakoneksi();

$q = $_POST['q'] ?? '';

if (strlen($q) < 2) {
    echo json_encode(['success' => true, 'data' => []]);
    exit;
}

$q = mysqli_real_escape_string($koneksi, $q);

$sql = "SELECT kd_penyakit, nm_penyakit FROM penyakit
WHERE kd_penyakit LIKE '%$q%' OR nm_penyakit LIKE '%$q%'
ORDER BY CASE WHEN kd_penyakit LIKE '$q%' THEN 0 ELSE 1 END, nm_penyakit
LIMIT 20";

$result = mysqli_query($koneksi, $sql);
$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $data[] = $row;
}

echo json_encode(['success' => true, 'data' => $data]);
mysqli_close($koneksi);
