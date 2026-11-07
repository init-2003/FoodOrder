if ('serviceWorker' in navigator && window.isSecureContext) {
  window.addEventListener('load', () => {
    const base = new URL('.', document.baseURI);
    navigator.serviceWorker.register(new URL('sw.js', base), { scope: base.pathname })
      .catch(error => console.warn('Service worker registration failed:', error));
  });
}
