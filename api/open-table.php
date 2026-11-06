<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

require_once __DIR__ . '/../includes/session.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    jsonResponse(['error' => ['code' => 'METHOD_NOT_ALLOWED', 'message' => 'Method not allowed']], 405);
}

$rawInput = file_get_contents('php://input');
$jsonData = json_decode($rawInput ?: '{}', true);
if (!is_array($jsonData)) {
    $jsonData = [];
}

$tableInput = trim((string)(
    $jsonData['TableID']
    ?? $jsonData['Table_ID']
    ?? $jsonData['table']
    ?? $_POST['TableID']
    ?? $_POST['Table_ID']
    ?? $_POST['table']
    ?? $_GET['TableID']
    ?? $_GET['Table_ID']
    ?? $_GET['table']
    ?? ''
));

$fnDocNoInput = trim((string)(
    $jsonData['fn_doc_no']
    ?? $jsonData['Fn_Doc_No']
    ?? $jsonData['doc']
    ?? $_POST['fn_doc_no']
    ?? $_POST['Fn_Doc_No']
    ?? $_POST['doc']
    ?? $_GET['fn_doc_no']
    ?? $_GET['Fn_Doc_No']
    ?? $_GET['doc']
    ?? ''
));

$branchId = trim((string)(
    $jsonData['Branch_ID']
    ?? $jsonData['branch']
    ?? $_POST['Branch_ID']
    ?? $_POST['branch']
    ?? $_GET['Branch_ID']
    ?? $_GET['branch']
    ?? (getenv('DEFAULT_BRANCH_ID') ?: '')
));
if ($branchId === '') { jsonResponse(['error' => ['code' => 'MISSING_BRANCH', 'message' => 'กรุณาระบุรหัสสาขาใน URL หรือ DEFAULT_BRANCH_ID']], 400); }

$tablePerson = trim((string)(
    $jsonData['Table_Person']
    ?? $jsonData['guests']
    ?? $_POST['Table_Person']
    ?? $_POST['guests']
    ?? $_GET['Table_Person']
    ?? $_GET['guests']
    ?? ''
));

$sessionEmId = trim((string)($_SESSION['Em_Id'] ?? ''));
if ($sessionEmId === '') {
    jsonResponse([
        'success' => false,
        'result' => 'Error',
        'error' => [
            'code' => 'UNAUTHORIZED',
            'message' => 'กรุณาเข้าสู่ระบบพนักงานก่อนทำการจองหรือเปิดโต๊ะ',
        ],
    ], 401);
}

$pbUser = $sessionEmId;

$legacyFormat = isset($_POST['TableID']) && empty($jsonData);

if ($tableInput === '') {
    if ($legacyFormat) {
        jsonResponse(['Error' => 'Missing TableID'], 400);
    }
    jsonResponse([
        'success' => false,
        'error' => [
            'code' => 'MISSING_TABLE_ID',
            'message' => 'กรุณาระบุหมายเลขโต๊ะ (TableID / table)',
        ],
    ], 400);
}


$pdo = Database::getConnection();
if (!$pdo) {
    jsonResponse([
        'success' => false,
        'result' => 'Error',
        'error' => [
            'code' => 'DATABASE_UNAVAILABLE',
            'message' => 'ไม่สามารถเชื่อมต่อฐานข้อมูล SQL Server ได้',
        ],
    ], 503);
}

try {
    $opened = openOrGetTableBill($pdo, $tableInput, $branchId, $pbUser, $tablePerson);

    $_SESSION['Table_ID'] = $opened['tableId'];
    $_SESSION['Table_Name'] = $opened['tableName'];
    $_SESSION['Fn_Doc_No'] = $opened['docNo'];

    jsonResponse([
        'success' => true,
        'result' => 'OK',
        'mode' => 'live',
        'created' => $opened['created'],
        'docNo' => $opened['docNo'],
        'Fn_Doc_No' => $opened['docNo'],
        'table' => $opened['tableUnpadded'],
        'Table_ID' => $opened['tableId'],
        'tableName' => $opened['tableName'],
        'Table_Name' => $opened['tableName'],
        'Table_STS' => $opened['tableSts'],
        'guests' => $opened['guests'],
    ]);
} catch (Throwable $e) {
    appLog('error', 'open-table request failed', ['error' => $e->getMessage()]);
    jsonResponse([
        'success' => false,
        'result' => 'Error',
        'error' => [
            'code' => 'OPEN_TABLE_FAILED',
            'message' => 'ไม่สามารถเปิดโต๊ะได้ กรุณาลองอีกครั้ง',
        ],
    ], 500);
}

