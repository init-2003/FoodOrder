<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/Services/TableOpeningService.php';

it('requires an explicit staff identity rather than reading a session', function () {
    $customer = $this->createMock(PDOStatement::class);
    $customer->method('fetchColumn')->willReturn('CASH');
    $pdo = $this->createMock(PDO::class);
    $pdo->method('prepare')->willReturn($customer);
    $pdo->expects($this->never())->method('beginTransaction');
    expect(fn () => openOrGetTableBill($pdo, '1', '002', ''))->toThrow(InvalidArgumentException::class);
});
