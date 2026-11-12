<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/Services/product_image.php';
require_once dirname(__DIR__, 2) . '/app/Services/ProductImageService.php';

it('maps stored upload paths to the image endpoint', function () {
    expect(productImageUrl('assets/uploads/products/eba26ab80a52ce4ee668d3be8e1d3a66.jpg'))
        ->toBe('api/product-image.php?file=eba26ab80a52ce4ee668d3be8e1d3a66.jpg');
    expect(productImageUrl('assets/uploads/products/อาหาร จาน 1.jpg'))
        ->toBe('api/product-image.php?file=' . rawurlencode('อาหาร จาน 1.jpg'));
    expect(productImageUrl('storage/uploads/products/noodles (1).jpg'))
        ->toBe('api/product-image.php?file=' . rawurlencode('noodles (1).jpg'));
    expect(productImageUrl('file=noodles%20%281%29.jpg'))
        ->toBe('api/product-image.php?file=noodles%20%281%29.jpg');
    $uploadDir = realpath(dirname(__DIR__, 2) . '/storage/uploads/products');
    if ($uploadDir !== false) {
        expect(productImageUrl($uploadDir . DIRECTORY_SEPARATOR . 'noodles (1).jpg'))
            ->toBe('api/product-image.php?file=' . rawurlencode('noodles (1).jpg'));
    }
    expect(productImageUrl('https://example.com/food.jpg'))->toBe('https://example.com/food.jpg');
    expect(productImageUrl('assets/images/noodles.jpg'))->toBe('assets/images/food-placeholder.svg');
    expect(productImageUrl('assets/uploads/products/../../secret.jpg'))->toBe('assets/uploads/products/../../secret.jpg');
});

it('rejects malformed image names and paths before reading storage', function () {
    expect(findProductImage('../secret.jpg', sys_get_temp_dir()))->toBeNull()
        ->and(findProductImage('123.jpg', sys_get_temp_dir()))->toBeNull()
        ->and(findProductImage(str_repeat('a', 32) . '.jpg', sys_get_temp_dir()))->toBeNull();
});
