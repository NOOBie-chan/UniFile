const CACHE_NAME = "unifile-v1";
const urlsToCache = [
  "/",
  "/upload.php",
  "/download.php",
  "/settings.php",
  "/verify.php",
  "/lock.json",
  "/queue.json",
  "/session.json",
  "/settings.json"
];

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(urlsToCache))
  );
});

self.addEventListener("fetch", (event) => {
  event.respondWith(
    caches.match(event.request).then((response) => response || fetch(event.request))
  );
});