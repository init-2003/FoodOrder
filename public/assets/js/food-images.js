(() => {
  const { branch, csrf } = window.foodImagesConfig;
  const items = document.querySelector('#items'), status = document.querySelector('#status');
  const search = document.querySelector('#search'), category = document.querySelector('#category');
  let products = [], categories = [];
  const t = text => window.staffTranslate ? window.staffTranslate(text) : text;
  const escape = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' })[char]);
  function render(animate = false) {
    const query = search.value.trim().toLocaleLowerCase();
    const matching = products.filter(item => (!category.value || item.category === category.value) && `${item.name} ${item.id}`.toLocaleLowerCase().includes(query));
    const english = document.documentElement.lang === 'en';
    items.innerHTML = matching.map(item => {
      const categoryName = categories.find(cat => cat.id === item.category)?.name;
      const unit = String(item.unit ?? '').trim();
      return `<article class="item"><div class="item-image"><img src="${escape(item.image)}" alt="" loading="lazy"></div><div class="item-info"><strong>${escape(item.name)}</strong><span class="item-code">${english ? 'Code' : 'รหัส'}: ${escape(item.id)}</span>${categoryName ? `<span class="item-category">${escape(categoryName)}</span>` : ''}<span class="item-price">฿${Number(item.price).toLocaleString('th-TH', { maximumFractionDigits: 2 })}${unit ? ` <span>/ ${escape(unit)}</span>` : ''}</span><button type="button" class="upload">${t(item.hasImage ? 'เปลี่ยนรูป' : 'เพิ่มรูป')}</button><input type="file" accept="image/jpeg,image/png,image/webp"></div></article>`;
    }).join('');
    status.textContent = matching.length ? `${matching.length} ${document.documentElement.lang === 'en' ? 'items' : 'รายการ'}` : t('ไม่พบรายการอาหาร');
    [...items.children].forEach((card, index) => {
      if (animate === true) {
        card.style.setProperty('--entrance-delay', `${index * 55}ms`);
        card.classList.add('item-enter');
      }
      const button = card.querySelector('button'), input = card.querySelector('input');
      button.addEventListener('click', () => input.click());
      input.addEventListener('change', async () => {
        const file = input.files?.[0]; if (!file) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) { alert(t('กรุณาเลือกรูป JPG, PNG หรือ WebP ขนาดไม่เกิน 5 MB')); input.value = ''; return; }
        const objectUrl = URL.createObjectURL(file), img = card.querySelector('img'), previous = img.src;
        img.src = objectUrl;
        const message = document.documentElement.lang === 'en' ? `Use this photo for ${matching[index].name}?` : `ใช้รูปนี้สำหรับ ${matching[index].name} หรือไม่?`;
        const accepted = await new Promise(resolve => requestAnimationFrame(() => setTimeout(() => resolve(confirm(message)), 0)));
        if (!accepted) { img.src = previous; URL.revokeObjectURL(objectUrl); input.value = ''; return; }
        const data = new FormData(); data.set('id', matching[index].id); data.set('branch', branch); data.set('image', file);
        button.disabled = true; button.textContent = t('กำลังบันทึก...');
        try {
          const response = await fetch('api/food-image.php', { method:'POST', headers:{ 'X-CSRF-Token':csrf, 'Accept':'application/json' }, body:data });
          const result = await response.json();
          if (!response.ok || !result.success) throw new Error(result.error?.message || 'บันทึกรูปไม่สำเร็จ');
          matching[index].image = result.image; matching[index].hasImage = true; img.src = result.image; button.textContent = t('เปลี่ยนรูป');
        } catch (error) { img.src = previous; alert(error.message || t('บันทึกรูปไม่สำเร็จ')); button.textContent = t(matching[index].hasImage ? 'เปลี่ยนรูป' : 'เพิ่มรูป'); }
        finally { URL.revokeObjectURL(objectUrl); input.value = ''; button.disabled = false; }
      });
    });
  }
  async function init() {
    try {
      const response = await fetch(`api/menu.php?${new URLSearchParams({branch})}`, {headers:{Accept:'application/json'}});
      if (!response.ok) throw new Error('ไม่สามารถโหลดรายการอาหารได้');
      const data = await response.json(); products = data.items; categories = data.categories;
      for (const cat of data.categories) { const option = document.createElement('option'); option.value = cat.id; option.textContent = cat.name; category.append(option); }
      render(true);
    } catch (error) { status.textContent = error.message || 'ไม่สามารถโหลดรายการอาหารได้'; }
  }
  search.addEventListener('input', render); category.addEventListener('change', render); init();
  window.addEventListener('staff-language-change', () => { if (products.length) render(); });
})();
