# Kinderarbeit

Aufgaben, Taschengeld und Konto für die ganze Familie –
für **kinderarbeit.example.de**.

Eltern stellen Aufgaben mit einem Betrag ein, die Kinder haken sie ab,
die Eltern bestätigen sie einmal, und erst dann wird der Betrag dem Konto
des Kindes gutgeschrieben. Dazu kommen regelmäßige Ausgaben, eine
vollständige Historie und der Restbetrag pro Monat.

## Überblick

| Bereich | Wer | Was |
|---|---|---|
| Anmeldung | alle | Profil antippen und PIN eingeben – oder persönlichen Zugangslink öffnen |
| Aufgaben | Kinder | offene Aufgaben sehen und als erledigt melden |
| Mein Konto | Kinder | Guthaben, Monatsübersicht, alle Buchungen |
| Verlauf | Kinder | eigene Meldungen mit Status |
| Übersicht | Eltern | **Wiedervorlage**: melden bestätigen oder ablehnen |
| Aufgaben | Eltern | Aufgaben anlegen, Betrag festlegen, zuweisen, pausieren |
| Kinder | Eltern | Konten im Vergleich, Detailseite je Kind |
| Ausgaben | Eltern | regelmäßige monatliche Ausgaben je Kind |
| Verlauf | Eltern | alle Buchungen, filterbar nach Kind und Monat |
| Familie | Eltern | Zugangslinks, PINs, Sperren, Symbole, Farben und angemeldete Geräte |
| Benachrichtigungen | alle | pro Gerät ein- und ausschaltbar, direkt auf der Startseite |

## Die Familie

Beim ersten Start werden fünf Profile angelegt:

| Profil | Rolle | Start-PIN |
|---|---|---|
| Emilius | Kind | `1111` |
| Julius | Kind | `2222` |
| Bruno | Kind | `3333` |
| Birgitta | Eltern | `4444` |
| Thies | Eltern | `5555` |

Beim ersten Anmelden muss jede und jeder eine eigene PIN setzen –
die Start-PINs funktionieren also genau einmal.

## Persönliche Zugangslinks

Wer keine PIN tippen mag, bekommt von den Eltern einen eigenen Link:

```
https://kinderarbeit.example.de/?z=1d1a952f7a3c4e0b8f6d2a91c5e7b403
```

Die Eltern erzeugen ihn unter **Familie**, kopieren ihn mit einem Klick oder
schicken ihn direkt über WhatsApp. Wer den Link öffnet, ist sofort angemeldet
und wird nicht nach der PIN gefragt; der Token verschwindet dabei aus der
Adresszeile.

* Der Token ist 128 Bit lang und damit nicht zu erraten.
* Ein Link gilt, bis er neu erzeugt oder zurückgezogen wird – er läuft nicht ab.
* „Neu erzeugen“ macht den bisherigen Link sofort ungültig. Das ist der Weg,
  wenn ein Link in falsche Hände geraten ist.
* Die Rechte bleiben dieselben: ein Kind, das über seinen Link hereinkommt,
  sieht weiterhin nur den eigenen Bereich.
* Wer den Link eines Elternteils hat, hat Zugriff auf den gesamten
  Elternbereich – solche Links also nur direkt an Birgitta oder Thies schicken.
* Die Anwendung merkt sich, wann ein Link zuletzt benutzt wurde.

Solange jemand ausschließlich den Link nutzt, bleibt die Start-PIN gültig.
Deshalb lohnt es sich, unter **Familie** trotzdem einmal eine eigene PIN zu setzen.

## WhatsApp

Ist im Profil eine Handynummer hinterlegt (unter **Familie**), erscheinen an drei
Stellen Knöpfe, die WhatsApp mit fertigem Text öffnen:

* Ein Kind sieht unter seinen offenen Meldungen „Bescheid sagen" je Elternteil –
  der Text nennt alle wartenden Aufgaben und die Summe
* Direkt nach dem Bestätigen fragt die Elternübersicht einmalig, ob das Kind
  Bescheid bekommen soll – mit Betrag und neuem Guthaben im Text
* Auf der Detailseite eines Kindes steht ein Knopf für eine Nachricht zwischendurch

**Verschickt wird nichts von allein.** Die Knöpfe öffnen WhatsApp mit vorbereitetem
Text; abgeschickt wird von Hand. Automatisch versendet nur die
WhatsApp-Business-API, und die verlangt ein verifiziertes Unternehmenskonto, eine
eigene Rufnummer und genehmigte Textvorlagen – für eine Familie unverhältnismäßig.

## Benachrichtigungen

Wer eingeschaltet hat, bekommt eine Meldung auf Handy oder Rechner – auch wenn
die Seite geschlossen ist:

| Anlass | geht an |
|---|---|
| Kind meldet eine Aufgabe als erledigt | beide Eltern |
| Eltern bestätigen | das Kind, mit Betrag und neuem Guthaben |
| Eltern lehnen ab oder nehmen zurück | das Kind |
| Eltern legen eine neue Aufgabe an | alle Kinder, bei Zuweisung nur dieses |
| Eltern buchen von Hand (Bonus, Auszahlung, Korrektur) | das Kind |

