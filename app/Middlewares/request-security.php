<?php
declare(strict_types=1);
require_once __DIR__ . '/../Database/db.php';
require_once __DIR__ . '/../Security/request_origin.php';
require_once __DIR__ . '/../Http/JsonResponse.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_SERVER['HTTP_ORIGIN'], $_SERVER['HTTP_HOST'])) {
    if (!requestOriginMatches((string)$_SERVER['HTTP_ORIGIN'], (string)$_SERVER['HTTP_HOST'])) {
        jsonResponse(['error' => ['code' => 'INVALID_ORIGIN', 'message' => 'คำขอไม่ถูกต้อง']], 403);
    }
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_once __DIR__ . '/../Security/session.php';
    $providedToken = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($providedToken === '' || !hash_equals((string)$_SESSION['csrf_token'], $providedToken)) {
        jsonResponse(['error' => ['code' => 'INVALID_CSRF', 'message' => 'เซสชันหมดอายุ กรุณารีเฟรชหน้าแล้วลองอีกครั้ง']], 403);
    }
}
