<?php
declare(strict_types=1);
require_once __DIR__ . '/WriteServiceResult.php';
require_once __DIR__ . '/product_image.php';
require_once __DIR__ . '/../Database/db.php';

function storeProductImage(PDO $pdo, string $id, string $branch, array $file, array $info, ?string $directory = null): array
{
$directory ??= dirname(__DIR__, 2) . '/storage/uploads/products';
if (is_link($directory) || (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory))) return writeServiceResult(['error' => ['message' => 'ไม่สามารถเตรียมพื้นที่เก็บรูปได้']], 503);
$relative = 'assets/uploads/products/' . bin2hex(random_bytes(16)) . '.jpg';
$target = $directory . '/' . basename($relative);
$image = @imagecreatefromstring((string)file_get_contents($file['tmp_name']));
if (!$image) return writeServiceResult(['error' => ['message' => 'ไม่สามารถอ่านไฟล์รูปภาพได้']], 400);
$canvas = imagecreatetruecolor($info[0], $info[1]);
imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
imagecopy($canvas, $image, 0, 0, 0, 0, $info[0], $info[1]);
$saved = imagejpeg($canvas, $target, 85);
unset($image, $canvas);
if (!$saved) { @unlink($target); return writeServiceResult(['error' => ['message' => 'ไม่สามารถบันทึกรูปได้']], 503); }
try {
    $pdo->beginTransaction();
    $hex = Database::hexThai($id);
    $stmt = $pdo->prepare("UPDATE Trade SET Link_Part_image = CONVERT(nvarchar(200), CAST(? AS varchar(200))) WHERE Branch_ID = ? AND Trade_ID = CONVERT(nvarchar(35), $hex)");
    $stmt->execute([$relative, $branch]);
    if ($stmt->rowCount() !== 1) throw new RuntimeException('Product missing or not unique');
    $stmt->closeCursor();
    $pdo->commit();
    return writeServiceResult(['success' => true, 'image' => productImageUrl($relative)]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    @unlink($target);
    appLog('error', 'Product image update failed', ['error' => $e->getMessage()]);
    return writeServiceResult(['error' => ['message' => 'ไม่สามารถบันทึกรูปได้ กรุณาลองอีกครั้ง']], 500);
}

}
