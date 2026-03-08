const CACHE_NAME = 'ha-powerflow-v1';
const ASSETS = [
  './',
  './css/style.css',
  './js/powerflow.js',
  './images/ha-powerflow.webp'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(ASSETS))
  );
});

self.addEventListener('fetch', (event) => {
  event.respondWith(
    caches.match(event.request).then((response) => response || fetch(event.request))
  );
});
