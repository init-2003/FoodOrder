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

it('returns the POS confirmation time and status for an item marked P', function () {
    $header = $this->createMock(PDOStatement::class);
    $header->method('fetch')->willReturn(['Fn_Doc_No' => '123', 'Doc_Sts' => 0, 'Fn_Total' => 80]);
    $details = $this->createMock(PDOStatement::class);
    $details->method('fetchAll')->willReturn([['ID_NO' => 1, 'Trade_ID' => 'A', 'Trade_Name' => 'Food', 'Qty' => 1, 'Sale_Price' => 80, 'Fn_Doc_Date_Time' => '2026-11-14 10:35:00', 'Group_Pro' => '', 'Unit_Name' => '', 'Food_Que_STS' => 'P', 'Print_STS' => '1']]);
    $pdo = $this->createMock(PDO::class);
    $pdo->method('prepare')->willReturnOnConsecutiveCalls($header, $details);
    $item = loadOrderHistory($pdo, '123', '001', '')['orders'][0]['items'][0];
    expect($item['orderedAt'])->toBe('2026-11-14 10:35:00')->and($item['foodQueSts'])->toBe('P')->and($item['statusDesc'])->toBe('รับออเดอร์แล้ว');
});
