// Screenshots für docs/screenshots und docs/screenshots/en.
//
// Anmeldedaten kommen ausschliesslich aus Umgebungsvariablen, die DU vor dem
// Start setzt (DOKUVAULT_USER / DOKUVAULT_PASSWORD) - das Skript selbst
// enthaelt kein Passwort und tippt keins ein, das nicht von dir kommt.
//
// Aufruf:
//   DOKUVAULT_USER=admin DOKUVAULT_PASSWORD=password node docs/screenshot-tool/capture.mjs
//
// Optional: SPRACHEN=de oder SPRACHEN=en, um nur eine Sprache zu erzeugen.
//
// Nachher aufraeumen: Das Skript meldet sich je Sprache einmal an, und das
// steht danach im Aktivitaetsprotokoll - auf dem Protokoll-Screenshot selbst
// und in "Letzte Aktivitaeten" auf dem Admin-Dashboard. Wer die Bilder fuer
// die README erzeugt, raeumt die eigenen Anmeldungen hinterher weg, sonst
// zeigen beide Bilder das Werkzeug statt der Anwendung:
//
//   php artisan tinker --execute='Spatie\Activitylog\Models\Activity::where("event","anmeldung")->where("created_at",">=",now()->subHour())->delete();'

import puppeteer from 'puppeteer';
import { mkdir } from 'node:fs/promises';
import path from 'node:path';

const BASIS = process.env.DOKUVAULT_URL || 'http://localhost:8141';
const NUTZER = process.env.DOKUVAULT_USER;
const PASSWORT = process.env.DOKUVAULT_PASSWORD;
const SPRACHEN = (process.env.SPRACHEN || 'de,en').split(',').map((s) => s.trim());

if (!NUTZER || !PASSWORT) {
  console.error('DOKUVAULT_USER und DOKUVAULT_PASSWORD muessen gesetzt sein.');
  console.error('Beispiel: DOKUVAULT_USER=admin DOKUVAULT_PASSWORD=password node docs/screenshot-tool/capture.mjs');
  process.exit(1);
}

// Retina, wie die bisherigen Bilder (2880x1800 = 1440x900 bei deviceScaleFactor 2).
const VIEWPORT = { width: 1440, height: 900, deviceScaleFactor: 2 };

const KUNDE = 'mustermann';

// Ziel je Screenshot: Pfad relativ zur Basis-URL, Dateiname ohne Endung.
const SEITEN = [
  { datei: 'login', pfad: '/login', angemeldet: false },
  { datei: 'dashboard', pfad: `/${KUNDE}` },
  { datei: 'search', pfad: '/search?search=srv' },
  { datei: 'computers', pfad: `/${KUNDE}/computer` },
  { datei: 'ipam', pfad: `/${KUNDE}/ip-plan` },
  { datei: 'certificates', pfad: `/${KUNDE}/certificate` },
  { datei: 'wizard', pfad: `/${KUNDE}/assistent` },
  // scrollZu: Der Schrank-Editor beginnt mit dem Formular (Name, Ort, Notiz).
  // Interessant ist aber die Bestueckung darunter - ohne das Scrollen zeigte
  // das Bild hauptsaechlich Eingabefelder und vom Rack nur die Oberkante.
  { datei: 'rack', pfad: `/${KUNDE}/rack/1/edit`, scrollZu: '#bestueckung' },
  { datei: 'patchpanel', pfad: `/${KUNDE}/patchpanel/1/edit` },
  { datei: 'patchpanel-liste', pfad: `/${KUNDE}/patchpanel` },
  { datei: 'rackcatalog', pfad: '/admin/rackcatalogitem' },
  { datei: 'rackliste', pfad: `/${KUNDE}/rack` },
  { datei: 'server', pfad: `/${KUNDE}/server` },
  { datei: 'vms', pfad: `/${KUNDE}/vm` },
  { datei: 'loginwebsites', pfad: `/${KUNDE}/loginwebsite` },
  { datei: 'eol', pfad: '/admin/eol' },
  { datei: 'protokoll', pfad: '/admin/activity' },
  { datei: 'admin-dashboard', pfad: '/admin' },
];

const AGENT_PFAD = `/${KUNDE}/agent`;

async function anmelden(page) {
  await page.goto(`${BASIS}/login`, { waitUntil: 'networkidle0' });
  await page.type('input[name=username]', NUTZER, { delay: 10 });
  await page.type('input[name=password]', PASSWORT, { delay: 10 });
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle0' }),
    // Nicht "button[type=submit]" allein: Die Sprachumschaltung im Layout
    // (x-locale-switch) traegt fuer jede Sprache ein eigenes <form> mit
    // button[type=submit], versteckt hinter "hidden" und im DOM VOR dem
    // Anmeldeformular. page.click() traf damit den ersten - unsichtbaren -
    // Treffer, und Puppeteer meldete "Node is either not clickable or not an
    // Element". Auf das Anmeldeformular eingegrenzt.
    page.click('form[action$="/login"] button[type=submit]'),
  ]);
}

async function seiteEinstellen(page, sprache) {
  // /locale/{code} ist ein POST mit CSRF-Formular (siehe locale-switch.blade.php),
  // kein Link. Das vorhandene Formular im DOM absenden statt eine eigene
  // Anfrage nachzubauen - dann stimmt der CSRF-Schutz von selbst.
  const geklickt = await page.evaluate((code) => {
    const form = [...document.querySelectorAll('form')]
      .find((f) => f.action.includes(`/locale/${code}`));
    if (!form) return false;
    form.submit();
    return true;
  }, sprache);

  if (geklickt) {
    await page.waitForNavigation({ waitUntil: 'networkidle0' }).catch(() => {});
  }
}

