<?php
declare(strict_types=1);

/** Resolve only known public paths, relative to the front controller's mount point. */
function resolvePublicRoute(string $requestUri, string $scriptName): array
{
    $path = parse_url($requestUri, PHP_URL_PATH);
    if (!is_string($path)) return ['route' => null];
    $scriptName = str_replace('\\', '/', $scriptName);
    $base = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    if ($base === '.') $base = '';
    if ($base !== '') {
        if ($path !== $base && !str_starts_with($path, $base . '/')) return ['route' => null];
        $path = substr($path, strlen($base));
    }
    if ($path === '' || $path === '/' || $path === '/index.php') return ['route' => '/'];
    if ($path === '/staff/food-images' || $path === '/staff/food-images/') return ['route' => null, 'redirect' => $base . '/staff/menus/'];
    if (preg_match('#^/([0-9]{6,10})/?$#D', $path, $m)) return ['route' => '/', 'bill' => $m[1]];
    if (preg_match('#^/staff/tables/([0-9]{6,10})/?$#D', $path, $m)) return ['route' => '/staff/tables/', 'bill' => $m[1]];
    $legacy = ['/staff/index.php' => '/staff/', '/staff/tables/index.php' => '/staff/tables/', '/staff/menus/index.php' => '/staff/menus/', '/staff/food-images/index.php' => '/staff/food-images/'];
    if (isset($legacy[$path])) return ['route' => $legacy[$path]];
    if (preg_match('#^/staff(?:/(?:tables|menus|food-images))?$#D', $path)) {
        return ['route' => null, 'redirect' => $base . $path . '/'];
    }
    if (preg_match('#^/staff/(?:tables|menus|food-images)/$#D', $path) || $path === '/staff/' || preg_match('#^/api/[a-zA-Z0-9-]+\.php$#D', $path)) return ['route' => $path];
    return ['route' => null];
}
