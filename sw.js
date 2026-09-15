/*
 * Kinderarbeit – Service Worker.
 *
 * Liegt bewusst im Wurzelverzeichnis und nicht unter assets/: der Geltungs-
 * bereich eines Service Workers reicht nur so weit wie sein eigener Ordner,
 * und gebraucht wird die ganze Anwendung.
 *
 * Er speichert nichts zwischen. Die Seiten kommen vom Server und aendern sich
 * mit jeder Bestaetigung – ein Zwischenspeicher wuerde hier veraltete
 * Kontostaende zeigen. Er ist ausschliesslich fuer Benachrichtigungen da.
 */

self.addEventListener('install', function () {
  // Sofort uebernehmen, damit eine neue Fassung nicht erst beim naechsten
  // vollstaendigen Schliessen aller Tabs aktiv wird.
  self.skipWaiting();
});

self.addEventListener('activate', function (event) {
  event.waitUntil(self.clients.claim());
});

self.addEventListener('push', function (event) {
  var daten = { title: 'Kinderarbeit', body: '', url: './?p=start', tag: 'kinderarbeit' };

  if (event.data) {
    try {
      var gelesen = event.data.json();
      daten.title = gelesen.title || daten.title;
      daten.body = gelesen.body || daten.body;
      daten.url = gelesen.url || daten.url;
      daten.tag = gelesen.tag || daten.tag;
    } catch (fehler) {
      daten.body = event.data.text();
    }
  }

  event.waitUntil(self.registration.showNotification(daten.title, {
    body: daten.body,
    icon: 'assets/favicon.svg',
    badge: 'assets/favicon.svg',
    tag: daten.tag,
    renotify: true,
    data: { url: daten.url }
  }));
});

self.addEventListener('notificationclick', function (event) {
  event.notification.close();

  var ziel = new URL((event.notification.data && event.notification.data.url) || './?p=start', self.location.href);

  event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (fenster) {
    // Ein bereits offenes Fenster der Anwendung wiederverwenden, statt jedes
    // Mal ein neues aufzumachen.
    for (var i = 0; i < fenster.length; i++) {
      var client = fenster[i];
      if (client.url.indexOf(self.registration.scope) === 0 && 'focus' in client) {
        if ('navigate' in client) client.navigate(ziel.href);
        return client.focus();
      }
    }
    return self.clients.openWindow(ziel.href);
  }));
});
