const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

test('every literal interface translation key has an English value', () => {
  const source = fs.readFileSync('public/assets/js/translations.js', 'utf8');
  const ui = vm.runInNewContext(source + '\nUI_EN');
  const files = ['app/Views/app.php', 'app/Views/Staff/tables.php', 'app/Views/Staff/header.php', 'app/Views/Staff/sidebar.php', 'public/assets/js/app.js'];
  for (const file of files) {
    const content = fs.readFileSync(file, 'utf8');
    for (const match of content.matchAll(/\bt\('([^']*[\u0e00-\u0e7f][^']*)'\)/gu)) {
      assert.ok(ui[match[1]], `${file}: missing ${match[1]}`);
    }
  }
  assert.equal(ui['อันเกิม'], 'Angeum');
  assert.equal(ui['รอออเดอร์'], 'Awaiting order');
});

test('staff expense interface translates while arbitrary database text is preserved', () => {
  const source = fs.readFileSync('public/assets/js/staff-header.js', 'utf8');
  const logout = { addEventListener() {}, set title(value) {}, set ariaLabel(value) {} };
  const document = { documentElement: {}, querySelectorAll: () => [], querySelector: () => logout };
  const window = { dispatchEvent() {} };
  const localStorage = { getItem: () => 'en' };
  vm.runInNewContext(source, { window, document, localStorage, Event: class {}, console });
  for (const text of ['บันทึกค่าใช้จ่าย', 'วันที่', 'ประเภทค่าใช้จ่าย', 'รายละเอียด', 'จำนวนเงิน (บาท)', 'ผู้จ่าย', 'บันทึก', 'ยกเลิก', 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้']) {
    assert.notEqual(window.staffTranslate(text), text, text);
  }
  assert.equal(window.staffTranslate('อันเกิม'), 'Angeum');
  assert.equal(window.staffTranslate('ชื่อสินค้าจากฐานข้อมูล'), 'ชื่อสินค้าจากฐานข้อมูล');
});
