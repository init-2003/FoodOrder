<?php
declare(strict_types=1);

require_once __DIR__ . '/../Services/functions.php';
require_once __DIR__ . '/../Services/TableService.php';
require_once __DIR__ . '/../Database/db.php';

require_once __DIR__ . '/../Security/session.php';

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
            'message' => 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้',
        ],
        'tables' => [],
    ], 503);
}

try {
    $tables = loadTables($pdo, $branchId);

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

