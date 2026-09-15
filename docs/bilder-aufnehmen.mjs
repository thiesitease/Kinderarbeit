/*
 * Nimmt die Bilder für die README auf.
 *
 * Gehört NICHT zur Anwendung – die braucht weiterhin kein Node und keinen
 * Build-Schritt. Dieses Skript ist ein Werkzeug für die Dokumentation und
 * läuft nur, wenn man es ausdrücklich aufruft:
 *
 *     php bin/demo-data.php --force
 *     php -S 127.0.0.1:8080 &
 *     node docs/bilder-aufnehmen.mjs
 *
 * Voraussetzung ist Playwright (npm i -D playwright). Steckt es woanders,
 * die import-Zeile anpassen.
 */

import { chromium } from 'playwright';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const BASE = process.env.BILDER_BASE ?? 'http://127.0.0.1:8080';
const OUT  = process.env.BILDER_OUT ?? join(dirname(fileURLToPath(import.meta.url)), 'bilder');

// Headless-Chromium kann sich bei keinem echten Push-Dienst anmelden.
// Ersetzt wird genau diese Grenze; die Oberfläche darüber ist die echte.
const PUSH_STUB = `
  let erlaubnis = 'default';
  Object.defineProperty(Notification, 'permission', { configurable: true, get: () => erlaubnis });
  Notification.requestPermission = () => { erlaubnis = 'granted'; return Promise.resolve('granted'); };
  const abo = () => ({ endpoint: 'https://fcm.googleapis.com/fcm/send/demo',
    toJSON: () => ({ endpoint: 'https://fcm.googleapis.com/fcm/send/demo', keys: {
      p256dh: 'BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcxaOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4',
      auth: 'BTBZMqHH6r4Tts7J_aSIgg' } }), unsubscribe: () => Promise.resolve(true) });
  PushManager.prototype.subscribe = () => Promise.resolve(abo());
  PushManager.prototype.getSubscription = () => Promise.resolve(null);
`;

const browser = await chromium.launch();

const neu = async ({ breit = false, dunkel = false } = {}) => {
  const c = await browser.newContext({
    viewport: breit ? { width: 1000, height: 780 } : { width: 400, height: 860 },
    deviceScaleFactor: 2,
    colorScheme: dunkel ? 'dark' : 'light',
    reducedMotion: 'reduce',
  });
  await c.addInitScript(PUSH_STUB);
  return c;
};

const anmelden = async (page, id, pin) => {
  await page.goto(`${BASE}/?p=login&user=${id}`, { waitUntil: 'networkidle' });
  await page.fill('#pin', pin);
  await page.click('button[type=submit]');
  await page.waitForLoadState('networkidle');
  // Die "Hallo X!"-Meldung wird beim Anzeigen verbraucht. Noch einmal laden,
  // damit sie auf dem Bild nicht alles nach unten schiebt.
  await page.reload({ waitUntil: 'networkidle' });
};

// Kopfzeile und Navigation kleben oben - ein Element, das genau an den oberen
// Rand gescrollt wird, verschwindet darunter. Deshalb ein Stück Vorlauf.
const KOPF = 122;

const schuss = async (page, name, { zu = null, warte = 400 } = {}) => {
  if (zu) {
    const el = page.locator(zu).first();
    if (await el.count()) {
      await el.evaluate((e, kopf) => {
        const y = e.getBoundingClientRect().top + window.scrollY - kopf;
        window.scrollTo({ top: Math.max(0, y), behavior: 'instant' });
      }, KOPF);
    }
  }
  await page.waitForTimeout(warte);
  await page.screenshot({ path: `${OUT}/${name}.png` });
  console.log('  ✓', name);
};

console.log('Anmeldung');
{
  const c = await neu(); const p = await c.newPage();
  await p.goto(`${BASE}/?p=login`, { waitUntil: 'networkidle' });
  await schuss(p, '01-anmeldung');
  await p.goto(`${BASE}/?p=login&user=1`, { waitUntil: 'networkidle' });
  await p.fill('#pin', '77');
  await schuss(p, '02-pin');
  await c.close();
}

