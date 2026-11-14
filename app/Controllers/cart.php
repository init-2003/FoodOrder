<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Services/functions.php';
require_once __DIR__ . '/../Database/db.php';
require_once __DIR__ . '/../Services/CartService.php';
require_once __DIR__ . '/../Services/AuditLog.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => ['code' => 'METHOD_NOT_ALLOWED', 'message' => 'Method not allowed']], 405);
}
$data = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($data)) jsonResponse(['error' => ['code' => 'INVALID_PAYLOAD', 'message' => 'ข้อมูลตะกร้าไม่ถูกต้อง']], 400);
$action = (string)($data['action'] ?? '');
$docNo = trim((string)($data['doc'] ?? ''));
$branch = trim((string)($data['branch'] ?? ''));
$session = trim((string)($data['session_token'] ?? ''));
if (!in_array($action, ['list', 'upsert', 'delete'], true)
    || $docNo === '' || mb_strlen($docNo, 'UTF-8') > 15
    || !preg_match('/^[A-Za-z0-9_-]{1,3}$/D', $branch)
    || !preg_match('/^st2_[a-f0-9]{64}$/D', $session)) {
    jsonResponse(['error' => ['code' => 'INVALID_PAYLOAD', 'message' => 'ข้อมูลตะกร้าไม่ถูกต้อง']], 400);
}
$key = trim((string)($data['key'] ?? ''));
$operation = trim((string)($data['operation_id'] ?? ''));
$itemInput = is_array($data['item'] ?? null) ? $data['item'] : [];
if ($action === 'upsert' && class_exists(\Symfony\Component\Validator\Validation::class)) {
    $validator = \Symfony\Component\Validator\Validation::createValidator();
    $itemId = trim((string)($itemInput['id'] ?? ''));
    $quantity = filter_var($itemInput['qty'] ?? null, FILTER_VALIDATE_INT);
    $note = trim((string)($itemInput['note'] ?? ''));
    if (count($validator->validate($itemId, [new \Symfony\Component\Validator\Constraints\NotBlank(), new \Symfony\Component\Validator\Constraints\Length(max: 35)]))
        || $quantity === false || count($validator->validate($quantity, [new \Symfony\Component\Validator\Constraints\Range(min: 1, max: 99)]))
        || count($validator->validate($note, [new \Symfony\Component\Validator\Constraints\Length(max: 200)]))) {
        jsonResponse(['error' => ['code' => 'INVALID_ITEM', 'message' => 'ข้อมูลรายการอาหารไม่ถูกต้อง']], 400);
    }
}
$operationHash = hash('sha256', json_encode([$action, $key, $itemInput['id'] ?? null, $itemInput['qty'] ?? null, $itemInput['note'] ?? null, $itemInput['revision'] ?? null], JSON_THROW_ON_ERROR));
if ($action !== 'list' && !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $key)) {
    jsonResponse(['error' => ['code' => 'INVALID_CART_KEY', 'message' => 'รหัสรายการไม่ถูกต้อง']], 400);
}
if ($action !== 'list' && !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $operation)) {
    jsonResponse(['error' => ['code' => 'INVALID_OPERATION', 'message' => 'รหัสคำขอไม่ถูกต้อง']], 400);
}
$pdo = Database::getConnection();
if (!$pdo) {
    jsonResponse(['error' => ['code' => 'DATABASE_UNAVAILABLE', 'message' => 'ไม่สามารถเชื่อมต่อฐานข้อมูลสำหรับตะกร้าได้']], 503);
}

try {
    $result = processCart($pdo, $action, $docNo, $branch, $session, $key, $operation, $itemInput, $operationHash);
    if ($action !== 'list' && ($result['status'] >= 400 || !empty($result['audit']))) {
        auditEvent('cart.' . $action, $result['status'] < 400 ? 'success' : 'failure', ['branch' => $branch, 'document' => $docNo, 'details' => $result['audit'] ?? ['reason' => $result['body']['error']['code'] ?? 'failed']]);
    }
    jsonResponse($result['body'], $result['status']);
} catch (Throwable $e) {
    if ($action !== 'list') auditEvent('cart.' . $action, 'failure', ['branch' => $branch, 'document' => $docNo, 'details' => ['reason' => 'cart_failed']]);
    if ($pdo->inTransaction()) $pdo->rollBack();
    appLog('error', 'cart request failed', ['error' => $e->getMessage()]);
    jsonResponse(['error' => ['code' => 'CART_FAILED', 'message' => 'บันทึกตะกร้าไม่สำเร็จ กรุณาลองอีกครั้ง']], 500);
}
