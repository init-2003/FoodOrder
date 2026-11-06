<?php
declare(strict_types=1);
// Development server router; prevents serving internal configuration and sources.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
if (preg_match('~(?:^|/)\.|\.(?:log|md)$|^/(?:config|includes|tests|tools|vendor|storage|database)(?:/|$)|^/(?:composer\.(?:json|lock)|package(?:-lock)?\.json|router\.php)$~i', $path)) {
    http_response_code(404);
    exit('Not found');
}
return false;
