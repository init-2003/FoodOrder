<?php
declare(strict_types=1);
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
if (preg_match('~(?:^|/)\.|\.(?:log|md)$|^/(?:app|config|routes|includes|tests|tools|vendor|storage|database)(?:/|$)|^/(?:composer\.(?:json|lock)|package(?:-lock)?\.json|router\.php)$~i', $path)) {
    http_response_code(404); exit('Not found');
}
$public = realpath(dirname(__DIR__) . '/public');
$file = realpath($public . $path);
if (preg_match('~^/(?:assets/.+\.(?:css|js|json|png|jpe?g|svg|ttf|woff2?|txt)|manifest\.php|sw\.js|offline\.html)$~i', $path) && $file !== false && str_starts_with($file, $public . DIRECTORY_SEPARATOR) && is_file($file)) return false;
$_SERVER['SCRIPT_NAME'] = '/index.php';
require dirname(__DIR__) . '/index.php';
return true;
