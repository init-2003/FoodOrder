<?php
declare(strict_types=1);
require_once __DIR__ . '/ProductImageService.php';

/** Convert uploaded image paths stored by earlier versions into public API URLs. */
function productImageUrl(string $value): string
{
    if (preg_match('~^assets/images/(?:curry|drink|icecream|noodles|pad-thai|salad)\.jpg$~iD', $value)) {
        return 'assets/images/food-placeholder.svg';
    }
    if (str_starts_with($value, 'file=')) {
        $filename = rawurldecode(substr($value, 5));
        if (isProductImageFilename($filename)) {
            return 'api/product-image.php?file=' . rawurlencode($filename);
        }
    }
    if (str_starts_with($value, 'assets/uploads/products/') && isProductImageFilename(substr($value, strlen('assets/uploads/products/')))) {
        return 'api/product-image.php?file=' . rawurlencode(substr($value, strlen('assets/uploads/products/')));
    }
    if (str_starts_with($value, 'storage/uploads/products/') && isProductImageFilename(substr($value, strlen('storage/uploads/products/')))) {
        return 'api/product-image.php?file=' . rawurlencode(substr($value, strlen('storage/uploads/products/')));
    }
    $directory = realpath(dirname(__DIR__, 2) . '/storage/uploads/products');
    if ($directory !== false) {
        $prefix = rtrim(str_replace('\\', '/', $directory), '/') . '/';
        $path = str_replace('\\', '/', $value);
        $matches = DIRECTORY_SEPARATOR === '\\' ? strncasecmp($path, $prefix, strlen($prefix)) === 0 : str_starts_with($path, $prefix);
        if ($matches && isProductImageFilename(substr($path, strlen($prefix)))) {
            return 'api/product-image.php?file=' . rawurlencode(substr($path, strlen($prefix)));
        }
    }
    return $value;
}
