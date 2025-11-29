<?php
require_once 'conf.php';

session_start();
$koneksi = bukakoneksi2();

$username = mysqli_real_escape_string($koneksi, $_POST['username']);
$password = mysqli_real_escape_string($koneksi, $_POST['password']);

// Enkripsi MD5
$md5_pass = md5($password);

// Cek user
$q = mysqli_query(
  $koneksi,
  "SELECT * FROM users WHERE username='$username' LIMIT 1"
);

if (mysqli_num_rows($q) == 0) {
  header("Location: ../login.php?error=Username tidak ditemukan");
  exit;
}

$user = mysqli_fetch_assoc($q);

// VERIFIKASI MENGGUNAKAN MD5
if ($md5_pass !== $user['password']) {
  header("Location: ../login.php?error=Password salah");
  exit;
}

// Simpan session
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];

header("Location: ../index.php");
exit;