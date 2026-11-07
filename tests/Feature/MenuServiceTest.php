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
