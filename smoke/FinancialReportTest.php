<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/Services/ExpenseReportService.php';

it('shows daily income, expenses, profit and expense categories for the selected date', function () {
    $income = $this->createMock(PDOStatement::class);
    $expenses = $this->createMock(PDOStatement::class);
    $profit = $this->createMock(PDOStatement::class);
    foreach ([$income, $expenses, $profit] as $statement) {
        $statement->expects($this->once())->method('execute')->with(['2026-11-17']);
    }
    $income->method('fetchColumn')->willReturn('1000.00');
    $expenses->method('fetchColumn')->willReturn('250.00');
    $profit->method('fetchColumn')->willReturn('750.00');
    $detail = $this->createMock(PDOStatement::class);
    $detail->expects($this->once())->method('execute')->with(['2026-11-17', '2026-11-17']);
    $detail->method('fetchAll')->willReturn([['Expense_Type_Name' => 'Food', 'Ex_Price' => '250.00']]);
    $db = $this->createMock(PDO::class);
    $db->method('prepare')->willReturnOnConsecutiveCalls($income, $expenses, $profit, $detail);
    $report = loadFinancialReport($db, 'daily', '2026-11-17');
    expect($report['revenue'])->toBe(1000.0)->and($report['total'])->toBe(250.0)
        ->and($report['profit'])->toBe(750.0)->and($report['items'])->toHaveCount(1);
});

it('shows a monthly loss and keeps missing expense details empty', function () {
    $income = $this->createMock(PDOStatement::class);
    $expenses = $this->createMock(PDOStatement::class);
    $profit = $this->createMock(PDOStatement::class);
    $detail = $this->createMock(PDOStatement::class);
    foreach ([$income, $expenses, $profit, $detail] as $statement) {
        $statement->expects($this->once())->method('execute')->with([2026, 11]);
    }
    $income->method('fetchColumn')->willReturn('100.00');
    $expenses->method('fetchColumn')->willReturn('250.00');
    $profit->method('fetchColumn')->willReturn('-150.00');
    $detail->method('fetchAll')->willReturn([]);
    $db = $this->createMock(PDO::class);
    $db->method('prepare')->willReturnOnConsecutiveCalls($income, $expenses, $profit, $detail);
    $report = loadFinancialReport($db, 'monthly', '2026-11-01');
    expect($report['profit'])->toBe(-150.0)->and($report['items'])->toBe([]);
});

it('distinguishes missing report values from zero', function () {
    $missing = $this->createMock(PDOStatement::class);
    $missing->method('fetchColumn')->willReturn(false);
    $zero = $this->createMock(PDOStatement::class);
    $zero->method('fetchColumn')->willReturn('0.00');
    $detail = $this->createMock(PDOStatement::class);
    $detail->method('fetchAll')->willReturn([]);
    $db = $this->createMock(PDO::class);
    $db->method('prepare')->willReturnOnConsecutiveCalls($missing, $zero, $missing, $detail);
    $report = loadFinancialReport($db, 'daily', '2026-11-17');
    expect($report['revenue'])->toBeNull()->and($report['total'])->toBe(0.0)
        ->and($report['profit'])->toBeNull()->and($report['items'])->toBe([]);
});
