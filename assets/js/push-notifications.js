// Web Push opt-in, shared by both interfaces. Registers push-sw.js at the
// project root (so its scope covers both '/' and '/sudo/'), asks for
// Notification permission, subscribes with the VAPID public key, and saves
// the subscription via save-push-subscription.php. Include from a page
// that already has a CSRF token input (name="csrf_token") on it, and a
// button/link with id="pushOptInBtn" for the user to opt in.
(function () {
    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    function csrfToken() {
        const el = document.querySelector('[name="csrf_token"]');
        return el ? el.value : '';
    }

    // Path depends on which interface the current page is in - '/sudo/'
    // pages need '../push-sw.js' and '../save-push-subscription.php';
    // writer-root pages use the plain relative form. Detected from the
    // current path rather than hardcoded per include site.
    const inSudo = window.location.pathname.indexOf('/sudo/') !== -1;
    const prefix = inSudo ? '../' : '';

    // save-push-subscription.php can't reliably tell writer and admin
    // sessions apart by guessing from which cookie happens to be present
    // (a browser can legitimately hold BOTH an admin and a writer session
    // cookie at once - e.g. testing both interfaces) - the client already
    // knows which interface it's on, so it says so explicitly instead.
    function subscribeUrl() {
        return prefix + 'save-push-subscription?iface=' + (inSudo ? 'admin' : 'writer')
            + '&csrf_token=' + encodeURIComponent(csrfToken());
    }

    window.iTaskerPush = {
        isSupported: function () {
            return 'serviceWorker' in navigator && 'PushManager' in window;
        },

        subscribe: function () {
            if (!this.isSupported()) {
                return Promise.reject(new Error('Push notifications are not supported in this browser.'));
            }

            const registerOptions = inSudo ? { scope: prefix } : {};
            return navigator.serviceWorker.register(prefix + 'push-sw.js', registerOptions)
                .then(function (registration) {
                    return Promise.all([registration, Notification.requestPermission()]);
                })
                .then(function ([registration, permission]) {
                    if (permission !== 'granted') {
                        throw new Error('Notification permission was not granted.');
                    }
                    return Promise.all([registration, fetch(prefix + 'vapid-public-key').then(function (r) { return r.json(); })]);
                })
                .then(function ([registration, keyData]) {
                    if (!keyData.publicKey) {
                        throw new Error('Push notifications are not configured on the server yet.');
                    }
                    return registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(keyData.publicKey),
                    });
                })
                .then(function (subscription) {
                    return fetch(subscribeUrl(), {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(subscription.toJSON()),
                    });
                })
                .then(function (r) { return r.json(); });
        },

        unsubscribe: function () {
            if (!this.isSupported()) return Promise.resolve();
            return navigator.serviceWorker.getRegistration().then(function (registration) {
                if (!registration) return;
                return registration.pushManager.getSubscription().then(function (subscription) {
                    if (!subscription) return;
                    const endpoint = subscription.endpoint;
                    return subscription.unsubscribe().then(function () {
                        return fetch(subscribeUrl(), {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: 'unsubscribe', endpoint: endpoint }),
                        });
                    });
                });
            });
        },
    };

    document.addEventListener('DOMContentLoaded', function () {
        const btn = document.getElementById('pushOptInBtn');
        if (!btn) return;

        if (!window.iTaskerPush.isSupported()) {
            btn.classList.add('d-none');
            return;
        }

        btn.addEventListener('click', function () {
            btn.disabled = true;
            window.iTaskerPush.subscribe()
                .then(function (data) {
                    if (data.success) {
                        if (typeof showToast === 'function') showToast('Push notifications enabled.', 'success');
                        btn.classList.add('d-none');
                    } else if (typeof showToast === 'function') {
                        showToast(data.message || 'Could not enable push notifications.', 'error');
                    }
                })
                .catch(function (err) {
                    if (typeof showToast === 'function') showToast(err.message || 'Could not enable push notifications.', 'error');
                })
                .finally(function () { btn.disabled = false; });
        });
    });
})();