console.log('Kinderbereich');
{
  const c = await neu(); const p = await c.newPage();
  await anmelden(p, 1, '7788');
  await schuss(p, '03-kind-aufgaben');

  // Das Kind erinnert Mama oder Papa an die offenen Meldungen.
  await schuss(p, '04-kind-bescheid', { zu: '.card:has(.btn--whatsapp)' });

  await schuss(p, '05-kind-monat', { zu: '.stats' });

  await p.goto(`${BASE}/?p=kind-konto`, { waitUntil: 'networkidle' });
  await schuss(p, '06-kind-konto', { zu: '.section:has(.entry), .list' });

  await p.goto(`${BASE}/?p=kind-verlauf`, { waitUntil: 'networkidle' });
  await schuss(p, '07-kind-verlauf');

  await p.goto(`${BASE}/?p=kind`, { waitUntil: 'networkidle' });
  await schuss(p, '08-benachrichtigungen', { zu: '[data-push]' });
  await c.close();
}

console.log('Elternbereich');
{
  const c = await neu(); const p = await c.newPage();
  await anmelden(p, 5, '8642');
  await schuss(p, '09-eltern-wiedervorlage');
  await schuss(p, '10-eltern-konten', { zu: '.section:has(.grid-2)' });

  // Ein Kind mit hinterlegter Handynummer, damit danach auch das
  // WhatsApp-Angebot zu sehen ist.
  const erste = p.locator('.review:has-text("Emilius") .btn--done').first();
  if (await erste.count()) { await erste.click(); await p.waitForLoadState('networkidle'); }
  await schuss(p, '11-eltern-bestaetigt');

  await p.goto(`${BASE}/?p=aufgaben`, { waitUntil: 'networkidle' });
  await schuss(p, '12-eltern-aufgaben');

  await p.goto(`${BASE}/?p=aufgabe-form`, { waitUntil: 'networkidle' });
  await p.fill('#title', 'Fahrrad putzen');
  await p.fill('#amount', '2,50');
  await schuss(p, '13-aufgabe-anlegen');

  await p.goto(`${BASE}/?p=kind-detail&id=1`, { waitUntil: 'networkidle' });
  await schuss(p, '14-eltern-kind-detail');

  await p.goto(`${BASE}/?p=buchung`, { waitUntil: 'networkidle' });
  await schuss(p, '15-buchung');

  await p.goto(`${BASE}/?p=familie`, { waitUntil: 'networkidle' });
  await schuss(p, '16-familie', { zu: '.card:has(input[id^="link-"]) .row' });
  await c.close();
}

// Tabellen brauchen Platz - bei Handybreite wäre die Hälfte abgeschnitten.
console.log('Breite Ansichten');
{
  const c = await neu({ breit: true }); const p = await c.newPage();
  await anmelden(p, 5, '8642');

  await p.goto(`${BASE}/?p=kinder`, { waitUntil: 'networkidle' });
  await schuss(p, '17-eltern-kinder');

  await p.goto(`${BASE}/?p=verlauf`, { waitUntil: 'networkidle' });
  await schuss(p, '18-eltern-verlauf');

  await p.goto(`${BASE}/?p=ausgaben`, { waitUntil: 'networkidle' });
  await schuss(p, '19-eltern-ausgaben');
  await c.close();
}

console.log('Dunkles Design');
{
  const c = await neu({ dunkel: true }); const p = await c.newPage();
  await anmelden(p, 3, '9182');
  await schuss(p, '20-dunkel-kind');
  await c.close();

  const c2 = await neu({ dunkel: true }); const p2 = await c2.newPage();
  await anmelden(p2, 5, '8642');
  await schuss(p2, '21-dunkel-eltern');
  await c2.close();
}

await browser.close();
console.log('fertig');
