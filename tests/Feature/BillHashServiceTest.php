<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/Services/BillHashService.php';

it('resolves a hash within the requested branch', function () {
    $hash = str_repeat('a', 64);
    $stmt = $this->createMock(PDOStatement::class);
    $stmt->expects($this->once())->method('execute')->with(['002', $hash]);
    $stmt->method('fetchAll')->willReturn([['Fn_Doc_No' => '68005642']]);
    $pdo = $this->createMock(PDO::class);
    $pdo->method('prepare')->willReturn($stmt);
    expect(findBillByHash($pdo, '002', $hash))->toBe('68005642');
});

it('rejects missing, invalid and ambiguous hashes', function () {
    $stmt = $this->createMock(PDOStatement::class);
    $stmt->method('fetchAll')->willReturn([['Fn_Doc_No' => '1'], ['Fn_Doc_No' => '2']]);
    $pdo = $this->createMock(PDO::class);
    $pdo->method('prepare')->willReturn($stmt);
    expect(findBillByHash($pdo, '002', 'invalid'))->toBeNull()
        ->and(findBillByHash($pdo, '', str_repeat('a', 64)))->toBeNull()
        ->and(findBillByHash($pdo, '002', str_repeat('a', 64)))->toBeNull();
});

it('only uses a stored hash when it uniquely resolves to the same bill', function () {
    $hash = str_repeat('a', 64);
    $stmt = $this->createMock(PDOStatement::class);
    $stmt->method('fetchAll')->willReturn([['Fn_Doc_No' => '68005656']]);
    $pdo = $this->createMock(PDO::class);
    $pdo->method('prepare')->willReturn($stmt);
    expect(canonicalBillHash($pdo, '002', '68005656', ' ' . $hash . ' '))->toBe($hash)
        ->and(canonicalBillHash($pdo, '002', '68005654', $hash))->toBe('')
        ->and(canonicalBillHash($pdo, '002', '68005654', ''))->toBe('');
});
