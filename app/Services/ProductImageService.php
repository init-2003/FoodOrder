<?php
declare(strict_types=1);

function isProductImageFilename(string $file): bool
{
    return $file !== '' && strlen($file) <= 500
        && preg_match('/\A[^\\\\\/:*?"<>|\x00-\x1F]+\.jpg\z/iuD', $file) === 1
        && !str_contains($file, '..');
}

function findProductImage(string $file, string $directory): ?string
{
    if (!isProductImageFilename($file)) return null;
    $path = $directory . '/' . $file;
    return !is_link($path) && is_file($path) && is_readable($path) ? $path : null;
}
