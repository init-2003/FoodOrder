<a class="brand<?= !empty($headerBrand) ? ' mobile-brand' : '' ?>" href="<?= $orderingHeader ? './' : 'staff/' ?>" <?php if ($orderingHeader): ?>@click.prevent="staff ? openTableSelector() : navigate('menu')" :aria-label="t('อันเกิม')"<?php else: ?>aria-label="อันเกิม"<?php endif; ?>>
  <span class="brand-mark"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11h18a9 9 0 0 1-18 0ZM7 21h10M8 3v4m4-5v5m4-4v4"/></svg></span>
  <span class="brand-copy"><span class="brand-th" <?php if ($orderingHeader): ?>x-text="t('อันเกิม')"<?php endif; ?>>อันเกิม</span><span class="brand-en" lang="vi">Ăn cơm</span></span>
</a>
