<?php
declare(strict_types=1);

/** Only accept forwarded addresses from explicitly configured, trusted proxies. */
function clientIp(array $server, string $trustedProxies): ?string
{
    $remote = trim((string)($server['REMOTE_ADDR'] ?? ''));
    if (!filter_var($remote, FILTER_VALIDATE_IP)) return null;
    $trusted = array_filter(array_map('trim', explode(',', $trustedProxies)), static fn($ip) => filter_var($ip, FILTER_VALIDATE_IP) !== false);
    $isTrusted = static function (string $ip) use ($trusted): bool {
        $address = @inet_pton($ip);
        foreach ($trusted as $proxy) if ($address !== false && $address === @inet_pton($proxy)) return true;
        return false;
    };
    if (!$isTrusted($remote)) return $remote;
    $forwarded = trim((string)($server['HTTP_X_FORWARDED_FOR'] ?? ''));
    if ($forwarded === '') return $remote;
    $chain = array_map('trim', explode(',', $forwarded));
    if (count($chain) > 32) return $remote;
    foreach ($chain as $ip) if (!filter_var($ip, FILTER_VALIDATE_IP)) return $remote;
    $chain[] = $remote;
    for ($i = count($chain) - 1; $i > 0 && $isTrusted($chain[$i]); $i--) {}
    return $chain[$i];
}
