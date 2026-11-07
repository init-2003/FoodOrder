<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/request_origin.php';

beforeEach(function () { $this->previousServer = $_SERVER; $this->previousOrigin = getenv('PUBLIC_ORIGIN'); putenv('PUBLIC_ORIGIN=https://testfood.example'); $_SERVER = ['HTTP_HOST' => 'testfood.example', 'REMOTE_ADDR' => '192.0.2.99']; });
afterEach(function () { $_SERVER = $this->previousServer; $this->previousOrigin === false ? putenv('PUBLIC_ORIGIN') : putenv('PUBLIC_ORIGIN=' . $this->previousOrigin); });

it('accepts the configured public https origin regardless of proxy ip', function () {
    expect(isSecureRequest())->toBeTrue()->and(requestOriginMatches('https://testfood.example', 'testfood.example'))->toBeTrue();
    $_SERVER['REMOTE_ADDR'] = '192.0.2.100';
    expect(requestOriginMatches('https://testfood.example', 'testfood.example'))->toBeTrue();
});
it('rejects unrelated origins and plain http for the public host', function () {
    expect(requestOriginMatches('https://evil.example', 'testfood.example'))->toBeFalse()->and(requestOriginMatches('http://testfood.example', 'testfood.example'))->toBeFalse();
});
it('allows direct localhost http for the exact port', function () {
    $_SERVER = ['HTTP_HOST' => 'localhost:8080'];
    expect(isSecureRequest())->toBeFalse()->and(requestOriginMatches('http://localhost:8080', 'localhost:8080'))->toBeTrue()->and(requestOriginMatches('http://localhost:8081', 'localhost:8080'))->toBeFalse();
});
it('does not use spoofed forwarded headers to mark an internal request secure', function () {
    $_SERVER = ['HTTP_HOST' => 'localhost:8080', 'HTTP_X_FORWARDED_PROTO' => 'https'];
    expect(isSecureRequest())->toBeFalse()->and(requestOriginMatches('https://testfood.example', 'localhost:8080'))->toBeFalse();
});
it('rejects an attacker-supplied host and origin when a public origin is configured', function () {
    $_SERVER = ['HTTP_HOST' => 'evil.example'];
    expect(requestOriginMatches('http://evil.example', 'evil.example'))->toBeFalse();
});
it('rejects malformed public origin', function () {
    putenv('PUBLIC_ORIGIN=https://testfood.example/path');
    expect(isSecureRequest())->toBeFalse()->and(requestOriginMatches('https://testfood.example', 'testfood.example'))->toBeFalse();
});
