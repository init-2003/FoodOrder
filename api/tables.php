<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

require_once __DIR__ . '/../includes/session.php';

$sessionEmId = trim((string)($_SESSION['Em_Id'] ?? ''));
if ($sessionEmId === '') {
    jsonResponse([
        'success' => false,
        'error' => [
            'code' => 'UNAUTHORIZED',
            'message' => 'กรุณาเข้าสู่ระบบพนักงานก่อนเลือกหรือเปิดโต๊ะ',
        ],
        'tables' => [],
    ], 401);
}

$branchId = trim((string)($_GET['Branch_ID'] ?? $_GET['branch'] ?? (getenv('DEFAULT_BRANCH_ID') ?: '')));
if ($branchId === '') { jsonResponse(['error' => ['code' => 'MISSING_BRANCH', 'message' => 'กรุณาระบุรหัสสาขาใน URL หรือ DEFAULT_BRANCH_ID']], 400); }


$pdo = Database::getConnection();
if (!$pdo) {
    jsonResponse([
        'success' => false,
        'mode' => 'offline',
        'error' => [
            'code' => 'DATABASE_UNAVAILABLE',
            'message' => 'ไม่สามารถเชื่อมต่อฐานข้อมูล SQL Server ได้',
        ],
        'tables' => [],
    ], 503);
}

try {
    // 1. Fetch all tables from PBM_Table
    $stmt = $pdo->prepare(
        "SELECT Branch_ID, Table_ID, CONVERT(varbinary(max), Table_Name) AS Table_Name, Table_STS
         FROM PBM_Table
         WHERE Branch_ID = ?
         ORDER BY Table_ID ASC"
    );
    $stmt->execute([$branchId]);
    $rawTables = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    // 2. Fetch all active open bills (Doc_Sts = 0) from Fnt_Header
    $stmtBills = $pdo->prepare(
        "SELECT Fn_Doc_No, Table_ID, Fn_Total, Table_Person, Fn_Doc_Time, Fn_Doc_Date_Time
         FROM Fnt_Header
         WHERE Branch_ID = ? AND Doc_Sts = 0
         ORDER BY Fn_Doc_Date_Time DESC"
    );
    $stmtBills->execute([$branchId]);
    $openBillsRaw = $stmtBills->fetchAll(PDO::FETCH_ASSOC);
    $stmtBills->closeCursor();

    $openBillMap = [];
    foreach ($openBillsRaw as $b) {
        $tId = trim((string)$b['Table_ID']);
        $tUnpadded = ltrim($tId, '0') ?: $tId;
        $tPadded = str_pad($tUnpadded, 3, '0', STR_PAD_LEFT);
        if (!isset($openBillMap[$tPadded])) {
            $openBillMap[$tPadded] = $b;
        }
        if (!isset($openBillMap[$tId])) {
            $openBillMap[$tId] = $b;
        }
        if (!isset($openBillMap[$tUnpadded])) {
            $openBillMap[$tUnpadded] = $b;
        }
    }

    $tables = [];
    foreach ($rawTables as $row) {
        $tableId = trim((string)$row['Table_ID']);
        $tableUnpadded = ltrim($tableId, '0') ?: $tableId;
        $tableName = trim(Database::decodeThai($row['Table_Name'] ?? null)) ?: ('โต๊ะ ' . $tableUnpadded);
        $tableSts = trim((string)($row['Table_STS'] ?? ''));

        $bill = $openBillMap[$tableId] ?? $openBillMap[$tableUnpadded] ?? null;
        $isOpen = ($tableSts === 'O') || ($bill !== null);
        $docNo = $bill ? trim((string)$bill['Fn_Doc_No']) : '';
        $total = $bill ? (float)$bill['Fn_Total'] : 0.0;
        $guests = $bill ? (int)($bill['Table_Person'] ?? 0) : 0;
        $docTime = $bill ? substr(trim((string)($bill['Fn_Doc_Time'] ?? '')), 0, 5) : '';

        $tables[] = [
            'id' => $tableId,
            'number' => $tableUnpadded,
            'name' => $tableName,
            'status' => $isOpen ? 'O' : $tableSts,
            'isOpen' => $isOpen,
            'statusText' => $isOpen ? 'เปิดบิลแล้ว' : 'ว่าง',
            'docNo' => $docNo,
            'total' => $total,
            'guests' => $guests > 0 ? $guests : null,
            'openedAt' => $docTime,
        ];
    }

    jsonResponse([
        'success' => true,
        'mode' => 'live',
        'branch' => $branchId,
        'tables' => $tables,
    ]);
} catch (Throwable $e) {
    appLog('error', 'tables request failed', ['error' => $e->getMessage()]);
    jsonResponse([
        'success' => false,
        'error' => [
            'code' => 'TABLES_ERROR',
            'message' => 'ไม่สามารถดึงข้อมูลโต๊ะได้ กรุณาลองอีกครั้ง',
        ],
        'tables' => [],
    ], 500);
}

