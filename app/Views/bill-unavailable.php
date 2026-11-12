<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php require __DIR__ . '/browser-head.php'; ?>
  <link rel="stylesheet" href="<?= htmlspecialchars(($publicBase ?? '/') . 'assets/css/font-remote.css', ENT_QUOTES, 'UTF-8') ?>">
  <style>
    * { box-sizing: border-box; }
    body { margin: 0; min-height: 100vh; min-height: 100dvh; display: grid; place-items: center; background: #fff; color: #263a31; font: 16px/1.6 'Noto Sans Thai', system-ui, sans-serif; }
    main { width: min(100%, 440px); padding: 32px 24px; text-align: center; }
    .icon { width: 88px; height: 88px; margin: 0 auto 24px; display: grid; place-items: center; border-radius: 50%; background: #fff0e9; color: #fa784e; }
    .icon svg { width: 42px; height: 42px; }
    h1 { margin: 0 0 8px; font-size: 24px; line-height: 1.4; }
    p { margin: 0; color: #718078; }
    header { position: absolute; top: 0; left: 0; right: 0; display: flex; align-items: center; justify-content: space-between; padding: 18px 28px; }
    .brand { display: flex; align-items: center; gap: 10px; }
    .brand-mark { display: grid; place-items: center; width: 40px; height: 40px; }
    .brand-mark img { width: 100%; height: 100%; object-fit: contain; }
    .brand-copy { display: flex; flex-direction: column; line-height: 1; }
    .brand-th { font: 32px/1 'SP KinKuSi', 'Noto Sans Thai', sans-serif; }
    .brand-en { color: #879488; font-size: 9px; letter-spacing: 1px; }
    .language { display: flex; padding: 3px; border: 1px solid #d9e0da; border-radius: 22px; background: #e9eeea; }
    .language button { width: 30px; height: 30px; padding: 0; border: 0; border-radius: 50%; background: transparent; color: #5e7166; font: 600 11px 'Noto Sans Thai', sans-serif; cursor: pointer; }
    .language button[aria-pressed="true"] { background: #fa784e; color: #fff; }
    @media (max-width: 760px) { header { padding: 16px; } }
  </style>
</head>
<body>
  <header>
    <div class="brand" aria-label="อันเกิม">
      <span class="brand-mark"><img src="<?= htmlspecialchars(($publicBase ?? '/') . 'assets/images/restaurant-logo.svg', ENT_QUOTES, 'UTF-8') ?>" alt=""></span>
      <span class="brand-copy"><span class="brand-th">อันเกิม</span></span>
    </div>
    <div class="language" role="group" aria-label="Language / ภาษา"><button type="button" data-language="th" aria-pressed="true">TH</button><button type="button" data-language="en" aria-pressed="false">EN</button></div>
  </header>
  <main>
    <div class="icon" aria-hidden="true"><svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13 7h22v34l-5-3-6 3-6-3-5 3V7Z"/><path d="M19 17h10M19 23h10M21 30l6-6m-6 0 6 6"/></svg></div>
    <h1 id="message-title"><?= $hashLookupUnavailable ? 'ไม่สามารถเชื่อมต่อข้อมูลบิลได้' : 'ไม่พบข้อมูลบิล' ?></h1>
    <p id="message-detail"><?= $hashLookupUnavailable ? 'กรุณาลองใหม่อีกครั้ง หรือติดต่อพนักงาน' : 'กรุณาตรวจสอบลิงก์หรือสแกน QR Code อีกครั้ง หากยังไม่พบบิล กรุณาติดต่อพนักงาน' ?></p>
  </main>
  <script>
    (function () {
      var unavailable = <?= $hashLookupUnavailable ? 'true' : 'false' ?>;
      var messages = {
        th: unavailable ? ['ไม่สามารถเชื่อมต่อข้อมูลบิลได้', 'กรุณาลองใหม่อีกครั้ง หรือติดต่อพนักงาน'] : ['ไม่พบข้อมูลบิล', 'กรุณาตรวจสอบลิงก์หรือสแกน QR Code อีกครั้ง หากยังไม่พบบิล กรุณาติดต่อพนักงาน'],
        en: unavailable ? ['Unable to connect to bill information', 'Please try again or contact a staff member.'] : ['Bill not found', 'Please check the link or scan the QR code again. If you still cannot find the bill, please contact a staff member.']
      };
      function setLanguage(language) {
        document.documentElement.lang = language;
        document.getElementById('message-title').textContent = messages[language][0];
        document.getElementById('message-detail').textContent = messages[language][1];
        document.querySelectorAll('[data-language]').forEach(function (button) { button.setAttribute('aria-pressed', String(button.dataset.language === language)); });
        try { localStorage.setItem('imdee:language', language); } catch (e) {}
      }
      document.querySelectorAll('[data-language]').forEach(function (button) { button.addEventListener('click', function () { setLanguage(button.dataset.language); }); });
      try { if (localStorage.getItem('imdee:language') === 'en') setLanguage('en'); } catch (e) {}
    })();
  </script>
</body>
</html>
