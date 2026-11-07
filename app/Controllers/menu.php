<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Services/functions.php';
require_once __DIR__ . '/../../app/Database/db.php';
require_once __DIR__ . '/../Services/MenuService.php';

$branchId = trim((string)($_GET['Branch_ID'] ?? $_GET['branch'] ?? (getenv('DEFAULT_BRANCH_ID') ?: '')));
if ($branchId === '') { jsonResponse(['error' => ['code' => 'MISSING_BRANCH', 'message' => 'กรุณาระบุรหัสสาขาใน URL หรือ DEFAULT_BRANCH_ID']], 400); }

$pdo = Database::getConnection();
if (!$pdo) {
    jsonResponse(['error' => ['code' => 'DATABASE_UNAVAILABLE', 'message' => 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้']], 503);
}

try {
    jsonResponse(loadMenu($pdo, $branchId));

} catch (Throwable $e) {
    appLog('error', 'Menu request failed', ['error' => $e->getMessage()]);
    jsonResponse(['error' => ['code' => 'MENU_UNAVAILABLE', 'message' => 'ไม่สามารถโหลดข้อมูลเมนูได้']], 503);
}
