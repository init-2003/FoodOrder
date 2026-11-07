<?php
declare(strict_types=1);

function requestFromTrustedProxy(): bool {
    $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $trusted = trim((string)(getenv('TRUSTED_PROXY_IP') ?: ''));
    $remoteBinary = @inet_pton($remote);
    $trustedBinary = @inet_pton($trusted);
    return $remoteBinary !== false && $trustedBinary !== false && hash_equals($trustedBinary, $remoteBinary);
}

function validPublicOrigin(): ?array {
    $value = trim((string)(getenv('PUBLIC_ORIGIN') ?: ''));
    $parts = parse_url($value);
    if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || !in_array($parts['path'] ?? '', ['', '/'], true)) return null;
    return $parts;
}

function isTrustedForwardedHttps(): bool {
    return requestFromTrustedProxy() && validPublicOrigin() !== null
        && strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))) === 'https';
}

function isSecureRequest(): bool {
    return (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') || isTrustedForwardedHttps();
}

function requestOriginMatches(string $originValue, string $hostValue): bool {
    $origin = parse_url($originValue);
    if (!is_array($origin) || !in_array($origin['scheme'] ?? '', ['http', 'https'], true) || empty($origin['host']) || isset($origin['user']) || isset($origin['pass']) || isset($origin['query']) || isset($origin['fragment']) || !in_array($origin['path'] ?? '', ['', '/'], true)) return false;
    $secure = isSecureRequest();
    $expected = isTrustedForwardedHttps() ? validPublicOrigin() : parse_url(($secure ? 'https://' : 'http://') . $hostValue);
    if (!is_array($expected) || empty($expected['host'])) return false;
    $scheme = $secure ? 'https' : 'http';
    $originPort = $origin['port'] ?? ($origin['scheme'] === 'https' ? 443 : 80);
    $expectedPort = $expected['port'] ?? ($scheme === 'https' ? 443 : 80);
    return $origin['scheme'] === $scheme && strtolower($origin['host']) === strtolower($expected['host']) && $originPort === $expectedPort;
}
