<?php
/**
 * Zinkly Social Lite - only for PHP's built-in test server:  php -S localhost:8080 router.php
 * (Apache uses .htaccess and nginx its own rules; they never use this file.)
 */
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (preg_match('~^/(app|data)(/|$)|/\.~', $path) || preg_match('~^/uploads/.*\.(php|phtml|phar)$~i', $path)
    || (preg_match('~^/extensions/~', $path) && !preg_match('~\.(css|js|png|jpe?g|gif|webp|svg|ico|woff2?|ttf|mp3|mp4|webm)$~i', $path))) {
    http_response_code(403);
    exit('Forbidden');
}
if ($path !== '/' && is_file(__DIR__ . $path) && !str_ends_with($path, '.php')) {
    return false; // real files (css, js, images) are served as they are
}
if (str_starts_with($path, '/install')) {
    $_SERVER['SCRIPT_NAME'] = '/install/index.php';
    require __DIR__ . '/install/index.php';
    return true;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/index.php';
