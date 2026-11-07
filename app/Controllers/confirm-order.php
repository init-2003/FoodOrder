<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Services/functions.php';
require_once __DIR__ . '/../../app/Database/db.php';
require_once __DIR__ . '/../../app/Security/session.php';
require_once __DIR__ . '/../Services/ConfirmOrderService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => ['code' => 'METHOD_NOT_ALLOWED', 'message' => 'Method not allowed']], 405);
}
if (empty($_SESSION['Em_Id'])) {
    jsonResponse(['error' => ['code' => 'UNAUTHORIZED', 'message' => 'กรุณาเข้าสู่ระบบพนักงาน']], 401);
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput ?: '{}', true);

$docNo = trim((string)($data['docNo'] ?? $data['Fn_Doc_No'] ?? $data['doc'] ?? ''));
$tradeId = trim((string)($data['tradeId'] ?? $data['Trade_ID'] ?? ''));
$branchId = trim((string)($data['branch'] ?? $data['Branch_ID'] ?? (getenv('DEFAULT_BRANCH_ID') ?: '')));
if ($branchId === '') { jsonResponse(['error' => ['code' => 'MISSING_BRANCH', 'message' => 'กรุณาระบุรหัสสาขาใน URL หรือ DEFAULT_BRANCH_ID']], 400); }

if ($docNo === '') {
    jsonResponse(['error' => ['code' => 'INVALID_PAYLOAD', 'message' => 'กรุณาระบุเลขที่บิล (Fn_Doc_No)']], 400);
}


$pdo = Database::getConnection();
if (!$pdo) {
    jsonResponse(['error' => ['code' => 'DATABASE_UNAVAILABLE', 'message' => 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้']], 503);
}

try {
    $result = confirmPendingOrder($pdo, $docNo, $tradeId, $branchId);
    jsonResponse($result['body'], $result['status']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    appLog('error', 'confirm-order request failed', ['error' => $e->getMessage()]);
    jsonResponse(['error' => ['code' => 'CONFIRM_FAILED', 'message' => 'เกิดข้อผิดพลาดในการยืนยันรายการ กรุณาลองอีกครั้ง']], 500);
}

