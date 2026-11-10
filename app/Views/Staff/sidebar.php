<?php
$orderingHeader = ($headerMode ?? '') === 'alpine';
$staffHeaderName = htmlspecialchars(trim((string) ($_SESSION['Em_Fullname'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$staffHeaderRole = htmlspecialchars(trim((string) ($_SESSION['Status'] ?? 'Staff')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<details class="account-menu" <?php if ($orderingHeader): ?>x-show="staff && page !== 'login'" x-cloak<?php else: ?>id="staff-account-menu"<?php endif; ?>>
  <summary aria-label="เมนูการทำงานและบัญชีพนักงาน">
    <svg class="hamburger-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <line x1="3.5" y1="5.5" x2="16.5" y2="5.5"/>
      <line x1="3.5" y1="12" x2="20.5" y2="12"/>
      <line x1="3.5" y1="18.5" x2="11.5" y2="18.5"/>
    </svg>
  </summary>
  <div class="account-menu-backdrop" aria-hidden="true"></div>
  <div class="account-menu-panel">
    <div class="account-menu-brand"><?php $headerBrand = false; require __DIR__ . '/brand.php'; ?></div>
    <div class="account-menu-divider"></div>
    <div class="account-menu-profile">
      <div class="account-menu-avatar" aria-hidden="true">
        <img src="assets/images/user.png" alt="">
      </div>
      <div class="account-menu-profile-info">
        <div class="account-menu-name"><span class="account-menu-name-text" <?php if ($orderingHeader): ?>x-text="staff?.name || ''"<?php endif; ?>><?= $orderingHeader ? '' : $staffHeaderName ?></span></div>
        <div class="account-menu-role" <?php if ($orderingHeader): ?>x-text="staff?.role || 'Staff'"<?php endif; ?>><?= $orderingHeader ? '' : $staffHeaderRole ?></div>
      </div>
    </div>
    <div class="account-menu-divider"></div>
    <div class="account-menu-section-label" <?php if ($orderingHeader): ?>x-text="t('เลือกการทำงาน')"<?php else: ?>data-i18n="เลือกการทำงาน"<?php endif; ?>>เลือกการทำงาน</div>
    <?php $sidebarMode = $orderingHeader ? 'alpine' : 'server'; require __DIR__ . '/navigation.php'; ?>
    <div class="account-menu-footer">
      <div class="account-menu-divider"></div>
      <div class="account-menu-lang-row">
        <span class="account-menu-label" <?php if ($orderingHeader): ?>x-text="language === 'en' ? 'Language' : 'ภาษา'"<?php else: ?>data-i18n="ภาษา"<?php endif; ?>>ภาษา</span>
        <div class="language-switch" <?php if ($orderingHeader): ?>:class="{'is-english': language === 'en'}"<?php endif; ?> role="group" aria-label="Language / ภาษา">
          <button type="button" <?php if ($orderingHeader): ?>@click="setLanguage('th')" :class="{'active': language === 'th'}" :aria-pressed="language === 'th'"<?php else: ?>data-language="th" class="active" aria-pressed="true"<?php endif; ?>>TH</button>
          <button type="button" <?php if ($orderingHeader): ?>@click="setLanguage('en')" :class="{'active': language === 'en'}" :aria-pressed="language === 'en'"<?php else: ?>data-language="en" aria-pressed="false"<?php endif; ?>>EN</button>
        </div>
      </div>
      <button type="button" class="account-menu-logout" <?php if ($orderingHeader): ?>@click="logoutStaff()" x-text="t('ออกจากระบบ')"<?php else: ?>id="staff-logout" data-i18n="ออกจากระบบ"<?php endif; ?>>ออกจากระบบ</button>
    </div>
  </div>
</details>
