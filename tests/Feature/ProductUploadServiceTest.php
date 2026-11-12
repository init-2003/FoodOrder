<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/Services/ProductUploadService.php';

it('stores an uploaded product image and commits its database reference', function () {
    if (!extension_loaded('gd')) $this->markTestSkipped('GD is required');
    $dir = sys_get_temp_dir() . '/ordering-image-' . bin2hex(random_bytes(12));
    mkdir($dir, 0700);
    $source = $dir . '/source.png';
    $image = imagecreatetruecolor(2, 2);
    imagepng($image, $source);
    unset($image);
    $stmt = $this->createMock(PDOStatement::class);
    $stmt->method('rowCount')->willReturn(1);
    $pdo = $this->createMock(PDO::class);
    $pdo->method('prepare')->willReturn($stmt);
    $pdo->expects($this->once())->method('commit');
    try {
        $result = storeProductImage($pdo, 'ITEM1', '002', ['tmp_name' => $source], [2, 2], $dir);
        expect($result['status'])->toBe(200)->and($result['body']['success'])->toBeTrue();
        $files = glob($dir . '/*.jpg');
        expect($files)->toHaveCount(1);
    } finally {
        foreach (glob($dir . '/*') as $file) unlink($file);
        rmdir($dir);
    }
});

it('removes only the selected product image reference', function () {
    $path = 'assets/uploads/products/' . str_repeat('a', 32) . '.jpg';
    $encoded = bin2hex(mb_convert_encoding($path, 'UTF-16LE', 'UTF-8'));
    $select = $this->createMock(PDOStatement::class);
    $select->expects($this->once())->method('execute')->with(['002']);
    $select->method('fetchAll')->willReturn([['image' => $encoded]]);
    $update = $this->createMock(PDOStatement::class);
    $update->expects($this->once())->method('execute')->with(['002']);
    $update->method('rowCount')->willReturn(1);
    $pdo = $this->createMock(PDO::class);
    $pdo->method('prepare')->willReturnOnConsecutiveCalls($select, $update);
    $pdo->expects($this->once())->method('commit');
    $result = removeProductImage($pdo, 'ITEM1', '002', productImageUrl($path));
    expect($result['status'])->toBe(200)->and($result['body']['image'])->toBe('assets/images/food-placeholder.svg');
});

it('does not remove an image that another user has changed', function () {
    $path = 'assets/uploads/products/' . str_repeat('b', 32) . '.jpg';
    $select = $this->createMock(PDOStatement::class);
    $select->method('fetchAll')->willReturn([['image' => bin2hex(mb_convert_encoding($path, 'UTF-16LE', 'UTF-8'))]]);
    $pdo = $this->createMock(PDO::class);
    $pdo->expects($this->once())->method('prepare')->willReturn($select);
    $pdo->expects($this->once())->method('rollBack');
    $pdo->expects($this->never())->method('commit');
    $result = removeProductImage($pdo, 'ITEM1', '002', 'api/product-image.php?file=' . str_repeat('a', 32) . '.jpg');
    expect($result['status'])->toBe(409);
});
