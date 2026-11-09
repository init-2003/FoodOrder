<?php $orderingHeader = ($headerMode ?? '') === 'alpine'; ?>
<link rel="stylesheet" href="assets/css/staff-topbar.css?v=<?= filemtime(__DIR__ . '/../../../public/assets/css/staff-topbar.css') ?>">
<header class="<?= $orderingHeader ? 'topbar' : 'head' ?> staff-shared-header">
  <div class="header-actions"><?php require __DIR__ . '/sidebar.php'; ?></div>
  <div class="topbar-default" <?php if ($orderingHeader): ?>x-show="!searchOpen || (page !== 'menu' && page !== 'tables')" x-transition:enter="search-enter" x-transition:enter-start="search-enter-start" x-transition:enter-end="search-enter-end" x-transition:leave="search-leave" x-transition:leave-start="search-enter-end" x-transition:leave-end="search-enter-start"<?php endif; ?>>
  <?php $headerBrand = true; require __DIR__ . '/brand.php'; $headerBrand = false; ?>
<?php if ($orderingHeader): ?>
      <div class="topbar-right">

        <div class="language-switch" :class="{'is-english': language === 'en'}" role="group" aria-label="Language / ภาษา" x-show="!staff || page === 'login'" x-cloak>
          <button type="button" @click="setLanguage('th')" :class="{'active': language === 'th'}" :aria-pressed="language === 'th'">TH</button>
          <button type="button" @click="setLanguage('en')" :class="{'active': language === 'en'}" :aria-pressed="language === 'en'">EN</button>
        </div>
        <button type="button" class="desktop-staff-btn" x-show="!staff && page !== 'login'" x-cloak @click="navigate('login')" :title="t('เข้าสู่ระบบพนักงาน')">
          <svg class="icon" aria-hidden="true"><use href="#i-user"/></svg>
          <span x-text="t('พนักงาน')">พนักงาน</span>
        </button>
      </div>
<?php endif; ?>
  </div>
<?php if ($orderingHeader): ?>
    <div class="menu-search-top" x-ref="menuSearchBar" x-show="searchOpen && (page === 'menu' || page === 'tables')" x-transition:enter="search-enter" x-transition:enter-start="search-enter-start" x-transition:enter-end="search-enter-end" x-transition:leave="search-leave" x-transition:leave-start="search-enter-end" x-transition:leave-end="search-enter-start" x-cloak>
      <label class="search-field" x-show="page === 'menu'"><svg class="icon"><use href="#i-search"/></svg><input x-ref="menuSearch" type="search" enterkeyhint="search" x-model.debounce.150ms="search" :placeholder="t('ค้นหาเมนูที่อยากทาน...')" :aria-label="t('ค้นหาอาหาร')" @focus="menuSearchFocused = true" @blur="menuSearchFocused = false" @keydown.enter.prevent="$el.blur()" @keydown.escape.stop="closeMenuSearch()"></label>
      <label class="search-field" x-show="page === 'tables'"><svg class="icon"><use href="#i-search"/></svg><input x-ref="tableSearchTop" type="search" enterkeyhint="search" x-model.trim="tableSearch" :placeholder="t('พิมพ์หมายเลขโต๊ะ')" :aria-label="t('ค้นหาหมายเลขโต๊ะ')" @keydown.enter.prevent="$el.blur()" @keydown.escape.stop="closeMenuSearch()"></label>
      <button type="button" class="menu-search-close" @click="closeMenuSearch()" :aria-label="t('ปิดการค้นหา')"><svg class="icon"><use href="#i-close"/></svg></button>
    </div>
<?php endif; ?>
</header>
<?php if (!$orderingHeader): ?>
<script>window.staffCsrf = <?= json_encode($_SESSION['csrf_token'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script defer src="assets/vendor/sweetalert2.min.js"></script>
<script defer src="assets/js/account-menu.js?v=<?= filemtime(__DIR__ . '/../../../public/assets/js/account-menu.js') ?>"></script>
<?php endif; ?>
