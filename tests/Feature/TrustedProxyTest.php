<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/request_origin.php';

beforeEach(function () { $this->previousServer = $_SERVER; putenv('TRUSTED_PROXY_IP=192.0.2.10'); putenv('PUBLIC_ORIGIN=https://testfood.example'); $_SERVER = ['REMOTE_ADDR' => '192.0.2.10', 'HTTP_X_FORWARDED_PROTO' => 'https']; });
afterEach(function () { $_SERVER = $this->previousServer; putenv('TRUSTED_PROXY_IP'); putenv('PUBLIC_ORIGIN'); });

it('accepts the configured external origin through the trusted proxy', function () {
    expect(isSecureRequest())->toBeTrue()->and(requestOriginMatches('https://testfood.example', 'localhost:8086'))->toBeTrue();
});

it('rejects a different origin even through the trusted proxy', function () {
    expect(requestOriginMatches('https://evil.example', 'localhost:8086'))->toBeFalse();
});

it('ignores spoofed forwarded protocol from other addresses', function () {
    $_SERVER['REMOTE_ADDR'] = '192.0.2.99';
    expect(isSecureRequest())->toBeFalse()->and(requestOriginMatches('https://testfood.example', 'testfood.example'))->toBeFalse();
});

it('allows direct http only for the exact host and port', function () {
    $_SERVER = ['REMOTE_ADDR' => '127.0.0.1'];
    expect(requestOriginMatches('http://localhost:8080', 'localhost:8080'))->toBeTrue()
        ->and(requestOriginMatches('http://localhost:8081', 'localhost:8080'))->toBeFalse();
});

it('does not trust forwarded protocol without configured external origin', function () {
    putenv('PUBLIC_ORIGIN');
    expect(isSecureRequest())->toBeFalse()->and(requestOriginMatches('https://testfood.example', 'testfood.example'))->toBeFalse();
});
