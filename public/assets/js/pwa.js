if ('serviceWorker' in navigator && window.isSecureContext) {
  window.addEventListener('load', () => {
    const base = new URL('.', document.baseURI);
    const worker = new URL('sw.js', base);
    if (window.appServiceWorkerVersion) worker.searchParams.set('v', window.appServiceWorkerVersion);
    navigator.serviceWorker.register(worker, { scope: base.pathname })
      .catch(error => console.warn('Service worker registration failed:', error));
  });
}
