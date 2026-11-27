// Run with: node tests/pwa-worker.js
'use strict';

const assert = require('assert');
const fs = require('fs');
const vm = require('vm');
const handlers = {};
let networkFails = false;
let calls = 0;
const context = {
    URL,
    Response,
    fetch: async function () {
        calls++;
        if (networkFails) {
            throw new Error('offline');
        }
        return new Response('live calendar');
    },
    self: {
        registration: { scope: 'https://example.org/staff-app/' },
        clients: { claim: async function () {} },
        skipWaiting: async function () {},
        addEventListener: function (type, handler) { handlers[type] = handler; }
    }
};
vm.runInNewContext(fs.readFileSync(__dirname + '/../assets/calendar-app-worker.js', 'utf8'), context);

function request(url, method, mode) {
    let response;
    handlers.fetch({
        request: { url, method, mode },
        respondWith: function (result) { response = result; }
    });
    return response;
}

(async function () {
    let result = await request('https://example.org/staff-app/?calsys_app_view=rooms', 'GET', 'navigate');
    assert.strictEqual(await result.text(), 'live calendar');
    networkFails = true;
    result = await request('https://example.org/staff-app/', 'GET', 'navigate');
    assert.strictEqual(result.status, 503);
    assert.match(await result.text(), /Connect to the internet/);
    assert.strictEqual(result.headers.get('Cache-Control'), 'no-store');
    assert.strictEqual(request('https://example.org/staff-app/', 'POST', 'navigate'), undefined);
    assert.strictEqual(request('https://example.org/staff-app/other/', 'GET', 'navigate'), undefined);
    assert.strictEqual(request('https://example.org/staff-app/', 'GET', 'cors'), undefined);
    assert.strictEqual(calls, 2);
    console.log('Staff app service worker passed.');
}()).catch(function (error) {
    console.error(error);
    process.exitCode = 1;
});
