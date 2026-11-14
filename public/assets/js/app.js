/* QR Self-Ordering with SQL Server POS Integration */
const isMobileLayout = () => window.matchMedia('(max-width: 760px), (min-width: 761px) and (max-width: 1100px) and (orientation: portrait)').matches;
if (/^[a-p]{64}$/.test(window.appCanonicalHash || '') && /\/staff\/tables\/[0-9]{6,10}\/?$/.test(window.location.pathname)) {
  const path = window.location.pathname.replace(/\/[0-9]{6,10}\/?$/, '/' + window.appCanonicalHash);
  history.replaceState(history.state, '', path + window.location.search + window.location.hash);
}
const urlParams = new URLSearchParams(window.location.search);
const pathDocMatch = window.location.pathname.match(/\/([0-9]{6,10})\/?$/);
const pathDoc = pathDocMatch ? pathDocMatch[1] : '';
const pathHash = /\/[a-p]{64}\/?$/.test(window.location.pathname);
const shortDoc = window.location.search.slice(1).split('&', 1)[0];
const defaultDoc = window.appResolvedDoc || urlParams.get('bill') || urlParams.get('Fn_Doc_No') || urlParams.get('doc') || urlParams.get('doc_no') || pathDoc || (/^\d+$/.test(shortDoc) ? shortDoc : '');
const billHashMap = new Map();
if (/^[a-p]{64}$/.test(window.appBillHash || '') && defaultDoc) billHashMap.set(window.appBillHash, defaultDoc);
function rememberBillHash(hash, doc) {
  if (/^[a-p]{64}$/.test(hash || '') && doc) billHashMap.set(hash, String(doc));
}
function preferredBillIdentifier(hash, doc) {
  const normalized = String(hash || '').trim();
  return /^[a-p]{64}$/.test(normalized) ? normalized : String(doc || '');
}
function routeBillIdentifier(path) {
  const match = path.match(/\/([0-9]{6,10}|[a-p]{64})\/?$/);
  return match ? match[1] : '';
}
function isVerifiedCustomerBill(path, hash, doc) {
  return Boolean(doc && /^[a-p]{64}$/.test(hash || '') && routeBillIdentifier(path) === hash && !/\/staff\/tables\//.test(path));
}
function customerBillPage(page, closed) {
  return page === 'login' ? (closed ? 'closed' : 'menu') : page;
}
function redirectClosedStaffBill(staff, isClosed) {
  if (!staff || !isClosed || !/\/staff\/tables\/(?:[0-9]{6,10}|[a-p]{64})\/?$/.test(location.pathname)) return false;
  window.location.replace(new URL('staff/tables/', document.baseURI).href);
  return true;
}
const defaultTable = urlParams.get('table') || '';
const defaultBranch = window.appBranch;
if (window.location.hash === '#login' && !defaultDoc && !defaultTable) {
  history.replaceState(history.state, '', window.location.pathname + window.location.search);
}
if (defaultDoc && /^\d+$/.test(defaultDoc) && shortDoc !== defaultDoc && !pathDoc && !pathHash && !urlParams.has('bill') && !urlParams.has('table')) {
  const remainingParams = new URLSearchParams(window.location.search);
  ['Fn_Doc_No', 'doc', 'doc_no', 'bill'].forEach(key => remainingParams.delete(key));
  const suffix = remainingParams.toString();
  history.replaceState(history.state, '', `${window.location.pathname}?${defaultDoc}${suffix ? '&' + suffix : ''}${window.location.hash}`);
}
function getDeviceSessionToken() {
  try {
    let token = localStorage.getItem('imdee:session_token');
    if (!token) {
      token = 'st_' + Date.now().toString(36) + '_' + Math.random().toString(36).substring(2, 10);
      localStorage.setItem('imdee:session_token', token);
    }
    return token;
  } catch {
    return 'st_' + Date.now().toString(36);
  }
}

const LEGACY_SESSION_TOKEN = getDeviceSessionToken();
function getCartSessionToken() {
  try {
    const existing = localStorage.getItem('imdee:cart_session_v2');
    if (existing && /^st2_[a-f0-9]{64}$/.test(existing)) return existing;
    if (typeof globalThis.crypto?.getRandomValues !== 'function') return '';
    const bytes = globalThis.crypto.getRandomValues(new Uint8Array(32));
    const token = 'st2_' + Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('');
    localStorage.setItem('imdee:cart_session_v2', token);
    return localStorage.getItem('imdee:cart_session_v2') === token ? token : '';
  } catch { return ''; }
}
const SESSION_TOKEN = getCartSessionToken();
// Each device has its own cart; bill and branch are part of the local cache key.
const LEGACY_CART_KEY = `imdee:cart:${LEGACY_SESSION_TOKEN}:v1`;
const CART_KEY = `imdee:session-cart:v3:${defaultBranch}:${defaultDoc || defaultTable}`;
const CATEGORY_KEY = `imdee:category:${defaultBranch}:v1`;
const DETAIL_KEY = `imdee:detail:${defaultBranch}:${defaultDoc || defaultTable}:v1`;
let cartLineSequence = 0;

function createCartLineKey() {
  const cryptoApi = globalThis.crypto;
  if (typeof cryptoApi?.randomUUID === 'function') return cryptoApi.randomUUID();
  if (typeof cryptoApi?.getRandomValues === 'function') {
    const bytes = cryptoApi.getRandomValues(new Uint8Array(16));
    return Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('');
  }
  cartLineSequence += 1;
  return `line_${Date.now().toString(36)}_${cartLineSequence.toString(36)}_${Math.random().toString(36).slice(2, 12)}`;
}

function readSelectedCategory() {
  try { return sessionStorage.getItem(CATEGORY_KEY) || 'recommended'; }
  catch { return 'recommended'; }
}
function readLanguage() {
  try { return localStorage.getItem('imdee:language') === 'en' ? 'en' : 'th'; }
  catch { return 'th'; }
}

function readStored(key, fallback) {
  try { return JSON.parse(localStorage.getItem(key)) ?? fallback; }
  catch { return fallback; }
}

function getCartStorageKey(branch, table, doc) {
  return `imdee:session-cart:v3:${branch || defaultBranch}:${doc || table || defaultTable}`;
}

function getStoredCart(tbl, doc, branch = defaultBranch) {
  try { return JSON.parse(sessionStorage.getItem(getCartStorageKey(branch, tbl, doc))) ?? []; }
  catch { return []; }
}

function apiFetch(url, options = {}) {
  const method = (options.method || 'GET').toUpperCase();
  if (method !== 'GET' && method !== 'HEAD') {
    options.headers = { ...options.headers, 'X-CSRF-Token': window.csrfToken };
  }
  return fetch(url, options);
}

const PosStore = {
  async cart(action, doc, branch, line = null, operationId = '') {
    const response = await apiFetch('api/cart.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ action, doc, branch, session_token: SESSION_TOKEN, key: line?.key, item: line, operation_id: operationId }),
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || !data.success || !Array.isArray(data.items)) {
      const error = new Error(data?.error?.message || 'ไม่สามารถเชื่อมต่อตะกร้าได้ กรุณาลองอีกครั้ง');
      error.code = data?.error?.code || 'CART_UNAVAILABLE';
      error.status = response.status;
      throw error;
    }
    return data.items;
  },
  async menu(branch = defaultBranch) {
    const response = await fetch('api/menu.php?' + new URLSearchParams({ branch }), { headers: { Accept: 'application/json' } });
    if (!response.ok) throw new Error('MENU_UNAVAILABLE');
    return response.json();
  },
  async readBill(table = '', doc = '', branch = defaultBranch) {
    try {
      const params = new URLSearchParams();
      if (doc) params.set('Fn_Doc_No', doc);
      if (table) params.set('table', table);
      if (branch) params.set('Branch_ID', branch);

      const response = await fetch(`api/bill.php?${params.toString()}`, { headers: { Accept: 'application/json' } });
      const data = await response.json().catch(() => ({}));
      if (!response.ok && data?.error) {
        return { error: data.error, bill: null };
      }
      return data;
    } catch {
      throw new Error('BILL_UNAVAILABLE');
    }
  },
  async orderHistory(table = '', doc = '', branch = defaultBranch) {
    try {
      const params = new URLSearchParams();
      if (doc) params.set('Fn_Doc_No', doc);
      if (table) params.set('table', table);
      if (branch) params.set('Branch_ID', branch);

      const response = await fetch(`api/order-history.php?${params.toString()}`, { headers: { Accept: 'application/json' } });
      if (!response.ok) throw new Error('HISTORY_UNAVAILABLE');
      return response.json();
    } catch {
      throw new Error('HISTORY_UNAVAILABLE');
    }
  },
  async placeOrder(items, table = '', doc = '', branch = defaultBranch, idempotencyKey = '', fromCart = false, staffId = '') {
    const idemKey = idempotencyKey || ('idem_' + SESSION_TOKEN + '_' + Date.now() + '_' + Math.random().toString(36).substring(2, 8));
    const payload = {
      table: table,
      doc: doc,
      branch: branch,
      staff_id: staffId,
      session_token: SESSION_TOKEN,
      idempotency_key: idemKey,
      from_cart: fromCart,
      items: items.map(item => ({
        key: item.key,
        revision: item.revision,
        id: item.id,
        name: item.name,
        price: item.price,
        qty: item.qty,
        note: item.note || '',
      })),
    };
    const response = await apiFetch('api/order.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Idempotency-Key': idemKey,
      },
      body: JSON.stringify(payload),
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok || !result.success) {
      const error = new Error(result?.error?.message || result?.message || 'ORDER_FAILED');
      error.code = result?.error?.code || 'ORDER_FAILED';
      error.status = response.status;
      throw error;
    }
    return result;
  },
  async confirmOrder(doc = '', tradeId = '', branch = defaultBranch) {
    const payload = {
      docNo: doc,
      tradeId: tradeId || '',
      branch: branch,
    };
    const response = await apiFetch('api/confirm-order.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify(payload),
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok || !result.success) {
      throw new Error(result?.error?.message || result?.message || 'CONFIRM_FAILED');
    }
    return result;
  },
  async openTable(table = '', guests = '', branch = defaultBranch, staffId = '') {
    const payload = {
      TableID: table,
      Table_Person: guests,
      Branch_ID: branch,
      Pb_User: staffId,
    };
    const response = await apiFetch('api/open-table.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify(payload),
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok || (!result.success && result.result !== 'OK')) {
      throw new Error(result?.error?.message || 'OPEN_TABLE_FAILED');
    }
    return result;
  },
  async login(username, password, remember) {
    const response = await apiFetch('api/auth.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ action: 'login', username, password, remember }),
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok || !result.success) {
      throw new Error(result?.error?.message || 'เข้าสู่ระบบไม่สำเร็จ');
    }
    return result;
  },
  async logout() {
    const response = await apiFetch('api/auth.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ action: 'logout' }),
    });
    return response.json().catch(() => ({}));
  },
  async currentStaff() {
    const response = await fetch('api/auth.php?action=me', { headers: { Accept: 'application/json' } });
    return response.json().catch(() => ({ authenticated: false, staff: null }));
  },
  async listTables(branch = defaultBranch) {
    const params = new URLSearchParams();
    if (branch) params.set('Branch_ID', branch);
    const response = await fetch(`api/tables.php?${params.toString()}`, { headers: { Accept: 'application/json' } });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || !data.success) {
      throw new Error(data?.error?.message || 'ไม่สามารถโหลดรายการโต๊ะได้');
    }
    return data;
  },
};

