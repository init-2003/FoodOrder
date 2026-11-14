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

it('stores the exact table ID returned by PBM_Table for a new bill', function (string $input, string $storedId) {
    $customer = $this->createMock(PDOStatement::class);
    $customer->method('fetchColumn')->willReturn('CASH');
    $table = $this->createMock(PDOStatement::class);
    $table->method('fetch')->willReturn(['Table_ID' => $storedId, 'Table_Name' => '', 'Table_STS' => '']);
    $existing = $this->createMock(PDOStatement::class);
    $existing->method('fetch')->willReturn(false);
    $counter = $this->createMock(PDOStatement::class);
    $counter->method('fetch')->willReturn(['PB_RUN_YR' => '68', 'PB_RUN_NO' => '1']);
    $counterUpdate = $this->createMock(PDOStatement::class);
    $tableUpdate = $this->createMock(PDOStatement::class);
    $tableUpdate->expects($this->once())->method('execute')->with([$storedId, '002']);
    $insert = $this->createMock(PDOStatement::class);
    $insert->expects($this->once())->method('execute')->with($this->callback(fn(array $values) => $values[6] === $storedId));
    $pdo = $this->createMock(PDO::class);
    $pdo->method('prepare')->willReturnOnConsecutiveCalls($customer, $table, $existing, $counter, $counterUpdate, $tableUpdate, $insert);
    $pdo->expects($this->once())->method('commit');
    $result = openOrGetTableBill($pdo, $input, '002', 'staff');
    expect($result['tableId'])->toBe($storedId)->and($result['created'])->toBeTrue();
})->with([['1', '001'], ['001', '001'], ['1', '1']]);

it('does not create a new bill when the table is missing from PBM_Table', function () {
    $customer = $this->createMock(PDOStatement::class);
    $customer->method('fetchColumn')->willReturn('CASH');
    $table = $this->createMock(PDOStatement::class);
    $table->method('fetch')->willReturn(false);
    $bill = $this->createMock(PDOStatement::class);
    $bill->method('fetch')->willReturn(false);
    $pdo = $this->createMock(PDO::class);
    $pdo->method('prepare')->willReturnOnConsecutiveCalls($customer, $table, $bill);
    $pdo->method('inTransaction')->willReturn(false, true);
    $pdo->expects($this->once())->method('rollBack');
    expect(fn() => openOrGetTableBill($pdo, '1', '002', 'staff'))->toThrow(InvalidArgumentException::class, 'ไม่พบหมายเลขโต๊ะในระบบ');
});
