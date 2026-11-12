<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Services/functions.php';
require_once __DIR__ . '/../Database/db.php';
require_once __DIR__ . '/../Services/OrderService.php';

require_once __DIR__ . '/../../app/Security/session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => ['code' => 'METHOD_NOT_ALLOWED', 'message' => 'Method not allowed']], 405);
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput ?: '{}', true);

if (!is_array($data) || empty($data['items']) || !is_array($data['items'])) {
    jsonResponse(['error' => ['code' => 'INVALID_PAYLOAD', 'message' => 'ไม่มีรายการอาหารในคำสั่งซื้อ']], 400);
}


$pdo = Database::getConnection();
if (!$pdo) {
    jsonResponse(['error' => ['code' => 'DATABASE_UNAVAILABLE', 'message' => 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้']], 503);
}

$docInput = trim((string)($data['doc'] ?? $data['docNo'] ?? $data['Fn_Doc_No'] ?? ''));
$tableInput = trim((string)($data['table'] ?? ''));
if ($docInput === '' && $tableInput === '') {
    jsonResponse(['error' => ['code' => 'MISSING_TABLE_OR_BILL', 'message' => 'กรุณาระบุหมายเลขโต๊ะหรือเลขที่บิล']], 400);
}
$tablePadded = str_pad((string)$tableInput, 3, '0', STR_PAD_LEFT);
$tableUnpadded = ltrim((string)$tableInput, '0');
$branchId = trim((string)($data['branch'] ?? $data['Branch_ID'] ?? (getenv('DEFAULT_BRANCH_ID') ?: '')));
if ($branchId === '') { jsonResponse(['error' => ['code' => 'MISSING_BRANCH', 'message' => 'กรุณาระบุรหัสสาขาใน URL หรือ DEFAULT_BRANCH_ID']], 400); }
$pbUser = trim((string)($_SESSION['Em_Id'] ?? ''));

$sessionToken = trim((string)($data['session_token'] ?? $data['sessionToken'] ?? ''));
$idempotencyKey = trim((string)($data['idempotency_key'] ?? $data['idempotencyKey'] ?? $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ''));
$fromCart = ($data['from_cart'] ?? false) === true;
if ($fromCart && (!preg_match('/^st2_[a-f0-9]{64}$/D', $sessionToken) || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $idempotencyKey))) {
    jsonResponse(['error' => ['code' => 'INVALID_CART', 'message' => 'ข้อมูลตะกร้าไม่ถูกต้อง']], 400);
}
$requestHash = hash('sha256', json_encode([$branchId, $docInput, $tableInput, $sessionToken, $fromCart, $data['items']], JSON_THROW_ON_ERROR));

// -------------------------------------------------------------
// Idempotency check: prevent duplicate submissions
// -------------------------------------------------------------
if ($idempotencyKey !== '') {
    $idemDir = __DIR__ . '/../../storage/idempotency';
    $idemFile = $idemDir . '/' . hash('sha256', $idempotencyKey) . '.json';
    if (file_exists($idemFile)) {
        $cached = json_decode((string)file_get_contents($idemFile), true);
        if (is_array($cached) && (time() - ($cached['time'] ?? 0)) < 600 && !empty($cached['response'])) {
            if (isset($cached['requestHash']) && !hash_equals((string)$cached['requestHash'], $requestHash)) {
                jsonResponse(['error' => ['code' => 'IDEMPOTENCY_MISMATCH', 'message' => 'ข้อมูลคำสั่งซื้อไม่ตรงกับคำขอก่อนหน้า']], 409);
            }
            if ($fromCart && !isset($cached['requestHash'])) {
                jsonResponse(['error' => ['code' => 'IDEMPOTENCY_MISMATCH', 'message' => 'กรุณาลองสั่งใหม่อีกครั้ง']], 409);
            }
            $cachedResp = $cached['response'];
            $cachedResp['idempotent'] = true;
            jsonResponse($cachedResp, 200);
        }
    }
}

try {
    $result = submitOrder($pdo, $data, $docInput, $tableInput, $tablePadded, $tableUnpadded, $branchId, $pbUser, $sessionToken, $idempotencyKey, $fromCart, $requestHash);
    jsonResponse($result['body'], $result['status']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    appLog('error', 'order request failed', ['error' => $e->getMessage()]);
    jsonResponse(['error' => ['code' => 'ORDER_FAILED', 'message' => 'เกิดข้อผิดพลาดในการบันทึกออเดอร์ กรุณาลองอีกครั้ง']], 500);
}
