<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/Http/RouteResolver.php';

it('resolves routes at root and inside a subdirectory', function () {
    expect(resolvePublicRoute('/api/order.php', '/index.php')['route'])->toBe('/api/order.php')
        ->and(resolvePublicRoute('/service-ordering/public/api/order.php', '/service-ordering/public/index.php')['route'])->toBe('/api/order.php')
        ->and(resolvePublicRoute('/service-ordering/public/staff/tables/68005642', '/service-ordering/public/index.php'))->toBe(['route' => '/staff/tables/', 'bill' => '68005642'])
        ->and(resolvePublicRoute('/68005642?x=1', '/index.php'))->toBe(['route' => '/', 'bill' => '68005642'])
        ->and(resolvePublicRoute('/service-ordering/publicity/api/order.php', '/service-ordering/public/index.php')['route'])->toBeNull();
});

it('resolves hash links for customers and staff without confusing bill numbers', function () {
    $hash = str_repeat('a', 64);
    expect(resolvePublicRoute('/shop/' . $hash, '/shop/index.php'))->toBe(['route' => '/', 'billHash' => $hash])
        ->and(resolvePublicRoute('/shop/staff/tables/' . $hash, '/shop/index.php'))->toBe(['route' => '/staff/tables/', 'billHash' => $hash])
        ->and(resolvePublicRoute('/shop/' . str_repeat('q', 64), '/shop/index.php')['route'])->toBeNull();
});

it('redirects old staff.php links to the current staff route', function () {
    expect(resolvePublicRoute('/staff.php', '/index.php'))->toBe(['route' => null, 'redirect' => '/staff/'])
        ->and(resolvePublicRoute('/shop/staff.php', '/shop/index.php'))->toBe(['route' => null, 'redirect' => '/shop/staff/']);
});
