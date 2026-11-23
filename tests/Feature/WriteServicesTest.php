<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/Services/CartService.php';
require_once dirname(__DIR__, 2) . '/app/Services/OrderService.php';
require_once dirname(__DIR__, 2) . '/app/Services/ConfirmOrderService.php';

it('rolls back a cart transaction when the bill is not open', function () {
    $stmt = $this->createMock(PDOStatement::class);
    $stmt->method('fetch')->willReturn(false);
    $pdo = $this->createMock(PDO::class);
    $pdo->expects($this->once())->method('beginTransaction');
    $pdo->expects($this->once())->method('rollBack');
    $pdo->method('prepare')->willReturn($stmt);
    $result = processCart($pdo, 'list', '68005642', '002', 'st2_test', '', '', [], 'hash');
    expect($result['status'])->toBe(409)->and($result['body']['error']['code'])->toBe('BILL_NOT_OPEN');
});

it('rolls back an order transaction for a missing bill', function () {
    $stmt = $this->createMock(PDOStatement::class);
    $stmt->method('fetch')->willReturn(false);
    $pdo = $this->createMock(PDO::class);
    $pdo->expects($this->once())->method('beginTransaction');
    $pdo->expects($this->once())->method('rollBack');
    $pdo->method('prepare')->willReturn($stmt);
    $result = submitOrder($pdo, ['items' => []], '68005642', '', '', '', '002', '', '', '', false, 'hash');
    expect($result['status'])->toBe(404)->and($result['body']['error']['code'])->toBe('BILL_NOT_FOUND');
});

it('rejects an order if the POS stopped selling the product after it was selected', function () {
    $stmt = $this->createMock(PDOStatement::class);
    $stmt->method('fetch')->willReturnOnConsecutiveCalls(
        ['Fn_Doc_No' => '68005642', 'Doc_Sts' => 0, 'Table_ID' => '01', 'Table_Name' => ''],
        ['Trade_Name' => substr(Database::hexThai('ข้าวผัด'), 2), 'Trade_Stop_Sts' => 'S'],
    );
    $pdo = $this->createMock(PDO::class);
    $pdo->expects($this->once())->method('beginTransaction');
    $pdo->expects($this->once())->method('rollBack');
    $pdo->expects($this->never())->method('commit');
    $pdo->method('prepare')->willReturn($stmt);

    $result = submitOrder($pdo, ['items' => [['id' => 'A', 'qty' => 1]]], '68005642', '', '', '', '002', '', '', '', false, 'hash');
    expect($result['status'])->toBe(409)
        ->and($result['body']['error']['code'])->toBe('ITEM_STOPPED')
        ->and($result['body']['error']['itemName'])->toBe('ข้าวผัด');
});

it('rolls back confirmation for a closed bill', function () {
    $stmt = $this->createMock(PDOStatement::class);
    $stmt->method('fetch')->willReturn(['Doc_Sts' => 1]);
    $pdo = $this->createMock(PDO::class);
    $pdo->expects($this->once())->method('beginTransaction');
    $pdo->expects($this->once())->method('rollBack');
    $pdo->method('prepare')->willReturn($stmt);
    $result = confirmPendingOrder($pdo, '68005642', '', '002');
    expect($result['status'])->toBe(400)->and($result['body']['error']['code'])->toBe('BILL_CLOSED');
});

it('commits confirmation for an open bill and reports affected items', function () {
    $bill = $this->createMock(PDOStatement::class);
    $bill->method('fetch')->willReturn(['Doc_Sts' => 0]);
    $update = $this->createMock(PDOStatement::class);
    $update->expects($this->once())->method('execute')->with(['ITEM1', '68005642', '002']);
    $update->method('rowCount')->willReturn(2);
    $pdo = $this->createMock(PDO::class);
    $pdo->method('prepare')->willReturnOnConsecutiveCalls($bill, $update);
    $pdo->expects($this->once())->method('commit');
    $result = confirmPendingOrder($pdo, '68005642', 'ITEM1', '002');
    expect($result['status'])->toBe(200)->and($result['body']['updatedCount'])->toBe(2);
});

it('commits a cart read for an open bill', function () {
    $bill = $this->createMock(PDOStatement::class);
    $bill->method('fetch')->willReturn(['Doc_Sts' => 0]);
    $items = $this->createMock(PDOStatement::class);
    $items->method('fetch')->willReturn(false);
    $pdo = $this->createMock(PDO::class);
    $pdo->method('prepare')->willReturnOnConsecutiveCalls($bill, $items);
    $pdo->expects($this->once())->method('commit');
    $result = processCart($pdo, 'list', '68005642', '002', 'st2_test', '', '', [], 'hash');
    expect($result['status'])->toBe(200)->and($result['body']['items'])->toBe([]);
});
