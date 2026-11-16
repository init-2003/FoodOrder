<?php
declare(strict_types=1);

/** The POS views use branch 001 for revenue and all branches for expenses. */
function loadFinancialReport(PDO $pdo, string $period, string $date): array
{
    $daily = $period === 'daily';
    $params = $daily ? [$date] : [(int)substr($date, 0, 4), (int)substr($date, 5, 2)];
    $queries = $daily ? [
        'revenue' => 'SELECT TotalAmount FROM dbo.V_Revenue_Daily WHERE Fn_Doc_Date = ?',
        'total' => 'SELECT OperatingExpenses FROM dbo.V_OperatingExpenses_Daily WHERE Doc_Date = ?',
        'profit' => 'SELECT NetProfit FROM dbo.V_Profit_Daily WHERE Doc_Date = ?',
    ] : [
        'revenue' => 'SELECT TotalAmount FROM dbo.V_Revenue_Monthly WHERE Doc_Year = ? AND Doc_Month = ?',
        'total' => 'SELECT OperatingExpenses FROM dbo.V_OperatingExpenses WHERE YearSale = ? AND MonthSale = ?',
        'profit' => 'SELECT NetProfit FROM dbo.V_Profit_Monthly WHERE YearSale = ? AND MonthSale = ?',
    ];
    $report = [];
    foreach ($queries as $key => $sql) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        $report[$key] = $value === false || $value === null ? null : (float)$value;
        $stmt->closeCursor();
    }
    // Match the monthly detail view's grouping and cancellation condition.
    $sql = $daily
        ? "SELECT CONVERT(varbinary(max), Expense_Type_Name) AS Expense_Type_Name, SUM(Ex_Price) AS Ex_Price FROM dbo.Expending WHERE Ex_Sts <> '3' AND Ex_Doc_Date >= CONVERT(date, ?) AND Ex_Doc_Date < DATEADD(day, 1, CONVERT(date, ?)) GROUP BY Expense_Type_Name ORDER BY Ex_Price DESC, Expense_Type_Name"
        : 'SELECT CONVERT(varbinary(max), Expense_Type_Name) AS Expense_Type_Name, Ex_Price FROM dbo.V_OperatingExpenses_Detail WHERE YearSale = ? AND MonthSale = ? ORDER BY Ex_Price DESC, Expense_Type_Name';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($daily ? [$date, $date] : $params);
    $report['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    return $report;
}
