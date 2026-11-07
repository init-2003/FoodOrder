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
