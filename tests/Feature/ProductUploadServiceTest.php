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
    $queries = [];
    $pdo->method('prepare')->willReturnCallback(function ($sql) use ($stmt, &$queries) { $queries[] = $sql; return $stmt; });
    $pdo->expects($this->exactly(2))->method('commit');
    try {
        $result = storeProductImage($pdo, 'ITEM1', '002', ['tmp_name' => $source, 'name' => 'noodles.png'], [2, 2], $dir);
        expect($result['status'])->toBe(200)->and($result['body']['success'])->toBeTrue();
        $files = glob($dir . '/*.jpg');
        expect($files)->toHaveCount(1)->and(basename($files[0]))->toBe('noodles.jpg');
        expect($queries[0])->toContain(Database::hexThai('file=noodles.jpg'));
        $second = storeProductImage($pdo, 'ITEM1', '002', ['tmp_name' => $source, 'name' => 'noodles.png'], [2, 2], $dir);
        expect($second['body']['image'])->toBe('api/product-image.php?file=noodles-1.jpg');
        expect($queries[1])->toContain(Database::hexThai('file=noodles-1.jpg'));
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

it('deletes an uploaded file only when no other product references it', function (bool $shared) {
    $dir = sys_get_temp_dir() . '/ordering-image-' . bin2hex(random_bytes(12));
    mkdir($dir, 0700);
    $file = $dir . '/noodles.jpg';
    file_put_contents($file, 'image');
    $encoded = bin2hex(mb_convert_encoding('file=noodles.jpg', 'UTF-16LE', 'UTF-8'));
    $select = $this->createMock(PDOStatement::class);
    $select->method('fetchAll')->willReturn([['image' => $encoded]]);
    $update = $this->createMock(PDOStatement::class);
    $update->method('rowCount')->willReturn(1);
    $references = $this->createMock(PDOStatement::class);
    $references->method('fetch')->willReturnOnConsecutiveCalls(...($shared ? [['image' => $encoded], false] : [false]));
    $pdo = $this->createMock(PDO::class);
    $pdo->method('prepare')->willReturnOnConsecutiveCalls($select, $update, $references);
    try {
        $result = removeProductImage($pdo, 'ITEM1', '002', 'api/product-image.php?file=noodles.jpg', $dir);
        expect($result['status'])->toBe(200)->and($result['body']['shared'])->toBe($shared)
            ->and(is_file($file))->toBe($shared);
    } finally {
        foreach (glob($dir . '/*') as $path) unlink($path);
        rmdir($dir);
    }
})->with([false, true]);
