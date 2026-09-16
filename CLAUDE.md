# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

Kinderarbeit ist eine Familienanwendung für Aufgaben und Taschengeld, veröffentlicht
auf **kinderarbeit.thiesreinhold.de**. Eltern stellen Aufgaben mit Betrag ein, Kinder
haken sie ab, Eltern bestätigen einmal, dann wird gutgeschrieben.

**Alles ist auf Deutsch** – Oberfläche, Kommentare, Commit-Nachrichten, Dokumentation.
Das bitte beibehalten.

## Commits

Autor und Committer sind immer **Thies Reinhold**
`<65960018+thiesitease@users.noreply.github.com>`, auch wenn Claude die Arbeit macht.
Keine `Co-Authored-By:`-Zeile, keine `Claude-Session:`-Zeile, kein „Generated with“-Hinweis
in der Nachricht.

Der Grund: GitHub verknüpft `noreply@anthropic.com` mit dem fremden Konto
github.com/claude und trägt es bei jeder solchen Zeile in die Contributor-Liste dieses
Repositorys ein. Die History wurde einmal davon bereinigt – sie soll es bleiben.

Auch nicht signieren: der Schlüssel einer Claude-Sitzung gehört nicht zu diesem
GitHub-Konto, die Commits stünden sonst als „Unverified“ da.

```bash
git config user.name "Thies Reinhold"
git config user.email "65960018+thiesitease@users.noreply.github.com"
git config commit.gpgsign false
```

## Befehle

```bash
php bin/selftest.php                 # 180 Prüfungen der Rechenlogik, ohne Webserver
php bin/demo-data.php --force        # Beispielbestand zum Ausprobieren (löscht die DB!)
php bin/zugangslink.php Emilius      # Zugangslink erzeugen (Rettungsanker per SSH)
php bin/reset-pin.php Thies 4711     # PIN zurücksetzen, wenn niemand mehr reinkommt
php bin/protokoll.php                # Ende von data/php-error.log anzeigen
php bin/reset-pin.php Bruno --start  # zurück auf die Start-PIN aus SEED_USERS
php bin/leeren.php                   # zeigt den Bestand, löscht nichts
php bin/leeren.php verlauf --ja      # Buchungen und Meldungen weg, Profile bleiben
php -S localhost:8080                # lokal ausprobieren
```

Es gibt kein Composer, kein Node, keinen Build-Schritt und keine Test-Bibliothek.
`bin/selftest.php` ist die Testsuite: eine Datei, ganzzahlige Vergleiche, eigene
temporäre Datenbank. Einzelne Fälle laufen nicht getrennt – die Datei ist schnell
genug, um immer ganz zu laufen.

## Architektur

**Konten sind ein Buchungsjournal, keine Salden.** `ledger` wird nur angehängt;
der Kontostand ist `SUM(amount_cents)`. Positive Beträge sind Gutschriften, negative
Abbuchungen. Nichts wird je überschrieben – eine zurückgenommene Bestätigung erzeugt
eine Gegenbuchung, kein `DELETE`. Wer einen Betrag „korrigieren" will, bucht dagegen.

