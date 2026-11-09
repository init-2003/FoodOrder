<?php
/** Shared navigation for server-rendered staff screens and the Alpine ordering screen. */
$navigation = require dirname(__DIR__, 3) . '/config/staff-navigation.php';
$useAlpine = ($sidebarMode ?? '') === 'alpine';
$currentRoute = $route ?? '';
if ($currentRoute === '/staff/food-images/') $currentRoute = '/staff/menus/';
?>
<nav class="account-menu-nav" aria-label="เมนูการทำงานพนักงาน">
<?php foreach ($navigation as $item):
    if (!$useAlpine && $item['key'] === 'expenses' && empty($canViewExpenses)) continue;
    $active = !$useAlpine && $currentRoute === $item['route'];
    $label = htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8');
    $href = htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8');
?>
  <a class="account-menu-link<?= $active ? ' active' : '' ?>" href="<?= $href ?>"<?= $active ? ' aria-current="page"' : '' ?><?php if ($useAlpine && $item['key'] === 'tables'): ?> :class="{'active': page === 'tables' || location.pathname.includes('/staff/tables/')}" :aria-current="page === 'tables' || location.pathname.includes('/staff/tables/') ? 'page' : null" @click.prevent="openTableSelector()"<?php elseif ($useAlpine && $item['key'] === 'expenses'): ?> x-show="staff?.canViewExpenses" x-cloak<?php endif ?>>
    <span class="account-menu-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $item['icon'] ?></svg></span>
    <span class="account-menu-text"><strong <?= $useAlpine ? 'x-text="t(&quot;' . $label . '&quot;)"' : 'data-i18n="' . $label . '"' ?>><?= $label ?></strong></span>
  </a>
<?php endforeach ?>
</nav>