document.addEventListener('alpine:init', () => {
  Alpine.data('orderingApp', (initialPage = 'menu', initialClosed = false, initialTable = '', initialDoc = '', initialCancelled = false) => {
    const customerBill = isVerifiedCustomerBill(location.pathname, window.appBillHash, initialDoc || window.appResolvedDoc);
    let hashRoute = location.hash.slice(1);
    if (hashRoute === 'card') hashRoute = 'cart';
    const isClosed = Boolean(initialClosed || initialPage === 'closed');
    const initialStaff = window.__IMDEE_STAFF__ || null;
    let startPage = document.documentElement.getAttribute('data-initial-route') || initialPage;
    if (customerBill) startPage = customerBillPage(startPage, isClosed || initialCancelled);
    if (startPage === 'tables' && !initialStaff) {
      startPage = 'login';
    }
    if (initialCancelled || (isClosed && startPage !== 'history')) {
      startPage = 'closed';
    }

    document.documentElement.setAttribute('data-page', startPage);

    const activeTable = initialTable || defaultTable;
    const activeDoc = initialDoc || defaultDoc;
    const initialCart = getStoredCart(activeTable, activeDoc);

    return {
      page: startPage, categories: [], items: [], recommendationPairs: [], categoryId: readSelectedCategory(), search: '', searchOpen: false, menuSearchFocused: false, language: readLanguage(),
      loading: true, loadError: false, cart: Array.isArray(initialCart) ? initialCart : [], orders: [], closed: isClosed,
      billLoaded: false,
      staff: initialStaff,
      loginUsername: '', loginPassword: '', loginRemember: false, loginLoading: false,
      orderSlidePosition: 0, orderSlidePointer: null, orderSlideStartX: 0, orderSlideMax: 0,
      tablesList: [], tablesLoading: false, tablesRefreshing: false, tableSearch: '', tableFilter: 'all',
      pendingItems: [], pendingTotal: 0, confirmedTotal: 0, confirming: false,
      selected: { id: '', name: '', english: '', price: 0, originalPrice: 0, unit: '', image: '', description: '', allergens: '', recommended: false, available: true },
      detailOpen: false, detailQty: 1, detailNote: '', noteFocused: false, noteFocusTimer: null, editingKey: null,
      sending: false, orderAwaitingConfirm: false, pendingRequest: null, returnFocus: null, storageWarningShown: false,
      cartReady: false, cartError: false, cartRevision: 0, legacyCheckDone: false,
      cartBump: false,
      categoryDrag: null, categorySuppressClick: false, categoryHasScrolled: false, menuCategoryStuck: false, tableCategoryStuck: false,
      restoreMenuCategory: performance.getEntriesByType('navigation')[0]?.type === 'reload',
      menuCategoryScrolling: false, menuCategoryScrollTimer: null,
      navHidden: false,
      navTouchStart: null, navSuppressClick: false, navDragging: false, navDragPosition: 0, navDragScale: 1, navPressed: false, navPressPosition: 0,
      table: activeTable,
      docNo: activeDoc,
      branch: defaultBranch,
      tableName: 'โต๊ะ ' + activeTable,
      guests: null,
      docSts: 0,
      docStsDesc: 'เปิดบิล',
      isCancelled: Boolean(initialCancelled),
      billError: null, waitingForTable: false,
      canOrder: true,
      mode: 'live',

      cartStorageKey() {
        return getCartStorageKey(this.branch, this.table, this.docNo);
      },
      get filteredTables() {
        const normalize = value => String(value || '').normalize('NFC').toLocaleLowerCase().replace(/[๐-๙]/g, digit => String('๐๑๒๓๔๕๖๗๘๙'.indexOf(digit))).replace(/\s+/g, ' ').trim();
        const q = normalize(this.tableSearch);
        const matching = this.tablesList.filter(tbl => {
          if (this.tableFilter === 'open' && !tbl.isOpen) return false;
          if (this.tableFilter === 'available' && tbl.isOpen) return false;
          if (!q) return true;
          return normalize(tbl.name || tbl.number).includes(q);
        });
        if (!q) return matching;
        const relevance = tbl => {
          const name = normalize(tbl.name || tbl.number);
          if (name === q) return 0;
          const numbers = name.match(/\d+/g) || [];
          if (/^\d+$/.test(q) && numbers.some(number => Number(number) === Number(q))) return 1;
          return name.startsWith(q) ? 2 : 3;
        };
        return matching.sort((a, b) => relevance(a) - relevance(b));
      },
      async submitLogin() {
        if (this.loginLoading) return;
        if (!this.loginUsername || !this.loginPassword) {
          await this.alert({ icon: 'warning', title: 'กรุณากรอกข้อมูล', text: 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน' });
          return;
        }
        this.loginLoading = true;
        try {
          const res = await PosStore.login(this.loginUsername, this.loginPassword, this.loginRemember);
          this.staff = res.staff;
          try { localStorage.setItem('imdee:staff', JSON.stringify(res.staff)); } catch {}
          this.loginPassword = '';
          await this.alert({ icon: 'success', title: 'เข้าสู่ระบบสำเร็จ', customClass: { popup: 'glass-success' }, timer: 1500, showConfirmButton: false });
          window.location.href = new URL('staff/', document.baseURI || window.location.href).href;
        } catch (err) {
          await this.alert({ icon: 'error', title: 'เข้าสู่ระบบไม่สำเร็จ', text: err.message || 'ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง' });
        } finally {
          this.loginLoading = false;
        }
      },
      async logoutStaff() {
        const confirm = await this.alert({
          title: 'ออกจากระบบพนักงาน?',
          text: this.staff ? `${this.language === 'en' ? 'Current user' : 'ผู้ใช้งานปัจจุบัน'}: ${this.staff.name}` : '',
          icon: 'question',
          showCancelButton: true,
          confirmButtonText: 'ออกจากระบบ',
          cancelButtonText: 'ยกเลิก',
        });
        if (!confirm.isConfirmed) return;
        await PosStore.logout();
        this.staff = null;
        try { localStorage.removeItem('imdee:staff'); } catch {}
        this.docNo = '';
        this.cart = [];
        this.orders = [];
        if (customerBill) { window.location.href = new URL('.', document.baseURI).href; return; }
        this.navigate('login');
      },
      async refreshTables() {
        if (this.tablesLoading || this.tablesRefreshing) return;
        this.tablesRefreshing = true;
        try {
          await Promise.all([this.loadTables(), new Promise(resolve => setTimeout(resolve, 800))]);
        } finally {
          this.tablesRefreshing = false;
        }
      },
      async loadTables() {
        if (!this.staff) {
          this.navigate('login');
          return;
        }
        this.tablesLoading = true;
        try {
          const data = await PosStore.listTables(this.branch);
          this.tablesList = Array.isArray(data.tables) ? data.tables : [];
        } catch (err) {
          if (err.message && err.message.includes('เข้าสู่ระบบ')) {
            this.staff = null;
            this.navigate('login');
          }
          console.error('loadTables error:', err);
        } finally {
          this.tablesLoading = false;
        }
      },
      async openTableSelector() {
        if (!this.staff) {
          this.navigate('login');
          return;
        }
        const staffTables = location.pathname.includes('/staff/tables')
          ? location.pathname.replace(/\/staff\/tables(?:\/.*)?$/, '/staff/tables/')
          : new URL('staff/tables/', document.baseURI).pathname;
        history.replaceState(history.state, '', staffTables);
        await this.loadTables();
        this.navigate('tables');
      },
      async selectTable(tbl) {
        if (!tbl) return;
        if (!this.staff) {
          await this.alert({
            icon: 'warning',
            title: 'กรุณาเข้าสู่ระบบก่อน',
            text: 'ต้องเข้าสู่ระบบพนักงานก่อนจึงจะสามารถจองหรือเปิดโต๊ะได้',
          });
          this.navigate('login');
          return;
        }
        if (tbl.isOpen) {
          // Table already has an open bill (or Table_STS == 'O') -> enter bill directly
          try {
            const res = await PosStore.openTable(tbl.id, tbl.guests ?? '', this.branch, this.staff?.emId || '');
            this.table = res.Table_ID || tbl.id || res.table || tbl.number;
            this.tableName = res.tableName || tbl.name;
            this.docNo = res.docNo || tbl.docNo || '';
            const billIdentifier = preferredBillIdentifier(res.Fn_Qrcode_Order, this.docNo);
            rememberBillHash(billIdentifier, this.docNo);
            this.closed = false;
            this.canOrder = true;
            this.cart = getStoredCart(this.table, this.docNo, this.branch);
            this.cartReady = false;
            await this.syncBill();
            if (!this.closed && this.docNo) {
              await this.syncRemoteCart();
            } else {
              this.cartReady = true;
            }
            const staffBase = location.pathname.includes('/staff/tables')
              ? location.pathname.replace(/\/staff\/tables(?:\/.*)?$/, '/staff/tables/')
              : new URL('staff/tables/', document.baseURI).pathname;
            const nextUrl = this.staff
              ? `${staffBase}${encodeURIComponent(billIdentifier)}#menu`
              : `${new URL(document.baseURI).pathname.replace(/\/?$/, '/')}${encodeURIComponent(billIdentifier)}#menu`;
            if (this.page === 'tables' && (location.hash !== '#tables' || location.search)) history.replaceState(history.state, '', `${staffBase}#tables`);
            history.pushState(history.state, '', nextUrl);
            this.navigate('menu');
          } catch (err) {
            await this.alert({ icon: 'error', title: 'ไม่สามารถเข้าบิลของโต๊ะได้', text: err.message || 'กรุณาลองอีกครั้ง' });
          }
        } else {
          // Table is available -> ask staff to confirm opening new bill (no guest count input)
          const promptRes = await this.alert({
            title: `${this.t('เปิดโต๊ะใหม่')}: ${tbl.name}`,
            icon: 'question',
            html: `<p class="table-open-prompt"></p><div class="staff-login-slide table-open-slide"><span class="staff-slide-label">${this.language === 'en' ? 'Swipe to open table' : 'เลื่อนเพื่อเปิดโต๊ะ'}</span><button type="button" class="staff-slide-handle" aria-label="${this.language === 'en' ? 'Open table' : 'เปิดโต๊ะ'}"><svg class="icon" aria-hidden="true"><use href="#i-arrow"></use></svg></button></div>`,
            showConfirmButton: false,
            showCancelButton: true,
            cancelButtonText: this.t('ยกเลิก'),
            customClass: { popup: 'table-open-confirm' },
            didOpen: popup => {
              popup.querySelector('.table-open-prompt').textContent = this.t('ต้องการเปิดบิลใหม่และเริ่มสั่งอาหารสำหรับโต๊ะนี้ใช่หรือไม่?');
              const track = popup.querySelector('.table-open-slide');
              const handle = track.querySelector('.staff-slide-handle');
              let pointer = null, start = 0, max = 0, position = 0;
              handle.addEventListener('pointerdown', event => {
                if (event.pointerType === 'mouse' && event.button !== 0) return;
                pointer = event.pointerId;
                start = event.clientX;
                max = Math.max(0, track.clientWidth - handle.offsetWidth - 8);
                position = 0;
                track.classList.add('is-dragging');
                handle.setPointerCapture(pointer);
              });
              handle.addEventListener('pointermove', event => {
                if (event.pointerId !== pointer) return;
                position = Math.min(max, Math.max(0, event.clientX - start));
                handle.style.transform = `translateX(${position}px)`;
              });
              const finish = (event, cancelled) => {
                if (event.pointerId !== pointer) return;
                pointer = null;
                track.classList.remove('is-dragging');
                if (!cancelled && max > 0 && position >= max * .85) {
                  handle.style.transform = `translateX(${max}px)`;
                  Swal.clickConfirm();
                } else {
                  handle.style.transform = '';
                }
              };
              handle.addEventListener('pointerup', event => finish(event, false));
              handle.addEventListener('pointercancel', event => finish(event, true));
              handle.addEventListener('click', event => { if (event.detail === 0) Swal.clickConfirm(); });
            },
          });
          if (!promptRes.isConfirmed) return;
          try {
            const res = await PosStore.openTable(tbl.id, '', this.branch, this.staff?.emId || '');
            this.table = res.Table_ID || tbl.id || res.table || tbl.number;
            this.tableName = res.tableName || tbl.name;
            this.docNo = res.docNo || '';
            const billIdentifier = preferredBillIdentifier(res.Fn_Qrcode_Order, this.docNo);
            rememberBillHash(billIdentifier, this.docNo);
            this.closed = false;
            this.canOrder = true;
            this.cart = [];
            this.persistCart();
            this.cartReady = true;
            await this.syncBill();
            await this.loadTables();
            const staffBase = location.pathname.includes('/staff/tables')
              ? location.pathname.replace(/\/staff\/tables(?:\/.*)?$/, '/staff/tables/')
              : new URL('staff/tables/', document.baseURI).pathname;
            const nextUrl = this.staff
              ? `${staffBase}${encodeURIComponent(billIdentifier)}#menu`
              : `${new URL(document.baseURI).pathname.replace(/\/?$/, '/')}${encodeURIComponent(billIdentifier)}#menu`;
            if (this.page === 'tables' && (location.hash !== '#tables' || location.search)) history.replaceState(history.state, '', `${staffBase}#tables`);
            history.pushState(history.state, '', nextUrl);
            this.navigate('menu');
            await this.alert({
              icon: 'success',
              title: `${this.t('เปิดบิลสำเร็จ')} #${this.docNo}`,
              text: this.tableName,
              customClass: { popup: 'glass-success' },
              timer: 1800,
              showConfirmButton: false,
            });
          } catch (err) {
            await this.alert({ icon: 'error', title: 'เปิดโต๊ะไม่สำเร็จ', text: err.message || 'กรุณาตรวจสอบการเชื่อมต่อฐานข้อมูล POS' });
          }
        }
      },

      triggerCartBump() {
        this.cartBump = true;
        clearTimeout(this._bumpTimer);
        this._bumpTimer = setTimeout(() => { this.cartBump = false; }, 360);
      },
      t(text) { return uiText(this.language, text); },
      setLanguage(language) {
        this.language = language === 'en' ? 'en' : 'th';
        document.documentElement.lang = this.language;
        try { localStorage.setItem('imdee:language', this.language); } catch { /* Keep the selection for this page. */ }
      },

      async init() {
        if (redirectClosedStaffBill(this.staff, isClosed && !initialCancelled)) return;
        document.documentElement.lang = this.language;
        document.addEventListener('pointerdown', event => {
          if (isMobileLayout() && this.menuSearchFocused && !this.$refs.menuSearchBar?.contains(event.target)) {
            this.$refs.menuSearch?.blur();
          }
        });
        let categoryFrame = 0;
        window.addEventListener('scroll', () => {
          const stickyBar = document.querySelector('.menu-category-sticky');
          const stickyTop = stickyBar ? parseFloat(getComputedStyle(stickyBar).top) || 0 : 0;
          this.menuCategoryStuck = this.page === 'menu' && !!stickyBar && stickyBar.getBoundingClientRect().top <= stickyTop + 1;
          const tableBar = document.querySelector('.table-category-sticky');
          const tableStickyTop = tableBar ? parseFloat(getComputedStyle(tableBar).top) || 0 : 0;
          this.tableCategoryStuck = this.page === 'tables' && !!tableBar && tableBar.getBoundingClientRect().top <= tableStickyTop + 1;
          if (this.menuCategoryScrolling) {
            this.finishMenuCategoryScroll();
            return;
          }
          if (categoryFrame) return;
          categoryFrame = requestAnimationFrame(() => {
            categoryFrame = 0;
            this.updateVisibleMenuCategory();
          });
        }, { passive: true });
        window.addEventListener('popstate', event => {
          if (this.detailOpen && !event.state?.imdeeDetail) this.closeDetail(true);
        });
        this.$watch('categoryId', value => {
          try { sessionStorage.setItem(CATEGORY_KEY, value); } catch { /* Storage may be unavailable. */ }
        });
        ['detailOpen', 'detailQty', 'detailNote', 'editingKey'].forEach(field => {
          this.$watch(field, () => this.persistOpenDetail());
        });
        document.documentElement.setAttribute('data-page', this.page);
        document.documentElement.removeAttribute('data-initial-route');

        const url = new URLSearchParams(window.location.search);
        const qFirst = (window.location.search || '').replace(/^\?/, '').split('&')[0];
        const shortDoc = /^\d+$/.test(qFirst) ? qFirst : '';
        const pathIdentifier = routeBillIdentifier(window.location.pathname);
        const pathDoc = billHashMap.get(pathIdentifier) || (customerBill ? (initialDoc || window.appResolvedDoc) : '') || (/^\d+$/.test(pathIdentifier) ? pathIdentifier : '');
        const hasScannedBill = !!(url.get('Fn_Doc_No') || url.get('doc') || url.get('doc_no') || url.get('bill') || url.get('table') || shortDoc || pathDoc);
        if (customerBill && location.hash === '#login') history.replaceState(history.state, '', `${location.pathname}${location.search}#${this.closed ? 'closed' : 'menu'}`);
        if (url.get('Fn_Doc_No') || url.get('doc') || url.get('doc_no') || url.get('bill') || shortDoc || pathDoc) {
          this.docNo = (/^[a-p]{64}$/.test(pathIdentifier) ? pathDoc : '') || url.get('Fn_Doc_No') || url.get('doc') || url.get('doc_no') || url.get('bill') || pathDoc || shortDoc;
        }
        if (url.get('table')) {
          this.table = url.get('table');
        }
        if (url.get('Branch_ID') || url.get('branch')) {
          this.branch = url.get('Branch_ID') || url.get('branch');
        }

        let preSyncRoute = location.hash.slice(1);
        if (preSyncRoute === 'card') preSyncRoute = 'cart';
        if (!this.staff && !hasScannedBill) {
          this.page = 'login';
          document.documentElement.setAttribute('data-page', this.page);
        } else if (this.staff && location.hash === '#tables') {
          if (location.search) {
            history.replaceState(history.state, '', `${location.pathname}#tables`);
          }
          this.page = 'tables';
          document.documentElement.setAttribute('data-page', this.page);
        } else if (hasScannedBill && ((!this.staff && preSyncRoute === 'tables') || (customerBill && preSyncRoute === 'login') || (!this.staff && preSyncRoute === 'login'))) {
          this.page = 'menu';
          document.documentElement.setAttribute('data-page', this.page);
        } else if (['login', 'tables', 'menu', 'cart', 'history', 'closed'].includes(preSyncRoute)) {
          if (!this.closed || preSyncRoute === 'history' || preSyncRoute === 'closed' || preSyncRoute === 'login' || preSyncRoute === 'tables') {
            this.page = preSyncRoute;
            document.documentElement.setAttribute('data-page', this.page);
          }
        }

        if (this.page === 'tables' || (this.staff && !hasScannedBill)) {
          this.loadTables();
        }

        if (this.page !== 'login' && (this.page !== 'tables' || this.docNo || this.table)) {
          await this.syncBill();
        }
        this.billLoaded = true;

        let postSyncRoute = location.hash.slice(1);
        if (postSyncRoute === 'card') postSyncRoute = 'cart';
        if (!this.staff && !hasScannedBill) {
          this.page = 'login';
        } else if (this.staff && location.hash === '#tables') {
          this.page = 'tables';
        } else if (hasScannedBill && ((!this.staff && postSyncRoute === 'tables') || (customerBill && postSyncRoute === 'login') || (!this.staff && postSyncRoute === 'login'))) {
          this.page = this.closed ? 'closed' : 'menu';
        } else if (['login', 'tables', 'menu', 'cart', 'history', 'closed'].includes(postSyncRoute)) {
          this.page = (this.closed && !['history', 'login', 'tables'].includes(postSyncRoute)) ? 'closed' : postSyncRoute;
        } else if (this.closed && !['history', 'login', 'tables'].includes(this.page)) {
          this.page = 'closed';
        }
        document.documentElement.setAttribute('data-page', this.page);
        if (hasScannedBill && this.table && this.docNo) {
          const canonicalSearch = `?table=${encodeURIComponent(this.table)}&bill=${encodeURIComponent(this.docNo)}`;
          const currentHash = location.hash && location.hash !== '#tables' ? location.hash : '#menu';
          if (location.search !== canonicalSearch && (url.has('Fn_Doc_No') || url.has('doc') || url.has('doc_no') || shortDoc)) {
            history.replaceState(history.state, '', `${location.pathname}${canonicalSearch}${currentHash}`);
          } else if (!location.hash || (!this.staff && location.hash === '#tables')) {
            const search = /^[a-p]{64}$/.test(pathIdentifier) ? location.search : (location.search || canonicalSearch);
            history.replaceState(history.state, '', `${location.pathname}${search}#${this.page === 'tables' && !this.staff ? 'menu' : this.page}`);
          }
        } else if (!location.hash && this.page === 'menu') {
          history.replaceState(history.state, '', `${location.pathname}${location.search}#menu`);
        }

        const restoreHistoryRoute = () => {
          const next = location.hash.slice(1);
          const currentParams = new URLSearchParams(location.search);
          const identifier = routeBillIdentifier(location.pathname);
          if (/^[a-p]{64}$/.test(identifier) && !billHashMap.has(identifier)) { location.reload(); return; }
          const currentPathDoc = billHashMap.get(identifier) || (/^\d+$/.test(identifier) ? identifier : '');
          const currentDoc = currentPathDoc || currentParams.get('Fn_Doc_No') || currentParams.get('doc') || currentParams.get('doc_no') || currentParams.get('bill') || (/^\d+$/.test(location.search.slice(1).split('&')[0]) ? location.search.slice(1).split('&')[0] : '');
          const currentTable = currentParams.get('table') || '';
          let targetPage = ['login', 'tables', 'menu', 'cart', 'history', 'closed'].includes(next) ? next : (currentDoc || currentTable ? 'menu' : (this.staff ? 'tables' : 'login'));
          if (customerBill) targetPage = customerBillPage(targetPage, this.closed);
          if (targetPage === 'tables' && !this.staff) {
            targetPage = (currentDoc || currentTable) ? 'menu' : 'login';
          }
          const billChanged = targetPage !== 'tables' && !!(currentDoc || currentTable) && (currentDoc !== this.docNo || (currentTable && currentTable !== this.table));
          const pageChanged = targetPage !== this.page;
          if (billChanged) this.closed = false;
          this.page = targetPage;
          if (this.closed && !['history', 'login', 'tables'].includes(this.page)) this.page = 'closed';
          if (this.page !== 'menu' && this.page !== 'tables' && this.searchOpen) this.closeMenuSearch();
          if (billChanged) {
            this.docNo = currentDoc;
            this.table = currentTable || this.table;
            this.cart = getStoredCart(this.table, this.docNo, this.branch);
            this.cartReady = false;
            this.syncBill().then(() => {
              if (!this.closed && this.docNo) this.syncRemoteCart();
            });
          }
          if (this.page === 'tables' && pageChanged) this.loadTables();
          document.documentElement.setAttribute('data-page', this.page);
        };
        window.addEventListener('popstate', restoreHistoryRoute);
        window.addEventListener('hashchange', restoreHistoryRoute);
        window.addEventListener('keydown', event => {
          if (event.key === '/' && !this.detailOpen && !this.closed && !['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName)) {
            event.preventDefault(); this.openMenuSearch();
          }
        });
        if (window.visualViewport) {
          let keyboardWasOpen = false;
          const syncVV = () => {
            const vv = window.visualViewport;
            const isKb = (window.innerHeight - vv.height) > 120;
            if (keyboardWasOpen && !isKb && this.menuSearchFocused && isMobileLayout()) this.$refs.menuSearch?.blur();
            keyboardWasOpen = isKb;
            document.documentElement.style.setProperty('--vv-height', `${vv.height}px`);
            document.documentElement.style.setProperty('--vv-top', `${vv.offsetTop}px`);
            document.documentElement.classList.toggle('keyboard-active', isKb);
            if (this.noteFocused) this.$nextTick(() => this.revealDetailNote());
          };
          window.visualViewport.addEventListener('resize', syncVV);
          window.visualViewport.addEventListener('scroll', syncVV);
          syncVV();
        }

        // Collapse the mobile navigation on downward scrolling and expand it on upward scrolling.
        let lastScrollY = window.scrollY;
        let navScrollDistance = 0;
        let scrollTicking = false;
        window.addEventListener('scroll', () => {
          if (scrollTicking) return;
          window.requestAnimationFrame(() => {
            // Ignore iOS rubber-band overscroll when detecting scroll direction.
            const maxScrollY = Math.max(0, document.documentElement.scrollHeight - window.innerHeight);
            const currentScrollY = Math.max(0, Math.min(maxScrollY, window.scrollY));
            if (Date.now() < (this._navScrollLockUntil || 0)) {
              lastScrollY = currentScrollY;
              navScrollDistance = 0;
              scrollTicking = false;
              return;
            }
            const diff = currentScrollY - lastScrollY;
            if (diff && Math.sign(diff) !== Math.sign(navScrollDistance)) navScrollDistance = 0;
            navScrollDistance += diff;

            if (!this.detailOpen) {
              if (currentScrollY <= 60) {
                this.navHidden = false;
                navScrollDistance = 0;
              } else if (navScrollDistance > 20 && currentScrollY > 100) {
                this.navHidden = true;
                navScrollDistance = 0;
              } else if (navScrollDistance < -20) {
                this.navHidden = false;
                navScrollDistance = 0;
              }
            }
            lastScrollY = currentScrollY;
            scrollTicking = false;
          });
          scrollTicking = true;
        }, { passive: true });

        // Auto-poll order status and bill updates (Real-time synchronization for all devices at the table)
        let _polling = false;
        const pollUpdates = async () => {
          if (_polling || document.hidden || this.sending || this.confirming) return;
          if (this.page === 'login') return;
          _polling = true;
          try {
            if (this.page === 'tables') {
              await this.loadTables();
            } else {
              await this.syncBill();
              if (!this.closed && this.mode === 'live' && this.docNo && !this.sending && !this.cartReady) {
                await this.syncRemoteCart();
              }
            }
          } catch (e) {
            // Silently ignore background polling errors
          } finally {
            _polling = false;
          }
        };

        setInterval(pollUpdates, 5000);

        document.addEventListener('visibilitychange', () => {
          if (!document.hidden) {
            pollUpdates();
          }
        });

        await this.loadMenu();
      },
    async loadMenu() {
      this.loading = true; this.loadError = false;
      try {
        const data = await PosStore.menu(this.branch);
        this.mode = data.mode || 'live';
        this.categories = [{ id: 'recommended', name: 'เมนูขายดี' }, ...data.categories.map(category => category.id === 'all'
          ? { ...category, name: 'หน้าหลัก', icon: 'home' }
          : category).filter(category => category.id !== 'recommended')];
        if (this.categoryId !== 'recommended' && !this.categories.some(category => category.id === this.categoryId)) {
          this.categoryId = 'all';
        }
        this.$nextTick(() => {
          const list = document.querySelector('.menu-category-sticky .category-list');
          const selected = list?.querySelector('.category-button.selected');
          if (selected) list.scrollLeft += selected.getBoundingClientRect().left - list.getBoundingClientRect().left - 8;
        });
        // The POS menu query has no availability field; keep ordering enabled
        // without presenting a fabricated status in the API response.
        this.items = data.items.map(item => ({ ...item, available: item.available !== false }));
        this.recommendationPairs = Array.isArray(data.recommendationPairs) ? data.recommendationPairs : [];
        const stored = getStoredCart(this.table, this.docNo) || this.cart;
        this.cart = Array.isArray(stored) ? stored.flatMap(line => {
          if (!line || !Number.isInteger(line.qty) || line.qty < 1) return [];
          const item = this.items.find(item => String(item.id).trim() === String(line.id).trim());
          if (item) {
            if (!item.available) return [];
            return [{ ...item, key: typeof line.key === 'string' ? line.key : createCartLineKey(), qty: Math.min(99, line.qty), note: String(line.note || '').slice(0, 200) }];
          }
          return [line];
        }) : [];
        if (!this.closed && this.docNo) {
          await this.syncRemoteCart();
        } else if (!this.closed && !this.docNo) {
          this.cartReady = true;
          this.cartError = false;
        }
        this.restoreOpenDetail();
      } catch { this.loadError = true; }
      finally {
        this.loading = false;
        if (this.restoreMenuCategory) {
          this.$nextTick(() => requestAnimationFrame(() => requestAnimationFrame(() => {
            if (this.page === 'menu' && !this.loadError && !this.search.trim()) {
              if (this.categoryId === 'recommended') {
                window.scrollTo({ top: 0, behavior: 'instant' });
              } else {
                const target = document.getElementById('menu-section-' + this.categoryId);
                if (target && target.getClientRects().length) target.scrollIntoView({ behavior: 'instant', block: 'start' });
              }
            }
            this.restoreMenuCategory = false;
          })));
        }
      }
    },
        cartPendingKey() { return `imdee:cart-operation:${SESSION_TOKEN}:${this.branch}:${this.docNo}`; },
    async syncRemoteCart() {
      if (this.sending || this.cartReady) return;
      const migrationKey = `imdee:session-cart-migrated:${this.branch}:${this.docNo}`;
      try {
        if (!SESSION_TOKEN) throw new Error('Cart session unavailable');
        if (sessionStorage.getItem(migrationKey) === 'done') {
          this.cartReady = true;
          this.cartError = false;
          return;
        }
        const pending = JSON.parse(sessionStorage.getItem(this.cartPendingKey()) || 'null');
        if (pending) {
          await PosStore.cart(pending.action, this.docNo, this.branch, pending.line, pending.operationId);
          sessionStorage.removeItem(this.cartPendingKey());
        }
        const rows = await PosStore.cart('list', this.docNo, this.branch);
        for (const line of rows) {
          const existing = this.cart.find(item => item.key === line.key);
          if (existing && (String(existing.id) !== String(line.id) || existing.qty !== line.qty || String(existing.note || '') !== String(line.note || ''))) {
            throw new Error('Cart changed during migration');
          }
          if (!existing) {
            const product = this.items.find(item => String(item.id).trim() === String(line.id).trim());
            this.cart.push({ ...(product || { available: true, image: 'assets/images/food-placeholder.svg' }), ...line });
          }
          const snapshot = JSON.stringify(this.cart);
          const cKey = this.cartStorageKey();
          sessionStorage.setItem(cKey, snapshot);
          if (sessionStorage.getItem(cKey) !== snapshot) throw new Error('Cart storage unavailable');
          await PosStore.cart('delete', this.docNo, this.branch, line, createCartLineKey());
        }
        sessionStorage.setItem(migrationKey, 'done');
        this.cartReady = true;
        this.cartError = false;
      } catch {
        this.cartReady = false;
        this.cartError = true;
      }
    },
    async retryCart() {
      if (!this.sending && this.docNo) await this.syncRemoteCart();
    },
    async commitCart(action, line) {
      if (this.sending || this.closed || !this.canOrder || !this.cartReady) return false;
      const cKey = this.cartStorageKey();
      if (sessionStorage.getItem(`${cKey}:pending-order`)) {
        await this.alert({ icon: 'warning', title: 'ยังยืนยันผลการสั่งไม่ได้', text: 'กรุณาตรวจสอบรายการที่สั่งหรือติดต่อพนักงานก่อนทำรายการต่อ' });
        return false;
      }
      const updated = action === 'delete' ? this.cart.filter(item => item.key !== line.key) : [...this.cart];
      if (action !== 'delete') {
        const index = updated.findIndex(item => item.key === line.key);
        if (index < 0) updated.push(line);
        else updated[index] = line;
      }
      try {
        const snapshot = JSON.stringify(updated);
        sessionStorage.setItem(cKey, snapshot);
        if (sessionStorage.getItem(cKey) !== snapshot) throw new Error('Cart storage unavailable');
        this.cart = updated;
        return true;
      } catch {
        await this.alert({ icon: 'warning', title: this.language === 'en' ? 'Cart unavailable' : 'บันทึกตะกร้าไม่ได้', text: this.language === 'en' ? 'Please enable browser storage and try again.' : 'กรุณาเปิดการจัดเก็บข้อมูลในเบราว์เซอร์แล้วลองอีกครั้ง' });
        return false;
      }
    },
async syncBill() {
      try {
        const billResp = await PosStore.readBill(this.table, this.docNo, this.branch);
        if (billResp.error) {
          if (this.page === 'tables' || (!customerBill && (this.page === 'login' || location.hash === '#tables' || location.hash === '#login'))) {
            this.docNo = '';
            this.closed = false;
            return;
          }
          this.billError = billResp.error.message;
          this.canOrder = false;
          this.closed = true;
          this.navigate('closed');
          return;
        }

        const bill = billResp.bill;
        if (bill) {
          if (!this.staff && bill.hasOpenBill === false) {
            this.waitingForTable = true;
            this.billError = null;
            this.canOrder = false;
            this.closed = true;
            if (this.detailOpen) this.closeDetail();
            if (this.page !== 'closed') this.navigate('closed');
            return;
          }
          const wasWaitingForTable = this.waitingForTable;
          const tableOnlyClosed = !this.staff && !this.docNo && this.page === 'closed' && !!bill.docNo;
          this.waitingForTable = false;
          this.billError = null;
          if (bill.docNo) this.docNo = bill.docNo;
          if (bill.table) this.table = bill.table;
          if (bill.tableName) this.tableName = bill.tableName;
          this.guests = bill.guests || null;
          this.docSts = typeof bill.docSts === 'number' ? bill.docSts : 0;
          if (redirectClosedStaffBill(this.staff, this.docSts === 1)) return;
          this.docStsDesc = bill.docStsDesc || (this.docSts === 0 ? 'เปิดบิล' : this.docSts === 1 ? 'ออกใบเสร็จแล้ว' : 'ยกเลิก');
          this.canOrder = bill.canOrder !== false && this.docSts === 0;
          const wasCancelled = this.isCancelled;
          this.isCancelled = (this.docSts === 3);

          if (this.docSts === 0) {
            // Doc_Sts = 0: เปิดบิล (Open - Can order)
            this.closed = false;
            this.canOrder = bill.canOrder !== false;
            if (wasWaitingForTable || tableOnlyClosed) this.navigate('menu');
          } else {
            // Doc_Sts = 1 (ออกใบเสร็จแล้ว) or Doc_Sts = 3 (ยกเลิก) -> ปิดสิทธิ์การสั่งเพิ่มของทุกอุปกรณ์ทันที
            if (typeof window.clearRememberedCustomerBill === 'function') window.clearRememberedCustomerBill();
            if (this.page === 'tables' || (!customerBill && (this.page === 'login' || location.hash === '#tables' || location.hash === '#login'))) {
              this.docNo = '';
              this.closed = false;
              return;
            }
            this.closed = true;
            this.canOrder = false;
            this.cart = [];
            this.persistCart();
            if (this.detailOpen) {
              this.closeDetail();
            }
            if ((this.isCancelled && !wasCancelled) || (this.page !== 'history' && location.hash !== '#history')) {
              this.navigate('closed');
            }
          }
        }

        const historyData = await PosStore.orderHistory(this.table, this.docNo, this.branch);
        if (historyData) {
          this.orders = Array.isArray(historyData.orders) ? historyData.orders : [];
          this.pendingItems = Array.isArray(historyData.pendingItems) ? historyData.pendingItems : [];
          this.pendingTotal = typeof historyData.pendingTotal === 'number' ? historyData.pendingTotal : 0;
          this.confirmedTotal = typeof historyData.confirmedTotal === 'number' ? historyData.confirmedTotal : 0;
        }
      } catch (err) {
        console.error('syncBill error:', err);
        this.billError = 'ไม่สามารถเชื่อมต่อข้อมูลบิลได้ กรุณาลองอีกครั้ง';
      }
    },
    persistCart() {
      this.pendingRequest = null;
      try {
        const payload = JSON.stringify(this.cart);
        sessionStorage.setItem(this.cartStorageKey(), payload);
      } catch {
        if (!this.storageWarningShown) {
          this.storageWarningShown = true;
          this.alert({ icon: 'warning', title: 'เบราว์เซอร์ไม่อนุญาตให้เก็บข้อมูล', text: 'ตะกร้าอาจหายเมื่อปิดแท็บ กรุณาเปิดการจัดเก็บข้อมูลในเบราว์เซอร์' });
        }
      }
    },
    get filteredItems() {
      const query = this.search.trim().toLocaleLowerCase();
      const hasRecommendations = this.items.some(item => item.recommended && item.available);
      const items = this.items.filter(item =>
        (query || this.categoryId === 'all' || (this.categoryId === 'recommended' ? (!hasRecommendations || (item.recommended && item.available)) : item.category === this.categoryId)) &&
        (!query || `${item.name} ${item.english || ''}`.toLocaleLowerCase().includes(query)));
      return this.categoryId === 'recommended' && !query && hasRecommendations
        ? items.sort((a, b) => (b.recommendationScore || 0) - (a.recommendationScore || 0) || (b.recommendationQuantity || 0) - (a.recommendationQuantity || 0) || String(a.id).localeCompare(String(b.id)))
        : items;
    },
    get bestSellerItems() {
      return this.items.filter(item => item.recommended && item.available)
        .sort((a, b) => (b.recommendationScore || 0) - (a.recommendationScore || 0) || (b.recommendationQuantity || 0) - (a.recommendationQuantity || 0) || String(a.id).localeCompare(String(b.id))).slice(0, 6);
    },
    get recommendedItems() { return this.search.trim() ? [] : this.bestSellerItems; },
    bestSellerRank(id) { return this.bestSellerItems.findIndex(item => String(item.id).trim() === String(id).trim()) + 1; },
    get rankedTrendingItems() {
      return this.items.filter(item => item.available && item.trendRecentQuantity >= 3)
        .sort((a, b) => b.trendScore - a.trendScore || b.trendRecentQuantity - a.trendRecentQuantity || String(a.id).localeCompare(String(b.id))).slice(0, 6);
    },
    get trendingItems() { return this.search.trim() ? [] : this.rankedTrendingItems; },
    trendingRank(id) { return this.rankedTrendingItems.findIndex(item => String(item.id).trim() === String(id).trim()) + 1; },
    get pairedItems() {
      const selected = new Set([...this.cart, ...this.historyItems].map(item => String(item.id).trim()));
      if (!selected.size) return [];
      const scores = new Map();
      for (const pair of this.recommendationPairs) {
        if (!selected.has(pair.source) || selected.has(pair.target)) continue;
        const old = scores.get(pair.target);
        if (!old || pair.confidence > old.confidence || (pair.confidence === old.confidence && pair.lift > old.lift)) scores.set(pair.target, pair);
      }
      return this.items.filter(item => item.available && scores.has(String(item.id).trim()))
        .sort((a, b) => {
          const x = scores.get(String(a.id).trim()), y = scores.get(String(b.id).trim());
          return y.confidence - x.confidence || y.lift - x.lift || y.count - x.count || String(a.id).localeCompare(String(b.id));
        }).slice(0, 6);
    },
    get menuSections() {
      const query = this.search.trim().toLocaleLowerCase();
      const matching = this.items.filter(item => !query || `${item.name} ${item.english || ''}`.toLocaleLowerCase().includes(query));
      const sections = this.categories.filter(category => !['all', 'recommended'].includes(category.id))
        .map(category => ({ ...category, items: matching.filter(item => item.category === category.id) }))
        .filter(category => category.items.length);
      const known = new Set(this.categories.map(category => category.id));
      const other = matching.filter(item => !known.has(item.category));
      if (other.length) sections.push({ id: 'other', name: this.language === 'en' ? 'Other' : 'เมนูอื่น ๆ', items: other });
      return sections;
    },
    get categoryTitle() { return this.search.trim() ? 'ผลการค้นหา' : this.categoryId === 'all' ? 'ทุกจานอร่อย คัดมาเพื่อคุณ' : this.categories.find(category => category.id === this.categoryId)?.name || ''; },
    get cartCount() { return this.cart.reduce((sum, line) => sum + line.qty, 0); },
    get cartTotal() { return this.cart.reduce((sum, line) => sum + line.price * line.qty, 0); },
    get historyItems() { return this.orders.flatMap(order => Array.isArray(order.items) ? order.items : []); },
    get historyTotal() {
      if (this.confirmedTotal || this.pendingTotal) {
        return this.confirmedTotal + this.pendingTotal;
      }
      return this.orders.reduce((sum, order) => sum + order.total, 0);
    },
    money(value) { return '฿' + Number(value).toLocaleString('th-TH', { maximumFractionDigits: 2 }); },
    formatTime(value) { return new Intl.DateTimeFormat(this.language === 'en' ? 'en-GB' : 'th-TH', { timeZone: 'Asia/Bangkok', hour: '2-digit', minute: '2-digit', day: 'numeric', month: 'short' }).format(new Date(value)); },
    formatHistoryTime(value) {
      if (!value) return '';
      const raw = String(value).trim();
      const normalized = /^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(\.\d+)?$/.test(raw)
        ? raw.replace(' ', 'T').replace(/\.\d+$/, '') + '+07:00' : raw;
      const date = new Date(normalized);
      if (!Number.isFinite(date.getTime())) return '';
      const zone = 'Asia/Bangkok';
      const dayKey = d => new Intl.DateTimeFormat('en-CA', { timeZone: zone, year: 'numeric', month: '2-digit', day: '2-digit' }).format(d);
      const time = new Intl.DateTimeFormat(this.language === 'en' ? 'en-GB' : 'th-TH', { timeZone: zone, hour: '2-digit', minute: '2-digit', hour12: false }).format(date);
      const day = dayKey(date) === dayKey(new Date()) ? (this.language === 'en' ? 'Today' : 'วันนี้') : new Intl.DateTimeFormat(this.language === 'en' ? 'en-GB' : 'th-TH-u-ca-gregory', { timeZone: zone, day: 'numeric', month: 'short', year: 'numeric' }).format(date);
      return `${day} · ${time}${this.language === 'en' ? '' : ' น.'}`;
    },
    quantityInCart(id) { return this.cart.filter(line => line.id === id).reduce((sum, line) => sum + line.qty, 0); },
    bounceMobileNav(nav) {
      if (!nav?.animate || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
      this._navBounce?.cancel();
      this._navBounce = nav.animate(
        [{ transform: 'scale(1)' }, { transform: 'scale(1.045)', offset: 0.38 }, { transform: 'scale(0.99)', offset: 0.72 }, { transform: 'scale(1)' }],
        { duration: 380, easing: 'ease-out' }
      );
    },
    handleMobileNavClick(event) {
      if (this.navSuppressClick) {
        event.preventDefault();
        event.stopPropagation();
        this.navSuppressClick = false;
        return;
      }
      const button = event.target.closest('button');
      if (button && !button.classList.contains('active')) this.bounceMobileNav(event.currentTarget);
    },
    startNavDrag(event) {
      if (this.closed || event.touches.length !== 1) return;
      const rect = event.currentTarget.getBoundingClientRect();
      const activeTab = this.page === 'menu' && this.searchOpen ? 'search' : this.page;
      this.navTouchStart = { x: event.touches[0].clientX, y: event.touches[0].clientY, width: (rect.width - 8) / 4, index: Math.max(0, ['menu', 'search', 'cart', 'history'].indexOf(activeTab)) };
      this.navPressPosition = this.navTouchStart.index * 100;
      this.navPressed = true;
      this.navSuppressClick = false;
    },
    moveNavDrag(event) {
      const start = this.navTouchStart;
      if (!start || event.touches.length !== 1) return;
      const dx = event.touches[0].clientX - start.x;
      const dy = event.touches[0].clientY - start.y;
      if (!this.navDragging && (Math.abs(dx) < 8 || Math.abs(dx) <= Math.abs(dy))) return;
      this.navDragging = true;
      this.navDragPosition = Math.max(0, Math.min(300, start.index * 100 + dx / start.width * 100));
      this.navDragScale = 1 + Math.min(.07, Math.abs(dx) / start.width * .045);
    },
    cancelNavDrag() {
      this.navTouchStart = null;
      this.navDragging = false;
      this.navPressed = false;
      this.navDragScale = 1;
    },
    endNavDrag(event) {
      const start = this.navTouchStart;
      this.navTouchStart = null;
      this.navPressed = false;
      if (!start || !event.changedTouches.length) return;
      if (!this.navDragging) return;
      const index = Math.round(this.navDragPosition / 100);
      this.navSuppressClick = true;
      const target = ['menu', 'search', 'cart', 'history'][index];
      const active = this.page === 'menu' && this.searchOpen ? 'search' : this.page;
      if (target !== active) this.bounceMobileNav(event.currentTarget);
      if (target === 'search') this.openMenuSearch();
      else this.navigate(target);
      this.navDragging = false;
      setTimeout(() => { this.navSuppressClick = false; }, 500);
    },
    navigate(page) {
      if (customerBill) page = customerBillPage(page, this.closed);
      if (page === 'tables' && !this.staff) {
        page = (this.docNo || this.table) ? 'menu' : 'login';
      }
      const previousPage = this.page;
      const repeatPage = page === this.page && !this.searchOpen;
      if (!repeatPage) this._navScrollLockUntil = Date.now() + 600;
      if (this.searchOpen) this.closeMenuSearch();
      const nextPage = this.closed && !['history', 'closed', 'login', 'tables'].includes(page) ? 'closed' : page;
      const order = ['menu', 'cart', 'history'];
      const from = order.indexOf(previousPage);
      const to = order.indexOf(nextPage);
      this._pageSlide?.cancel();
      if (from !== -1 && to !== -1 && from !== to && isMobileLayout() && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        const selector = nextPage === 'cart' ? '.cart-panel' : nextPage === 'history' ? '.history-section' : '.main-content > section[aria-label="เมนูอาหาร"]';
        const target = document.querySelector(selector);
        if (target?.animate) {
          this._pageSlide = target.animate(
            [{ opacity: 0.92, transform: `translateX(${to > from ? 16 : -16}px)` }, { opacity: 1, transform: 'translateX(0)' }],
            { duration: 260, easing: 'cubic-bezier(.22, 1, .36, 1)' }
          );
        }
      }
      this.page = nextPage;
      this.navHidden = false;
      document.documentElement.setAttribute('data-page', this.page);
      if (this.page === 'tables' || this.page === 'login') {
        const staffTables = location.pathname.includes('/staff/tables')
          ? location.pathname.replace(/\/staff\/tables(?:\/.*)?$/, '/staff/tables/')
          : new URL('staff/tables/', document.baseURI).pathname;
        const targetUrl = this.page === 'tables' ? staffTables : new URL('.', document.baseURI).pathname;
        history.pushState(history.state, '', targetUrl);
      } else {
        if (location.hash !== '#' + this.page) {
          history.pushState(history.state, '', `${location.pathname}${location.search}#${this.page}`);
        }
      }
      if (window.scrollY > 0 && !(repeatPage && nextPage === 'closed')) window.scrollTo({ top: 0, behavior: repeatPage ? 'smooth' : 'instant' });
    },
    openMenuSearch() {
      const isTables = this.page === 'tables';
      const repeatSearch = (this.page === 'menu' || isTables) && this.searchOpen;
      if (this.page !== 'menu' && !isTables) this.navigate('menu');
      if (!isTables) this.categoryId = 'all';
      this.searchOpen = true;
      window.scrollTo({ top: 0, behavior: repeatSearch ? 'smooth' : 'instant' });
      this.$refs.menuSearchBar?.style.removeProperty('display');
      if (isTables) {
        this.$refs.tableSearchTop?.focus({ preventScroll: true });
      } else {
        this.$refs.menuSearch?.focus({ preventScroll: true });
      }
    },
    closeMenuSearch() {
      this.$refs.menuSearch?.blur();
      this.menuSearchFocused = false;
      this.searchOpen = false;
      if (this.page === 'tables') {
        this.tableSearch = '';
      } else {
        this.search = '';
      }
    },
    startCategoryDrag(event) {
      if (event.pointerType !== 'mouse' || event.button !== 0) return;
      this.categoryDrag = { x: event.clientX, scroll: event.currentTarget.scrollLeft, dragging: false };
      this.categorySuppressClick = false;
    },
    moveCategoryDrag(event) {
      const drag = this.categoryDrag;
      if (!drag || event.pointerType !== 'mouse') return;
      const distance = event.clientX - drag.x;
      if (!drag.dragging && Math.abs(distance) < 5) return;
      if (!drag.dragging) {
        drag.dragging = true;
        event.currentTarget.setPointerCapture(event.pointerId);
      }
      event.currentTarget.scrollLeft = drag.scroll - distance;
      event.preventDefault();
    },
    endCategoryDrag(event) {
      if (!this.categoryDrag) return;
      this.categorySuppressClick = this.categoryDrag.dragging;
      this.categoryDrag = null;
      if (event.currentTarget.hasPointerCapture(event.pointerId)) event.currentTarget.releasePointerCapture(event.pointerId);
      if (this.categorySuppressClick) setTimeout(() => { this.categorySuppressClick = false; }, 0);
    },
    scrollCategoryWheel(event) {
      const list = event.currentTarget;
      if (!window.matchMedia('(pointer: fine)').matches || list.scrollWidth <= list.clientWidth) return;
      const delta = Math.abs(event.deltaX) > Math.abs(event.deltaY) ? event.deltaX : event.deltaY;
      if ((delta < 0 && list.scrollLeft > 0) || (delta > 0 && list.scrollLeft < list.scrollWidth - list.clientWidth - 1)) {
        list.scrollLeft += delta;
        event.preventDefault();
      }
    },
    selectMenuCategory(id) {
      this.categoryId = id;
      this.search = '';
      this.menuCategoryScrolling = true;
      this.finishMenuCategoryScroll();
      this.$nextTick(() => {
        if (id === 'recommended') {
          document.querySelector('.menu-category-sticky .category-list')?.scrollTo({ left: 0, behavior: 'smooth' });
          window.scrollTo({ top: 0, behavior: 'smooth' });
          return;
        }
        const target = id === 'all' ? document.getElementById('menu-sections') : document.getElementById('menu-section-' + id);
        target?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    },
    finishMenuCategoryScroll() {
      clearTimeout(this.menuCategoryScrollTimer);
      this.menuCategoryScrollTimer = setTimeout(() => {
        this.menuCategoryScrolling = false;
        this.updateVisibleMenuCategory();
      }, 200);
    },
    updateVisibleMenuCategory() {
      if (this.page !== 'menu' || this.loading || this.restoreMenuCategory || this.menuCategoryScrolling || this.search.trim() || this.detailOpen) return;
      const bar = document.querySelector('.menu-category-sticky');
      const sections = [...document.querySelectorAll('#menu-sections .menu-category-section')];
      if (!bar || !sections.length) return;
      const threshold = bar.getBoundingClientRect().bottom + 20;
      let active = 'recommended';
      for (const section of sections) {
        if (section.getBoundingClientRect().top <= threshold) active = section.id.slice('menu-section-'.length);
        else break;
      }
      if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 4) active = sections[sections.length - 1].id.slice('menu-section-'.length);
      if (!this.categories.some(category => category.id === active)) return;
      if (active === this.categoryId) return;
      this.categoryId = active;
      this.$nextTick(() => {
        const list = bar.querySelector('.category-list');
        const selected = list?.querySelector('.category-button.selected');
        if (!selected) return;
        const listRect = list.getBoundingClientRect();
        const buttonRect = selected.getBoundingClientRect();
        list.scrollTo({ left: list.scrollLeft + buttonRect.left - listRect.left - 8, behavior: 'smooth' });
      });
    },
    async quickAdd(item) {
      if (!item.available || this.closed || this.sending || !this.canOrder || !this.cartReady) return;
      const existing = this.cart.find(line => line.id === item.id && !line.note && line.qty < 99);
      const line = { ...item, key: existing?.key || createCartLineKey(), revision: existing?.revision, qty: existing ? existing.qty + 1 : 1, note: '' };
      if (await this.commitCart('upsert', line, true)) this.triggerCartBump();
    },
    selectCategory(id) {
      this.categoryId = id; this.search = '';
      window.scrollTo({ top: 0, behavior: 'instant' });
    },
    persistOpenDetail() {
      try {
        if (!this.detailOpen) { sessionStorage.removeItem(DETAIL_KEY); return; }
        sessionStorage.setItem(DETAIL_KEY, JSON.stringify({ id: this.selected.id, qty: this.detailQty, note: this.detailNote, editingKey: this.editingKey }));
      } catch { /* The dialog still works if session storage is unavailable. */ }
    },
    restoreOpenDetail() {
      let saved;
      try { saved = JSON.parse(sessionStorage.getItem(DETAIL_KEY) || 'null'); } catch { return; }
      if (!saved || this.detailOpen || this.closed || !this.canOrder) return;
      const item = this.items.find(entry => String(entry.id) === String(saved.id));
      const editLine = saved.editingKey && this.cart.find(line => line.key === saved.editingKey && String(line.id) === String(saved.id));
      if (!item || !item.available || (saved.editingKey && !editLine)) {
        try { sessionStorage.removeItem(DETAIL_KEY); } catch { /* Ignore unavailable storage. */ }
        return;
      }
      this.selected = item;
      this.detailQty = Math.min(99, Math.max(1, Math.floor(Number(saved.qty) || 1)));
      this.detailNote = String(saved.note || '').slice(0, 200);
      this.editingKey = editLine ? editLine.key : null;
      this.detailOpen = true;
      this.ensureDetailHistory();
    },
    ensureDetailHistory() {
      if (!history.state?.imdeeDetail) {
        history.pushState({ ...history.state, imdeeDetail: true }, '', location.href);
      }
    },
    openDetail(item) {
      if (this.sending || this.closed || !this.canOrder) return;
      this.returnFocus = document.activeElement;
      this.selected = item;
      this.detailQty = 1;
      this.detailNote = '';
      this.editingKey = null;
      this.detailOpen = true;
      this.ensureDetailHistory();
      if (!isMobileLayout()) {
        setTimeout(() => this.$refs.detailDialog?.querySelector('.detail-close')?.focus({ preventScroll: true }), 320);
      }
    },
    editLine(line) {
      const item = this.items.find(item => item.id === line.id);
      if (!item) return;
      this.openDetail(item);
      this.detailQty = line.qty;
      this.detailNote = line.note;
      this.editingKey = line.key;
    },
    closeDetail(fromHistory = false) {
      if (!this.detailOpen) return;
      this.endDetailNoteEditing();
      if (fromHistory && this.$refs.detailOverlay) this.$refs.detailOverlay.style.display = 'none';
      this.detailOpen = false;
      this.persistOpenDetail();
      if (!fromHistory && history.state?.imdeeDetail) history.back();
      if (!isMobileLayout() && this.returnFocus?.focus) {
        this.$nextTick(() => this.returnFocus.focus({ preventScroll: true }));
      }
    },
    beginDetailNoteEditing() {
      clearTimeout(this.noteFocusTimer);
      if (!isMobileLayout()) {
        this.noteFocused = true;
        return;
      }
      this.noteFocusTimer = setTimeout(() => {
        if (!this.detailOpen || document.activeElement !== this.$refs.detailNoteInput) return;
        this.noteFocused = true;
        this.$nextTick(() => this.revealDetailNote());
      }, 250);
    },
    endDetailNoteEditing() {
      clearTimeout(this.noteFocusTimer);
      this.noteFocusTimer = null;
      this.noteFocused = false;
    },
    revealDetailNote() {
      if (!this.noteFocused || !isMobileLayout()) return;
      const content = this.$refs.detailContent;
      const input = this.$refs.detailNoteInput;
      if (!content || !input) return;
      const contentRect = content.getBoundingClientRect();
      const inputRect = input.getBoundingClientRect();
      const visibleBottom = Math.min(contentRect.bottom, window.visualViewport ? window.visualViewport.offsetTop + window.visualViewport.height : window.innerHeight);
      if (inputRect.bottom > visibleBottom - 12) content.scrollTop += inputRect.bottom - visibleBottom + 12;
      else if (inputRect.top < contentRect.top + 12) content.scrollTop -= contentRect.top + 12 - inputRect.top;
    },
    trapFocus(event) {
      const elements = [...this.$refs.detailDialog.querySelectorAll('button:not([disabled]), textarea, [tabindex="0"]')];
      const first = elements[0], last = elements[elements.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    },
    async saveDetail() {
      if (!this.selected?.available || this.closed || this.sending || !this.canOrder) return;
      const note = this.detailNote.trim().slice(0, 200);
      const qty = Math.min(99, Math.max(1, Math.floor(this.detailQty)));
      let line;
      if (this.editingKey) {
        const existing = this.cart.find(item => item.key === this.editingKey);
        if (!existing) return;
        line = { ...this.selected, key: this.editingKey, revision: existing.revision, qty, note };
      } else {
        const existing = this.cart.find(line => line.id === this.selected.id && line.note === note && line.qty + qty <= 99);
        line = { ...this.selected, key: existing?.key || createCartLineKey(), revision: existing?.revision, qty: existing ? existing.qty + qty : qty, note };
      }
      const edited = !!this.editingKey;
      if (!await this.commitCart('upsert', line, true)) return;
      this.closeDetail();
      this.triggerCartBump();
      this.alert({ position: 'center', backdrop: false, icon: 'success', title: edited ? 'แก้ไขรายการแล้ว' : 'เพิ่มลงตะกร้าแล้ว', customClass: { popup: 'glass-success' }, showConfirmButton: false, timer: 1800 });
    },
    async quickOrder() {
      if (!this.selected?.available || this.closed || this.sending || !this.canOrder) return;
      const note = this.detailNote.trim().slice(0, 200);
      const qty = Math.min(99, Math.max(1, Math.floor(this.detailQty)));
      const itemToOrder = {
        ...this.selected,
        key: createCartLineKey(),
        qty: qty,
        note: note,
      };

      const lineTotal = itemToOrder.price * qty;
      const confirm = await this.alert({
        title: 'ต้องการยืนยันสั่งอาหารใช่หรือไม่',
        text: this.language === 'en'
          ? `"${itemToOrder.name}" · ${qty} ${itemToOrder.unit || 'item(s)'} · Total ${this.money(lineTotal)}`
          : `"${itemToOrder.name}" จำนวน ${qty} ${itemToOrder.unit || 'จาน'} รวม ${Number(lineTotal).toLocaleString('th-TH')} บาท`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'ยืนยันสั่งอาหาร',
        cancelButtonText: 'ยกเลิก',
      });
      if (!confirm.isConfirmed) return;

      this.sending = true;
      const idemKey = 'idem_' + SESSION_TOKEN + '_' + Date.now() + '_' + Math.random().toString(36).substring(2, 8);
      try {
        const result = await PosStore.placeOrder([itemToOrder], this.table, this.docNo, this.branch, idemKey, false, this.staff?.emId || '');
        if (result.docNo) {
          this.docNo = result.docNo;
        }
        this.closeDetail();
        await this.syncBill();
        this.navigate('history');
        await this.alert({
          icon: 'success',
          title: 'รอยืนยันออเดอร์',
          customClass: { popup: 'glass-success' },
          timer: 2000,
          showConfirmButton: false,
        });
      } catch (error) {
        if (error.message.includes('BILL_CLOSED') || error.message.includes('ออกใบเสร็จ')) {
          await this.syncBill();
          this.navigate('closed');
          await this.alert({
            icon: 'warning',
            title: 'บิลปิดแล้ว',
            text: 'บิลนี้ออกใบเสร็จเรียบร้อยแล้ว ไม่สามารถสั่งอาหารเพิ่มได้',
          });
        } else {
          await this.alert({
            icon: 'error',
            title: 'ยังส่งออเดอร์ไม่ได้',
            text: this.language === 'en' ? 'Could not complete the request. Please contact the staff.' : (error.message || 'กรุณาตรวจสอบการเชื่อมต่อกับระบบ POS'),
          });
        }
      } finally {
        this.sending = false;
      }
    },
    async changeQuantity(key, amount) {
      if (this.sending || this.closed || !this.canOrder) return;
      const line = this.cart.find(line => line.key === key);
      if (!line) return;

      if (amount < 0 && line.qty <= 1) {
        await this.removeCartLine(key);
        return;
      }
      const qty = Math.min(99, Math.max(1, line.qty + amount));
      if (qty !== line.qty && await this.commitCart('upsert', { ...line, qty })) this.triggerCartBump();
    },
    async removeCartLine(key) {
      if (this.sending || this.closed || !this.canOrder) return;
      const line = this.cart.find(item => item.key === key);
      if (!line) return;
      const confirm = await this.alert({
          title: 'ยืนยันลบรายการ?',
          text: this.language === 'en' ? `Remove "${line.name}" from your cart?` : `คุณต้องการนำ "${line.name}" ออกจากตะกร้าหรือไม่?`,
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#e04f32',
          cancelButtonColor: '#8c938f',
          confirmButtonText: 'ลบรายการ',
          cancelButtonText: 'ยกเลิก',
          reverseButtons: true
        });
      if (!confirm.isConfirmed || this.sending || this.closed || !this.canOrder) return;
      if (await this.commitCart('delete', line)) this.triggerCartBump();
    },
    alert(options) {
      const translated = { ...options };
      for (const key of ['title', 'text', 'confirmButtonText', 'cancelButtonText', 'denyButtonText']) {
        if (typeof translated[key] === 'string') translated[key] = this.t(translated[key]);
      }
      return Swal.fire({ confirmButtonColor: '#ef7147', cancelButtonColor: '#8c938f', confirmButtonText: this.t('ตกลง'), reverseButtons: true, ...translated });
    },
    startOrderSlide(event) {
      if (this.sending || this.closed || (event.pointerType === 'mouse' && event.button !== 0)) return;
      this.orderSlidePointer = event.pointerId;
      this.orderSlideStartX = event.clientX;
      this.orderSlideMax = Math.max(0, this.$refs.orderSlideTrack.clientWidth - event.currentTarget.offsetWidth - 8);
      this.orderSlidePosition = 0;
      event.currentTarget.setPointerCapture(event.pointerId);
    },
    moveOrderSlide(event) {
      if (this.orderSlidePointer !== event.pointerId) return;
      this.orderSlidePosition = Math.min(this.orderSlideMax, Math.max(0, event.clientX - this.orderSlideStartX));
    },
    async endOrderSlide(event, cancelled = false) {
      if (this.orderSlidePointer !== event.pointerId) return;
      const complete = !cancelled && this.orderSlideMax > 0 && this.orderSlidePosition >= this.orderSlideMax * .85;
      this.orderSlidePointer = null;
      if (!complete) {
        this.orderSlidePosition = 0;
        return;
      }
      this.orderSlidePosition = this.orderSlideMax;
      try {
        await this.submitOrder();
      } finally {
        this.orderSlidePosition = 0;
      }
    },
    async submitOrder() {
      if (this.sending || this.closed || !this.canOrder) return;
      if (!this.cart.length) {
        await this.alert({ icon: 'warning', title: 'ตะกร้าของคุณว่าง', text: 'กรุณาเลือกอาหารก่อนสั่ง' });
        return;
      }
      const cKey = this.cartStorageKey();
      if (sessionStorage.getItem(`${cKey}:pending-order`)) {
        await this.alert({ icon: 'warning', title: 'ยังยืนยันผลการสั่งไม่ได้', text: 'กรุณาตรวจสอบรายการที่สั่งหรือติดต่อพนักงานก่อนสั่งซ้ำ' });
        return;
      }
      if (!this.cartReady) {
        await this.alert({ icon: 'warning', title: this.language === 'en' ? 'Cart unavailable' : 'ยังเชื่อมต่อตะกร้าไม่ได้', text: this.language === 'en' ? 'Please reconnect and check your cart first.' : 'กรุณาลองเชื่อมต่อตะกร้าอีกครั้งก่อนสั่งอาหาร' });
        return;
      }
      this.sending = true;
      this.orderAwaitingConfirm = true;
      try {
        const confirm = await this.alert({
          title: 'ต้องการยืนยันสั่งอาหารใช่หรือไม่',
          text: this.language === 'en'
            ? `${this.tableName || ('Table ' + this.table)}${this.docNo ? ' · #' + this.docNo : ''} · ${this.cartCount} items · Total ${this.money(this.cartTotal)}`
            : `${this.tableName || ('โต๊ะ ' + this.table)}${this.docNo ? ' · บิล ' + this.docNo : ''} · ${this.cartCount} รายการ รวม ${Number(this.cartTotal).toLocaleString('th-TH')} บาท`,
          icon: 'question',
          showCancelButton: true,
          confirmButtonColor: '#ef7147',
          cancelButtonColor: '#8c938f',
          confirmButtonText: 'ยืนยันสั่งอาหาร',
          cancelButtonText: 'ยกเลิก',
          reverseButtons: true
        });
        if (!confirm.isConfirmed) return;
        this.orderAwaitingConfirm = false;

        const idemKey = 'idem_' + SESSION_TOKEN + '_' + Date.now() + '_' + Math.random().toString(36).substring(2, 8);
        sessionStorage.setItem(`${cKey}:pending-order`, idemKey);
        const result = await PosStore.placeOrder(this.cart, this.table, this.docNo, this.branch, idemKey, false, this.staff?.emId || '');
        if (result.docNo) {
          this.docNo = result.docNo;
        }
        this.cart = [];
        this.persistCart();
        sessionStorage.removeItem(`${cKey}:pending-order`);
        await this.syncBill();
        this.navigate('history');

        await this.alert({
          icon: 'success',
          title: 'รอยืนยันออเดอร์',
          customClass: { popup: 'glass-success' },
          timer: 2000,
          showConfirmButton: false,
        });
      } catch (error) {
        if (error.status >= 400 && error.status < 500 && error.status !== 408 && error.status !== 429) {
          sessionStorage.removeItem(`${cKey}:pending-order`);
        }
        if (error.code === 'CART_ITEM_CHANGED') {
          this.sending = false;
          await this.syncRemoteCart();
          await this.syncBill();
          await this.alert({ icon: 'warning', title: this.language === 'en' ? 'Cart changed' : 'รายการในตะกร้ามีการเปลี่ยนแปลง', text: this.language === 'en' ? 'Please check your cart and order history before trying again.' : 'กรุณาตรวจสอบตะกร้าและรายการที่สั่งก่อนลองอีกครั้ง' });
          return;
        }
        if (error.message.includes('BILL_CLOSED') || error.message.includes('ออกใบเสร็จ')) {
          await this.syncBill();
          this.navigate('closed');
          await this.alert({
            icon: 'warning',
            title: 'บิลปิดแล้ว',
            text: 'บิลนี้ออกใบเสร็จเรียบร้อยแล้ว ไม่สามารถสั่งอาหารเพิ่มได้'
          });
        } else if (error.message.includes('BILL_CANCELLED') || error.message.includes('ยกเลิก')) {
          await this.syncBill();
          this.navigate('closed');
          await this.alert({
            icon: 'error',
            title: 'บิลถูกยกเลิก',
            text: 'บิลนี้ถูกยกเลิกแล้ว (Doc_Sts = 3) ไม่สามารถสั่งอาหารได้'
          });
        } else {
          await this.alert({
            icon: 'error',
            title: 'ยังส่งออเดอร์ไม่ได้',
            text: this.language === 'en' ? 'Could not complete the request. Please contact the staff.' : (error.message || 'กรุณาตรวจสอบการเชื่อมต่อกับระบบ POS')
          });
        }
      } finally {
        this.orderAwaitingConfirm = false;
        this.sending = false;
      }
    },
    async confirmPending(tradeId = null, tradeName = '') {
      if (this.confirming || this.closed || !this.canOrder) return;
      const targetText = this.language === 'en'
        ? (tradeId ? `"${tradeName || tradeId}"` : `all ${this.pendingItems.length} pending items`)
        : (tradeId ? `รายการ "${tradeName || tradeId}"` : `รายการที่รอการยืนยันทั้งหมด (${this.pendingItems.length} รายการ)`);
      const confirm = await this.alert({
        title: 'ยืนยันสั่งอาหาร?',
        text: this.language === 'en' ? `Send ${targetText} to the kitchen now?` : `ต้องการยืนยันส่ง ${targetText} เข้าห้องครัวเพื่อเริ่มปรุงอาหารทันทีหรือไม่?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'ยืนยันส่งครัว',
        cancelButtonText: 'ตรวจทานก่อน',
      });
      if (!confirm.isConfirmed) return;

      this.confirming = true;
      try {
        const result = await PosStore.confirmOrder(this.docNo, tradeId, this.branch);
        await this.syncBill();
        await this.alert({
          icon: 'success',
          title: 'ยืนยันรายการเรียบร้อย!',
          text: this.language === 'en' ? 'Items sent to the kitchen.' : (result.message || 'ส่งรายการอาหารเข้าห้องครัวเรียบร้อยแล้ว (Food_Que_STS = S)'),
          timer: 2000,
          showConfirmButton: false,
        });
      } catch (err) {
        await this.alert({
          icon: 'error',
          title: 'ไม่สามารถยืนยันได้',
          text: this.language === 'en' ? 'Could not confirm the items. Please contact the staff.' : (err.message || 'เกิดข้อผิดพลาดในการยืนยันรายการอาหาร'),
        });
      } finally {
        this.confirming = false;
      }
    },
    staffHelp() { this.alert({ title: 'ให้เราช่วยดูแลมื้อนี้', text: this.language === 'en' ? 'Please ask a staff member for assistance.' : 'หากต้องการความช่วยเหลือ กรุณาแจ้งพนักงานได้ทันที', icon: 'info', confirmButtonText: 'เข้าใจแล้ว' }); },
  };
});
});

