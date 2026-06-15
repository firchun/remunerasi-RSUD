<?php
session_start();
require_once __DIR__ . '/../config/autoload.php';

define('VIEWS_PATH', __DIR__ . '/../views');

$request = $_SERVER['REQUEST_URI'];
$basePath = '/remon';
$path = str_replace($basePath, '', parse_url($request, PHP_URL_PATH));
$path = trim($path, '/');

$routes = require __DIR__ . '/../routes/web.php';

$matched = false;
foreach ($routes as $route => $handler) {
    if ($path === $route) {
        [$controllerName, $method] = $handler;
        if (class_exists($controllerName)) {
            $controller = new $controllerName();
            if (method_exists($controller, $method)) {
                $controller->$method();
                $matched = true;
                break;
            }
        }
    }
}

if (!$matched) {
    http_response_code(404);
    echo '404 - Halaman tidak ditemukan';
}
