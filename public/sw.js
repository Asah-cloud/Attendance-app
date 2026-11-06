// Shows staff messages as phone notifications, even when the app is closed or the user is logged out.
self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = { body: event.data ? event.data.text() : '' };
    }

    event.waitUntil(self.registration.showNotification(data.title || 'New message', {
        body: data.body || '',
        tag: data.tag,
        renotify: Boolean(data.tag),
        requireInteraction: Boolean(data.urgent),
        vibrate: data.urgent ? [300, 150, 300, 150, 300] : [200],
        icon: '/images/asah-apex-logo-512.png',
        badge: '/favicon-48.png',
        data: { url: data.url || '/dashboard' },
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = new URL(event.notification.data?.url || '/dashboard', self.location.origin).href;

    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
        const open = windows.find((client) => client.url.startsWith(self.location.origin));
        if (open) {
            open.navigate(url);
            return open.focus();
        }
        return self.clients.openWindow(url);
    }));
});
