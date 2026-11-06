<?php
declare(strict_types=1);

function createLoginLimiter(string $clientIp, string $cacheDirectory): \Symfony\Component\RateLimiter\LimiterInterface {
    if (!is_dir($cacheDirectory) && !@mkdir($cacheDirectory, 0770, true) && !is_dir($cacheDirectory)) {
        throw new RuntimeException('Cannot create login rate-limit directory');
    }
    $cache = new \Symfony\Component\Cache\Adapter\FilesystemAdapter('login', 0, $cacheDirectory);
    $storage = new \Symfony\Component\RateLimiter\Storage\CacheStorage($cache);
    $locks = new \Symfony\Component\Lock\LockFactory(new \Symfony\Component\Lock\Store\FlockStore($cacheDirectory));
    $factory = new \Symfony\Component\RateLimiter\RateLimiterFactory(['id' => 'staff_login', 'policy' => 'sliding_window', 'limit' => 20, 'interval' => '5 minutes'], $storage, $locks);
    return $factory->create(hash('sha256', $clientIp));
}
