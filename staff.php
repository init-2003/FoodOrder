<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/session.php';
if (empty($_SESSION['Em_Id'])) { header('Location: ./#login'); exit; }
header('Cache-Control: private, no-store');
$name = htmlspecialchars((string)($_SESSION['Em_Fullname'] ?? ''), ENT_QUOTES, 'UTF-8');
?><!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>หน้าหลักพนักงาน · อันเกิม</title><link rel="stylesheet" href="assets/css/staff-images.css?v=2"></head>
<body><main class="shell"><?php require __DIR__ . '/includes/staff-header.php'; ?><section class="welcome"><p class="eyebrow" data-i18n="หน้าหลักพนักงาน">หน้าหลักพนักงาน</p><h1 data-i18n="เลือกการทำงาน">เลือกการทำงาน</h1><p data-i18n="จัดการโต๊ะและรูปเมนูอาหารของร้าน">จัดการโต๊ะและรูปเมนูอาหารของร้าน</p><div class="choices"><a class="choice" href="./#tables"><span class="choice-icon">▦</span><span><strong data-i18n="เลือกโต๊ะ">เลือกโต๊ะ</strong><small data-i18n="เปิดโต๊ะและรับรายการอาหาร">เปิดโต๊ะและรับรายการอาหาร</small></span><span class="arrow">→</span></a><a class="choice" href="food-images.php"><span class="choice-icon">▧</span><span><strong data-i18n="จัดการรายการอาหาร">จัดการรายการอาหาร</strong><small data-i18n="เพิ่มและเปลี่ยนรูปอาหาร">เพิ่มและเปลี่ยนรูปอาหาร</small></span><span class="arrow">→</span></a></div></section></main><script src="assets/js/staff-header.js?v=1" defer></script></body></html>
