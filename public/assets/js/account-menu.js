(() => {
  function isDesktop() {
    return window.matchMedia('(min-width: 761px) and (orientation: landscape), (min-width: 1101px)').matches;
  }

  function isPinnedDesktop() {
    return isDesktop() && (document.body.classList.contains('has-staff-sidebar') || document.body.classList.contains('staff-home'));
  }

  function initAccountMenu() {
    const menus = document.querySelectorAll('.account-menu');
    menus.forEach(menu => {
      if (menu.dataset.accountMenuInit) return;
      menu.dataset.accountMenuInit = 'true';

      const summary = menu.querySelector('summary');
      const panel = menu.querySelector('.account-menu-panel');
      const backdrop = menu.querySelector('.account-menu-backdrop');
      const nameBox = menu.querySelector('.account-menu-name');
      const nameText = menu.querySelector('.account-menu-name-text');
      let nameCopy;
      function updateNameScroll() {
        if (!nameBox || !nameText || !nameBox.clientWidth) return;
        if (nameCopy) nameCopy.textContent = nameText.textContent;
        const overflow = Math.max(0, nameText.scrollWidth - nameBox.clientWidth);
        nameBox.classList.toggle('is-scrolling', overflow > 1);
        nameBox.style.setProperty('--name-scroll-duration', `${(nameText.scrollWidth + 48) / 22}s`);
      }
      if (nameBox && nameText) {
        nameCopy = document.createElement('span');
        nameCopy.className = 'account-menu-name-text account-menu-name-copy';
        nameCopy.setAttribute('aria-hidden', 'true');
        nameBox.appendChild(nameCopy);
        if (window.ResizeObserver) new ResizeObserver(updateNameScroll).observe(nameBox);
        new MutationObserver(updateNameScroll).observe(nameText, { childList: true, characterData: true, subtree: true });
        if (document.fonts?.ready) document.fonts.ready.then(updateNameScroll);
        window.addEventListener('resize', updateNameScroll);
        requestAnimationFrame(updateNameScroll);
      }
      let timer;
      let closing = false;
      let onAnimationEnd;

      if (isPinnedDesktop()) {
        menu.open = true;
      }

      function closeMenu(returnFocus = false) {
        if (isPinnedDesktop()) return;
        if (!menu.open || closing) return;
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
          menu.open = false;
          menu.classList.remove('is-closing');
          document.body.classList.remove('account-drawer-open');
          document.body.classList.remove('account-drawer-closing');
          if (returnFocus && summary) summary.focus();
          return;
        }
        closing = true;
        menu.classList.add('is-closing');
        document.body.classList.add('account-drawer-closing');
        const finish = () => {
          clearTimeout(timer);
          if (panel) {
            panel.removeEventListener('animationend', onAnimationEnd);
          }
          menu.open = false;
          menu.classList.remove('is-closing');
          closing = false;
          document.body.classList.remove('account-drawer-open');
          document.body.classList.remove('account-drawer-closing');
          if (returnFocus && summary) summary.focus();
        };
        onAnimationEnd = event => {
          if (event.target === panel && event.animationName === 'account-drawer-slide-out') {
            finish();
          }
        };
        if (panel) {
          panel.addEventListener('animationend', onAnimationEnd);
        }
        timer = setTimeout(finish, 320);
      }

      if (summary) {
        summary.addEventListener('click', event => {
          if (isPinnedDesktop()) {
            event.preventDefault();
            return;
          }
          if (menu.open) {
            event.preventDefault();
            closeMenu();
          }
        });
      }

      menu.addEventListener('toggle', () => {
        if (isPinnedDesktop()) return;
        if (menu.open) {
          requestAnimationFrame(updateNameScroll);
          document.body.classList.add('account-drawer-open');
          document.body.classList.remove('account-drawer-closing');
        } else {
          document.body.classList.remove('account-drawer-open');
          document.body.classList.remove('account-drawer-closing');
        }
      });

      if (backdrop) {
        backdrop.addEventListener('click', event => {
          if (isPinnedDesktop()) return;
          event.preventDefault();
          event.stopPropagation();
          closeMenu();
        });
      }

      if (panel) {
        // Start closing the mobile drawer before the logout confirmation opens.
        panel.addEventListener('click', event => {
          if (event.target.closest('.account-menu-logout')) closeMenu();
        }, true);

        panel.addEventListener('click', event => {
          if (isPinnedDesktop()) return;
          if (event.target.closest('.account-menu-drawer-close')) {
            event.preventDefault();
            event.stopPropagation();
            closeMenu(true);
            return;
          }
          if (event.target.closest('a')) {
            closeMenu();
          }
        });
      }

      document.addEventListener('click', event => {
        if (isPinnedDesktop()) return;
        if (menu.open && !menu.contains(event.target)) {
          closeMenu();
        }
      });

      document.addEventListener('keydown', event => {
        if (isPinnedDesktop()) return;
        if (event.key === 'Escape' && menu.open) {
          event.preventDefault();
          event.stopPropagation();
          closeMenu(true);
        } else if (event.key === 'Tab' && menu.open && !closing && panel) {
          const controls = [summary, ...panel.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled])')].filter(element => element && element.getClientRects().length);
          if (!controls.length) return;
          const first = controls[0], last = controls[controls.length - 1];
          if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
          else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
      });

      window.addEventListener('resize', () => {
        if (isPinnedDesktop()) {
          clearTimeout(timer);
          if (panel && onAnimationEnd) panel.removeEventListener('animationend', onAnimationEnd);
          closing = false;
          menu.open = true;
          document.body.classList.remove('account-drawer-open');
          document.body.classList.remove('account-drawer-closing');
          menu.classList.remove('is-closing');
        } else if (!isDesktop() && !document.body.classList.contains('account-drawer-open')) {
          menu.open = false;
        }
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAccountMenu);
  } else {
    initAccountMenu();
  }
})();
