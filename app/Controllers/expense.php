<?php
declare(strict_types=1);
require_once __DIR__ . '/../Services/functions.php';
require_once __DIR__ . '/../Security/session.php';
require_once __DIR__ . '/../Services/ExpenseAccessService.php';
require_once __DIR__ . '/../Services/ExpenseWriteService.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') jsonResponse(['error' => ['message' => 'Method not allowed']], 405);
$employee = trim((string)($_SESSION['Em_Id'] ?? '')); $user = trim((string)($_SESSION['SUser'] ?? ''));
if ($employee === '' || $user === '') jsonResponse(['error' => ['message' => 'กรุณาเข้าสู่ระบบพนักงาน']], 401);
$db = Database::getConnection();
if (!$db) jsonResponse(['error' => ['message' => 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้']], 503);
try {
    if (!staffCanViewExpenses($db, $employee, $user)) jsonResponse(['error' => ['message' => 'คุณไม่มีสิทธิ์บันทึกค่าใช้จ่าย']], 403);
    $token = (string)($_POST['submission_token'] ?? '');
    if ($token === '' || !isset($_SESSION['expense_submission']) || !hash_equals((string)$_SESSION['expense_submission']['token'], $token)) jsonResponse(['error' => ['message' => 'แบบฟอร์มหมดอายุ กรุณาเปิดหน้าใหม่']], 400);
    if (isset($_SESSION['expense_submission']['doc'])) jsonResponse(['success' => true, 'docNo' => $_SESSION['expense_submission']['doc'], 'date' => $_SESSION['expense_submission']['date']]);
    $data = validateExpense($_POST);
    $branch = trim((string)(getenv('DEFAULT_BRANCH_ID') ?: ''));
    $doc = saveExpense($db, $branch, $user, $data);
    $_SESSION['expense_submission']['doc'] = $doc;
    $_SESSION['expense_submission']['date'] = $data['date'];
    jsonResponse(['success' => true, 'docNo' => $doc, 'date' => $data['date']]);
} catch (InvalidArgumentException $e) {
    jsonResponse(['error' => ['message' => $e->getMessage()]], 422);
} catch (Throwable $e) {
    appLog('error', 'Expense save failed', ['error' => $e->getMessage()]);
    jsonResponse(['error' => ['message' => 'ไม่สามารถบันทึกค่าใช้จ่ายได้ กรุณาติดต่อผู้ดูแลระบบ']], 503);
}
