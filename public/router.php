<?php

// Router wbudowanego serwera PHP.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// /d/<nazwa> -> ekran pełnoekranowy
if (preg_match('#^/d(/[A-Za-z0-9_-]*)?/?$#', $path)) {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/display.html');
    return true;
}

// /raport -> raport energii (z zachowaniem parametrów)
if (preg_match('#^/raport/?$#', $path)) {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/raport.html');
    return true;
}

// /admin, /admin/, /admin/cokolwiek -> panel konfiguracji
if (preg_match('#^/admin(/.*)?$#', $path)) {
    header('Location: /admin.php', true, 302);
    return true;
}

return false;
