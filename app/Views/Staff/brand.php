<a class="brand<?= !empty($headerBrand) ? ' mobile-brand' : '' ?>" href="<?= $orderingHeader ? './' : 'staff/' ?>" <?php if ($orderingHeader): ?>@click.prevent="staff ? openTableSelector() : navigate('menu')" :aria-label="t('อันเกิม')"<?php else: ?>aria-label="อันเกิม"<?php endif; ?>>
  <span class="brand-mark"><img src="assets/images/restaurant-logo.svg?v=<?= assetVersion('assets/images/restaurant-logo.svg') ?>" alt=""></span>
  <span class="brand-copy"><span class="brand-th" <?php if ($orderingHeader): ?>x-text="t('อันเกิม')"<?php else: ?>data-i18n="อันเกิม"<?php endif; ?>>อันเกิม</span></span>
</a>
