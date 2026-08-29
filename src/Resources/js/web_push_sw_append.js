/* nowo-pwa-web-push — kit default (service_worker.web_push: true) */
/* Hosts should send JSON: { title, body, icon?, badge?, url?, tag? }. */
const NOWO_PWA_PUSH_DEFAULTS = {
  title: '__NOWO_PWA_PUSH_TITLE__',
  icon: '__NOWO_PWA_PUSH_ICON__',
  badge: '__NOWO_PWA_PUSH_BADGE__',
  url: '__NOWO_PWA_PUSH_URL__',
  tag: '__NOWO_PWA_PUSH_TAG__',
};

self.addEventListener('push', (event) => {
  let data = {};
  try {
    data = event.data ? event.data.json() : {};
  } catch (error) {
    data = { body: event.data ? event.data.text() : '' };
  }

  const title = data.title || NOWO_PWA_PUSH_DEFAULTS.title || 'Notification';
  const options = {
    body: data.body || '',
    icon: data.icon || NOWO_PWA_PUSH_DEFAULTS.icon,
    badge: data.badge || NOWO_PWA_PUSH_DEFAULTS.badge,
    data: { url: data.url || NOWO_PWA_PUSH_DEFAULTS.url || '/' },
    renotify: true,
    tag: data.tag || NOWO_PWA_PUSH_DEFAULTS.tag || 'nowo-pwa',
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const targetUrl = nowoPwaResolveSameOriginNotificationUrl(
    (event.notification.data && event.notification.data.url) || NOWO_PWA_PUSH_DEFAULTS.url || '/'
  );

  event.waitUntil((async () => {
    const allClients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (const client of allClients) {
      if ('focus' in client) {
        await client.focus();
        if ('navigate' in client) {
          await client.navigate(targetUrl);
        }
        return;
      }
    }
    if (self.clients.openWindow) {
      await self.clients.openWindow(targetUrl);
    }
  })());
});

function nowoPwaResolveSameOriginNotificationUrl(rawUrl) {
  try {
    const parsed = new URL(rawUrl, self.location.origin);
    if (parsed.origin !== self.location.origin) {
      return '/';
    }
    return parsed.pathname + parsed.search + parsed.hash;
  } catch (error) {
    return '/';
  }
}
