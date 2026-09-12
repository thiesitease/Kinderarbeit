# Kinderarbeit

Aufgaben, Taschengeld und Konto für die ganze Familie –
für **kinderarbeit.thiesreinhold.de**.

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
| Familie | Eltern | Zugangslinks, PINs, Sperren, Symbole und Farben |

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
https://kinderarbeit.thiesreinhold.de/?z=1d1a952f7a3c4e0b8f6d2a91c5e7b403
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

Wer einen Cronjob hat, kann zusätzlich täglich `bin/cron.php` aufrufen.

## Technik

* PHP 8.0 oder neuer, SQLite über PDO – **keine weiteren Abhängigkeiten**
* kein Build-Schritt, kein Composer, kein Node
* funktioniert vollständig ohne JavaScript (JS ist nur Komfort)
* helles und dunkles Design, für das Handy gebaut
* CSRF-Schutz an allen Formularen, PIN-Sperre nach fünf Fehlversuchen
* Zugangslinks mit 128-Bit-Token, jederzeit erneuerbar und zurückziehbar

```
index.php              Einstiegspunkt und Routing
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
php bin/cron.php            # bucht fällige feste Ausgaben (optional per Cron)
php bin/reset-pin.php Emilius 4711   # PIN zurücksetzen, wenn niemand mehr reinkommt
php bin/zugangslink.php             # alle Zugangslinks anzeigen
php bin/zugangslink.php Emilius     # neuen Zugangslink erzeugen
```

## Lokal ausprobieren

```bash
php -S localhost:8080
```

Dann `http://localhost:8080` im Browser öffnen.

Einrichtung auf dem Server: siehe [docs/DEPLOY.md](docs/DEPLOY.md).
