<?php

$envFile = __DIR__ . '/../.env';

if (!file_exists($envFile)) {
    die('.env file not found. Salin .env-contoh ke .env dan sesuaikan konfigurasi.');
}

$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#')) continue;

    $parts = explode('=', $line, 2);
    if (count($parts) !== 2) continue;

    $key = trim($parts[0]);
    $value = trim($parts[1]);

    $value = trim($value, '"\'');
    $value = match (strtolower($value)) {
        'true' => true,
        'false' => false,
        'null' => null,
        default => $value,
    };

    $_ENV[$key] = $value;
    putenv("$key=$value");
}
