<?php
declare(strict_types=1);

/** Convert uploaded image paths stored by earlier versions into public API URLs. */
function productImageUrl(string $value): string
{
    if (preg_match('~^assets/uploads/products/([a-f0-9]{32}\.jpg)$~iD', $value, $match)) {
        return 'api/product-image.php?file=' . $match[1];
    }
    return $value;
}
