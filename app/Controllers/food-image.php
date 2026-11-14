<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/Services/functions.php';
require_once __DIR__ . '/../../app/Database/db.php';
require_once __DIR__ . '/../../app/Security/session.php';
require_once __DIR__ . '/../Services/ProductUploadService.php';
require_once __DIR__ . '/../Services/AuditLog.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') jsonResponse(['error' => ['message' => 'Method not allowed']], 405);
if (empty($_SESSION['Em_Id'])) jsonResponse(['error' => ['message' => 'กรุณาเข้าสู่ระบบพนักงาน']], 401);
$branch = trim((string)($_POST['branch'] ?? ''));
$id = trim((string)($_POST['id'] ?? ''));
$configuredBranch = trim((string)(getenv('DEFAULT_BRANCH_ID') ?: ''));
if ($branch === '' || $configuredBranch === '' || $branch !== $configuredBranch || $id === '' || mb_strlen($id, 'UTF-8') > 35) jsonResponse(['error' => ['message' => 'ข้อมูลสินค้าไม่ถูกต้อง']], 400);
if (($_POST['action'] ?? '') === 'delete') {
    $expected = trim((string)($_POST['expected_image'] ?? ''));
    if ($expected === '' || strlen($expected) > 2048) jsonResponse(['error' => ['message' => 'ข้อมูลรูปภาพไม่ถูกต้อง']], 400);
    $pdo = Database::getConnection();
    if (!$pdo) jsonResponse(['error' => ['message' => 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้']], 503);
    $result = removeProductImage($pdo, $id, $branch, $expected);
    auditEvent('product_image.delete', $result['status'] < 400 ? 'success' : 'failure', ['branch' => $branch, 'subject' => $id, 'details' => ['before' => $expected, 'after' => $result['status'] < 400 ? null : $expected]]);
    jsonResponse($result['body'], $result['status']);
}
$file = $_FILES['image'] ?? null;
if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) <= 0 || $file['size'] > 5 * 1024 * 1024 || !is_uploaded_file($file['tmp_name'])) jsonResponse(['error' => ['message' => 'กรุณาเลือกรูปภาพขนาดไม่เกิน 5 MB']], 400);
if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) jsonResponse(['error' => ['message' => 'เซิร์ฟเวอร์ยังไม่รองรับการประมวลผลรูปภาพ กรุณาติดต่อผู้ดูแลระบบ']], 503);
$info = @getimagesize($file['tmp_name']);
if (!$info || !in_array($info['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true) || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > 16000000) jsonResponse(['error' => ['message' => 'รองรับรูป JPG, PNG หรือ WebP ขนาดไม่เกิน 16 ล้านพิกเซล']], 400);
$pdo = Database::getConnection();
if (!$pdo) jsonResponse(['error' => ['message' => 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้']], 503);
$result = storeProductImage($pdo, $id, $branch, $file, $info);
auditEvent('product_image.save', $result['status'] < 400 ? 'success' : 'failure', ['branch' => $branch, 'subject' => $id, 'details' => ['after' => $result['body']['image'] ?? null]]);
jsonResponse($result['body'], $result['status']);
