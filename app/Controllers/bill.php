<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Services/functions.php';
require_once __DIR__ . '/../Database/db.php';
require_once __DIR__ . '/../Services/BillService.php';

require_once __DIR__ . '/../../app/Security/session.php';

$sessionEmId = trim((string)($_SESSION['Em_Id'] ?? ''));
$fnDocNo = trim((string)($_GET['Fn_Doc_No'] ?? $_GET['doc'] ?? $_GET['doc_no'] ?? $_GET['bill'] ?? ''));
$branchId = trim((string)($_GET['Branch_ID'] ?? $_GET['branch'] ?? (getenv('DEFAULT_BRANCH_ID') ?: '')));
if ($branchId === '') { jsonResponse(['error' => ['code' => 'MISSING_BRANCH', 'message' => 'กรุณาระบุรหัสสาขาใน URL หรือ DEFAULT_BRANCH_ID']], 400); }
$tableInput = trim((string)($_GET['table'] ?? ''));

if ($fnDocNo === '' && $tableInput === '') {
    jsonResponse(['error' => ['code' => 'MISSING_TABLE_OR_BILL', 'message' => 'กรุณาระบุหมายเลขโต๊ะหรือเลขที่บิล']], 400);
}


$pdo = Database::getConnection();
if (!$pdo) {
    $tableDisplay = $tableInput;
    jsonResponse([
        'mode' => 'offline',
        'bill' => [
            'id' => $fnDocNo ?: 'OFFLINE',
            'docNo' => $fnDocNo ?: '',
            'docSts' => 0,
            'docStsDesc' => 'ออฟไลน์',
            'table' => $tableDisplay,
            'tableName' => 'โต๊ะ ' . $tableDisplay,
            'guests' => null,
            'status' => 'open',
            'total' => 0.0,
            'closed' => false,
            'canOrder' => false,
        ],
    ]);
}

try {
    $result = loadBill($pdo, $fnDocNo, $branchId, $tableInput, $sessionEmId);
    jsonResponse($result['body'], $result['status']);
} catch (Throwable $e) {
    appLog('error', 'bill request failed', ['error' => $e->getMessage()]);
    jsonResponse([
        'mode' => 'error',
        'error' => [
            'code' => 'SERVER_ERROR',
            'message' => 'ไม่สามารถโหลดข้อมูลบิลได้ กรุณาลองอีกครั้ง',
        ],
        'bill' => [
            'id' => '',
            'docNo' => '',
            'docSts' => 0,
            'table' => $tableInput,
            'tableName' => $tableInput !== '' ? 'โต๊ะ ' . $tableInput : '',
            'status' => 'open',
            'total' => 0.0,
            'closed' => false,
            'canOrder' => false,
        ],
    ], 500);
}
