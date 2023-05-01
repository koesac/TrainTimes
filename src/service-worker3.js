// Register the service worker
self.addEventListener('install', function(event) {
    console.log('Service worker installed');
  });


  // Define cache name
var CACHE_NAME = 'my-cache';

// Cache all requests
self.addEventListener('fetch', function (event) {
  event.respondWith(
    caches.open('mysite-dynamic').then(function (cache) {
      console.log("restored from cache");
      return fetch(event.request).then(function (response) {
        cache.put(event.request, response.clone());
        console.log("cached");
        console.log("returned from network");
        return response;

      });
    }),
  );
});
