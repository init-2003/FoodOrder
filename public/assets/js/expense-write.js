(() => {
  const dialog = document.querySelector('#expense-dialog');
  const form = document.querySelector('#expense-form');
  const save = document.querySelector('#expense-save');
  const error = document.querySelector('#expense-form-error');
  const t = text => window.staffTranslate ? window.staffTranslate(text) : text;
  let saving = false, closing = false;
  document.querySelector('#expense-open').addEventListener('click', () => { error.hidden = true; dialog.showModal(); });
  const close = () => {
    if (saving || closing || !dialog.open) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) { dialog.close(); return; }
    closing = true;
    dialog.classList.add('is-closing');
    const onAnimationEnd = event => { if (event.target === dialog && event.animationName === 'expense-dialog-out') finish(); };
    const finish = () => { if (!closing) return; closing = false; dialog.removeEventListener('animationend', onAnimationEnd); dialog.classList.remove('is-closing'); dialog.close(); };
    dialog.addEventListener('animationend', onAnimationEnd);
    window.setTimeout(finish, 250);
  };
  document.querySelector('#expense-close').addEventListener('click', close);
  document.querySelector('#expense-cancel').addEventListener('click', close);
  dialog.addEventListener('cancel', event => { event.preventDefault(); close(); });
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (saving || !form.reportValidity()) return;
    saving = true; save.disabled = true; save.textContent = t('กำลังบันทึก...'); error.hidden = true;
    try {
      const response = await fetch('api/expense.php', { method: 'POST', headers: { 'X-CSRF-Token': window.staffCsrf, 'Accept': 'application/json' }, body: new FormData(form) });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.error?.message || 'ไม่สามารถบันทึกค่าใช้จ่ายได้');
      const [year, month] = result.date.split('-');
      const params = new URLSearchParams({ year, month: String(Number(month)), saved: result.docNo });
      window.location.assign('staff/expenses/?' + params);
    } catch (e) {
      error.dataset.i18n = e.message || 'ไม่สามารถเชื่อมต่อได้ กรุณาลองอีกครั้ง';
      error.textContent = t(error.dataset.i18n); error.hidden = false;
      saving = false; save.disabled = false; save.textContent = t('บันทึก');
    }
  });
})();
