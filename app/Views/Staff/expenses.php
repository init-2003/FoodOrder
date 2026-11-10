<?php
declare(strict_types=1);
require_once __DIR__ . '/../../Database/db.php';
require_once __DIR__ . '/../../Services/ExpenseReportService.php';
header('Cache-Control: private, no-store');
$now = new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok'));
$yearInput = $_GET['year'] ?? $now->format('Y');
$monthInput = $_GET['month'] ?? $now->format('n');
$year = filter_var($yearInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1900, 'max_range' => 9999]]);
$month = filter_var($monthInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]);
$error = '';
$years = [];
$report = ['total' => null, 'items' => []];
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
  <link rel="stylesheet" href="assets/css/staff-images.css?v=<?= filemtime(__DIR__ . '/../../../public/assets/css/staff-images.css') ?>">
  <link rel="stylesheet" href="assets/css/expenses.css?v=<?= filemtime(__DIR__ . '/../../../public/assets/css/expenses.css') ?>">
</head>
<body class="staff-home expense-page has-staff-sidebar">
<?php require __DIR__ . '/staff-header.php'; ?>
<main class="expense-shell">
  <div class="expense-heading">
    <div><h1 data-i18n="ค่าใช้จ่าย">ค่าใช้จ่าย</h1></div>
  </div>
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
<script src="assets/js/staff-header.js?v=<?= filemtime(__DIR__ . '/../../../public/assets/js/staff-header.js') ?>" defer></script>
</body></html>
