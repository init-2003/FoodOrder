<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/Database/db.php';
require_once dirname(__DIR__, 2) . '/app/Services/TableService.php';

it('matches open bills to zero-padded table IDs and preserves available tables', function () {
    $tables = $this->createMock(PDOStatement::class);
    $bills = $this->createMock(PDOStatement::class);
    $tables->expects($this->once())->method('execute')->with(['002']);
    $bills->expects($this->once())->method('execute')->with(['002']);
    $tables->method('fetchAll')->willReturn([
        ['Table_ID' => '002', 'Table_Name' => null, 'Table_STS' => ''],
        ['Table_ID' => '003', 'Table_Name' => null, 'Table_STS' => ''],
    ]);
    $bills->method('fetchAll')->willReturn([
        ['Table_ID' => '2', 'Fn_Doc_No' => '68005642', 'Fn_Total' => 150, 'Table_Person' => 0, 'Fn_Doc_Time' => '10:30:00'],
    ]);
    $pdo = $this->createMock(PDO::class);
    $pdo->expects($this->exactly(2))->method('prepare')->willReturnOnConsecutiveCalls($tables, $bills);
    $result = loadTables($pdo, '002');
    expect($result[0]['number'])->toBe('2')
        ->and($result[0]['isOpen'])->toBeTrue()
        ->and($result[0]['docNo'])->toBe('68005642')
        ->and($result[1]['isOpen'])->toBeFalse();
});
