const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync('public/assets/js/app.js', 'utf8');
const billHash = 'ijokbmjkngkfcofekbhedodgdbljdodnhhlbmkialjmakceflhdlocodnjffekha';

function setup({ path = '/' + billHash, hash = '', doc = '68005652', verifiedHash = billHash, closed = false } = {}) {
  const listeners = {};
  const attrs = { 'data-initial-route': closed ? 'closed' : 'menu' };
  const location = { pathname: path, search: '', hash, href: 'https://example.test' + path + hash };
  const history = { state: null, replaceState(_state, _title, url) { const parsed = new URL(url, 'https://example.test'); Object.assign(location, { pathname: parsed.pathname, search: parsed.search, hash: parsed.hash, href: parsed.href }); }, pushState(_state, _title, url) { this.replaceState(null, '', url); } };
  const document = { baseURI: 'https://example.test/', documentElement: { getAttribute: k => attrs[k], setAttribute: (k, v) => { attrs[k] = v; }, removeAttribute: k => { delete attrs[k]; } }, addEventListener(name, fn) { if (name === 'alpine:init') listeners.alpine = fn; }, querySelector() { return null; }, hidden: false };
  const storage = { getItem() { return null; }, setItem() {}, removeItem() {} };
  const window = { location, appBillHash: verifiedHash, appResolvedDoc: doc, appBranch: '001', matchMedia: () => ({ matches: false }), addEventListener(name, fn) { listeners[name] = fn; }, scrollY: 0, innerHeight: 800 };
  let factory;
  const context = { window, document, location, history, URL, URLSearchParams, localStorage: storage, sessionStorage: storage, performance: { getEntriesByType: () => [] }, Alpine: { data(_name, fn) { factory = fn; } }, setInterval() {}, setTimeout() {}, requestAnimationFrame() {}, console };
  vm.runInNewContext(source, context);
  listeners.alpine();
  const app = factory('menu', closed, '10', doc, false);
  app.$watch = () => {}; app.$nextTick = () => {}; app.syncBill = async () => {}; app.loadMenu = async () => {};
  return { app, location, listeners, attrs };
}

test('verified customer QR with #login opens the bill and retains its document', async () => {
  const { app, location } = setup({ hash: '#login' });
  await app.init();
  assert.equal(app.page, 'menu'); assert.equal(app.docNo, '68005652'); assert.equal(location.hash, '#menu');
});

test('verified customer QR never navigates to login, including history changes', async () => {
  const { app, location, listeners } = setup();
  await app.init();
  app.navigate('login'); assert.equal(app.page, 'menu');
  location.hash = '#login'; listeners.hashchange(); assert.equal(app.page, 'menu');
});

test('closed verified bill routes login requests to the closed page', async () => {
  const { app, location } = setup({ hash: '#login', closed: true });
  await app.init(); app.navigate('login');
  assert.equal(app.page, 'closed'); assert.equal(app.docNo, '68005652'); assert.equal(location.hash, '#closed');
});

test('staff route and missing verification do not gain customer-bill routing', async () => {
  const noBill = setup({ path: '/', doc: '', verifiedHash: '', hash: '' });
  await noBill.app.init(); assert.equal(noBill.app.page, 'login');
  const staffPath = setup({ path: '/staff/tables/' + billHash, doc: '', verifiedHash: '', hash: '' });
  await staffPath.app.init(); assert.equal(staffPath.app.page, 'login');
});
