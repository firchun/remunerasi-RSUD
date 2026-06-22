<?php
require_once 'conf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$koneksi = bukakoneksi();

$username = mysqli_real_escape_string($koneksi, $_POST['username']);
$password = mysqli_real_escape_string($koneksi, $_POST['password']);

// Cek user di tabel admin dengan AES_ENCRYPT
$sql = "SELECT AES_DECRYPT(usere, 'nur') as username_decrypted 
        FROM admin 
        WHERE usere = AES_ENCRYPT('$username','nur') 
        AND passworde = AES_ENCRYPT('$password','windi') 
        LIMIT 1";

$q = mysqli_query($koneksi, $sql);

if (!$q || mysqli_num_rows($q) == 0) {
  header("Location: ../login.php?error=Username atau password salah");
  exit;
}

$user = mysqli_fetch_assoc($q);

// Simpan session
$_SESSION['user_id'] = $username;
$_SESSION['username'] = $username;

header("Location: ../index.php");
exit;