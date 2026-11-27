(function () {
    'use strict';
    var worker = document.querySelector('meta[name="calsys-app-worker"]');
    var scope = document.querySelector('meta[name="calsys-app-scope"]');
    var status = document.querySelector('[data-calsys-app-status]');
    function showStatus(message) {
        if (status) {
            status.textContent = message;
            status.hidden = false;
        }
    }
    if (worker && scope && !window.isSecureContext) {
        showStatus(status.dataset.insecure);
    } else if (worker && scope && 'serviceWorker' in navigator) {
        navigator.serviceWorker.register(worker.content, { scope: scope.content }).catch(function (error) {
            console.error('CommonCalendar: unable to register the app service worker.', error);
            showStatus(status.dataset.failed);
        });
    }
}());