**Beträge sind immer ganzzahlige Cent.** Nirgends Fließkomma. `Money::parse()` nimmt
Nutzereingaben („2,50", „2.50", „19,90 €"), `Money::format()` gibt deutsch formatiert
aus. Wer eine neue Betragseingabe baut, geht durch diese beiden.

**Die Monatsübersicht muss aufgehen.** In `Ledger::monthSummary()` gilt die Invariante

    earned + bonus + corrections − expenses − payouts = net

Die Kategorien in `ledger.category` decken alle Buchungen ab. Wer eine neue Kategorie
einführt, muss sie in `monthSummary()` **und** in den Kacheln der Views ergänzen,
sonst zeigen die Kacheln eine Summe, die nicht stimmt.

**Bestätigen ist eine Transaktion.** `Completions::approve()` setzt den Status per
`UPDATE ... WHERE id = :id AND status = 'pending'` und schreibt erst bei `rowCount() > 0`
die Gutschrift – beides in einer Transaktion. Diese `WHERE`-Bedingung ist der Schutz
davor, dass ein Doppelklick oder zwei gleichzeitig bestätigende Eltern doppelt buchen.
Nicht durch ein Lesen-dann-Schreiben ersetzen.

**Feste Ausgaben brauchen keinen Cron.** `Billing::run()` läuft beim ersten
Seitenaufruf des Tages mit (`settings.last_billing_run`) und holt auch zurückliegende
Monate nach. Gegen Doppelbuchung schützt `UNIQUE(expense_id, month)` in
`expense_bookings`, nicht eine Prüfung im PHP-Code.

**Zwei Wege hinein.** PIN (`Auth::attempt`) oder persönlicher Zugangslink
(`?z=<32 Hex>` → `Auth::attemptToken`). Beim Link wird der erzwungene PIN-Wechsel
übersprungen – sonst wäre der bequeme Zugang keiner – und sofort weitergeleitet,
damit der Token nicht in der Adresszeile stehen bleibt. Rechte hängen ausschließlich
an `users.role`, nie am Anmeldeweg.

**Angemeldet bleiben geht nicht über die Sitzung.** Deren Cookie lebt zwar
30 Tage, die Sitzungsdatei räumt PHP aber nach `session.gc_maxlifetime` weg –
voreingestellt 24 Minuten Untätigkeit –, und auf geteiltem Webhosting leeren
fremde Aufräumläufe dasselbe Verzeichnis mit. Deshalb `app/Remember.php`: ein
eigenes Cookie `selector:validator`, ein Jahr gültig, je Gerät ein Token.
Gesucht wird über den selector, verglichen der validator gegen seinen
SHA-256-Hash mit `hash_equals`. `Auth::restore()` läuft früh in `index.php` und
baut die Sitzung stillschweigend neu auf.

Das Cookie hält auch fest, **ob der Zugang über den Link kam** (`via_link`) –
sonst fragte die Anwendung beim nächsten Besuch doch noch nach der PIN und der
bequeme Zugang wäre keiner. `Users::createToken()` und `clearToken()` rufen
`Remember::forgetLinkDevices()`: ein zurückgezogener Link soll wirklich nicht
mehr gelten, auch nicht über ein Gerät, das damit angemeldet wurde. Wer die PIN
benutzt hat, bleibt drin.

`app_start_session()` legt die Sitzungen nach `data/sessions/` statt ins
gemeinsame Verzeichnis des Servers. Wer das tut, muss auch selbst aufräumen –
daher dort `session.gc_probability`. Das Verzeichnis ist in der `.gitignore`
und im `rsync`-Aufruf des Workflows ausgenommen, sonst löschte `--delete` es
bei jeder Veröffentlichung.

`is_https()` steht in `helpers.php` und nicht in der `bootstrap.php`: `base_url()`
und die Cookies brauchen es, und die Werkzeuge in `bin/` laden nur die Helfer.

**Routing ohne mod_rewrite.** Alles läuft über `?p=seite` in `index.php`. Links werden
mit `url()` gebaut. Bewusst so, damit die Anwendung auf jedem Webhosting läuft.

**Schema-Migrationen.** `Database::migrate()` legt Tabellen per
`CREATE TABLE IF NOT EXISTS` an. Für später hinzugekommene **Spalten** reicht das
nicht – dafür gibt es `Database::addColumn()`, das `PRAGMA table_info` prüft und
notfalls `ALTER TABLE` ausführt. Neue Spalten immer dort eintragen, sonst bricht
das nächste Update auf dem Server.

**Benachrichtigungen sind selbst gebaut.** `app/WebPush.php` macht Web-Push nach
RFC 8291 (Verschlüsselung) und RFC 8292 (VAPID) – ohne Bibliothek, weil es keinen
Composer gibt und openssl plus `hash_hkdf` genügen. Die Verschlüsselung ist gegen
den **Testvektor aus RFC 8291, Anhang A** geprüft; der Test steht in
`bin/selftest.php`. Wer dort etwas ändert, muss diesen Test bestehen – ein Fehler
fällt sonst erst auf, wenn niemand mehr Benachrichtigungen bekommt.

Verschickt wird **mitten im Seitenaufruf** und parallel (`curl_multi`), weil das
Hosting keine Hintergrundprozesse erlaubt. `Push::deliver()` wirft nie: eine
Bestätigung darf nicht daran scheitern, dass Google gerade nicht antwortet.
Antwortet der Push-Dienst mit 404 oder 410, ist das Abonnement endgültig weg und
wird gelöscht – sonst sammeln sich tote Einträge an, die jeden Versand bremsen.

Ein Abonnement gehört zu einem **Gerät**, nicht zu einer Person; der Schlüssel ist
`endpoint`. Meldet sich dort jemand anderes an, übernimmt `ON CONFLICT (endpoint)`
den Eintrag – deshalb meldet `assets/app.js` ein vorhandenes Abo einmal je Sitzung
nach. Ohne das bekäme auf dem Familien-Tablet noch das vorige Kind die Nachrichten.

Das **VAPID-Schlüsselpaar** entsteht einmal und liegt in `settings`. Ein neues Paar
macht alle bestehenden Abonnements ungültig – also nie neu erzeugen.

**`sw.js` gehört ins Wurzelverzeichnis.** Der Geltungsbereich eines Service Workers
reicht nur so weit wie sein eigener Ordner; unter `assets/` läge er außerhalb der
Anwendung. Er speichert bewusst nichts zwischen – die Seiten ändern sich mit jeder
Bestätigung, ein Zwischenspeicher zeigte veraltete Kontostände. Die `.htaccess`
nimmt ihn deshalb vom Zwischenspeichern aus.

**`[hidden]` braucht `!important`.** Die Browser-Regel `[hidden] { display: none }`
verliert gegen jede eigene Regel mit `display` – etwa `.row { display: flex }`.
In `assets/app.css` steht deshalb ganz bewusst `[hidden] { display: none !important }`.
Ohne das wären versteckte Elemente trotzdem zu sehen.

**Löschen gibt es nur auf der Kommandozeile.** `bin/leeren.php` ist die einzige
Stelle, an der Buchungen verschwinden – im Elternbereich gibt es bewusst keinen
Knopf dafür, einen Fingerbreit neben dem Taschengeld der Kinder. Das Werkzeug
legt vorher eine Sicherung in `data/` an (dort durch `.htaccess` gesperrt, in
`.gitignore`, vom `rsync` ausgenommen) und fragt nach, solange nicht `--ja`
dabeisteht.

Zwei Fallen, die dort schon eingebaut sind: Nach dem Leeren rückt der
`start_month` der festen Ausgaben auf den **nächsten** Monat – sonst holt
`Billing::run()` beim nächsten Seitenaufruf jeden vergangenen Monat nach und die
Abbuchungen wären sofort wieder da. Und die Datenbankdateien bekommen danach
`chmod 0664`, weil auf dem Server der Webserver unter einem anderen Benutzer
läuft als die Kommandozeile; im Werkszustand-Modus wird die Datei nur gelöscht
und vom Webserver selbst neu angelegt, damit sie ihm gehört.

**Abgeschaltete Absende-Knöpfe müssen wieder aufwachen.** `assets/app.js` schaltet
nach dem Abschicken jeden `button[type=submit]` ab, damit ein Doppelklick nicht
doppelt bucht. Wer danach im Browser **zurückgeht**, bekommt die Seite aus dem
Zurück-Cache genau so wieder, wie er sie verlassen hat – mit dem abgeschalteten
Knopf. Das Formular ist dann tot und es sieht aus, als hinge die Seite. Deshalb
gibt ein `pageshow`-Handler (`event.persisted`) alles wieder frei. Headless-Browser
nutzen den Zurück-Cache nicht, ein Test dafür schlägt also nicht an.

**Abschottung.** `app/`, `data/`, `bin/` und `docs/` sind über `.htaccess` gesperrt;
zusätzlich beginnt jede PHP-Datei außerhalb des Einstiegspunkts mit
`defined('KINDERARBEIT') || exit;`. Beides beibehalten.

## Veröffentlichen auf manitu

Ein Push auf den Hauptbranch veröffentlicht über `.github/workflows/deploy.yml`.
Ausführlich in `docs/DEPLOY.md`. Die folgenden Punkte haben jeweils einen halben
Nachmittag gekostet und sind nirgends sonst dokumentiert:

* **Der SSH-Benutzer ist nicht der Kunden-Login.** manitu legt dafür einen eigenen
  an, etwa `ssh300011006` – zu finden im Kundenbereich unter „SSH-Benutzer". Der
  Kunden-Login (`pete\thies`) funktioniert dort nicht.
* **Nur Schlüssel, kein Passwort.** Der öffentliche Schlüssel muss über den
  Kundenbereich hinterlegt werden; `ssh-copy-id` scheitert zwangsläufig, weil es
  selbst schon eine funktionierende Anmeldung bräuchte.
* **Der Server begrenzt Verbindungen.** Nach wenigen Verbindungen in kurzer Zeit
  verwirft eine Firewall weitere – was als `Connection timed out` erscheint, obwohl
  Sekunden vorher noch etwas durchkam. **Deshalb macht der Workflow nur drei
  SSH-Verbindungen pro Lauf.** Keine Wiederholungsschleifen, kein `ssh-keyscan`,
  keinen separaten Verbindungstest hinzufügen – das verschlimmert es. Aus demselben
  Grund ist `SSH_KNOWN_HOSTS` als Secret erforderlich.
* **`rsync` braucht `--omit-dir-times`.** Das Zielverzeichnis gehört `site100029489`,
  angemeldet wird als `ssh300011006`; den Zeitstempel darf nur der Eigentümer setzen.
  Ohne das Flag endet ein vollständig geglückter Transfer mit Exit-Code 23, und die
  folgenden Schritte laufen nicht mehr.
* **`data/` braucht 775.** Der Webserver läuft als `site100029489`, übertragen wird
  als `ssh300011006`; ohne Gruppenschreibrecht kann die Anwendung die Datenbank nicht
  anlegen. Der letzte Schritt des Workflows setzt das.
* **`DEPLOY_PATH` zeigt auf die Subdomain, niemals auf `web/`.** Dort liegen alle
  Websites des Pakets nebeneinander – `rsync --delete` würde sie löschen. Der Schritt
  „Zielverzeichnis prüfen" blockiert das; diese Prüfung nicht entfernen.

**Wartung ohne SSH-Sitzung.** `.github/workflows/wartung.yml` läuft nur von Hand
(Actions → Wartung → „Run workflow“) und führt über dieselben Secrets einen
Befehl auf dem Server aus – **eine** SSH-Verbindung pro Lauf, dieselbe
`concurrency`-Gruppe wie die Veröffentlichung, damit nie beides gleichzeitig
läuft. Zum Löschen muss im Feld „bestaetigen“ genau `ja` stehen.

Was dort bewusst **nicht** auswählbar ist: „alles löschen“ (macht die
Zugangslinks ungültig – gehört an eine Stelle, an der man tippt statt klickt)
und das Anzeigen der Zugangslinks (die stünden danach dauerhaft im Protokoll
des Laufs). Beides weiter von Hand über SSH.

Aus demselben Grund setzt „pin-zuruecksetzen“ **nur** die Start-PIN aus
`Database::SEED_USERS`. Die steht ohnehin im Quelltext; eine frei gewählte PIN
stünde dagegen hinterher dauerhaft im Protokoll des Laufs. Die Start-PINs
bestehen absichtlich aus lauter gleichen Ziffern und fallen deshalb durch
`validate_pin()` – `reset-pin.php --start` geht daran vorbei, setzt aber
`must_change_pin`, damit sie nicht liegen bleibt.

**Was die AGB von manitu für SSH-Benutzer verbieten** und was deshalb hier nicht
vorkommen darf: eigene `crontab`-Einträge (dafür gibt es das Cronjob-Feature im
Kundenbereich), dauerhafte oder Hintergrundprozesse, alles was auf einem Port lauscht,
Container, Rechteerweiterung. Auch die SSH-Erweiterung von Visual Studio Code ist
tabu – sie installiert dort dauerhaft laufende Server-Software.

## Unter Windows

Die Entwicklung läuft auf Windows mit PowerShell. Drei wiederkehrende Stolpersteine:

* PowerShell löst `~` bei fremden Programmen **nicht** auf – `ssh-keygen -f ~/.ssh/x`
  legt einen Ordner namens `~` im aktuellen Verzeichnis an. Immer `$HOME` verwenden.
* `ssh-copy-id` gibt es nicht.
* Das mitgelieferte `ssh-keyscan` ist zu alt für OpenSSH 9.9 auf dem Server
  (`unsupported KEX method`). Serverschlüssel stattdessen mit
  `ssh-keygen -F <host>` aus der eigenen `known_hosts` holen.

Die `.gitignore` schließt Schlüssel aus (`.ssh/`, ein Verzeichnis `~`, `id_*`,
`*_deploy`, `*.pem`) – genau für den Fall, dass `ssh-keygen` im Projektverzeichnis
landet.
