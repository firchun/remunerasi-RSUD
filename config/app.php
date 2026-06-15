<?php
define('BASE_URL', $_ENV['BASE_URL'] ?? 'http://localhost/remon');
define('APP_NAME', $_ENV['APP_NAME'] ?? 'Remunerasi RSUD MERAUKE');
define('APP_ENV', $_ENV['APP_ENV'] ?? 'development');
define('TIMEZONE', $_ENV['TIMEZONE'] ?? 'Asia/Jayapura');
date_default_timezone_set(TIMEZONE);
