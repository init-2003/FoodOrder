<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/Security/client_ip.php';

it('ignores forwarded addresses from untrusted connections', function () {
    expect(clientIp(['REMOTE_ADDR' => '192.0.2.7', 'HTTP_X_FORWARDED_FOR' => '203.0.113.9'], '::1'))->toBe('192.0.2.7');
    expect(clientIp(['REMOTE_ADDR' => '::1', 'HTTP_X_FORWARDED_FOR' => '203.0.113.9'], ''))->toBe('::1');
});

it('takes the nearest untrusted address from a trusted proxy chain', function () {
    expect(clientIp(['REMOTE_ADDR' => '::1', 'HTTP_X_FORWARDED_FOR' => '198.51.100.10, 203.0.113.9, 192.0.2.2'], '::1,192.0.2.2'))->toBe('203.0.113.9');
    expect(clientIp(['REMOTE_ADDR' => '::1', 'HTTP_X_FORWARDED_FOR' => '198.51.100.10'], '0:0:0:0:0:0:0:1'))->toBe('198.51.100.10');
});

it('falls back to the direct peer when the forwarded chain is invalid', function () {
    expect(clientIp(['REMOTE_ADDR' => '::1', 'HTTP_X_FORWARDED_FOR' => '198.51.100.10, invalid'], '::1'))->toBe('::1');
    expect(clientIp(['REMOTE_ADDR' => 'invalid'], '::1'))->toBeNull();
});
