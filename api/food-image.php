<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') jsonResponse(['error' => ['message' => 'Method not allowed']], 405);
if (empty($_SESSION['Em_Id'])) jsonResponse(['error' => ['message' => 'กรุณาเข้าสู่ระบบพนักงาน']], 401);
$branch = trim((string)($_POST['branch'] ?? ''));
$id = trim((string)($_POST['id'] ?? ''));
$configuredBranch = trim((string)(getenv('DEFAULT_BRANCH_ID') ?: ''));
if ($branch === '' || $configuredBranch === '' || $branch !== $configuredBranch || $id === '' || mb_strlen($id, 'UTF-8') > 35) jsonResponse(['error' => ['message' => 'ข้อมูลสินค้าไม่ถูกต้อง']], 400);
$file = $_FILES['image'] ?? null;
if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) <= 0 || $file['size'] > 5 * 1024 * 1024 || !is_uploaded_file($file['tmp_name'])) jsonResponse(['error' => ['message' => 'กรุณาเลือกรูปภาพขนาดไม่เกิน 5 MB']], 400);
if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) jsonResponse(['error' => ['message' => 'เซิร์ฟเวอร์ยังไม่รองรับการประมวลผลรูปภาพ กรุณาติดต่อผู้ดูแลระบบ']], 503);
$info = @getimagesize($file['tmp_name']);
if (!$info || !in_array($info['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true) || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > 16000000) jsonResponse(['error' => ['message' => 'รองรับรูป JPG, PNG หรือ WebP ขนาดไม่เกิน 16 ล้านพิกเซล']], 400);
$pdo = Database::getConnection();
if (!$pdo) jsonResponse(['error' => ['message' => 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้']], 503);
$directory = __DIR__ . '/../assets/uploads/products';
if (is_link($directory) || (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory))) jsonResponse(['error' => ['message' => 'ไม่สามารถเตรียมพื้นที่เก็บรูปได้']], 503);
$relative = 'assets/uploads/products/' . bin2hex(random_bytes(16)) . '.jpg';
$target = dirname(__DIR__) . '/' . $relative;
$image = @imagecreatefromstring((string)file_get_contents($file['tmp_name']));
if (!$image) jsonResponse(['error' => ['message' => 'ไม่สามารถอ่านไฟล์รูปภาพได้']], 400);
$canvas = imagecreatetruecolor($info[0], $info[1]);
imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
imagecopy($canvas, $image, 0, 0, 0, 0, $info[0], $info[1]);
$saved = imagejpeg($canvas, $target, 85);
imagedestroy($image); imagedestroy($canvas);
if (!$saved) { @unlink($target); jsonResponse(['error' => ['message' => 'ไม่สามารถบันทึกรูปได้']], 503); }
try {
    $pdo->beginTransaction();
    $hex = Database::hexThai($id);
    $stmt = $pdo->prepare("UPDATE Trade SET Link_Part_image = CONVERT(nvarchar(200), CAST(? AS varchar(200))) WHERE Branch_ID = ? AND Trade_ID = CONVERT(nvarchar(35), $hex)");
    $stmt->execute([$relative, $branch]);
    if ($stmt->rowCount() !== 1) throw new RuntimeException('Product missing or not unique');
    $stmt->closeCursor();
    $pdo->commit();
    jsonResponse(['success' => true, 'image' => $relative]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    @unlink($target);
    appLog('error', 'Product image update failed', ['error' => $e->getMessage()]);
    jsonResponse(['error' => ['message' => 'ไม่สามารถบันทึกรูปได้ กรุณาลองอีกครั้ง']], 500);
}
