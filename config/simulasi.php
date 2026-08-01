<?php
$SIMULASI_DB_PATH = __DIR__ . '/../database/simulasi.sqlite';

function getSimulasiDB() {
    global $SIMULASI_DB_PATH;
    $dir = dirname($SIMULASI_DB_PATH);
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    if (!extension_loaded('pdo_sqlite')) {
        throw new RuntimeException('Driver pdo_sqlite tidak tersedia. Aktifkan extension=pdo_sqlite di php.ini server.');
    }
    $db = new PDO("sqlite:$SIMULASI_DB_PATH");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec("PRAGMA journal_mode=WAL");
    $db->exec("PRAGMA foreign_keys=ON");
    return $db;
}
