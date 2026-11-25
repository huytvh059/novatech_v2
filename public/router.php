<?php
declare(strict_types=1);

// Router dành cho PHP built-in development server (`php -S 127.0.0.1:8080 -t public public/router.php`).
// Không dùng cho production (production dùng Nginx trong deploy/nginx-novatech.conf).
$uri = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';

if (str_starts_with($uri, '/atk-assets/')) {
    $rel = substr($uri, strlen('/atk-assets/'));
    $base = realpath(__DIR__ . '/../vendor/atk4/ui/public');
    $target = $base !== false ? realpath($base . '/' . $rel) : false;

    if ($base !== false && $target !== false && str_starts_with($target, $base) && is_file($target)) {
        $ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'css' => 'text/css; charset=utf-8',
            'js' => 'application/javascript; charset=utf-8',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject',
            'map' => 'application/json',
            default => 'application/octet-stream',
        };
        header('Content-Type: ' . $mime);
        readfile($target);
        return true;
    }

    http_response_code(404);
    return true;
}

if ($uri !== '/' && is_file(__DIR__ . $uri)) {
    return false;
}

require __DIR__ . '/index.php';
return true;
