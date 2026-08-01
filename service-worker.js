const CACHE_NAME = "unifile-v1";
const STATIC_ASSETS = [
  "/",
  "/index.php",
  "/upload.php",
  "/download.php",
  "/settings.php",
  "/verify.php",
  "/manifest.json",
  "/public/uni.png",
  "/public/icon-192.png",
  "/public/icon-512.png",
  "https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap"
];

// Install - cache all static files
self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(STATIC_ASSETS);
    })
  );
  self.skipWaiting();
});

// Activate - delete old caches
self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key))
      );
    })
  );
  self.clients.claim();
});

// Fetch - serve from cache first, then network
self.addEventListener("fetch", (event) => {
  // Don't cache POST requests or file uploads/downloads
  if (event.request.method !== "GET") return;

  event.respondWith(
    caches.match(event.request).then((cachedResponse) => {
      // Return cached file if found
      if (cachedResponse) {
        return cachedResponse;
      }
      // Otherwise fetch from network and cache it
      return fetch(event.request).then((networkResponse) => {
        return caches.open(CACHE_NAME).then((cache) => {
          cache.put(event.request, networkResponse.clone());
          return networkResponse;
        });
      });
    }).catch(() => {
      // Fallback if offline and not cached
      if (event.request.destination === "document") {
        return caches.match("/index.php");
      }
    })
  );
});