<?php
require_once __DIR__ . '/../Security/security.php';
$script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$base = rtrim(str_replace('\\', '/', dirname($script)), '/');
if (preg_match('#/[0-9]{6,10}$#', $base)) $base = preg_replace('#/[0-9]{6,10}$#', '', $base);
if (str_ends_with($base, '/pages')) $base = substr($base, 0, -6);
if (str_ends_with($base, '/staff/tables')) $base = substr($base, 0, -13);
if (str_ends_with($base, '/staff')) $base = substr($base, 0, -6);
$base = ($base === '' || $base === '.') ? '/' : $base . '/';
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
  <meta name="theme-color" content="#fb784c">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <meta name="apple-mobile-web-app-title" content="อันเกิม">
  <meta name="description" content="อันเกิม — เลือกความอร่อยในแบบคุณ สั่งอาหารจากโต๊ะได้ง่าย ๆ">
  <base href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>">
  <?php require __DIR__ . '/browser-head.php'; ?>
  <link rel="manifest" href="manifest.php">
  <link rel="apple-touch-icon" href="assets/images/pwa-180.png">
  <script>
    (function() {
      try {
        var isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        if (isIOS) document.documentElement.classList.add('ios-system-font');
        var isClosed = <?= !empty($initialClosed) || ($initialPage === 'closed') ? 'true' : 'false' ?>;
        var isCancelled = <?= !empty($initialCancelled) ? 'true' : 'false' ?>;
        var initPage = '<?= htmlspecialchars($initialPage ?? 'menu', ENT_QUOTES, 'UTF-8') ?>';
        var hasStaff = <?= !empty($staffEmId) ? 'true' : 'false' ?>;
        var hasScannedBill = <?= (!empty($fnDocNo) || !empty($tableNo)) ? 'true' : 'false' ?>;
        var h = (window.location.hash || '').replace(/^#/, '');
        if (h === 'card') h = 'cart';
        var validHashes = ['login', 'tables', 'menu', 'cart', 'card', 'history', 'closed'];
        if (hasStaff && h === 'tables') {
          try {
            if (window.location.search) {
              window.history.replaceState(null, '', window.location.pathname + '#tables');
            }
          } catch (e) {}
        } else if (!hasStaff && hasScannedBill && (h === 'tables' || h === 'login')) {
          h = 'menu';
        }
        if (!hasStaff && !hasScannedBill) h = 'login';
        var p = (isCancelled || (isClosed && h !== 'history')) ? 'closed' : (validHashes.indexOf(h) !== -1 ? h : (hasStaff && h === 'tables' ? 'tables' : initPage));
        document.documentElement.setAttribute('data-page', p);
        document.documentElement.setAttribute('data-initial-route', p);
      } catch (e) {}
    })();
  </script>
  <style>
    [x-cloak] { display: none !important; }
    /* Active & pre-paint route locks (Zero flicker guarantee) */
    html[data-page="login"] .main-content > section[aria-label="เมนูอาหาร"],
    html[data-page="tables"] .main-content > section[aria-label="เมนูอาหาร"],
    html[data-page="history"] .main-content > section[aria-label="เมนูอาหาร"],
    html[data-page="closed"] .main-content > section[aria-label="เมนูอาหาร"],
    html[data-initial-route="login"] .main-content > section[aria-label="เมนูอาหาร"],
    html[data-initial-route="tables"] .main-content > section[aria-label="เมนูอาหาร"],
    html[data-initial-route="history"] .main-content > section[aria-label="เมนูอาหาร"],
    html[data-initial-route="closed"] .main-content > section[aria-label="เมนูอาหาร"] {
      display: none !important;
    }
    html[data-page="login"] .login-section,
    html[data-initial-route="login"] .login-section {
      display: flex !important;
    }
    html[data-page="tables"] .tables-section,
    html[data-initial-route="tables"] .tables-section {
      display: block !important;
    }
    html[data-page="login"] .cart-panel,
    html[data-page="login"] .mobile-bottom,
    html[data-page="login"] .sidebar,
    html[data-page="tables"] .sidebar,
    html[data-page="tables"] .cart-panel,
    html[data-page="tables"] .mobile-bottom,
    html[data-initial-route="login"] .cart-panel,
    html[data-initial-route="login"] .mobile-bottom,
    html[data-initial-route="login"] .sidebar,
    html[data-initial-route="tables"] .sidebar,
    html[data-initial-route="tables"] .cart-panel,
    html[data-initial-route="tables"] .mobile-bottom {
      display: none !important;
    }
    @media (max-width: 760px), (min-width: 761px) and (max-width: 1100px) and (orientation: portrait) {
      html[data-page="cart"] .main-content,
      html[data-initial-route="cart"] .main-content {
        display: none !important;
      }
      html[data-page="cart"] .cart-panel,
      html[data-initial-route="cart"] .cart-panel {
        display: flex !important;
      }
      html[data-page="history"] .cart-panel,
      html[data-page="menu"] .cart-panel,
      html[data-page="tables"] .cart-panel,
      html[data-initial-route="tables"] .cart-panel {
        display: none !important;
      }
      html[data-page="cart"] .topbar,
      html[data-page="history"] .topbar,
      html[data-page="menu"] .topbar,
      html[data-page="tables"] .topbar,
      html[data-page="login"] .topbar,
      html[data-initial-route="cart"] .topbar,
      html[data-initial-route="menu"] .topbar,
      html[data-initial-route="tables"] .topbar,
      html[data-initial-route="login"] .topbar {
        border-radius: 0 !important;
        margin-bottom: 0 !important;
        border: none !important;
        box-shadow: none !important;
      }
    }
    html[data-page="history"] .history-section,
    html[data-initial-route="history"] .history-section {
      display: block !important;
    }
    html[data-page="closed"] .closed-section,
    html[data-initial-route="closed"] .closed-section {
      display: flex !important;
    }
    html[data-page="closed"] .cart-panel,
    html[data-page="closed"] .mobile-bottom,
    html[data-initial-route="closed"] .cart-panel,
    html[data-initial-route="closed"] .mobile-bottom {
      display: none !important;
    }
    html[data-page="menu"] .main-content > section[aria-label="เมนูอาหาร"] {
      display: block !important;
    }
    html[data-page="menu"] .history-section,
    html[data-page="menu"] .closed-section,
    html[data-page="menu"] .login-section,
    html[data-page="menu"] .tables-section {
      display: none !important;
    }
  </style>
  <link rel="preload" href="assets/fonts/noto-sans-thai-400.ttf" as="font" type="font/ttf" crossorigin>
  <link rel="preload" href="assets/fonts/noto-sans-thai-500.ttf" as="font" type="font/ttf" crossorigin>
  <link rel="preload" href="assets/fonts/noto-sans-thai-600.ttf" as="font" type="font/ttf" crossorigin>
  <link rel="preload" href="assets/fonts/noto-sans-thai-700.ttf" as="font" type="font/ttf" crossorigin>
  <link rel="preload" href="assets/fonts/sov-patigon.ttf" as="font" type="font/ttf" crossorigin>
  <link rel="stylesheet" href="assets/css/font-remote.css?v=<?= filemtime(__DIR__ . '/../../public/assets/css/font-remote.css') ?>">
  <link rel="stylesheet" href="assets/css/tailwind.css">
  <link rel="stylesheet" href="assets/css/app.css?v=<?= filemtime(__DIR__ . '/../../public/assets/css/app.css') ?>">
  <link rel="stylesheet" href="assets/css/cart-sync.css?v=<?= filemtime(__DIR__ . '/../../public/assets/css/cart-sync.css') ?>">
  <script>
    window.__IMDEE_STAFF__ = <?= json_encode($staffEmId !== '' ? [
      'emId' => $staffEmId,
      'name' => $staffName ?: $staffEmId,
      'username' => $staffUser,
      'role' => $staffRole ?: 'Staff',
      'canViewExpenses' => !empty($canViewExpenses),
    ] : null, JSON_UNESCAPED_UNICODE) ?>;
  </script>
  <script defer src="public/assets/vendor/sweetalert2.min.js"></script>
  <script defer src="assets/js/translations.js?v=<?= filemtime(__DIR__ . '/../../public/assets/js/translations.js') ?>"></script>
  <script defer src="assets/js/pwa.js?v=<?= filemtime(__DIR__ . '/../../public/assets/js/pwa.js') ?>"></script>
  <script>window.appBranch = <?= json_encode($branchId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
  <script>window.appResolvedDoc = <?= json_encode((string)($fnDocNo ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>; window.appBillHash = <?= json_encode((string)(($billHash ?? '') ?: ($canonicalBillHash ?? '')), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>; window.appCanonicalHash = <?= json_encode((string)($canonicalBillHash ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
  <script>window.csrfToken = <?= json_encode($_SESSION['csrf_token'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
  <script defer src="assets/js/app.js?v=<?= filemtime(__DIR__ . '/../../public/assets/js/app.js') ?>"></script>
  <script defer src="assets/js/account-menu.js?v=<?= filemtime(__DIR__ . '/../../public/assets/js/account-menu.js') ?>"></script>
  <script defer src="public/assets/vendor/alpine.min.js"></script>
</head>
<body class="<?= (!empty($staffEmId) && ($initialPage ?? '') !== 'login') ? 'has-staff-sidebar' : '' ?>" x-data="orderingApp('<?= htmlspecialchars($initialPage, ENT_QUOTES, 'UTF-8') ?>', <?= !empty($initialClosed) ? 'true' : 'false' ?>, '<?= htmlspecialchars((string)($displayTable ?? ''), ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars((string)($displayDoc ?? ''), ENT_QUOTES, 'UTF-8') ?>', <?= !empty($initialCancelled) ? 'true' : 'false' ?>)" @keydown.escape.window="closeDetail()" :class="{'dialog-open': detailOpen, 'detail-note-active': detailOpen && noteFocused, 'closed-bill-page': page === 'closed' || page === 'login', 'has-staff-sidebar': staff && page !== 'login'}">
<svg class="icon-defs" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><defs>
  <symbol id="i-home" viewBox="0 0 24 24"><path d="M3 10.5 12 3l9 7.5M5.5 9.5V21h13V9.5M9.5 21v-7h5v7"/></symbol>
  <symbol id="i-snack" viewBox="0 0 24 24"><path d="M5 9h14l-1.4 11H6.4L5 9Zm3 0L7 4h3l1 5m2 0 1-6h3l-1 6"/></symbol>
  <symbol id="i-herbal" viewBox="0 0 24 24"><path d="M4 12h14l-1 7H6l-2-7Zm14 1h1a2 2 0 0 1 0 4h-1M9 10c-3-3-1-6 3-7 1 4 0 6-3 7Zm0 0 3-4"/></symbol>
  <symbol id="i-wine" viewBox="0 0 24 24"><path d="M6 4h12l-1 5a5 5 0 0 1-10 0L6 4Zm6 10v6m-4 0h8M7 8h10"/></symbol>
  <symbol id="i-water" viewBox="0 0 24 24"><path d="M12 3c-2 3-6 7-6 11a6 6 0 0 0 12 0c0-4-4-8-6-11Zm-3 12a3 3 0 0 0 3 3"/></symbol>
  <symbol id="i-teapot" viewBox="0 0 24 24"><path d="M6 10h12v3a6 6 0 0 1-12 0v-3Zm2-2h8l-1-3H9L8 8Zm4-5v2M6 11H3l1 3 2 1m12-4h1a2 2 0 0 1 0 4h-1M7 20h10"/></symbol>
  <symbol id="i-fizzy" viewBox="0 0 24 24"><path d="M6 7h12l-1 13H7L6 7Zm2 4h8M14 4l2-2m-5 2h.01M15 6h.01M10 14h.01M14 17h.01"/></symbol>
  <symbol id="i-arrow" viewBox="0 0 24 24"><path d="M5 12h14m-6-6 6 6-6 6"/></symbol>
  <symbol id="i-back" viewBox="0 0 24 24"><path d="M19 12H5m6-6-6 6 6 6"/></symbol>
  <symbol id="i-up-right" viewBox="0 0 24 24"><path d="M6 18 18 6M6 6h12v12"/></symbol>
  <symbol id="i-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></symbol>
  <symbol id="i-minus" viewBox="0 0 24 24"><path d="M5 12h14"/></symbol>
  <symbol id="i-close" viewBox="0 0 24 24"><path d="m6 6 12 12M18 6 6 18"/></symbol>
  <symbol id="i-search" viewBox="0 0 24 24"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/></symbol>
  <symbol id="i-search-fill" viewBox="0 0 24 24"><circle cx="10.5" cy="10.5" r="6.5" fill="currentColor" stroke="currentColor"/><path d="m16 16 5 5" stroke="currentColor" stroke-width="2.5"/></symbol>
  <symbol id="i-grid" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/></symbol>
  <symbol id="i-grid-fill" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="2" fill="currentColor" stroke="currentColor"/><rect x="14" y="3" width="7" height="7" rx="2" fill="currentColor" stroke="currentColor"/><rect x="3" y="14" width="7" height="7" rx="2" fill="currentColor" stroke="currentColor"/><rect x="14" y="14" width="7" height="7" rx="2" fill="currentColor" stroke="currentColor"/></symbol>
  <symbol id="i-bowl" viewBox="0 0 24 24"><path d="M3 11h18a9 9 0 0 1-18 0ZM7 21h10M8 3v4m4-5v5m4-4v4"/></symbol>
  <symbol id="i-leaf" viewBox="0 0 24 24"><path d="M20 3c-9-1-17 3-15 11 3 8 16 4 15-11ZM3 21 15 9"/></symbol>
  <symbol id="i-cup" viewBox="0 0 24 24"><path d="M5 8h12v6a6 6 0 0 1-12 0V8Zm12 1h2a3 3 0 0 1 0 6h-2M4 22h14M8 2v3m5-3v3"/></symbol>
  <symbol id="i-cake" viewBox="0 0 24 24"><path d="M4 21V11h16v10H4Zm0-7q3 4 5 0 3 4 6 0 2 4 5 0M8 11V7m8 4V7M8 3v1m8-1v1"/></symbol>
  <symbol id="i-sparkles" viewBox="0 0 24 24"><path d="m12 3 2.5 6.5L21 12l-6.5 2.5L12 21l-2.5-6.5L3 12l6.5-2.5L12 3ZM21 2v4m-2-2h4"/></symbol>
  <symbol id="i-receipt" viewBox="0 0 24 24"><path d="M5 3v18l3-2 4 2 4-2 3 2V3l-3 2-4-2-4 2-3-2Zm4 6h6m-6 4h6m-6 4h3"/></symbol>
  <symbol id="i-receipt-fill" viewBox="0 0 24 24"><path d="M5 3v18l3-2 4 2 4-2 3 2V3l-3 2-4-2-4 2-3-2Z" fill="currentColor" stroke="currentColor"/><path d="M9 9h6m-6 4h6m-6 4h3" fill="none" stroke="#ffffff" stroke-width="1.8" stroke-linecap="round"/></symbol>
  <symbol id="i-bag" viewBox="0 0 24 24"><path d="M5 7h14l2 14H3L5 7Zm3 0V6a4 4 0 0 1 8 0v1"/></symbol>
  <symbol id="i-bag-fill" viewBox="0 0 24 24"><path d="M5 7h14l2 14H3L5 7Z" fill="currentColor" stroke="currentColor"/><path d="M8 7V6a4 4 0 0 1 8 0v1" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></symbol>
  <symbol id="i-pin" viewBox="0 0 24 24"><path d="M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 0 1 14 0Z"/><circle cx="12" cy="10" r="2"/></symbol>
  <symbol id="i-bell" viewBox="0 0 24 24"><path d="M4 17h16l-2-3V9a6 6 0 0 0-12 0v5l-2 3Zm5 3h6M12 1v2"/></symbol>
  <symbol id="i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></symbol>
  <symbol id="i-check" viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></symbol>
  <symbol id="i-edit" viewBox="0 0 24 24"><path d="m15 4 5 5M4 20l5-1L21 7l-5-5L4 14v6ZM13 21h8"/></symbol>
  <symbol id="i-trash" viewBox="0 0 24 24"><path d="M4 7h16M9 7V4h6v3m3 0-1 13H7L6 7m4 4v5m4-5v5"/></symbol>
  <symbol id="i-users" viewBox="0 0 24 24"><circle cx="9" cy="7" r="3"/><path d="M3 20v-3a6 6 0 0 1 12 0v3M16 4a3 3 0 0 1 0 6m2 3a5 5 0 0 1 3 4v3"/></symbol>
  <symbol id="i-info" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10v1"/></symbol>
  <symbol id="i-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></symbol>
  <symbol id="i-logout" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4m7 14 5-5-5-5m5 5H9"/></symbol>
  <symbol id="i-table" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="3"/><path d="M3 10h18M9 10v10m6-10v10"/></symbol>
  <symbol id="i-utensils" viewBox="0 0 24 24"><path d="M18 2v6a3 3 0 0 1-3 3 3 3 0 0 1-3-3V2M15 11v11M5 2v5a2 2 0 0 0 2 2h0a2 2 0 0 0 2-2V2M7 9v13"/></symbol>
  <symbol id="i-wallet" viewBox="0 0 24 24"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/></symbol>
  <symbol id="i-flame" viewBox="0 0 24 24"><path fill="currentColor" stroke="none" d="M13.1 2.4c.4 3.1-1.6 4.5-3.1 6.2-1.3 1.4-2.4 2.9-2.4 5.2 0 1.2.5 2.2 1.2 2.8-.1-2 1.2-3.6 3-4.9 0 1.9 1.3 2.7 2 3.9.5.8.7 1.7.5 2.6 1.4-.7 2.3-2.2 2.3-4.1 0-2.3-1-4.4-2.4-6.1 3.8 1.9 6 5 6 8.3A8.2 8.2 0 0 1 12 24a8.2 8.2 0 0 1-8.2-8.2c0-4.7 2.6-7.4 5.2-9.6 1.4-1.2 2.8-2.4 4.1-3.8Z"/></symbol>
  <symbol id="i-bolt" viewBox="0 0 24 24"><path fill="currentColor" stroke="none" d="M13.6 1 4.5 13h6.1l-1 10L19.5 10h-6.1z"/></symbol>
</defs></svg>

<aside class="sidebar" :inert="detailOpen" x-show="page !== 'login' && page !== 'tables'">
  <a class="brand" href="./" @click.prevent="staff ? openTableSelector() : navigate('menu')" :aria-label="t('อันเกิม')"><span class="brand-mark"><svg class="icon"><use href="#i-bowl"/></svg></span><span><span class="brand-th" x-text="t('อันเกิม')">อันเกิม</span><span class="brand-en" lang="vi">Ăn cơm</span></span></a>
  <div class="sidebar-rule"></div>
  <span class="nav-label"><span x-text="t('มื้ออร่อยของคุณ')">มื้ออร่อยของคุณ</span></span>
  <nav class="space-y-2" :aria-label="t('เมนูหลัก')">
    <button class="nav-item" :class="{'active': page === 'menu' && !searchOpen}" @click="navigate('menu')" x-show="page === 'tables' || (!closed && (table || docNo))" :disabled="page === 'tables'" x-cloak><svg class="icon"><use href="#i-grid"/></svg><span x-text="t('เมนูอาหาร')">เมนูอาหาร</span></button>
    <button type="button" class="nav-item" :class="{'active': page === 'menu' && searchOpen}" @click="openMenuSearch()" x-show="page === 'tables' || (!closed && (table || docNo))" :disabled="page === 'tables'" x-cloak><svg class="icon"><use href="#i-search"/></svg><span x-text="t('ค้นหา')">ค้นหา</span></button>
    <button class="nav-item" :class="{'active': page === 'history'}" @click="navigate('history')" x-show="page === 'tables' || (table || docNo)" :disabled="page === 'tables'" x-cloak><svg class="icon"><use href="#i-receipt"/></svg><span x-text="t('รายการที่สั่ง')">รายการที่สั่ง</span><span class="nav-count" x-show="page !== 'tables' && historyItems.length" x-text="historyItems.reduce((sum, item) => sum + item.qty, 0)"></span></button>
  </nav>
  <div class="sidebar-bottom">
    <div class="table-ticket" x-show="page !== 'tables' && (table || docNo)" x-cloak>
      <span class="ticket-label" x-text="docNo ? (t('บิล') + ' #' + docNo) : t('หมายเลขโต๊ะ')">หมายเลขโต๊ะ</span>
      <div class="table-number"><strong x-text="table || '-'">12</strong><span>TABLE</span></div>
      <div class="ticket-line"></div>
      <div class="ticket-guests">
        <svg class="icon"><use :href="staff ? '#i-user' : '#i-users'"/></svg>
        <span x-text="staff ? staff.name : (guests ? guests + ' ' + t('ท่าน') : '')"></span>
        <span class="status-dot" :class="{'status-closed': closed && !isCancelled, 'status-cancelled': isCancelled}"></span>
      </div>
    </div>
    <button class="staff-button" x-show="staff || (!table && !docNo)" x-cloak @click="staff ? logoutStaff() : navigate('login')">
      <svg class="icon"><use :href="staff ? '#i-logout' : '#i-user'"/></svg>
      <span x-text="staff ? t('ออกจากระบบพนักงาน') : t('เข้าสู่ระบบพนักงาน')">เข้าสู่ระบบพนักงาน</span>
    </button>
    <p class="sidebar-footer"><span class="footer-copy" x-text="t('ทำทุกมื้อ ให้เป็นมื้อดี ๆ')">ทำทุกมื้อ ให้เป็นมื้อดี ๆ</span><span>MADE WITH A LITTLE EXTRA LOVE</span></p>
  </div>
</aside>

<div class="workspace" :inert="detailOpen">
  <?php $headerMode = 'alpine'; require __DIR__ . '/Staff/header.php'; ?>
      <nav class="desktop-top-nav" :aria-label="t('เมนูหลัก')" x-show="page !== 'login' && page !== 'tables'">
        <button type="button" class="desktop-nav-item" :class="{'active': page === 'menu' && !searchOpen}" @click="navigate('menu')" x-show="page === 'tables' || (!closed && (table || docNo))" :disabled="page === 'tables'" x-cloak>
          <svg class="icon" aria-hidden="true"><use href="#i-grid"/></svg>
          <span x-text="t('เมนูอาหาร')">เมนูอาหาร</span>
        </button>
        <button type="button" class="desktop-nav-item" :class="{'active': page === 'menu' && searchOpen}" @click="openMenuSearch()" x-show="page === 'tables' || (!closed && (table || docNo))" :disabled="page === 'tables'" x-cloak>
          <svg class="icon" aria-hidden="true"><use href="#i-search"/></svg>
          <span x-text="t('ค้นหา')">ค้นหา</span>
        </button>
        <button type="button" class="desktop-nav-item" :class="{'active': page === 'history'}" @click="navigate('history')" x-show="page === 'tables' || (table || docNo)" :disabled="page === 'tables'" x-cloak>
          <svg class="icon" aria-hidden="true"><use href="#i-receipt"/></svg>
          <span x-text="t('รายการที่สั่ง')">รายการที่สั่ง</span>
          <span class="desktop-nav-count" x-show="page !== 'tables' && historyItems.length" x-text="historyItems.reduce((sum, item) => sum + item.qty, 0)"></span>
        </button>
      </nav>

  <div class="content-layout" :class="{'wide-view': page === 'closed' || page === 'login' || page === 'tables' || closed}">
    <main id="main-content" class="main-content">
      <!-- 1. Staff Login Section -->
      <section x-show="page === 'login'" <?= ($initialPage !== 'login') ? 'style="display: none;"' : '' ?> x-cloak class="login-section page-animated" aria-label="เข้าสู่ระบบพนักงาน">
        <div class="staff-login-box">
          <div class="staff-login-header">
            <h1 class="staff-login-title" x-text="t('เข้าสู่ระบบพนักงาน')">เข้าสู่ระบบพนักงาน</h1>
            <p class="staff-login-subtitle" x-text="t('กรุณาเข้าสู่ระบบ เพื่อเลือกโต๊ะ เปิดบิล และรับรายการอาหาร')">กรุณาเข้าสู่ระบบ เพื่อเลือกโต๊ะ เปิดบิล และรับรายการอาหาร</p>
          </div>
          <form class="staff-login-form" @submit.prevent="submitLogin()" novalidate>
            <div class="staff-field">
              <label for="staff-username" x-text="t('ชื่อผู้ใช้งาน')">ชื่อผู้ใช้งาน</label>
              <input id="staff-username" type="text" x-model.trim="loginUsername" autocomplete="username" required :placeholder="t('กรอกชื่อผู้ใช้')" :disabled="loginLoading">
            </div>
            <div class="staff-field">
              <label for="staff-password" x-text="t('รหัสผ่าน')">รหัสผ่าน</label>
              <input id="staff-password" type="password" x-model="loginPassword" autocomplete="current-password" required :placeholder="t('กรอกรหัสผ่าน')" :disabled="loginLoading">
            </div>
            <label class="staff-remember"><input type="checkbox" x-model="loginRemember" :disabled="loginLoading"><span x-text="t('จำการเข้าสู่ระบบ')">จำการเข้าสู่ระบบ</span></label>
            <button type="submit" class="staff-login-button" :disabled="loginLoading"><span class="spinner" x-show="loginLoading" x-cloak></span><span x-text="loginLoading ? t('กำลังเข้าสู่ระบบ...') : t('เข้าสู่ระบบ')">เข้าสู่ระบบ</span></button>
          </form>
        </div>
      </section>

      <?php require __DIR__ . '/Staff/tables.php'; ?>

      <section x-show="page === 'menu' || page === 'cart'" <?= ($initialPage !== 'menu' && $initialPage !== 'cart') ? 'style="display: none;"' : '' ?> x-cloak class="page-animated" :class="{'cart-background-menu': page === 'cart'}" aria-label="เมนูอาหาร">
                <div class="menu-category-sticky" :class="{'is-stuck': menuCategoryStuck}"><div class="category-list" :class="{'category-list-filtered': categoryId !== 'all', 'is-mouse-dragging': categoryDrag?.dragging, 'has-scroll-left': categoryHasScrolled}" role="group" :aria-label="t('หมวดหมู่อาหาร')" @scroll="categoryHasScrolled = $el.scrollLeft > 2" @pointerdown="startCategoryDrag($event)" @pointermove="moveCategoryDrag($event)" @pointerup="endCategoryDrag($event)" @pointercancel="endCategoryDrag($event)" @click.capture="if (categorySuppressClick) { $event.preventDefault(); $event.stopPropagation(); categorySuppressClick = false; }" @wheel="scrollCategoryWheel($event)"><template x-for="category in categories.filter(category => category.id !== 'all')" :key="category.id"><button class="category-button" x-show="!loading" :class="{'selected': categoryId === category.id}" :aria-pressed="categoryId === category.id" @click="selectMenuCategory(category.id)"><span x-text="category.id === 'all' ? t('เมนูทั้งหมด') : (category.id === 'recommended' ? t('เมนูแนะนำ') : category.name)"></span></button></template><template x-if="loading"><div class="skeleton-categories" role="status" :aria-label="t('กำลังโหลดหมวดหมู่อาหาร')"><span class="skeleton skeleton-category"></span><span class="skeleton skeleton-category"></span><span class="skeleton skeleton-category"></span></div></template></div></div>
<div class="hero hero-table-card" x-show="!searchOpen">
          <div class="hero-table-number"><strong x-text="tableName || table"></strong></div>
          <div class="hero-bill-number" x-show="docNo" x-cloak><span x-text="t('เลขที่บิล')">เลขที่บิล</span><strong x-text="docNo"></strong></div>
        </div>
        <div class="food-grid skeleton-menu" x-show="loading" role="status" :aria-label="t('กำลังโหลดเมนูอาหาร')"><template x-for="n in 6" :key="n"><div class="food-card skeleton-card" aria-hidden="true"><div class="skeleton skeleton-food-image"></div><div class="food-card-body"><div class="skeleton skeleton-line skeleton-title"></div><div class="skeleton skeleton-line skeleton-short"></div><div class="skeleton skeleton-line skeleton-price"></div></div></div></template></div>
        <div class="empty-state" x-show="!loading && loadError" x-cloak><svg class="icon"><use href="#i-info"/></svg><h3><span x-text="t('โหลดเมนูไม่สำเร็จ')">โหลดเมนูไม่สำเร็จ</span></h3><p><span x-text="t('กรุณาตรวจสอบการเชื่อมต่อแล้วลองอีกครั้ง')">กรุณาตรวจสอบการเชื่อมต่อแล้วลองอีกครั้ง</span></p><button class="primary-button" @click="loadMenu()"><span x-text="t('ลองอีกครั้ง')">ลองอีกครั้ง</span></button></div>
        <div class="empty-state search-empty" x-show="!loading && !loadError && !menuSections.length" x-cloak><svg class="icon"><use href="#i-search"/></svg><h3 x-text="t('ยังไม่เจอเมนูที่ค้นหา')"></h3><p x-text="t('ลองคำค้นอื่น หรือเลือกดูเมนูทั้งหมด')"></p><button class="text-button" @click="search = ''; categoryId = 'all'" x-text="t('ดูเมนูทั้งหมด')"></button></div>
        <div x-show="!loading && !loadError" class="menu-content">
          <section id="menu-section-recommended" class="menu-recommendations" x-show="recommendedItems.length" x-cloak>
            <h2 x-text="t('เมนูขายดี')">เมนูขายดี</h2>
            <div class="food-grid recommendation-grid"><template x-for="(item, index) in recommendedItems" :key="item.id">
              <article class="food-card recommendation-card" role="button" tabindex="0" @click="openDetail(item)" @keydown.enter.prevent="openDetail(item)" @keydown.space.prevent="openDetail(item)" :aria-label="t('ดูรายละเอียด ') + item.name">
                <div class="food-image-button"><img :src="item.image" :alt="item.name" loading="lazy"><span class="recommendation-rank"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M13.1 2.4c.4 3.1-1.6 4.5-3.1 6.2-1.3 1.4-2.4 2.9-2.4 5.2 0 1.2.5 2.2 1.2 2.8-.1-2 1.2-3.6 3-4.9 0 1.9 1.3 2.7 2 3.9.5.8.7 1.7.5 2.6 1.4-.7 2.3-2.2 2.3-4.1 0-2.3-1-4.4-2.4-6.1 3.8 1.9 6 5 6 8.3A8.2 8.2 0 0 1 12 24a8.2 8.2 0 0 1-8.2-8.2c0-4.7 2.6-7.4 5.2-9.6 1.4-1.2 2.8-2.4 4.1-3.8Z"/></svg><span x-text="t('ขายดี') + ' #' + (index + 1)"></span></span></div>
                <div class="food-card-body"><span class="food-title" x-text="item.name"></span><div class="food-card-bottom"><strong class="price" x-text="money(item.price)"></strong><span class="menu-quick-add" aria-hidden="true"><svg class="icon"><use href="#i-plus"/></svg></span></div></div>
              </article>
            </template></div>
          </section>
          <section class="menu-recommendations" x-show="trendingItems.length" x-cloak>
            <h2 x-text="t('เมนูมาแรง')"></h2>
            <div class="food-grid recommendation-grid"><template x-for="(item, index) in trendingItems" :key="item.id">
              <article class="food-card recommendation-card" role="button" tabindex="0" @click="openDetail(item)" @keydown.enter.prevent="openDetail(item)" @keydown.space.prevent="openDetail(item)" :aria-label="t('ดูรายละเอียด ') + item.name"><div class="food-image-button"><img :src="item.image" :alt="item.name" loading="lazy"><span class="recommendation-rank trending-rank"><svg aria-hidden="true"><use href="#i-bolt"/></svg><span x-text="t('มาแรง') + ' #' + (index + 1)"></span></span></div><div class="food-card-body"><span class="food-title" x-text="item.name"></span><div class="food-card-bottom"><strong class="price" x-text="money(item.price)"></strong><span class="menu-quick-add" aria-hidden="true"><svg class="icon"><use href="#i-plus"/></svg></span></div></div></article>
            </template></div>
          </section>
          <section class="menu-recommendations" x-show="pairedItems.length" x-cloak>
            <h2 x-text="t('เมนูที่มักสั่งคู่กัน')"></h2>
            <div class="food-grid recommendation-grid"><template x-for="item in pairedItems" :key="item.id">
              <article class="food-card recommendation-card" role="button" tabindex="0" @click="openDetail(item)" @keydown.enter.prevent="openDetail(item)" @keydown.space.prevent="openDetail(item)" :aria-label="t('ดูรายละเอียด ') + item.name"><div class="food-image-button"><img :src="item.image" :alt="item.name" loading="lazy"></div><div class="food-card-body"><span class="food-title" x-text="item.name"></span><div class="food-card-bottom"><strong class="price" x-text="money(item.price)"></strong><span class="menu-quick-add" aria-hidden="true"><svg class="icon"><use href="#i-plus"/></svg></span></div></div></article>
            </template></div>
          </section>
          <div id="menu-sections"><template x-for="section in menuSections" :key="section.id">
            <section class="menu-category-section" :id="'menu-section-' + section.id">
              <h2 x-text="section.name"></h2>
              <template x-for="item in section.items" :key="item.id"><article class="menu-item-row" :class="{'sold-out': !item.available}" role="button" tabindex="0" @click="openDetail(item)" @keydown.enter.prevent="openDetail(item)" @keydown.space.prevent="openDetail(item)" :aria-label="t('ดูรายละเอียด ') + item.name">
                <div class="menu-row-image"><img :src="item.image" :alt="item.name" loading="lazy"></div>
                <div class="menu-row-info"><span class="menu-row-title" x-text="item.name"></span><p x-show="item.description && !item.description.startsWith('เมนูสดใหม่ ปรุงจากวัตถุดิบคุณภาพ')" x-text="item.description"></p><div class="menu-row-ranks" x-show="bestSellerRank(item.id) > 0 || trendingRank(item.id) > 0" x-cloak><span class="menu-rank-badge" x-show="bestSellerRank(item.id) > 0"><svg aria-hidden="true"><use href="#i-flame"/></svg><span x-text="t('ขายดี') + ' #' + bestSellerRank(item.id)"></span></span><span class="menu-rank-badge trending-rank" x-show="trendingRank(item.id) > 0"><svg aria-hidden="true"><use href="#i-bolt"/></svg><span x-text="t('มาแรง') + ' #' + trendingRank(item.id)"></span></span></div><div class="menu-row-price"><strong class="price" x-text="money(item.price)"></strong></div></div>
                <span class="menu-quick-add" aria-hidden="true"><svg class="icon"><use href="#i-plus"/></svg></span>
              </article></template>
            </section>
          </template></div>
        </div>
      </section>

      <section x-show="page === 'history'" x-cloak class="history-section page-animated">
        <div class="history-loading" x-show="!billLoaded" role="status" :aria-label="t('กำลังโหลดรายการที่สั่ง')">
          <div class="history-summary skeleton-summary-layout" aria-hidden="true"><div><div class="skeleton skeleton-summary-label"></div><div class="skeleton skeleton-summary-total"></div><div class="skeleton skeleton-summary-label" style="margin-top:8px"></div></div></div>
          <template x-for="n in 3" :key="n"><div class="skeleton-history-order" aria-hidden="true"><div class="skeleton-history-row"><div class="skeleton skeleton-history-image"></div><div class="skeleton-history-copy"><div class="skeleton skeleton-line"></div><div class="skeleton skeleton-line skeleton-short"></div><div class="skeleton skeleton-line skeleton-price"></div></div></div></div></template>
        </div>
        <div class="history-summary" x-show="billLoaded" x-cloak>
          <div class="history-bill-info">
            <strong class="history-table-number" x-text="tableName || table"></strong>
            <span class="history-bill-number" x-show="docNo" x-text="t('เลขที่บิล') + ' ' + docNo"></span>
          </div>
          <div>
            <span><span x-text="t('ยอดรวมทั้งหมด')">ยอดรวมทั้งหมด</span></span>
            <strong x-text="money(historyTotal)"></strong>
            <span class="history-order-count" x-text="t('จำนวนรวม') + ' ' + historyItems.reduce((sum, item) => sum + item.qty, 0) + ' ' + t('รายการ')"></span>
          </div>
        </div>

        <p class="page-subtitle history-subtitle" x-show="billLoaded" x-cloak><span x-text="docSts === 1 ? t('ประวัติรายการอาหารที่สั่งทั้งหมดจะแสดงที่นี่') : t('รายการที่ส่งเข้าครัวทั้งหมดจะแสดงที่นี่')">รายการที่ส่งเข้าครัวทั้งหมดจะแสดงที่นี่</span> <span aria-hidden="true">👇</span></p>

        <!-- Empty state when orders is empty -->
        <div class="empty-state history-empty" x-show="billLoaded && !historyItems.length" x-cloak>
          <div class="empty-illustration"><svg class="icon"><use href="#i-receipt"/></svg></div>
          <h3><span x-text="t('มื้ออร่อยกำลังรออยู่')">มื้ออร่อยกำลังรออยู่</span></h3>
          <p><span x-text="t('เมื่อส่งออเดอร์แล้ว รายการของคุณจะอยู่ที่นี่')">เมื่อส่งออเดอร์แล้ว รายการของคุณจะอยู่ที่นี่</span></p>
          <button class="text-button" @click="navigate('menu')" x-show="!closed"><span x-text="t('ไปเลือกเมนูกัน')">ไปเลือกเมนูกัน</span> <svg class="icon"><use href="#i-arrow"/></svg></button>
        </div>

        <!-- Confirmed orders section -->
        <div class="history-list" x-show="billLoaded && historyItems.length > 0" x-cloak>
            <article class="history-card">
              <template x-for="line in historyItems" :key="line.key">
                <div class="cart-line history-cart-line">
                  <img :src="line.image" :alt="line.name">
                  <div class="cart-line-content">
                    <div class="cart-line-title"><h3 x-text="line.name"></h3></div>
                    <p class="history-item-time" x-show="formatHistoryTime(line.orderedAt)" x-text="formatHistoryTime(line.orderedAt)"></p>
                    <p class="cart-note" x-show="line.note" x-text="line.note"></p>
                    <div class="cart-line-bottom">
                      <strong x-text="money(line.price * line.qty)"></strong>
                      <span class="history-quantity" x-text="t('จำนวน') + ' ' + line.qty + (line.unit ? ' ' + line.unit : '')"></span>
                    </div>
                  </div>
                </div>
              </template>
            </article>
        </div>
      </section>

      <section class="closed-section page-animated" x-show="page === 'closed'" x-cloak>
        <div class="skeleton-closed" x-show="!billLoaded" role="status" :aria-label="t('กำลังโหลดข้อมูลบิล')"><div class="skeleton skeleton-closed-icon"></div><div class="skeleton skeleton-closed-label"></div><div class="skeleton skeleton-closed-title"></div><div class="skeleton skeleton-closed-button"></div></div>
        <div class="closed-content" x-show="billLoaded" x-cloak>
        <div class="closed-icon" :class="{'cancelled-icon': isCancelled}">
          <svg class="icon"><use :href="waitingForTable ? '#i-info' : (isCancelled ? '#i-close' : '#i-check')"/></svg>
        </div>
        <span class="eyebrow" x-show="!waitingForTable" x-text="isCancelled ? 'BILL CANCELLED' : (billError ? 'BILL NOT FOUND' : 'THANK YOU FOR SHARING YOUR MEAL')"></span>
        <h1 x-text="waitingForTable ? t('โต๊ะนี้ยังไม่เปิดบิล') : (isCancelled ? t('บิลนี้ถูกยกเลิกแล้ว') : (billError ? t('ไม่พบข้อมูลบิล') : t('ขอบคุณที่ใช้บริการ')))"></h1>
        <p x-show="waitingForTable" x-text="t('กรุณาติดต่อพนักงานเพื่อเปิดโต๊ะ')"></p>
        <p x-show="docNo && !isCancelled && !billError && !waitingForTable" x-text="t('เลขที่บิล') + ' ' + docNo"></p>
        <p x-show="isCancelled && docNo" x-text="t('เลขที่บิล') + ' ' + docNo"></p>
        <p x-show="billError" style="color: #b91c1c; font-weight: 500;" x-text="language === 'en' ? 'Unable to load this bill. Please contact the staff.' : billError"></p>
        <button class="primary-button" x-show="!isCancelled && !waitingForTable && !billError" @click="navigate('history')"><span x-text="t('ดูรายการที่สั่ง')">ดูรายการที่สั่ง</span> <svg class="icon"><use href="#i-receipt"/></svg></button>
        </div>
      </section>

    </main>

    <aside class="cart-panel" :class="{'cart-page-active': page === 'cart'}" x-show="!closed && page !== 'closed' && page !== 'login' && page !== 'tables'" <?= (!empty($initialClosed) || in_array($initialPage, ['closed', 'login', 'tables'], true)) ? 'style="display: none;"' : '' ?> :aria-label="t('ตะกร้าอาหาร')">
      <div class="skeleton-cart" x-show="!billLoaded || loading" role="status" :aria-label="t('กำลังโหลดตะกร้าอาหาร')">
        <div class="cart-panel-header"><div class="skeleton skeleton-cart-heading"></div><div class="skeleton skeleton-cart-icon"></div></div>
        <div class="cart-items"><template x-for="n in 2" :key="n"><div class="cart-line" aria-hidden="true"><div class="skeleton skeleton-cart-image"></div><div class="cart-line-content"><div class="skeleton skeleton-line skeleton-title"></div><div class="skeleton skeleton-line skeleton-short"></div><div class="skeleton skeleton-line skeleton-price"></div></div></div></template></div>
        <div class="cart-checkout"><div class="skeleton skeleton-line"></div><div class="skeleton skeleton-line skeleton-title"></div><div class="skeleton skeleton-cart-button"></div></div>
      </div>
      <template x-if="billLoaded && !loading"><div class="cart-loaded">
      <div class="cart-panel-header"><div><span class="eyebrow" x-text="(tableName || (t('โต๊ะ') + ' ' + table)) + (docNo ? ' · #' + docNo : '')">A GOOD MEAL AWAITS</span><h2><span class="cart-heading-text" x-text="t('ตะกร้าอาหาร')">ตะกร้าอาหาร</span> <span :class="{'bump-active': cartBump}" x-text="cartCount" x-cloak></span></h2></div><svg class="icon cart-header-icon"><use href="#i-bag"/></svg></div>
      <div class="cart-sync-error" x-show="cartError" x-cloak role="alert"><span x-text="language === 'en' ? 'Cart is unavailable. Displayed items may be out of date.' : 'เชื่อมต่อตะกร้าไม่ได้ รายการที่แสดงอาจไม่เป็นปัจจุบัน'"></span><button type="button" @click="retryCart()" :disabled="sending" x-text="t('ลองอีกครั้ง')"></button></div>
      <div class="cart-items" :class="{'is-empty': !cart.length}">
        <div class="cart-empty" x-show="!cart.length && !cartError" x-cloak><div class="empty-illustration"><svg class="icon"><use href="#i-bag"/></svg><span>+</span></div><h3><span x-text="t('เติมความอร่อยลงตะกร้า')">เติมความอร่อยลงตะกร้า</span></h3><p><span x-text="t('เลือกเมนูที่ถูกใจ')">เลือกเมนูที่ถูกใจ</span><br><span x-text="t('แล้วมื้อดี ๆ จะเริ่มต้นขึ้นที่นี่')">แล้วมื้อดี ๆ จะเริ่มต้นขึ้นที่นี่</span></p><button class="text-button" @click="navigate('menu')"><span x-text="t('ไปเลือกเมนูกัน')">ไปเลือกเมนูกัน</span> <svg class="icon"><use href="#i-arrow"/></svg></button></div>
        <template x-for="line in cart" :key="line.key"><article class="cart-line"><img :src="line.image" :alt="line.name"><div class="cart-line-content"><div class="cart-line-title"><h3 x-text="line.name"></h3><div class="cart-line-actions"><button type="button" @click="editLine(line)" :disabled="sending || !cartReady" :aria-label="t('แก้ไข') + ' ' + line.name"><svg class="icon"><use href="#i-edit"/></svg></button><button type="button" @click="removeCartLine(line.key)" :disabled="sending || !cartReady" :aria-label="t('ลบ') + ' ' + line.name + ' ' + t('ออกจากตะกร้า')"><svg class="icon"><use href="#i-trash"/></svg></button></div></div><p class="cart-note" x-text="line.note || t('ไม่ระบุหมายเหตุ')"></p><div class="cart-line-bottom"><strong x-text="money(line.price * line.qty)"></strong><div class="quantity-stepper small"><button @click="changeQuantity(line.key, -1)" :disabled="sending || !cartReady" :aria-label="t('ลดจำนวน') + ' ' + line.name"><svg class="icon"><use href="#i-minus"/></svg></button><span x-text="line.qty"></span><button @click="changeQuantity(line.key, 1)" :disabled="sending || !cartReady || line.qty >= 99" :aria-label="t('เพิ่มจำนวน') + ' ' + line.name"><svg class="icon"><use href="#i-plus"/></svg></button></div></div></div></article></template>
      </div>
      <div class="cart-checkout" x-show="cart.length > 0" x-cloak><div class="summary-row"><span x-text="t('ค่าอาหาร') + ' (' + cartCount + ' ' + t('รายการ') + ')'"></span><span x-text="money(cartTotal)"></span></div><div class="summary-total"><span><span x-text="t('ยอดรวม')">ยอดรวม</span></span><strong x-text="money(cartTotal)"></strong></div><div class="staff-login-slide order-slide" x-ref="orderSlideTrack" :class="{'is-disabled': sending || closed, 'is-dragging': orderSlidePointer !== null}"><span class="staff-slide-label" x-text="orderAwaitingConfirm ? t('รอยืนยันสั่งอาหาร') : (sending ? t('กำลังส่งรายการ...') : (closed ? t('บิลปิดแล้ว') : t('เลื่อนเพื่อสั่งอาหาร')))"></span><button type="button" class="staff-slide-handle" :disabled="sending || closed" :style="'transform: translateX(' + orderSlidePosition + 'px)'" :aria-label="t('ยืนยันสั่งอาหาร')" @pointerdown="startOrderSlide($event)" @pointermove="moveOrderSlide($event)" @pointerup="endOrderSlide($event)" @pointercancel="endOrderSlide($event, true)" @click="if ($event.detail === 0) submitOrder()"><svg class="icon" x-show="!sending"><use href="#i-arrow"/></svg><span class="spinner" x-show="sending" x-cloak></span></button></div></div>
      </div></template>
    </aside>
  </div>
</div>

<div class="mobile-bottom" :class="{'is-collapsed': navHidden}" x-show="billLoaded && page !== 'closed' && page !== 'login' && page !== 'tables' && !menuSearchFocused" x-cloak>
  <nav class="mobile-nav" :aria-label="t('เมนูมือถือ')" :inert="navHidden" @touchstart="startNavDrag($event)" @touchmove="moveNavDrag($event)" @touchend="endNavDrag($event)" @touchcancel="cancelNavDrag()" @click.capture="handleMobileNavClick($event)">
    <span class="nav-indicator-pill" :class="['tab-' + (page === 'menu' && searchOpen ? 'search' : page), { 'is-dragging': navDragging, 'is-pressed': navPressed }]" :style="navDragging ? 'transform: translate3d(' + navDragPosition + '%, 0, 0) scale(' + navDragScale + ', ' + (1 - (navDragScale - 1) * 0.3) + ')' : navPressed ? 'transform: translate3d(' + navPressPosition + '%, 0, 0) scale(1.04)' : ''" aria-hidden="true" x-show="!closed"></span>
    <button :class="{'active': page === 'menu' && !searchOpen}" @click="navigate('menu')" x-show="!closed">
      <span class="nav-icon-wrap"><svg class="icon"><use :href="page === 'menu' && !searchOpen ? '#i-grid-fill' : '#i-grid'"/></svg></span>
      <span><span x-text="t('เมนูอาหาร')">เมนูอาหาร</span></span>
    </button>
    <button type="button" :class="{'active': page === 'menu' && searchOpen}" @click="openMenuSearch()" x-show="!closed" :aria-label="t('ค้นหาเมนู')">
      <span class="nav-icon-wrap"><svg class="icon"><use :href="page === 'menu' && searchOpen ? '#i-search-fill' : '#i-search'"/></svg></span>
      <span><span x-text="t('ค้นหา')">ค้นหา</span></span>
    </button>
    <button :class="{'active': page === 'cart'}" @click="navigate('cart')" x-show="!closed">
      <span class="nav-icon-wrap">
        <svg class="icon"><use :href="page === 'cart' ? '#i-bag-fill' : '#i-bag'"/></svg>
        <span x-show="cartCount" class="mobile-count" :class="{'bump-active': cartBump}" x-text="cartCount"></span>
      </span>
      <span><span x-text="t('ตะกร้าอาหาร')">ตะกร้าอาหาร</span></span>
    </button>
    <button :class="{'active': page === 'history'}" @click="navigate('history')">
      <span class="nav-icon-wrap">
        <svg class="icon"><use :href="page === 'history' ? '#i-receipt-fill' : '#i-receipt'"/></svg>
        <span class="mobile-order-dot" x-show="orders.length"></span>
      </span>
      <span><span x-text="t('รายการที่สั่ง')">รายการที่สั่ง</span></span>
    </button>
  </nav>
  <button type="button" class="mobile-nav-collapsed" @click="navHidden = false" :tabindex="navHidden ? 0 : -1" :aria-hidden="!navHidden" :aria-label="t('เปิดเมนูนำทาง หน้าปัจจุบัน: ') + t(page === 'menu' && searchOpen ? 'ค้นหา' : page === 'menu' ? 'เมนูอาหาร' : page === 'cart' ? 'ตะกร้าอาหาร' : 'รายการที่สั่ง')">
    <svg class="icon"><use :href="page === 'menu' && searchOpen ? '#i-search-fill' : page === 'menu' ? '#i-grid-fill' : page === 'cart' ? '#i-bag-fill' : '#i-receipt-fill'"/></svg>
    <span class="mobile-count" x-show="page === 'cart' && cartCount" x-text="cartCount"></span>
  </button>
</div>

<button type="button" class="floating-search" :class="{'nav-is-hidden': navHidden}" x-show="page === 'menu' && !closed && !detailOpen && !searchOpen" x-cloak @click="openMenuSearch()" :aria-label="t('ค้นหาเมนู')"><svg class="icon"><use href="#i-search"/></svg></button>

<div class="detail-backdrop" x-show="detailOpen" x-cloak aria-hidden="true"></div>
<div class="detail-overlay" x-ref="detailOverlay" x-show="detailOpen" x-cloak @pointerdown.self="if (noteFocused && isMobileLayout()) $event.preventDefault()" @click.self="noteFocused && isMobileLayout() ? $refs.detailNoteInput.blur() : closeDetail()" @touchmove.self.prevent x-transition:enter="detail-overlay-enter" x-transition:enter-start="detail-overlay-enter-start" x-transition:enter-end="detail-overlay-enter-end" x-transition:leave="detail-overlay-leave" x-transition:leave-start="detail-overlay-leave-start" x-transition:leave-end="detail-overlay-leave-end">
  <section class="detail-dialog" :class="{'note-focused': noteFocused}" role="dialog" aria-modal="true" aria-labelledby="detail-title" @keydown.tab="trapFocus($event)" x-ref="detailDialog" x-show="detailOpen" x-transition:enter="detail-dialog-enter" x-transition:enter-start="detail-dialog-enter-start" x-transition:enter-end="detail-dialog-enter-end" x-transition:leave="detail-dialog-leave" x-transition:leave-start="detail-dialog-leave-start" x-transition:leave-end="detail-dialog-leave-end">
    <div class="detail-dialog-inner">
      <div class="detail-image">
        <div class="sheet-handle" aria-hidden="true"></div>
        <img :src="selected?.image || ''" :alt="selected?.name || ''">
        <button class="detail-close" @click="closeDetail()" :aria-label="t('ปิดรายละเอียด')" x-ref="detailClose"><svg class="icon"><use href="#i-close"/></svg></button>
      </div>
      <div class="detail-content" x-ref="detailContent">
      <div class="detail-body">
        <div class="detail-heading">
          <div>
            <h2 id="detail-title" x-text="selected?.name || ''"></h2>
          </div>
        </div>
        <div class="detail-price">
          <strong x-text="money(selected?.price || 0)"></strong>
          <s x-show="selected?.originalPrice" x-text="money(selected?.originalPrice || 0)"></s>
          <span style="font-size: 13px; color: #8e988b; margin-left: 6px; font-weight: normal;" x-show="selected?.unit" x-text="'/ ' + (selected?.unit || '')"></span>
        </div>
        <div class="detail-note-label">
          <label for="item-note"><span x-text="t('หมายเหตุถึงครัว')">หมายเหตุถึงครัว</span></label><span><span x-text="t('ไม่บังคับ')">ไม่บังคับ</span></span>
        </div>
        <textarea id="item-note" x-ref="detailNoteInput" x-model="detailNote" maxlength="200" rows="3" enterkeyhint="done" :aria-label="t('หมายเหตุถึงครัว')" :placeholder="t('เช่น ไม่เผ็ด ไม่ใส่ถั่ว หรือแพ้อาหารชนิดใด')" @focus="beginDetailNoteEditing()" @keydown.enter="if (isMobileLayout() && !$event.shiftKey && !$event.isComposing && $event.keyCode !== 229) { $event.preventDefault(); $el.blur(); }" @blur="endDetailNoteEditing()"></textarea>
        <div class="note-count" x-text="detailNote.length + '/200'"></div>
      </div>
      <div class="detail-bottom">
        <div class="detail-quantity">
          <span><span x-text="t('จำนวน')">จำนวน</span></span>
          <div class="quantity-stepper">
            <button @click="detailQty = Math.max(1, detailQty - 1)" :disabled="detailQty <= 1" :aria-label="t('ลดจำนวน')"><svg class="icon"><use href="#i-minus"/></svg></button>
            <span x-text="detailQty"></span>
            <button @click="detailQty = Math.min(99, detailQty + 1)" :disabled="detailQty >= 99" :aria-label="t('เพิ่มจำนวน')"><svg class="icon"><use href="#i-plus"/></svg></button>
          </div>
        </div>
        <div class="detail-actions" :class="{'is-editing': !!editingKey}">
          <button type="button" class="btn-detail-cart" @click="saveDetail()" :disabled="!selected?.available || closed || sending">
            <svg class="icon"><use href="#i-bag"/></svg>
            <span x-text="editingKey ? t('บันทึกการแก้ไข') : t('เพิ่มลงตะกร้า')"></span>
          </button>
          <button type="button" class="btn-detail-order" @click="quickOrder()" :disabled="!selected?.available || closed || sending" x-show="!editingKey">
            <span class="spinner" x-show="sending"></span>
            <span x-text="sending ? t('กำลังสั่ง...') : t('สั่งอาหารเลย')"></span>
          </button>
        </div>
      </div>
      </div>
    </div>
  </section>
</div>
<noscript><div class="noscript">กรุณาเปิด JavaScript เพื่อเลือกเมนูและสั่งอาหาร</div></noscript>
</body>
</html>



