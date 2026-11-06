<?php
declare(strict_types=1);

function createLoginLimiter(string $clientIp, string $cacheDirectory): \Symfony\Component\RateLimiter\LimiterInterface {
    $cache = new \Symfony\Component\Cache\Adapter\FilesystemAdapter('login', 0, $cacheDirectory);
    $storage = new \Symfony\Component\RateLimiter\Storage\CacheStorage($cache);
    $locks = new \Symfony\Component\Lock\LockFactory(new \Symfony\Component\Lock\Store\FlockStore());
    $factory = new \Symfony\Component\RateLimiter\RateLimiterFactory(['id' => 'staff_login', 'policy' => 'sliding_window', 'limit' => 20, 'interval' => '5 minutes'], $storage, $locks);
    return $factory->create(hash('sha256', $clientIp));
}
