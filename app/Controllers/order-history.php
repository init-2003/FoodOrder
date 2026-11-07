<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Services/functions.php';
require_once __DIR__ . '/../Database/db.php';
require_once __DIR__ . '/../Services/OrderHistoryService.php';

$fnDocNo = trim((string)($_GET['Fn_Doc_No'] ?? $_GET['doc'] ?? $_GET['doc_no'] ?? $_GET['bill'] ?? ''));
$branchId = trim((string)($_GET['Branch_ID'] ?? $_GET['branch'] ?? (getenv('DEFAULT_BRANCH_ID') ?: '')));
if ($branchId === '') { jsonResponse(['error' => ['code' => 'MISSING_BRANCH', 'message' => 'กรุณาระบุรหัสสาขาใน URL หรือ DEFAULT_BRANCH_ID']], 400); }
$tableInput = trim((string)($_GET['table'] ?? ''));

if ($fnDocNo === '' && $tableInput === '') {
    jsonResponse(['error' => ['code' => 'MISSING_TABLE_OR_BILL', 'message' => 'กรุณาระบุหมายเลขโต๊ะหรือเลขที่บิล']], 400);
}


$pdo = Database::getConnection();
if (!$pdo) {
    jsonResponse([
        'mode' => 'offline',
        'pendingItems' => [],
        'pendingTotal' => 0,
        'orders' => [],
        'confirmedTotal' => 0,
        'total' => 0,
        'docSts' => 0,
        'closed' => false,
        'cancelled' => false
    ]);
}

try {
    jsonResponse(loadOrderHistory($pdo, $fnDocNo, $branchId, $tableInput));
} catch (Throwable $e) {
    appLog('error', 'order-history request failed', ['error' => $e->getMessage()]);
    jsonResponse([
        'mode' => 'error',
        'error' => 'ไม่สามารถโหลดรายการที่สั่งได้ กรุณาลองอีกครั้ง',
        'pendingItems' => [],
        'pendingTotal' => 0,
        'orders' => [],
        'confirmedTotal' => 0,
        'total' => 0
    ]);
}
