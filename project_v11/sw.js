// sw.js - NakreosStream Service Worker
const CACHE_NAME = 'nakreosstream-v1';
const STATIC_ASSETS = [
  '/',
  '/manifest.json',
  '/assets/img/default-avatar.png',
  '/assets/img/no-thumb.png',
];

// Install
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
      return cache.addAll(STATIC_ASSETS.filter(url => !url.includes('googleapis')));
    }).catch(() => {})
  );
  self.skipWaiting();
});

// Activate
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
    )
  );
  self.clients.claim();
});

// Fetch - Network first, cache fallback
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);

  // Skip non-GET, admin, ajax, api requests
  if (event.request.method !== 'GET') return;
  if (url.pathname.startsWith('/admin') || url.pathname.startsWith('/ajax') || url.pathname.startsWith('/api')) return;
  if (url.hostname !== self.location.hostname) return;

  // HTML: network first
  if (event.request.headers.get('Accept')?.includes('text/html')) {
    event.respondWith(
      fetch(event.request)
        .then(res => {
          if (res.ok) {
            const clone = res.clone();
            caches.open(CACHE_NAME).then(c => c.put(event.request, clone));
          }
          return res;
        })
        .catch(() => caches.match(event.request))
    );
    return;
  }

  // Assets: cache first
  event.respondWith(
    caches.match(event.request).then(cached => {
      if (cached) return cached;
      return fetch(event.request).then(res => {
        if (res.ok && res.type === 'basic') {
          const clone = res.clone();
          caches.open(CACHE_NAME).then(c => c.put(event.request, clone));
        }
        return res;
      }).catch(() => new Response('Offline', { status: 503 }));
    })
  );
});

// Push Notification
self.addEventListener('push', event => {
  const data = event.data ? event.data.json() : { title: 'NakreosStream', body: 'Yeni bildirim' };
  event.waitUntil(
    self.registration.showNotification(data.title, {
      body:    data.body,
      icon:    '/assets/img/icon-192.png',
      badge:   '/assets/img/icon-192.png',
      vibrate: [200, 100, 200],
      data:    { url: data.url || '/' }
    })
  );
});

self.addEventListener('notificationclick', event => {
  event.notification.close();
  event.waitUntil(clients.openWindow(event.notification.data.url));
});
