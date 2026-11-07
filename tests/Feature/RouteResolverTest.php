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
