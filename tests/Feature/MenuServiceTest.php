<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/Services/MenuService.php';

it('returns an empty menu without sample products when the branch has no data', function () {
    $stmt = $this->createMock(PDOStatement::class);
    $stmt->method('fetchAll')->willReturn([]);
    $stmt->expects($this->exactly(4))->method('execute')->with(['002']);
    $pdo = $this->createMock(PDO::class);
    $pdo->expects($this->exactly(4))->method('prepare')->willReturn($stmt);
    $menu = loadMenu($pdo, '002');
    expect($menu['branch'])->toBe('002')
        ->and($menu['items'])->toBe([])
        ->and($menu['categories'])->toBe([])
        ->and($menu['totalItems'])->toBe(0);
});

it('returns stopped products in the menu with ordering disabled', function () {
    $product = [
        'Trade_Name' => '', 'Type_Name' => '', 'Unit_Name' => '', 'Link_Part_image' => '',
        'Sale_Price' => 50, 'Food_Type' => 'FOOD',
    ];
    $stmt = $this->createMock(PDOStatement::class);
    $stmt->method('fetchAll')->willReturnOnConsecutiveCalls([
        $product + ['Trade_ID' => 'A', 'Trade_Stop_Sts' => 'S '],
        $product + ['Trade_ID' => 'B', 'Trade_Stop_Sts' => ''],
    ], [], [], []);
    $pdo = $this->createMock(PDO::class);
    $pdo->method('prepare')->willReturn($stmt);

    expect(array_column(loadMenu($pdo, '002')['items'], 'available', 'id'))
        ->toBe(['A' => false, 'B' => true]);
});
