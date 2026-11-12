<?php
declare(strict_types=1);
require_once __DIR__ . '/../../Database/db.php';
require_once __DIR__ . '/../../Services/ExpenseReportService.php';
require_once __DIR__ . '/../../Services/ExpenseWriteService.php';
header('Cache-Control: private, no-store');
$now = new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok'));
$yearInput = $_GET['year'] ?? $now->format('Y');
$monthInput = $_GET['month'] ?? $now->format('n');
$year = filter_var($yearInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1900, 'max_range' => 9999]]);
$month = filter_var($monthInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]);
$error = '';
$years = [];
$report = ['total' => null, 'items' => []];
$expenseOptions = ['types' => [], 'staff' => []];
$optionsError = '';
$_SESSION['expense_submission'] = ['token' => bin2hex(random_bytes(32))];
try {
    $optionsDb = Database::getConnection();
    if (!$optionsDb) throw new RuntimeException('Database unavailable');
    $expenseOptions = loadExpenseOptions($optionsDb);
    if (!$expenseOptions['types'] || !$expenseOptions['staff']) $optionsError = 'ไม่พบประเภทค่าใช้จ่ายหรือข้อมูลพนักงาน';
} catch (Throwable $e) {
    appLog('error', 'Expense options unavailable', ['error' => $e->getMessage()]);
    $optionsError = 'ไม่สามารถโหลดข้อมูลสำหรับบันทึกค่าใช้จ่ายได้';
}
try {
    $db = Database::getConnection();
    if (!$db) throw new RuntimeException('Database unavailable');
    $years = loadExpenseYears($db);
    if ($year === false || $month === false) {
        http_response_code(400);
        $error = 'กรุณาเลือกปีและเดือนให้ถูกต้อง';
    } elseif (!$years) {
        $error = 'ไม่มีข้อมูลรายจ่ายในฐานข้อมูล';
    } elseif (!in_array($year, $years, true)) {
        if (isset($_GET['year'])) {
            http_response_code(400);
            $error = 'ไม่มีข้อมูลรายจ่ายสำหรับปีที่เลือก';
        }
        $year = $years[0];
    }
    if ($error === '') $report = loadMonthlyExpenses($db, $year, $month);
} catch (Throwable $e) {
    appLog('error', 'Expense report failed', ['error' => $e->getMessage()]);
    http_response_code(503);
    $error = 'ไม่สามารถโหลดรายงานได้ กรุณาลองใหม่อีกครั้ง';
}
$displayYear = $year === false ? ($years[0] ?? (int)$now->format('Y')) : $year;
$displayMonth = $month === false ? (int)$now->format('n') : $month;
$months = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
?><!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <base href="<?= htmlspecialchars($appBase ?? './', ENT_QUOTES, 'UTF-8') ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php require __DIR__ . '/../browser-head.php'; ?>
  <link rel="stylesheet" href="assets/css/staff-images.css?v=<?= hash_file('sha256', __DIR__ . '/../../../public/assets/css/staff-images.css') ?>">
  <link rel="stylesheet" href="assets/css/expenses.css?v=<?= hash_file('sha256', __DIR__ . '/../../../public/assets/css/expenses.css') ?>">
