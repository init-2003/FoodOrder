<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Security/login_limiter.php';

it('limits repeated attempts and resets after a successful login', function () {
    $limiter = createLoginLimiter('test-' . bin2hex(random_bytes(16)), sys_get_temp_dir());
    for ($i = 0; $i < 20; $i++) expect($limiter->consume()->isAccepted())->toBeTrue();
    expect($limiter->consume()->isAccepted())->toBeFalse();
    $limiter->reset();
    expect($limiter->consume()->isAccepted())->toBeTrue();
    $limiter->reset();
});
