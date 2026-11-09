(() => {
  const translations = {
    'หน้าหลักพนักงาน': 'Staff home', 'เลือกการทำงาน': 'Choose an action', 'จัดการโต๊ะและรูปเมนูอาหารของร้าน': 'Manage tables and food photos',
    'เลือกโต๊ะ': 'Select table', 'เปิดโต๊ะและรับรายการอาหาร': 'Open tables and take orders', 'จัดการรายการอาหาร': 'Manage food items',
    'เพิ่มและเปลี่ยนรูปอาหาร': 'Add or change food photos', '← หน้าหลัก': '← Home', 'จัดการเมนู': 'Menu management',
    'รายการอาหาร': 'Food items', 'เพิ่มหรือเปลี่ยนรูปอาหารที่แสดงในเมนู': 'Add or change menu photos',
    'ค้นหาชื่อหรือรหัสอาหาร': 'Search name or item code', 'ทุกหมวดหมู่': 'All categories', 'กำลังโหลดรายการอาหาร...': 'Loading food items...',
    'เพิ่มรูป': 'Add photo', 'เปลี่ยนรูป': 'Change photo', 'กำลังบันทึก...': 'Saving...', 'ไม่พบรายการอาหาร': 'No food items found',
    'ไม่สามารถโหลดรายการอาหารได้': 'Unable to load food items', 'บันทึกรูปไม่สำเร็จ': 'Unable to save photo',
    'กรุณาเลือกรูป JPG, PNG หรือ WebP ขนาดไม่เกิน 5 MB': 'Choose a JPG, PNG or WebP image up to 5 MB',
    'จัดการโต๊ะ': 'Table management', 'สถานะโต๊ะอาหาร': 'Table status',
    'เลือกโต๊ะเพื่อเปิดบิลใหม่ หรือเข้ารายการอาหารของโต๊ะที่เปิดอยู่': 'Select a table to open a new bill or view active orders',
    'ค้นหาหมายเลขโต๊ะ': 'Search table number', 'ทั้งหมด': 'All', 'เปิดบิลแล้ว': 'Occupied', 'ว่าง': 'Available',
    'รีเฟรช': 'Refresh', 'กำลังโหลดรายการโต๊ะ...': 'Loading tables...', 'ไม่พบโต๊ะที่ค้นหา': 'No tables found',
    'เปิดโต๊ะใหม่': 'Open new table', 'เข้ารายการอาหาร': 'Go to order', 'เลขที่บิล': 'Bill No.',
    'ยอดรวม': 'Total', 'เวลาเปิด': 'Opened at', 'สถานะ': 'Status', 'โต๊ะพร้อมให้บริการ': 'Table available'
  };
  function readLanguage() { try { return localStorage.getItem('imdee:language') === 'en' ? 'en' : 'th'; } catch { return 'th'; } }
  window.staffTranslate = text => readLanguage() === 'en' ? (translations[text] || text) : text;
  function apply() {
    const language = readLanguage(); document.documentElement.lang = language;
    document.querySelector('.language-switch')?.classList.toggle('is-english', language === 'en');
    document.querySelectorAll('[data-language]').forEach(button => { const active = button.dataset.language === language; button.classList.toggle('active', active); button.setAttribute('aria-pressed', String(active)); });
    document.querySelectorAll('[data-i18n]').forEach(element => { element.textContent = window.staffTranslate(element.dataset.i18n); });
    document.querySelectorAll('[data-i18n-placeholder]').forEach(element => { element.placeholder = window.staffTranslate(element.dataset.i18nPlaceholder); element.setAttribute('aria-label', element.placeholder); });
    const logout = document.querySelector('#staff-logout'); logout.title = logout.ariaLabel = language === 'en' ? 'Log out' : 'ออกจากระบบ';
    window.dispatchEvent(new Event('staff-language-change'));
  }
  document.querySelectorAll('[data-language]').forEach(button => button.addEventListener('click', () => { try { localStorage.setItem('imdee:language', button.dataset.language); } catch {} apply(); }));
  document.querySelector('#staff-logout').addEventListener('click', async event => {
    if (!confirm(readLanguage() === 'en' ? 'Log out of the staff account?' : 'ออกจากระบบพนักงานหรือไม่?')) return;
    event.currentTarget.disabled = true;
    try {
      const response = await fetch('api/auth.php', {method:'POST', headers:{'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-Token':window.staffCsrf}, body:JSON.stringify({action:'logout'})});
      if (!response.ok) throw new Error();
      try { localStorage.removeItem('imdee:staff'); } catch {}
      window.location.href = './';
    } catch { alert(readLanguage() === 'en' ? 'Unable to log out. Please try again.' : 'ออกจากระบบไม่สำเร็จ กรุณาลองอีกครั้ง'); event.currentTarget.disabled = false; }
  });
  apply();
})();