/**
 * Screenshot der Auto-Dokumentation.
 *
 * Der interessante Zustand - Token und Script sichtbar - steht nur direkt
 * nach dem Anlegen in der Session (session('newToken'), einmalig). Ohne
 * eigenes Anlegen zeigte der Screenshot nur das leere Formular. Danach den
 * Token wieder widerrufen, damit ein zweiter Lauf des Skripts nicht einen
 * weiteren "Screenshot"-Token anhaeuft.
 */
async function autodocScreenshot(page, zielOrdner) {
  await page.goto(`${BASIS}${AGENT_PFAD}`, { waitUntil: 'networkidle0' });

  const formVorhanden = await page.evaluate(() => !!document.querySelector('form[action*="/agent"] select[name=site_id]'));
  if (!formVorhanden) {
    console.error('  FEHLER bei autodoc: kein Standort fuer den Kunden - Token-Formular fehlt.');
    return;
  }

  await page.type('input[name=name]', 'Screenshot Proxmox', { delay: 10 });
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle0' }),
    // Das Anlegen-Formular postet exakt auf ".../agent" (agent.store); das
    // Widerrufen-Formular je Token auf ".../agent/{id}" (agent.destroy) -
    // "endsWith" unterscheidet die beiden zuverlaessig.
    page.evaluate(() => document.querySelector('form[action$="/agent"] button[type=submit]').click()),
  ]);

  await new Promise((r) => setTimeout(r, 400));
  await page.screenshot({ path: path.join(zielOrdner, 'autodoc.png') });
  console.log('  ' + path.join(zielOrdner, 'autodoc.png'));

  // Aufraeumen: den gerade erzeugten Token wieder widerrufen, damit ein
  // zweiter Lauf des Skripts nicht einen weiteren "Screenshot"-Token
  // anhaeuft. Der eben erzeugte Token steht als erster in der Liste "Aktive
  // Token". Der Bestaetigungsdialog (confirm()) wird automatisch angenommen.
  page.once('dialog', (dialog) => dialog.accept());
  const geklickt = await page.evaluate(() => {
    const knopf = document.querySelector('form[action*="/agent/"] button[type=submit]');
    if (!knopf) return false;
    knopf.closest('form').requestSubmit(knopf);
    return true;
  });
  if (geklickt) {
    await page.waitForNavigation({ waitUntil: 'networkidle0' }).catch(() => {});
  }
}

async function screenshot(page, ziel, dateipfad) {
  await page.goto(`${BASIS}${ziel.pfad}`, { waitUntil: 'networkidle0', timeout: 30000 });
  // Kurze Ruhe fuer Livewire/Alpine-Animationen (Dropdowns, Fade-ins).
  await new Promise((r) => setTimeout(r, 400));

  // scrollZu: Manche Seite beginnt mit einem Formular, und das Sehenswerte
  // steht darunter - beim Schrank-Editor das Rack selbst. Ueber eine id im
  // Markup, damit es in beiden Sprachen dieselbe Stelle trifft.
  if (ziel.scrollZu) {
    const gefunden = await page.evaluate((wahl) => {
      const el = document.querySelector(wahl);
      if (!el) return false;
      el.scrollIntoView({ block: 'start' });
      return true;
    }, ziel.scrollZu);

    if (!gefunden) {
      console.error(`  WARNUNG bei ${ziel.datei}: ${ziel.scrollZu} nicht gefunden - Bild zeigt den Seitenanfang.`);
    }

    await new Promise((r) => setTimeout(r, 300));
  }
  await page.screenshot({ path: dateipfad, fullPage: false });
  console.log('  ' + dateipfad);
}

const browser = await puppeteer.launch({ headless: 'new' });
try {
  for (const sprache of SPRACHEN) {
    const zielOrdner = sprache === 'de'
      ? 'docs/screenshots'
      : `docs/screenshots/${sprache}`;
    await mkdir(zielOrdner, { recursive: true });

    console.log(`\n=== ${sprache} ===`);

    // Eigener, isolierter Kontext je Sprache statt browser.newPage(): Ohne
    // Trennung teilen sich alle Seiten eines Browsers ihre Cookies. Der
    // "de"-Durchlauf blieb angemeldet, und /login leitet einen angemeldeten
    // Benutzer ueber die guest-Middleware weiter - der "en"-Durchlauf fand
    // dann kein Formular mehr vor ("No element found for selector:
    // input[name=username]"), weil er bereits eingeloggt war.
    const context = await browser.createBrowserContext();
    const page = await context.newPage();
    await page.setViewport(VIEWPORT);

    // Die Login-Seite zuerst laden: Nur dort steht das Formular, ueber das
    // sich die Sprache umschalten laesst (POST mit CSRF, siehe
    // locale-switch.blade.php) - auf einer leeren Seite gibt es das Formular
    // noch nicht.
    const loginSeite = SEITEN.find((s) => s.angemeldet === false);
    await page.goto(`${BASIS}${loginSeite.pfad}`, { waitUntil: 'networkidle0' });
    await seiteEinstellen(page, sprache);
    await screenshot(page, loginSeite, path.join(zielOrdner, `${loginSeite.datei}.png`));

    await anmelden(page);

    for (const seite of SEITEN) {
      if (seite.angemeldet === false) continue;
      try {
        await screenshot(page, seite, path.join(zielOrdner, `${seite.datei}.png`));
      } catch (fehler) {
        console.error(`  FEHLER bei ${seite.datei}: ${fehler.message}`);
      }
    }

    try {
      await autodocScreenshot(page, zielOrdner);
    } catch (fehler) {
      console.error(`  FEHLER bei autodoc: ${fehler.message}`);
    }

    await context.close();
  }
} finally {
  await browser.close();
}

console.log('\nFertig. Bitte pruefen und dann committen.');
