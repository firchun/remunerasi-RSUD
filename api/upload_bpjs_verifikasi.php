<?php
set_time_limit(300);
require_once '../config/conf.php';
header('Content-Type: application/json');

if (!isset($_FILES['file'])) {
    echo json_encode(['success' => false, 'message' => 'File tidak ditemukan']);
    exit;
}

$file = $_FILES['file'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if ($ext !== 'pdf') {
    echo json_encode(['success' => false, 'message' => 'Hanya file PDF yang diizinkan']);
    exit;
}

$tmp_dir = sys_get_temp_dir() ?: __DIR__;
$filename = 'bpjs_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._\-]/', '_', $file['name']);
$dest = $tmp_dir . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan file']);
    exit;
}

$python_script = __DIR__ . '/../scripts/parse_bpjs_pdf.py';
$cmd = escapeshellcmd("python3") . " " . escapeshellarg($python_script) . " " . escapeshellarg($dest) . " 2>&1";
$output = shell_exec($cmd);

$result = json_decode($output, true);

if (!$result || isset($result['error'])) {
    @unlink($dest);
    echo json_encode(['success' => false, 'message' => $result['error'] ?? 'Gagal memproses PDF']);
    exit;
}

@unlink($dest);

echo json_encode([
    'success' => true,
    'filename' => $file['name'],
    'total' => $result['total'],
    'rows' => $result['rows']
]);
