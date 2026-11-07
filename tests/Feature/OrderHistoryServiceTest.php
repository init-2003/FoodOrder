<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/Services/OrderHistoryService.php';

it('returns an empty history when the bill does not exist', function () {
    $stmt = $this->createMock(PDOStatement::class);
    $stmt->expects($this->once())->method('execute')->with(['68005642', '002']);
    $stmt->method('fetch')->willReturn(false);
    $pdo = $this->createMock(PDO::class);
    $pdo->expects($this->once())->method('prepare')->willReturn($stmt);
    $history = loadOrderHistory($pdo, '68005642', '002', '');
    expect($history['mode'])->toBe('live')
        ->and($history['orders'])->toBe([])
        ->and($history['total'])->toBe(0)
        ->and($history['closed'])->toBeFalse();
});