Eingeschaltet wird auf der eigenen Startseite, **auf jedem Gerät einzeln** –
das Handy weiß nichts vom Rechner. Unter **Familie** sehen die Eltern, wer wie
viele Geräte angemeldet hat, und können sie abmelden.

Kommt die Benachrichtigung nach dem Bestätigen beim Kind an, entfällt das
WhatsApp-Angebot auf der Elternübersicht – zweimal dasselbe braucht niemand.

**Auf dem iPhone** gibt es Benachrichtigungen nur, wenn die Seite über „Teilen →
Zum Home-Bildschirm" als App gespeichert ist. Die Anwendung weist darauf hin,
wenn sie erkennt, dass genau das fehlt.

Technisch ist das Web-Push nach RFC 8291 und RFC 8292, selbst geschrieben in
`app/WebPush.php` – das Hosting hat keinen Composer, und PHP bringt mit openssl
und `hash_hkdf` alles Nötige mit. Die Verschlüsselung ist gegen den Testvektor
aus RFC 8291 geprüft; der Test steht in `bin/selftest.php`. Verschickt wird
mitten im Seitenaufruf und parallel an alle Geräte, weil das Hosting keine
Hintergrundprozesse erlaubt. Scheitert eine Zustellung, wird sie geloggt – eine
Bestätigung scheitert daran nie. Meldet der Push-Dienst 404 oder 410, ist das
Abonnement endgültig weg und wird gelöscht.

## Wie das Geld gerechnet wird

Jedes Kind hat ein Buchungsjournal. Positive Beträge sind Gutschriften,
negative Abbuchungen; der Kontostand ist immer die Summe aller Buchungen.
Nichts wird überschrieben, deshalb bleibt jede Änderung nachvollziehbar.

Beträge werden als ganzzahlige **Cent** gespeichert – so entstehen keine
Rundungsfehler.

Die Monatsübersicht geht immer auf:

```
verdient + Bonus + Korrekturen − feste Ausgaben − Auszahlungen = Rest im Monat
```

Das **Guthaben** ist der tatsächliche Kontostand über alle Monate hinweg,
der **Rest im Monat** die Bilanz des gewählten Monats.

## Regelmäßige Ausgaben

Eine feste Ausgabe (zum Beispiel „Beitrag Fitnessstudio“, 19,90 € am 1.)
wird automatisch abgebucht. Weil auf einfachem Webhosting nicht immer ein
Cronjob zur Verfügung steht, prüft die Anwendung das beim ersten
Seitenaufruf des Tages und holt auch zurückliegende Monate nach.
Ein eindeutiger Schlüssel über Ausgabe und Monat schließt Doppelbuchungen aus.

Ein Cronjob wird dafür **nicht gebraucht**. Wer trotzdem einen will, nutzt das
Cronjob-Feature des Hosters – eigene `crontab`-Einträge sind bei manitu
untersagt (siehe [docs/DEPLOY.md](docs/DEPLOY.md)).

## Technik

* PHP 8.0 oder neuer, SQLite über PDO – **keine weiteren Abhängigkeiten**
* kein Build-Schritt, kein Composer, kein Node
* funktioniert vollständig ohne JavaScript (JS ist nur Komfort)
* helles und dunkles Design, für das Handy gebaut
* CSRF-Schutz an allen Formularen, PIN-Sperre nach fünf Fehlversuchen
* Zugangslinks mit 128-Bit-Token, jederzeit erneuerbar und zurückziehbar
* Web-Push ohne fremde Bibliothek (RFC 8291/8292), gegen den Testvektor geprüft

```
index.php              Einstiegspunkt und Routing
sw.js                  Service Worker (nur für Benachrichtigungen)
app/                   Anwendungscode (nicht öffentlich erreichbar)
  Controller/          eine Datei je Bereich
  Repo/                Datenbankzugriff
  views/               Templates
assets/                CSS, JavaScript, Symbole
data/                  SQLite-Datenbank (wird beim ersten Start angelegt)
bin/                   Kommandozeilenwerkzeuge
docs/DEPLOY.md         Anleitung für die Einrichtung auf dem Server
```

## Werkzeuge für die Kommandozeile

```bash
php bin/selftest.php        # prüft die Rechenlogik (ohne Webserver)
php bin/demo-data.php       # legt einen Beispielbestand zum Ausprobieren an
php bin/cron.php            # bucht fällige feste Ausgaben (optional, siehe oben)
php bin/reset-pin.php Emilius 4711   # PIN zurücksetzen, wenn niemand mehr reinkommt
php bin/zugangslink.php             # alle Zugangslinks anzeigen
php bin/zugangslink.php Emilius     # neuen Zugangslink erzeugen
```

## Lokal ausprobieren

```bash
php -S localhost:8080
```

Dann `http://localhost:8080` im Browser öffnen.

## Veröffentlichen

Ein Push auf den Hauptbranch veröffentlicht automatisch auf
kinderarbeit.example.de – sobald die vier Secrets hinterlegt sind.
Der Ablauf prüft erst die Logik, überträgt dann per `rsync` (die Datenbank
bleibt dabei unangetastet) und schaut zum Schluss von außen nach, ob die
Seite läuft und `data/` und `app/` abgeschottet sind.

Einrichtung und alle anderen Wege: siehe [docs/DEPLOY.md](docs/DEPLOY.md).