</head>
<body class="staff-home expense-page has-staff-sidebar">
<?php require __DIR__ . '/staff-header.php'; ?>
<main class="expense-shell">
  <div class="expense-heading">
    <div><h1 data-i18n="ค่าใช้จ่าย">ค่าใช้จ่าย</h1></div>
    <button type="button" class="expense-add" id="expense-open" <?= $optionsError !== '' ? 'disabled' : '' ?>>บันทึกค่าใช้จ่าย</button>
  </div>
  <?php if (isset($_GET['saved']) && preg_match('/^[a-zA-Z0-9]{1,15}$/D', (string)$_GET['saved'])): ?><p class="expense-saved" role="status">บันทึกค่าใช้จ่ายแล้ว เลขที่เอกสาร <?= htmlspecialchars((string)$_GET['saved'], ENT_QUOTES, 'UTF-8') ?></p><?php endif ?>
  <?php if ($optionsError !== ''): ?><p class="expense-message" role="status"><?= htmlspecialchars($optionsError, ENT_QUOTES, 'UTF-8') ?></p><?php endif ?>
  <dialog id="expense-dialog" class="expense-dialog" aria-labelledby="expense-dialog-title">
    <form id="expense-form">
      <div class="expense-dialog-header"><div><h2 id="expense-dialog-title">บันทึกค่าใช้จ่าย</h2></div><button type="button" id="expense-close" aria-label="ปิด"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg></button></div>
      <input type="hidden" name="submission_token" value="<?= htmlspecialchars($_SESSION['expense_submission']['token'], ENT_QUOTES, 'UTF-8') ?>">
      <label>วันที่ <input type="date" name="date" value="<?= $now->format('Y-m-d') ?>" required></label>
      <label>ประเภทค่าใช้จ่าย <select name="type" required><option value="">เลือกประเภทค่าใช้จ่าย</option><?php foreach ($expenseOptions['types'] as $type): ?><option value="<?= htmlspecialchars($type['id'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($type['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option><?php endforeach ?></select></label>
      <label>รายละเอียด <textarea name="detail" rows="3" maxlength="1000" placeholder="ระบุรายละเอียดค่าใช้จ่าย" required></textarea></label>
      <label>จำนวนเงิน (บาท) <input type="number" name="amount" min="0.01" max="999999999999.99" step="0.01" inputmode="decimal" placeholder="0.00" required></label>
      <label>ผู้จ่าย <select name="payer" required><option value="">เลือกผู้จ่าย</option><?php foreach ($expenseOptions['staff'] as $payer): ?><option value="<?= htmlspecialchars($payer['id'], ENT_QUOTES, 'UTF-8') ?>" <?= $payer['id'] === trim((string)($_SESSION['Em_Id'] ?? '')) ? 'selected' : '' ?>><?= htmlspecialchars($payer['name'] !== '' ? $payer['name'] : $payer['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option><?php endforeach ?></select></label>
      <p id="expense-form-error" role="alert" hidden></p>
      <div class="expense-dialog-actions"><button type="button" id="expense-cancel">ยกเลิก</button><button type="submit" id="expense-save">บันทึก</button></div>
    </form>
  </dialog>
  <form method="get" action="staff/expenses/" class="expense-filters">
    <label><span data-i18n="เดือน">เดือน</span><select name="month"><?php foreach ($months as $i => $name): ?><option value="<?= $i + 1 ?>" data-i18n="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" <?= $displayMonth === $i + 1 ? 'selected' : '' ?>><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach ?></select></label>
    <label><span data-i18n="ปี (ค.ศ.)">ปี (ค.ศ.)</span><select name="year" <?= !$years ? 'disabled' : '' ?>><?php if (!$years): ?><option data-i18n="ไม่มีข้อมูล">ไม่มีข้อมูล</option><?php else: foreach ($years as $availableYear): ?><option value="<?= $availableYear ?>" <?= $displayYear === $availableYear ? 'selected' : '' ?>><?= $availableYear ?></option><?php endforeach; endif ?></select></label>
    <button type="submit" data-i18n="แสดงรายงาน">แสดงรายงาน</button>
  </form>
  <?php if ($error !== ''): ?>
    <div class="expense-panel"><p class="expense-message" role="alert" data-i18n="<?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p></div>
  <?php else: ?>
  <div class="expense-layout">
    <section class="expense-panel expense-main" aria-labelledby="expense-details-title">
      <div class="expense-summary"><span data-i18n="รวมค่าใช้จ่ายทั้งหมด">รวมค่าใช้จ่ายทั้งหมด</span><strong<?= $report['total'] === null ? ' data-i18n="ไม่มีข้อมูล"' : '' ?>><?= $report['total'] === null ? 'ไม่มีข้อมูล' : '฿ ' . number_format($report['total'], 2) ?></strong></div>
      <div class="expense-section-heading"><h2 id="expense-details-title" data-i18n="รายจ่ายแยกตามประเภท">รายจ่ายแยกตามประเภท</h2><span><?= count($report['items']) ?> <span data-i18n-count="<?= count($report['items']) ?>">ประเภท</span></span></div>
      <?php if (!$report['items']): ?><p class="expense-message" data-i18n="ไม่มีข้อมูลรายจ่ายในเดือนนี้">ไม่มีข้อมูลรายจ่ายในเดือนนี้</p><?php else: ?>
      <?php
      $amounts = array_map(static fn(array $item): float => (float)$item['Ex_Price'], $report['items']);
      $total = $report['total'];
      $canShowShares = $total !== null && $total > 0 && min($amounts) >= 0;
      ?>
      <?php if (!$canShowShares): ?><p class="expense-message" data-i18n="ไม่สามารถแสดงสัดส่วนได้เมื่อยอดรวมไม่เป็นบวกหรือมีรายการติดลบ">ไม่สามารถแสดงสัดส่วนได้เมื่อยอดรวมไม่เป็นบวกหรือมีรายการติดลบ</p><?php endif ?>
      <div class="expense-chart" aria-label="สัดส่วนรายจ่ายเทียบกับยอดรวมทั้งหมด" data-i18n-aria-label="สัดส่วนรายจ่ายเทียบกับยอดรวมทั้งหมด">
        <?php foreach ($report['items'] as $item):
          $amount = (float)$item['Ex_Price'];
          $share = $canShowShares ? ($amount / $total * 100) : null;
        ?>        <div class="expense-chart-row">
          <?php $categoryName = htmlspecialchars(Database::decodeThai($item['Expense_Type_Name'] ?? null), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
          <div class="expense-chart-label"><span><?= $categoryName ?></span><strong><?= number_format($amount, 2) ?> <span data-i18n="บาท">บาท</span><?= $share !== null ? ' · ' . number_format($share, 1) . '%' : '' ?></strong></div>
          <?php if ($share !== null): ?><div class="expense-chart-track"><span class="expense-chart-bar" style="left:0;width:<?= number_format(min(100, $share), 4, '.', '') ?>%"></span></div><?php endif ?>
        </div>
        <?php endforeach ?>
      </div>
      <?php endif ?>
    </section>
    <aside class="expense-panel expense-context" aria-labelledby="expense-context-title">
      <h2 id="expense-context-title" data-i18n="รายละเอียดรายงาน">รายละเอียดรายงาน</h2>
      <dl><div><dt data-i18n="ช่วงเวลา">ช่วงเวลา</dt><dd><span data-i18n="<?= htmlspecialchars($months[$displayMonth - 1], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($months[$displayMonth - 1], ENT_QUOTES, 'UTF-8') ?></span> <?= $displayYear ?></dd></div><div><dt data-i18n="รายการ">รายการ</dt><dd><?= count($report['items']) ?> <span data-i18n-count="<?= count($report['items']) ?>">ประเภท</span></dd></div></dl>
    </aside>
  </div>
  <?php endif ?>
</main>
<script src="assets/js/staff-header.js?v=<?= hash_file('sha256', __DIR__ . '/../../../public/assets/js/staff-header.js') ?>" defer></script>
<script src="assets/js/expense-write.js?v=<?= hash_file('sha256', __DIR__ . '/../../../public/assets/js/expense-write.js') ?>" defer></script>
</body></html>
