<?php
require_once '../config/conf.php';

// Koneksi DB2
$konektor2 = bukakoneksi2();

// Cek apakah file diupload
if (!isset($_FILES['file'])) {
  die("File tidak ditemukan");
}

$file = $_FILES['file']['tmp_name'];

if (!file_exists($file)) {
  die("File tidak dapat dibaca");
}

// Buka file CSV
$handle = fopen($file, "r");

if (!$handle) {
  die("Gagal membuka file");
}

$baris = 0;

while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {

  // Lewati header
  if ($baris == 0) {
    $baris++;
    continue;
  }

  // Pastikan CSV memiliki 2 kolom
  if (count($data) < 2) {
    continue; // Lewati baris rusak
  }

  // Ambil data CSV
  $no_sep = mysqli_real_escape_string($konektor2, $data[0]);
  $total_bpjs = mysqli_real_escape_string($konektor2, $data[1]);

  // Query insert
  $sql = "INSERT INTO inacbd (no_sep, total_bpjs)
            VALUES ('$no_sep', '$total_bpjs')";

  mysqli_query($konektor2, $sql);
}

fclose($handle);
mysqli_close($konektor2);

header("Location: /rajal/index.php");
exit;