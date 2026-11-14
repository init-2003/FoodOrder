(() => {
  const translations = {
    'อันเกิม': 'Angeum', 'เมนู': 'Menu', 'ภาษา': 'Language', 'ออกจากระบบ': 'Log out',
    'เมนูการทำงาน': 'Staff menu', 'ปิดเมนู': 'Close menu',
    'หน้าหลักพนักงาน': 'Staff home', 'เลือกการทำงาน': 'Choose an action', 'จัดการโต๊ะและรูปเมนูอาหารของร้าน': 'Manage tables and food photos',
    'เลือกโต๊ะ': 'Select table', 'เปิดโต๊ะและรับรายการอาหาร': 'Open tables and take orders', 'จัดการรายการอาหาร': 'Manage food items',
    'เพิ่มและเปลี่ยนรูปอาหาร': 'Add or change food photos', '← หน้าหลัก': '← Home', 'จัดการเมนู': 'Menu management',
    'รายการอาหาร': 'Food items', 'เมนูอาหาร': 'Food menu', 'เพิ่มหรือเปลี่ยนรูปอาหารที่แสดงในเมนู': 'Add or change menu photos',
    'ค้นหาชื่อหรือรหัสอาหาร': 'Search name or item code', 'ทุกหมวดหมู่': 'All categories', 'กำลังโหลดรายการอาหาร...': 'Loading food items...',
    'เพิ่มรูป': 'Add photo', 'เปลี่ยนรูป': 'Change photo', 'ลบรูป': 'Remove photo', 'ลบรูปไม่สำเร็จ': 'Unable to remove photo', 'กำลังบันทึก...': 'Saving...', 'ไม่พบรายการอาหาร': 'No food items found',
    'ไม่สามารถโหลดรายการอาหารได้': 'Unable to load food items', 'บันทึกรูปไม่สำเร็จ': 'Unable to save photo',
    'กรุณาเลือกรูป JPG, PNG หรือ WebP ขนาดไม่เกิน 5 MB': 'Choose a JPG, PNG or WebP image up to 5 MB',
    'จัดการโต๊ะ': 'Table management', 'สถานะโต๊ะอาหาร': 'Table status',
    'เลือกโต๊ะเพื่อเปิดบิลใหม่ หรือเข้ารายการอาหารของโต๊ะที่เปิดอยู่': 'Select a table to open a new bill or view active orders',
    'ค้นหาหมายเลขโต๊ะ': 'Search table number', 'ทั้งหมด': 'All', 'เปิดบิลแล้ว': 'Occupied', 'ว่าง': 'Available',
    'รีเฟรช': 'Refresh', 'กำลังโหลดรายการโต๊ะ...': 'Loading tables...', 'ไม่พบโต๊ะที่ค้นหา': 'No tables found',
    'เปิดโต๊ะใหม่': 'Open new table', 'เข้ารายการอาหาร': 'Go to order', 'เลขที่บิล': 'Bill No.',
    'ยอดรวม': 'Total', 'เวลาเปิด': 'Opened at', 'สถานะ': 'Status', 'โต๊ะพร้อมให้บริการ': 'Table available',
    'รายรับ-รายจ่าย': 'Expenses', 'ดูรายจ่ายประจำเดือน': 'Monthly expenses'
  };
  Object.assign(translations, {
    'หน้าหลัก': 'Home', 'เลือกโต๊ะ/เปิดบิล': 'Tables / Open bill', 'จัดการเมนูอาหาร': 'Manage menu',
    'รายรับ-รายจ่าย · อันเกิม': 'Expenses · Angeum',
    'ค่าใช้จ่าย · อันเกิม': 'Expenses · Angeum',
    'ภาพรวมรายจ่ายของร้าน แยกตามประเภท': 'An overview of store expenses by category',
    'เดือน': 'Month', 'ปี (ค.ศ.)': 'Year (CE)', 'แสดงรายงาน': 'Show report',
    'ค่าใช้จ่าย': 'Expenses', 'ยอดรวมทั้งหมด': 'Grand total', 'รวมค่าใช้จ่ายทั้งหมด': 'Total expenses',
    'บันทึกค่าใช้จ่าย': 'Add expense', 'บันทึกค่าใช้จ่ายแล้ว เลขที่เอกสาร': 'Expense saved. Document no.',
    'วันที่': 'Date', 'ประเภทค่าใช้จ่าย': 'Expense category', 'เลือกประเภทค่าใช้จ่าย': 'Select an expense category',
    'รายละเอียด': 'Details', 'ระบุรายละเอียดค่าใช้จ่าย': 'Enter expense details', 'จำนวนเงิน (บาท)': 'Amount (THB)',
    'ผู้จ่าย': 'Paid by', 'เลือกผู้จ่าย': 'Select a payer', 'ยกเลิก': 'Cancel', 'บันทึก': 'Save', 'ปิด': 'Close',
    'ไม่พบประเภทค่าใช้จ่ายหรือข้อมูลพนักงาน': 'Expense categories or staff are unavailable.',
    'ไม่สามารถโหลดข้อมูลสำหรับบันทึกค่าใช้จ่ายได้': 'Unable to load expense form data.',
    'ไม่สามารถบันทึกค่าใช้จ่ายได้': 'Unable to save the expense.',
    'ไม่สามารถบันทึกค่าใช้จ่ายได้ กรุณาติดต่อผู้ดูแลระบบ': 'Unable to save the expense. Please contact the administrator.',
    'ไม่สามารถเชื่อมต่อได้ กรุณาลองอีกครั้ง': 'Unable to connect. Please try again.',
    'กรุณาระบุวันที่ให้ถูกต้อง': 'Enter a valid date.',
    'กรุณาระบุรายละเอียดไม่เกิน 1,000 ตัวอักษร': 'Enter expense details of no more than 1,000 characters.',
    'กรุณาระบุจำนวนเงินที่มากกว่า 0 และทศนิยมไม่เกิน 2 ตำแหน่ง': 'Enter an amount greater than zero with at most two decimal places.',
    'กรุณาเลือกประเภทค่าใช้จ่ายและผู้จ่าย': 'Select an expense category and payer.',
    'ข้อมูลสาขาหรือผู้บันทึกไม่ถูกต้อง': 'Invalid branch or employee information.',
    'คุณไม่มีสิทธิ์บันทึกค่าใช้จ่าย': 'You do not have permission to save expenses.',
    'แบบฟอร์มหมดอายุ กรุณาเปิดหน้าใหม่': 'The form has expired. Please reload the page.',
    'กรุณาเข้าสู่ระบบพนักงาน': 'Please sign in as staff.',
    'หมวดหมู่': 'Category', 'ข้อมูลสินค้าไม่ถูกต้อง': 'Invalid product information.',
    'ข้อมูลรูปภาพไม่ถูกต้อง': 'Invalid image information.', 'ไม่พบเมนูที่เลือก': 'The selected item was not found.',
    'รูปอาหารมีการเปลี่ยนแปลง กรุณารีเฟรชหน้าแล้วลองอีกครั้ง': 'The photo has changed. Please refresh and try again.',
    'กรุณาเลือกรูปภาพขนาดไม่เกิน 5 MB': 'Choose an image up to 5 MB.',
    'รองรับรูป JPG, PNG หรือ WebP ขนาดไม่เกิน 16 ล้านพิกเซล': 'Choose a JPG, PNG or WebP image up to 16 megapixels.',
    'เซิร์ฟเวอร์ยังไม่รองรับการประมวลผลรูปภาพ กรุณาติดต่อผู้ดูแลระบบ': 'Image processing is unavailable. Please contact the administrator.',
    'ไม่สามารถเชื่อมต่อฐานข้อมูลได้': 'Unable to connect to the database.',
    'ชื่อไฟล์หรือพาธรูปภาพยาวเกินไป': 'The image filename or path is too long.',
    'ไม่สามารถเตรียมพื้นที่เก็บรูปได้': 'Unable to prepare image storage.', 'ไม่สามารถบันทึกรูปได้': 'Unable to save the image.',
    'ไม่สามารถบันทึกรูปได้ กรุณาลองอีกครั้ง': 'Unable to save the image. Please try again.',
    'ไม่สามารถลบรูปได้ กรุณาลองอีกครั้ง': 'Unable to remove the image. Please try again.', 'ไม่สามารถอ่านไฟล์รูปภาพได้': 'Unable to read the image.',
    'รายจ่ายแยกตามประเภท': 'Expenses by category', 'ประเภท': 'categories',
    'รายละเอียดรายงาน': 'Report details', 'ช่วงเวลา': 'Period', 'รายการ': 'Items', 'บาท': 'baht',
    'ไม่มีข้อมูล': 'No data', 'ไม่มีข้อมูลรายจ่ายในเดือนนี้': 'No expenses for this month',
    'กรุณาเลือกปีและเดือนให้ถูกต้อง': 'Please select a valid month and year',
    'ไม่มีข้อมูลรายจ่ายในฐานข้อมูล': 'No expense data is available',
    'ไม่มีข้อมูลรายจ่ายสำหรับปีที่เลือก': 'No expense data is available for the selected year',
    'ไม่สามารถโหลดรายงานได้ กรุณาลองใหม่อีกครั้ง': 'Unable to load the report. Please try again.',
    'ไม่สามารถแสดงสัดส่วนได้เมื่อยอดรวมไม่เป็นบวกหรือมีรายการติดลบ': 'Shares are unavailable when the total is not positive or an item is negative',
    'สัดส่วนรายจ่ายเทียบกับยอดรวมทั้งหมด': 'Expense shares of the grand total',
    'มกราคม': 'January', 'กุมภาพันธ์': 'February', 'มีนาคม': 'March', 'เมษายน': 'April',
    'พฤษภาคม': 'May', 'มิถุนายน': 'June', 'กรกฎาคม': 'July', 'สิงหาคม': 'August',
    'กันยายน': 'September', 'ตุลาคม': 'October', 'พฤศจิกายน': 'November', 'ธันวาคม': 'December'
  });
  function readLanguage() { try { return localStorage.getItem('imdee:language') === 'en' ? 'en' : 'th'; } catch { return 'th'; } }
  window.staffTranslate = text => readLanguage() === 'en' ? (translations[text] || text) : text;
  function apply() {
    const language = readLanguage(); document.documentElement.lang = language;
    document.querySelectorAll('.language-switch').forEach(el => el.classList.toggle('is-english', language === 'en'));
    document.querySelectorAll('[data-language]').forEach(button => { const active = button.dataset.language === language; button.classList.toggle('active', active); button.setAttribute('aria-pressed', String(active)); });
    document.querySelectorAll('[data-i18n]').forEach(element => { element.textContent = window.staffTranslate(element.dataset.i18n); });
    document.querySelectorAll('[data-i18n-placeholder]').forEach(element => { element.placeholder = window.staffTranslate(element.dataset.i18nPlaceholder); element.setAttribute('aria-label', element.placeholder); });
    document.querySelectorAll('[data-i18n-aria-label]').forEach(element => { element.setAttribute('aria-label', window.staffTranslate(element.dataset.i18nAriaLabel)); });
    document.querySelectorAll('[data-i18n-count]').forEach(element => { element.textContent = language === 'en' ? (Number(element.dataset.i18nCount) === 1 ? 'category' : 'categories') : 'ประเภท'; });
    const logout = document.querySelector('#staff-logout'); logout.title = logout.ariaLabel = language === 'en' ? 'Log out' : 'ออกจากระบบ';
    window.dispatchEvent(new Event('staff-language-change'));
  }
  document.querySelectorAll('[data-language]').forEach(button => button.addEventListener('click', () => { try { localStorage.setItem('imdee:language', button.dataset.language); } catch {} apply(); }));
  document.querySelector('#staff-logout').addEventListener('click', async event => {
    const button = event.currentTarget;
    const isEnglish = readLanguage() === 'en';
    const name = document.querySelector('.account-menu-name-text:not(.account-menu-name-copy)')?.textContent.trim() || '';
    const result = await Swal.fire({
      title: isEnglish ? 'Log out of the staff account?' : 'ออกจากระบบพนักงาน?',
      text: name ? (isEnglish ? `Current user: ${name}` : `ผู้ใช้งานปัจจุบัน: ${name}`) : '',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: isEnglish ? 'Log out' : 'ออกจากระบบ',
      cancelButtonText: isEnglish ? 'Cancel' : 'ยกเลิก',
      confirmButtonColor: '#ef7147',
      cancelButtonColor: '#8c938f',
      reverseButtons: true,
    });
    if (!result.isConfirmed) return;
    button.disabled = true;
    try {
      const response = await fetch('api/auth.php', {method:'POST', headers:{'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-Token':window.staffCsrf}, body:JSON.stringify({action:'logout'})});
      if (!response.ok) throw new Error();
      try { localStorage.removeItem('imdee:staff'); } catch {}
      window.location.href = './';
    } catch { button.disabled = false; await Swal.fire({ icon: 'error', title: isEnglish ? 'Unable to log out' : 'ออกจากระบบไม่สำเร็จ', text: isEnglish ? 'Please try again.' : 'กรุณาลองอีกครั้ง', confirmButtonColor: '#ef7147', confirmButtonText: isEnglish ? 'OK' : 'ตกลง' }); }
  });
  apply();
})();
