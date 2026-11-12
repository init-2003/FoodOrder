<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/Views/asset-version.php';

it('versions shared assets by their contents and renders versioned fonts', function () {
    $root = dirname(__DIR__, 2);
    $path = 'assets/images/restaurant-logo.svg';
    expect(assetVersion($path))->toBe(hash_file('sha256', $root . '/public/' . $path));
    $publicBase = '/shop/';
    ob_start(); require $root . '/app/Views/browser-head.php'; $head = ob_get_clean();
    expect($head)->toContain('/shop/' . $path . '?v=' . assetVersion($path))
        ->toContain('/shop/assets/fonts/SP_KinKuSi.ttf?v=' . assetVersion('assets/fonts/SP_KinKuSi.ttf'));
});
