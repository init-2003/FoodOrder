<?php
declare(strict_types=1);

it('reports database unavailability instead of authenticating a sample account', function () {
    $r = runApi('auth.php', 'POST', post: ['username' => 'admin', 'password' => 'admin2']);
    expect($r['status'])->toBe(503)->and($r['json']['error']['code'])->toBe('DATABASE_UNAVAILABLE');
});

it('uses a supplied branch for menu requests', function () {
    $r = runApi('menu.php', query: ['Branch_ID' => '002'], branch: '');
    expect($r['status'])->toBe(503)->and($r['json']['error']['code'])->toBe('DATABASE_UNAVAILABLE');
});
