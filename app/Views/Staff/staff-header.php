<header class="head">
  <a class="brand" href="staff/" aria-label="อันเกิม">
    <span class="brand-mark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11h18a9 9 0 0 1-18 0ZM7 21h10M8 3v4m4-5v5m4-4v4"/></svg></span>
    <span class="brand-copy"><span class="brand-th">อันเกิม</span><span class="brand-en" lang="vi">Ăn cơm</span></span>
  </a>
  <div class="header-actions"><div class="language-switch" role="group" aria-label="Language / ภาษา"><button type="button" data-language="th" aria-pressed="true">TH</button><button type="button" data-language="en" aria-pressed="false">EN</button></div><button class="logout-icon" type="button" id="staff-logout" aria-label="ออกจากระบบ" title="ออกจากระบบ"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4m7 14 5-5-5-5m5 5H9"/></svg></button></div>
</header>
<script>window.staffCsrf = <?= json_encode($_SESSION['csrf_token'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
