<?php
declare(strict_types=1);
require_once __DIR__ . '/../../Database/db.php';
require_once __DIR__ . '/../../Security/session.php';
header('Cache-Control: private, no-store');
$branch = trim((string)(getenv('DEFAULT_BRANCH_ID') ?: ''));
?><!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <base href="<?= htmlspecialchars($appBase ?? './', ENT_QUOTES, 'UTF-8') ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#ffffff">
  <title>จัดการรายการอาหาร · อันเกิม</title>
  <link rel="stylesheet" href="assets/css/staff-images.css?v=3">
  <link rel="stylesheet" href="assets/css/food-images.css?v=<?= filemtime(__DIR__ . '/../../../public/assets/css/food-images.css') ?>">
</head>
<body class="staff-home food-image-page">
  <?php require __DIR__ . '/staff-header.php'; ?>
  <main class="shell food-image-shell">
    <section class="manage" aria-labelledby="menu-management-title">
      <div class="manage-heading">
        <div>
          <h1 id="menu-management-title" data-i18n="รายการอาหาร">รายการอาหาร</h1>
          <p data-i18n="เพิ่มหรือเปลี่ยนรูปอาหารที่แสดงในเมนู">เพิ่มหรือเปลี่ยนรูปอาหารที่แสดงในเมนู</p>
        </div>
      </div>
      <div class="filters">
        <label class="menu-search">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.4"/><path d="m16 16 4.2 4.2"/></svg>
          <input id="search" type="search" data-i18n-placeholder="ค้นหาชื่อหรือรหัสอาหาร" placeholder="ค้นหาชื่อหรือรหัสอาหาร" aria-label="ค้นหาชื่อหรือรหัสอาหาร" autocomplete="off">
        </label>
        <select id="category" aria-label="หมวดหมู่"><option value="" data-i18n="ทุกหมวดหมู่">ทุกหมวดหมู่</option></select>
      </div>
      <p id="status" role="status" data-i18n="กำลังโหลดรายการอาหาร...">กำลังโหลดรายการอาหาร...</p>
      <div id="items" class="items"></div>
    </section>
  </main>
  <script>window.foodImagesConfig = <?= json_encode(['branch' => $branch, 'csrf' => $_SESSION['csrf_token']], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
  <script src="assets/js/food-images.js?v=<?= filemtime(__DIR__ . '/../../../public/assets/js/food-images.js') ?>" defer></script>
  <script src="assets/js/staff-header.js?v=<?= filemtime(__DIR__ . '/../../../public/assets/js/staff-header.js') ?>" defer></script>
</body>
</html>
