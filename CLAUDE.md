# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

Kinderarbeit ist eine Familienanwendung für Aufgaben und Taschengeld, veröffentlicht
auf **kinderarbeit.example.de**. Eltern stellen Aufgaben mit Betrag ein, Kinder
haken sie ab, Eltern bestätigen einmal, dann wird gutgeschrieben.

**Alles ist auf Deutsch** – Oberfläche, Kommentare, Commit-Nachrichten, Dokumentation.
Das bitte beibehalten.

## Befehle

```bash
php bin/selftest.php                 # 148 Prüfungen der Rechenlogik, ohne Webserver
php bin/demo-data.php --force        # Beispielbestand zum Ausprobieren (löscht die DB!)
php bin/zugangslink.php Emilius      # Zugangslink erzeugen (Rettungsanker per SSH)
php bin/reset-pin.php Thies 4711     # PIN zurücksetzen, wenn niemand mehr reinkommt
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

**Abschottung.** `app/`, `data/`, `bin/` und `docs/` sind über `.htaccess` gesperrt;
zusätzlich beginnt jede PHP-Datei außerhalb des Einstiegspunkts mit
`defined('KINDERARBEIT') || exit;`. Beides beibehalten.

## Veröffentlichen auf manitu

Ein Push auf den Hauptbranch veröffentlicht über `.github/workflows/deploy.yml`.
Ausführlich in `docs/DEPLOY.md`. Die folgenden Punkte haben jeweils einen halben
Nachmittag gekostet und sind nirgends sonst dokumentiert:

* **Der SSH-Benutzer ist nicht der Kunden-Login.** manitu legt dafür einen eigenen
  an, etwa `ssh000000000` – zu finden im Kundenbereich unter „SSH-Benutzer". Der
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
* **`rsync` braucht `--omit-dir-times`.** Das Zielverzeichnis gehört `site000000000`,
  angemeldet wird als `ssh000000000`; den Zeitstempel darf nur der Eigentümer setzen.
  Ohne das Flag endet ein vollständig geglückter Transfer mit Exit-Code 23, und die
  folgenden Schritte laufen nicht mehr.
* **`data/` braucht 775.** Der Webserver läuft als `site000000000`, übertragen wird
  als `ssh000000000`; ohne Gruppenschreibrecht kann die Anwendung die Datenbank nicht
  anlegen. Der letzte Schritt des Workflows setzt das.
* **`DEPLOY_PATH` zeigt auf die Subdomain, niemals auf `web/`.** Dort liegen alle
  Websites des Pakets nebeneinander – `rsync --delete` würde sie löschen. Der Schritt
  „Zielverzeichnis prüfen" blockiert das; diese Prüfung nicht entfernen.

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
