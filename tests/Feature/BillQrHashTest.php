<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/functions.php';

it('matches the Pascal SHA256 alphabet conversion for a bill number', function () {
    expect(getHashToAlphabet('68005645'))->toBe('cacbmbnlmplajbmonkncdaojeecnajblcacpamljaenlmaeopngjdannfpmfnido');
});

it('preserves leading zeroes and returns 64 characters from a to p', function () {
    $value = getHashToAlphabet('00123');
    expect($value)->toHaveLength(64)->toMatch('/^[a-p]{64}$/')->not->toBe(getHashToAlphabet('123'));
});
