'use strict';

self.addEventListener('install', function (event) {
    event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', function (event) {
    var url = new URL(event.request.url);
    var scope = new URL(self.registration.scope);
    if (event.request.method !== 'GET' || event.request.mode !== 'navigate' ||
        url.origin !== scope.origin || url.pathname !== scope.pathname) {
        return;
    }
    event.respondWith(fetch(event.request).catch(function () {
        return new Response(
            '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CommonCalendar offline</title><body><main><h1>CommonCalendar is offline</h1><p>Connect to the internet and reload to view calendars or make bookings.</p></main></body></html>',
            { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store' } }
        );
    }));
});
