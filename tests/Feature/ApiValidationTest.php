<?php
declare(strict_types=1);

it('requires a username and password to sign in', function () {
    $r = runApi('auth.php', 'POST');
    expect($r['status'])->toBe(400)->and($r['json']['error']['code'])->toBe('MISSING_CREDENTIALS');
});

it('does not accept GET when requesting login', function () {
    $r = runApi('auth.php', 'GET', ['action' => 'login']);
    expect($r['status'])->toBe(405)->and($r['json']['error']['code'])->toBe('METHOD_NOT_ALLOWED');
});

it('rejects cross-origin state changing requests', function () {
    $r = runApi('auth.php', 'POST', server: ['HTTP_ORIGIN' => 'https://other.example', 'HTTP_HOST' => 'restaurant.example']);
    expect($r['status'])->toBe(403)->and($r['json']['error']['code'])->toBe('INVALID_ORIGIN');
});

it('requires POST for logout', function () {
    $r = runApi('auth.php', query: ['action' => 'logout']);
    expect($r['status'])->toBe(405)->and($r['json']['error']['code'])->toBe('METHOD_NOT_ALLOWED');
});

it('requires staff to confirm pending orders', function () {
    $r = runApi('confirm-order.php', 'POST');
    expect($r['status'])->toBe(401)->and($r['json']['error']['code'])->toBe('UNAUTHORIZED');
});

it('requires staff authentication to save expenses', function () {
    expect(runApi('expense.php', 'POST')['status'])->toBe(401);
});

it('requires staff login to list or open tables', function (string $endpoint) {
    $r = runApi($endpoint, $endpoint === 'open-table.php' ? 'POST' : 'GET');
    expect($r['status'])->toBe(401)->and($r['json']['error']['code'])->toBe('UNAUTHORIZED');
})->with(['tables.php', 'open-table.php']);

it('rejects missing or incorrect CSRF tokens on state changing endpoints', function (string $endpoint, string $csrf) {
    $r = runApi($endpoint, 'POST', csrf: $csrf);
    expect($r['status'])->toBe(403)->and($r['json']['error']['code'])->toBe('INVALID_CSRF');
})->with(['auth.php', 'open-table.php', 'cart.php', 'order.php', 'confirm-order.php', 'food-image.php', 'expense.php'])->with(['missing', 'invalid']);

it('does not allow opening tables with GET', function () {
    $r = runApi('open-table.php');
    expect($r['status'])->toBe(405)->and($r['json']['error']['code'])->toBe('METHOD_NOT_ALLOWED');
});

it('requires a table or a bill number', function (string $endpoint) {
    $r = runApi($endpoint);
    expect($r['status'])->toBe(400)->and($r['json']['error']['code'])->toBe('MISSING_TABLE_OR_BILL');
})->with(['bill.php', 'order-history.php']);

it('requires a configured branch rather than defaulting to 001', function (string $endpoint) {
    $r = runApi($endpoint, branch: '');
    expect($r['status'])->toBe(400)->and($r['json']['error']['code'])->toBe('MISSING_BRANCH');
})->with(['menu.php', 'bill.php', 'order-history.php']);

it('does not return sample menu data when the database is unavailable', function () {
    $r = runApi('menu.php');
    expect($r['status'])->toBe(503)->and($r['json']['error']['code'])->toBe('DATABASE_UNAVAILABLE')->and($r['json'])->not->toHaveKey('items');
});

it('requires POST for cart and orders', function (string $endpoint) {
    $r = runApi($endpoint);
    expect($r['status'])->toBe(405)->and($r['json']['error']['code'])->toBe('METHOD_NOT_ALLOWED');
})->with(['cart.php', 'order.php', 'confirm-order.php']);
