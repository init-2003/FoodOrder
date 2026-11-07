<?php
declare(strict_types=1);

function validPublicOrigin(): ?array {
    $parts = parse_url(trim((string)(getenv('PUBLIC_ORIGIN') ?: '')));
    if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || !in_array($parts['path'] ?? '', ['', '/'], true)) return null;
    return $parts;
}

function isPublicOriginHost(): bool {
    $public = validPublicOrigin();
    $host = parse_url('http://' . (string)($_SERVER['HTTP_HOST'] ?? ''));
    if (!$public || !is_array($host) || empty($host['host'])) return false;
    return strcasecmp($public['host'], $host['host']) === 0 && (int)($public['port'] ?? 443) === (int)($host['port'] ?? 443);
}

function isSecureRequest(): bool {
    return (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') || isPublicOriginHost();
}

function requestOriginMatches(string $originValue, string $hostValue): bool {
    $origin = parse_url($originValue);
    if (!is_array($origin) || !in_array($origin['scheme'] ?? '', ['http', 'https'], true) || empty($origin['host']) || isset($origin['user']) || isset($origin['pass']) || isset($origin['query']) || isset($origin['fragment']) || !in_array($origin['path'] ?? '', ['', '/'], true)) return false;
    $secure = isSecureRequest();
    if (validPublicOrigin() !== null && !isPublicOriginHost()) {
        $directHost = parse_url('http://' . $hostValue, PHP_URL_HOST);
        $isPrivateIp = is_string($directHost) && filter_var($directHost, FILTER_VALIDATE_IP) !== false
            && filter_var($directHost, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
        if (!is_string($directHost) || (strcasecmp($directHost, 'localhost') !== 0 && !$isPrivateIp)) return false;
    }
    $expected = isPublicOriginHost() ? validPublicOrigin() : parse_url(($secure ? 'https://' : 'http://') . $hostValue);
    if (!is_array($expected) || empty($expected['host'])) return false;
    $scheme = $secure ? 'https' : 'http';
    $originPort = $origin['port'] ?? ($origin['scheme'] === 'https' ? 443 : 80);
    $expectedPort = $expected['port'] ?? ($scheme === 'https' ? 443 : 80);
    return $origin['scheme'] === $scheme && strcasecmp($origin['host'], $expected['host']) === 0 && $originPort === $expectedPort;
}
