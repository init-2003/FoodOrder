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

/** Remove the selected product's image reference without deleting files shared by other products. */
function removeProductImage(PDO $pdo, string $id, string $branch, string $expectedImage): array
{
    try {
        $pdo->beginTransaction();
        $hex = Database::hexThai($id);
        $query = $pdo->prepare("SELECT CONVERT(varbinary(max), Link_Part_image) AS image FROM Trade WITH (UPDLOCK, HOLDLOCK) WHERE Branch_ID = ? AND Trade_ID = CONVERT(nvarchar(35), $hex)");
        $query->execute([$branch]);
        $rows = $query->fetchAll(PDO::FETCH_ASSOC);
        $query->closeCursor();
        if (count($rows) !== 1) {
            $pdo->rollBack();
            return writeServiceResult(['error' => ['message' => 'ไม่พบเมนูที่เลือก']], 404);
        }
        $current = trim(Database::decodeThai($rows[0]['image'] ?? null));
        if ($current === '' || !hash_equals(productImageUrl($current), $expectedImage)) {
            $pdo->rollBack();
            return writeServiceResult(['error' => ['message' => 'รูปอาหารมีการเปลี่ยนแปลง กรุณารีเฟรชหน้าแล้วลองอีกครั้ง']], 409);
        }
        $update = $pdo->prepare("UPDATE Trade SET Link_Part_image = '' WHERE Branch_ID = ? AND Trade_ID = CONVERT(nvarchar(35), $hex)");
        $update->execute([$branch]);
        if ($update->rowCount() !== 1) throw new RuntimeException('Product image was not updated');
        $update->closeCursor();
        $pdo->commit();
        return writeServiceResult(['success' => true, 'image' => 'assets/images/food-placeholder.svg']);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        appLog('error', 'Product image removal failed', ['error' => $e->getMessage()]);
        return writeServiceResult(['error' => ['message' => 'ไม่สามารถลบรูปได้ กรุณาลองอีกครั้ง']], 500);
    }
}
