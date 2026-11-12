<?php
declare(strict_types=1);
require_once __DIR__ . '/WriteServiceResult.php';
require_once __DIR__ . '/product_image.php';
require_once __DIR__ . '/../Database/db.php';

function storeProductImage(PDO $pdo, string $id, string $branch, array $file, array $info, ?string $directory = null): array
{
$directory ??= dirname(__DIR__, 2) . '/storage/uploads/products';
if (is_link($directory) || (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory))) return writeServiceResult(['error' => ['message' => 'ไม่สามารถเตรียมพื้นที่เก็บรูปได้']], 503);
$directory = realpath($directory);
if ($directory === false) return writeServiceResult(['error' => ['message' => 'ไม่สามารถเตรียมพื้นที่เก็บรูปได้']], 503);
$original = str_replace('\\', '/', (string)($file['name'] ?? ''));
$name = pathinfo(basename($original), PATHINFO_FILENAME);
$name = trim((string)preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+|\.{2,}/u', '_', $name), " .\t\n\r\0\x0B");
$name = mb_substr($name, 0, 120, 'UTF-8');
if ($name === '' || preg_match('/\A(?:CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])\z/iD', $name)) $name = 'image';
$suffix = 0;
do {
    $filename = $name . ($suffix ? '-' . $suffix : '') . '.jpg';
    $target = $directory . DIRECTORY_SEPARATOR . $filename;
    $storedPath = 'file=' . rawurlencode($filename);
    if (strlen(mb_convert_encoding($storedPath, 'UTF-16LE', 'UTF-8')) > 400) return writeServiceResult(['error' => ['message' => 'ชื่อไฟล์หรือพาธรูปภาพยาวเกินไป']], 400);
    if (file_exists($target)) { $handle = false; $suffix++; continue; }
    $handle = @fopen($target, 'x');
    if ($handle === false && !file_exists($target)) return writeServiceResult(['error' => ['message' => 'ไม่สามารถบันทึกรูปได้']], 503);
    $suffix++;
} while ($handle === false);
fclose($handle);
$relative = 'assets/uploads/products/' . $filename;
$image = @imagecreatefromstring((string)file_get_contents($file['tmp_name']));
if (!$image) { @unlink($target); return writeServiceResult(['error' => ['message' => 'ไม่สามารถอ่านไฟล์รูปภาพได้']], 400); }
$canvas = imagecreatetruecolor($info[0], $info[1]);
imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
imagecopy($canvas, $image, 0, 0, 0, 0, $info[0], $info[1]);
$saved = imagejpeg($canvas, $target, 85);
unset($image, $canvas);
if (!$saved) { @unlink($target); return writeServiceResult(['error' => ['message' => 'ไม่สามารถบันทึกรูปได้']], 503); }
try {
    $pdo->beginTransaction();
    $hex = Database::hexThai($id);
    $imageHex = Database::hexThai($storedPath);
    $stmt = $pdo->prepare("UPDATE Trade SET Link_Part_image = CONVERT(nvarchar(200), $imageHex) WHERE Branch_ID = ? AND Trade_ID = CONVERT(nvarchar(35), $hex)");
    $stmt->execute([$branch]);
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

/** Remove the product reference and, when unshared, its uploaded file. */
function removeProductImage(PDO $pdo, string $id, string $branch, string $expectedImage, ?string $directory = null): array
{
    $stagedFile = null;
    $originalFile = null;
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
        $shared = false;
        $url = productImageUrl($current);
        $prefix = 'api/product-image.php?file=';
        if (str_starts_with($url, $prefix)) {
            $filename = rawurldecode(substr($url, strlen($prefix)));
            $directory ??= dirname(__DIR__, 2) . '/storage/uploads/products';
            $originalFile = findProductImage($filename, $directory);
            if ($originalFile !== null) {
                $references = $pdo->prepare("SELECT CONVERT(varbinary(max), Link_Part_image) AS image FROM Trade WITH (HOLDLOCK) WHERE Link_Part_image IS NOT NULL AND Link_Part_image <> ''");
                $references->execute();
                while ($reference = $references->fetch(PDO::FETCH_ASSOC)) {
                    if (productImageUrl(trim(Database::decodeThai($reference['image'] ?? null))) === $url) { $shared = true; break; }
                }
                $references->closeCursor();
                if (!$shared) {
                    $stagedFile = $directory . '/.delete-' . bin2hex(random_bytes(16));
                    if (!@rename($originalFile, $stagedFile)) throw new RuntimeException('Cannot remove product image file');
                }
            }
        }
        $pdo->commit();
        if ($stagedFile !== null && !@unlink($stagedFile)) appLog('error', 'Could not remove staged product image');
        return writeServiceResult(['success' => true, 'image' => 'assets/images/food-placeholder.svg', 'shared' => $shared]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($stagedFile !== null && is_file($stagedFile) && $originalFile !== null && !@rename($stagedFile, $originalFile)) appLog('error', 'Could not restore product image after rollback');
        appLog('error', 'Product image removal failed', ['error' => $e->getMessage()]);
        return writeServiceResult(['error' => ['message' => 'ไม่สามารถลบรูปได้ กรุณาลองอีกครั้ง']], 500);
    }
}
