<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/Services/BillService.php';

it('returns a 404 when a requested bill is absent from the branch', function () {
    $stmt = $this->createMock(PDOStatement::class);
    $stmt->expects($this->once())->method('execute')->with(['68005642', '002']);
    $stmt->method('fetch')->willReturn(false);
    $pdo = $this->createMock(PDO::class);
    $pdo->expects($this->once())->method('prepare')->willReturn($stmt);
    $result = loadBill($pdo, '68005642', '002', '', '');
    expect($result['status'])->toBe(404)
        ->and($result['body']['error']['code'])->toBe('BILL_NOT_FOUND');
});
