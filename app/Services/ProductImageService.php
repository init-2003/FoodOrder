<?php
declare(strict_types=1);

function findProductImage(string $file, string $directory): ?string
{
    if (!preg_match('/\A[a-f0-9]{32}\.jpg\z/iD', $file)) return null;
    $path = $directory . '/' . $file;
    return !is_link($path) && is_file($path) && is_readable($path) ? $path : null;
}
