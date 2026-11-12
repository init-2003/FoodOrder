<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/Services/ExpenseWriteService.php';

it('validates expense input and rejects invalid dates and amounts', function () {
    $valid = ['date' => '2026-11-12', 'detail' => 'ค่าอาหาร', 'amount' => '120.50', 'type' => '01', 'payer' => '001'];
    expect(validateExpense($valid)['amount'])->toBe('120.50');
    foreach ([['date' => '2026-02-30'], ['amount' => '0'], ['amount' => '1.234'], ['type' => '']] as $invalid) {
        try { validateExpense(array_replace($valid, $invalid)); $this->fail('Invalid input was accepted'); }
        catch (InvalidArgumentException $e) { expect($e->getMessage())->not->toBeEmpty(); }
    }
});

it('locks the expense counter and commits the new document with its increment', function () {
    $type = $this->createMock(PDOStatement::class); $type->method('fetchAll')->willReturn([['name' => bin2hex(mb_convert_encoding('ค่าอาหาร', 'UTF-16LE', 'UTF-8'))]]);
    $payer = $this->createMock(PDOStatement::class); $payer->method('fetchAll')->willReturn([['name' => bin2hex(mb_convert_encoding('สมชาย', 'UTF-16LE', 'UTF-8'))]]);
    $counter = $this->createMock(PDOStatement::class); $counter->method('fetchAll')->willReturn([['PB_RUN_YR' => '69', 'PB_RUN_NO' => 123]]);
    $increment = $this->createMock(PDOStatement::class); $increment->method('rowCount')->willReturn(1);
    $insert = $this->createMock(PDOStatement::class);
    $insert->expects($this->once())->method('execute')->with($this->callback(fn($p) => $p[1] === '69000123' && $p[5] === '69000123' && $p[4] === '01'));
    $db = $this->createMock(PDO::class);
    $queries = [];
    $db->method('prepare')->willReturnCallback(function ($sql) use (&$queries, $type, $payer, $counter, $increment, $insert) { $queries[] = $sql; return [$type, $payer, $counter, $increment, $insert][count($queries) - 1]; });
    $db->expects($this->once())->method('beginTransaction'); $db->expects($this->once())->method('commit');
    $doc = saveExpense($db, '001', 'admin', ['date' => '2026-11-12', 'detail' => 'ค่าอาหาร', 'amount' => '120.50', 'type' => '01', 'payer' => '001']);
    expect($doc)->toBe('69000123')->and($queries[2])->toContain('UPDLOCK', 'HOLDLOCK');
});

it('rolls back if the expense counter is missing', function () {
    $type = $this->createMock(PDOStatement::class); $type->method('fetchAll')->willReturn([['name' => '']]);
    $payer = $this->createMock(PDOStatement::class); $payer->method('fetchAll')->willReturn([['name' => '']]);
    $counter = $this->createMock(PDOStatement::class); $counter->method('fetchAll')->willReturn([]);
    $db = $this->createMock(PDO::class); $db->method('prepare')->willReturnOnConsecutiveCalls($type, $payer, $counter);
    $db->method('inTransaction')->willReturn(true); $db->expects($this->once())->method('rollBack'); $db->expects($this->never())->method('commit');
    expect(fn() => saveExpense($db, '001', 'admin', ['date' => '2026-11-12', 'detail' => 'x', 'amount' => '1', 'type' => '01', 'payer' => '001']))->toThrow(RuntimeException::class);
});

it('rolls back the counter increment when the insert fails', function () {
    $type = $this->createMock(PDOStatement::class); $type->method('fetchAll')->willReturn([['name' => '']]);
    $payer = $this->createMock(PDOStatement::class); $payer->method('fetchAll')->willReturn([['name' => '']]);
    $counter = $this->createMock(PDOStatement::class); $counter->method('fetchAll')->willReturn([['PB_RUN_YR' => '69', 'PB_RUN_NO' => 1]]);
    $increment = $this->createMock(PDOStatement::class); $increment->method('rowCount')->willReturn(1);
    $insert = $this->createMock(PDOStatement::class); $insert->method('execute')->willThrowException(new RuntimeException('insert failed'));
    $db = $this->createMock(PDO::class); $db->method('prepare')->willReturnOnConsecutiveCalls($type, $payer, $counter, $increment, $insert);
    $db->method('inTransaction')->willReturn(true); $db->expects($this->once())->method('rollBack'); $db->expects($this->never())->method('commit');
    expect(fn() => saveExpense($db, '001', 'admin', ['date' => '2026-11-12', 'detail' => 'x', 'amount' => '1', 'type' => '01', 'payer' => '001']))->toThrow(RuntimeException::class);
});
