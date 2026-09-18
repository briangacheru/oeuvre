// Service worker for Web Push, shared by both interfaces. Registered from
// the project root (see assets/js/push-notifications.js) so its scope
// ('/') covers both '/' (writer) and '/sudo/' (admin) - a service worker
// registered from inside /sudo/ could only ever control /sudo/*.
//
// Deliberately minimal: this app already has its own in-tab notification
// system (see the Notification.requestPermission() calls in navi.php and
// chat.php) for while a tab is open. This worker only adds what that
// can't do - a notification while no tab is open at all - so it doesn't
// duplicate any of that logic.

self.addEventListener('install', function (event) {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', function (event) {
    if (!event.data) return;

    let payload;
    try {
        payload = event.data.json();
    } catch (e) {
        payload = { title: 'iTasker', body: event.data.text() };
    }

    const title = payload.title || 'iTasker';
    const options = {
        body: payload.body || '',
        icon: '/assets/img/favicons/android-chrome-192x192.png',
        badge: '/assets/img/favicons/favicon-32x32.png',
        data: { url: payload.url || '/' },
        tag: payload.tag || undefined,
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    const url = (event.notification.data && event.notification.data.url) || '/';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            for (const client of clientList) {
                if (client.url === url && 'focus' in client) {
                    return client.focus();
                }
            }
            if (self.clients.openWindow) {
                return self.clients.openWindow(url);
            }
        })
    );
});
