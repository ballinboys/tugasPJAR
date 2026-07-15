<?php

/**
 * Dev-only router for PHP's built-in server (`php -S host:port router.php`).
 *
 * Under Apache/XAMPP, mod_rewrite serves files in public/ directly and only
 * routes missing paths to index.php. The built-in server has no such rule, so
 * without this shim every request (including /assets/*.css) is handed to the
 * Zend front controller and comes back as HTML. Here we serve real files as-is
 * and delegate everything else to index.php.
 */

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $path;

if ($path !== '/' && is_file($file)) {
    return false; // let the built-in server stream the static file with its own MIME type
}

require __DIR__ . '/index.php';
