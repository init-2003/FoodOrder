<?php
declare(strict_types=1);
$appBase = $definition['base'];
require dirname(__DIR__) . '/Middlewares/staff-access.php';
$view = $definition['view'] ?? '';
require_once __DIR__ . '/../Services/ExpenseAccessService.php';
$canViewExpenses = false;
$permissionUnavailable = false;
try {
    $db = Database::getConnection();
    if (!$db) throw new RuntimeException('Database unavailable');
    $canViewExpenses = staffCanViewExpenses($db, trim((string)($_SESSION['Em_Id'] ?? '')), trim((string)($_SESSION['SUser'] ?? '')));
} catch (Throwable $e) {
    appLog('error', 'Expense access check failed', ['error' => $e->getMessage()]);
    $permissionUnavailable = true;
}
if ($view === 'Staff/expenses.php' && !$canViewExpenses) {
    http_response_code($permissionUnavailable ? 503 : 403);
    exit($permissionUnavailable ? 'ไม่สามารถตรวจสอบสิทธิ์ได้ กรุณาลองใหม่อีกครั้ง' : 'คุณไม่มีสิทธิ์เข้าถึงรายงานนี้');
}
if (($definition['page'] ?? '') === 'tables') {
    $uriPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
    $hasBillPath = (bool)preg_match('#/staff/tables/(?:[0-9]{6,10}|[a-p]{64})(?:/|$)#D', $uriPath);
    if (empty($_GET['Fn_Doc_No']) && empty($_GET['doc']) && empty($_GET['bill']) && empty($_GET['table']) && !$hasBillPath) {
        $initialPage = 'tables';
    }
    require __DIR__ . '/main.php';
    return;
}
require dirname(__DIR__) . '/Views/' . $definition['view'];
