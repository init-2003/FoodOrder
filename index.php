<?php
declare(strict_types=1);
require __DIR__ . '/app/Http/RouteResolver.php';
$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
$publicBase = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
if ($publicBase === '.') $publicBase = '';
$publicBase .= '/';
$resolved = resolvePublicRoute((string)($_SERVER['REQUEST_URI'] ?? '/'), $scriptName);
if (isset($resolved['redirect'])) { header('Location: ' . $resolved['redirect'], true, 301); exit; }
$route = $resolved['route'];
if ($route === null) { http_response_code(404); exit('Not found'); }
if (isset($resolved['bill'])) $_GET['bill'] = $resolved['bill'];
if (isset($resolved['billHash'])) $_GET['billHash'] = $resolved['billHash'];
$routes = require __DIR__ . '/routes/web.php';
$apiRoutes = require __DIR__ . '/routes/api.php';
$definition = $routes[$route] ?? $apiRoutes[$route] ?? null;
if ($definition === null) { http_response_code(404); exit('Not found'); }
if (isset($apiRoutes[$route]) && $definition['controller'] !== 'product-image') {
    require __DIR__ . '/app/Middlewares/request-security.php';
}
require __DIR__ . '/app/Controllers/' . $definition['controller'] . '.php';
