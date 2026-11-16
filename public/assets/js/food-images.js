(() => {
  const { branch, csrf } = window.foodImagesConfig;
  const items = document.querySelector('#items'), status = document.querySelector('#status');
  const search = document.querySelector('#search'), category = document.querySelector('#category');
  let products = [], categories = [];
  const t = text => window.staffTranslate ? window.staffTranslate(text) : text;
  const escape = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' })[char]);
  const viewer = document.createElement('dialog');
  viewer.className = 'food-image-viewer';
  viewer.innerHTML = `<div class="viewer-tools"><button type="button" data-viewer="zoom-in" aria-label="Zoom in">+</button><button type="button" data-viewer="zoom-out" aria-label="Zoom out">−</button></div><button type="button" class="viewer-close" data-viewer="close" aria-label="Close">×</button><button type="button" class="viewer-prev" data-viewer="prev" aria-label="Previous image">‹</button><div class="viewer-stage"><img alt=""></div><button type="button" class="viewer-next" data-viewer="next" aria-label="Next image">›</button><div class="viewer-caption"></div>`;
  document.body.append(viewer);
  const viewerImage = viewer.querySelector('img'), viewerCaption = viewer.querySelector('.viewer-caption');
  let viewerItems = [], viewerIndex = 0, viewerZoom = 1;
  function showViewerImage() {
    const item = viewerItems[viewerIndex];
    if (!item) return;
    viewerZoom = 1;
    viewerImage.style.transform = '';
    viewerImage.src = item.image;
    viewerImage.alt = item.name;
    viewerCaption.textContent = item.name;
    viewer.querySelector('[data-viewer="prev"]').hidden = viewerItems.length < 2;
    viewer.querySelector('[data-viewer="next"]').hidden = viewerItems.length < 2;
  }
  function viewerAction(action) {
    if (action === 'close') return viewer.close();
    if (action === 'prev' || action === 'next') {
      viewerIndex = (viewerIndex + (action === 'next' ? 1 : -1) + viewerItems.length) % viewerItems.length;
      showViewerImage();
    } else if (action === 'zoom-in' || action === 'zoom-out') {
      viewerZoom = Math.max(1, Math.min(3, viewerZoom + (action === 'zoom-in' ? 0.25 : -0.25)));
      viewerImage.style.transform = `scale(${viewerZoom})`;
    }
  }
  viewer.addEventListener('click', event => {
    const control = event.target.closest('[data-viewer]');
    if (control) viewerAction(control.dataset.viewer);
    else if (event.target === viewer) viewer.close();
  });
  viewer.addEventListener('keydown', event => {
    if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
      event.preventDefault();
      viewerAction(event.key === 'ArrowLeft' ? 'prev' : 'next');
    }
  });
  function render(animate = false) {
    const query = search.value.trim().toLocaleLowerCase();
    const matching = products.filter(item => (!category.value || item.category === category.value) && `${item.name} ${item.id}`.toLocaleLowerCase().includes(query));
    const english = document.documentElement.lang === 'en';
    items.innerHTML = matching.map(item => {
      const categoryName = categories.find(cat => cat.id === item.category)?.name;
      const unit = String(item.unit ?? '').trim();
      return `<article class="item"><div class="item-top"><div class="item-image"><img src="${escape(item.image)}" alt="" loading="lazy" ${item.hasImage ? '' : 'hidden'}><svg class="item-placeholder" ${item.hasImage ? 'hidden' : ''} viewBox="0 0 52 52" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13c-2 2.5 2 4.5 0 7M26 11c-2 2.5 2 4.5 0 7M34 13c-2 2.5 2 4.5 0 7M7 26h38M9 26c0 9 6.5 15 17 15s17-6 17-15M19 46h14"/></svg></div><div class="item-info"><strong>${escape(item.name)}</strong><span class="item-code">${english ? 'Code' : 'รหัส'} ${escape(item.id)}</span>${categoryName ? `<span class="item-category">${escape(categoryName)}</span>` : ''}</div></div><div class="item-footer"><span class="item-price">฿${Number(item.price).toLocaleString('th-TH', { maximumFractionDigits: 2 })}${unit ? ` <span>/ ${escape(unit)}</span>` : ''}</span><div class="image-actions"><button type="button" class="upload"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="2.5" width="12" height="11" rx="2"/><circle cx="5.8" cy="6.2" r="1.1"/><path d="M2.5 12l3.5-3.5 2.5 2.5 2-2 3 3"/></svg><span>${t(item.hasImage ? 'เปลี่ยนรูป' : 'เพิ่มรูป')}</span></button><button type="button" class="remove-image" ${item.hasImage ? '' : 'hidden'}><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 4.5h11M6 4.5V3h4v1.5M4 4.5l.6 8.5h6.8l.6-8.5M6.5 7v4M9.5 7v4"/></svg><span>${t('ลบรูป')}</span></button></div></div><input type="file" accept="image/jpeg,image/png,image/webp"></article>`;
    }).join('');
    status.textContent = matching.length ? `${matching.length} ${document.documentElement.lang === 'en' ? 'items' : 'รายการ'}` : t('ไม่พบรายการอาหาร');
    [...items.children].forEach((card, index) => {
      if (animate === true) {
        card.style.setProperty('--entrance-delay', `${index * 55}ms`);
        card.classList.add('item-enter');
      }
      const button = card.querySelector('.upload'), removeButton = card.querySelector('.remove-image'), input = card.querySelector('input');
      const image = card.querySelector('.item-image');
      const cardImage = image.querySelector('img'), placeholder = image.querySelector('.item-placeholder');
      cardImage.addEventListener('error', () => { cardImage.hidden = true; placeholder.hidden = false; });
      image.setAttribute('role', 'button');
      image.tabIndex = matching[index].hasImage ? 0 : -1;
      image.setAttribute('aria-label', `${english ? 'View photo' : 'ดูรูปอาหาร'}: ${matching[index].name}`);
      function openViewer() {
        if (!matching[index].hasImage) return;
        viewerItems = matching.filter(item => item.hasImage);
        viewerIndex = viewerItems.indexOf(matching[index]);
        showViewerImage();
        viewer.showModal();
      }
      image.addEventListener('click', openViewer);
      image.addEventListener('keydown', event => {
        if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); openViewer(); }
      });
      button.addEventListener('click', () => input.click());
      removeButton.addEventListener('click', async () => {
        const item = matching[index];
        const english = document.documentElement.lang === 'en';
        const confirmation = await Swal.fire({
          title: english ? 'Remove this photo?' : 'ลบรูปอาหาร?',
          text: item.name,
          icon: 'question',
          showCancelButton: true,
          confirmButtonText: english ? 'Remove photo' : 'ลบรูป',
          cancelButtonText: english ? 'Cancel' : 'ยกเลิก',
          confirmButtonColor: '#ef7147',
          cancelButtonColor: '#8c938f',
          reverseButtons: true,
        });
        if (!confirmation.isConfirmed) return;
        const data = new FormData(); data.set('action', 'delete'); data.set('id', item.id); data.set('branch', branch); data.set('expected_image', item.image);
        button.disabled = true; removeButton.disabled = true;
        try {
          const response = await fetch('api/food-image.php', { method: 'POST', headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }, body: data });
          const result = await response.json();
          if (!response.ok || !result.success) throw new Error(t(result.error?.message || 'ลบรูปไม่สำเร็จ'));
          item.image = result.image; item.hasImage = false; cardImage.src = result.image; cardImage.hidden = true; placeholder.hidden = false; image.tabIndex = -1;
          button.querySelector('span').textContent = t('เพิ่มรูป'); removeButton.hidden = true;
          Swal.fire({
            icon: result.shared ? 'info' : 'success',
            title: english ? 'Photo removed' : 'ลบรูปเรียบร้อยแล้ว',
            text: result.shared ? (english ? 'The file is still used by another menu item.' : 'ไฟล์ยังถูกใช้งานโดยเมนูอื่น') : item.name,
            showConfirmButton: !!result.shared,
            ...(result.shared ? { confirmButtonText: english ? 'OK' : 'ตกลง', confirmButtonColor: '#ef7147' } : { timer: 1600 }),
          });
        } catch (error) { alert(error.message || t('ลบรูปไม่สำเร็จ')); }
        finally { button.disabled = false; removeButton.disabled = false; }
      });
      input.addEventListener('change', async () => {
        const file = input.files?.[0]; if (!file) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) { alert(t('กรุณาเลือกรูป JPG, PNG หรือ WebP ขนาดไม่เกิน 5 MB')); input.value = ''; return; }
        const english = document.documentElement.lang === 'en';
        const confirmation = await Swal.fire({
          title: english ? 'Confirm photo?' : 'ยืนยันรูปอาหาร?',
          text: matching[index].name,
          icon: 'question',
          showCancelButton: true,
          confirmButtonText: english ? 'Confirm' : 'ยืนยัน',
          cancelButtonText: english ? 'Cancel' : 'ยกเลิก',
          confirmButtonColor: '#ef7147',
          cancelButtonColor: '#8c938f',
          reverseButtons: true,
        });
        if (!confirmation.isConfirmed) { input.value = ''; return; }
        const objectUrl = URL.createObjectURL(file), img = card.querySelector('img'), previous = img.src;
        img.src = objectUrl; img.hidden = false; placeholder.hidden = true;
        const data = new FormData(); data.set('id', matching[index].id); data.set('branch', branch); data.set('image', file);
        button.disabled = true; removeButton.disabled = true; button.querySelector('span').textContent = t('กำลังบันทึก...');
        try {
          const response = await fetch('api/food-image.php', { method:'POST', headers:{ 'X-CSRF-Token':csrf, 'Accept':'application/json' }, body:data });
          const result = await response.json();
          if (!response.ok || !result.success) throw new Error(t(result.error?.message || 'บันทึกรูปไม่สำเร็จ'));
          const wasChanged = matching[index].hasImage;
          matching[index].image = result.image; matching[index].hasImage = true; img.src = result.image; image.tabIndex = 0; button.querySelector('span').textContent = t('เปลี่ยนรูป'); removeButton.hidden = false;
          const english = document.documentElement.lang === 'en';
          Swal.fire({
            icon: 'success',
            title: english ? (wasChanged ? 'Photo updated' : 'Photo added') : (wasChanged ? 'เปลี่ยนรูปสำเร็จ' : 'เพิ่มรูปสำเร็จ'),
            text: matching[index].name,
            showConfirmButton: false,
            timer: 1600,
          });
        } catch (error) { img.src = previous; img.hidden = !matching[index].hasImage; placeholder.hidden = matching[index].hasImage; alert(error.message || t('บันทึกรูปไม่สำเร็จ')); button.querySelector('span').textContent = t(matching[index].hasImage ? 'เปลี่ยนรูป' : 'เพิ่มรูป'); }
        finally { URL.revokeObjectURL(objectUrl); input.value = ''; button.disabled = false; removeButton.disabled = false; }
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
    } catch (error) { status.textContent = t(error.message || 'ไม่สามารถโหลดรายการอาหารได้'); }
  }
  search.addEventListener('input', render); category.addEventListener('change', render); init();
  window.addEventListener('staff-language-change', () => { if (products.length) render(); });
})();
