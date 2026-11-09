<?php
declare(strict_types=1);

/** Read the monthly expenses views. The views aggregate across all branches. */
function loadMonthlyExpenses(PDO $pdo, int $year, int $month): array
{
    $summary = $pdo->prepare('SELECT OperatingExpenses FROM dbo.V_OperatingExpenses WHERE YearSale = ? AND MonthSale = ?');
    $summary->execute([$year, $month]);
    $total = $summary->fetchColumn();
    $summary->closeCursor();
    $detail = $pdo->prepare('SELECT CONVERT(varbinary(max), Expense_Type_Name) AS Expense_Type_Name, Ex_Price FROM dbo.V_OperatingExpenses_Detail WHERE YearSale = ? AND MonthSale = ? ORDER BY Ex_Price DESC, Expense_Type_Name');
    $detail->execute([$year, $month]);
    $rows = $detail->fetchAll(PDO::FETCH_ASSOC);
    $detail->closeCursor();
    return ['total' => $total === false ? null : (float)$total, 'items' => $rows];
}

/** List the years available in the expense summary view, newest first. */
function loadExpenseYears(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT DISTINCT YearSale FROM dbo.V_OperatingExpenses WHERE YearSale BETWEEN 1900 AND 9999 ORDER BY YearSale DESC');
    $years = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    $stmt->closeCursor();
    return $years;
}
