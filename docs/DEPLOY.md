# Einrichtung auf dem Server

Für **kinderarbeit.thiesreinhold.de** auf einem Webspace oder Server bei manitu.

Die Anwendung braucht nichts als PHP und SQLite: kein Composer, kein Node,
keinen Build-Schritt und keine MySQL-Datenbank. Hochladen genügt.

---

## 1. Voraussetzungen prüfen

| Was | Anforderung |
|---|---|
| PHP | 8.0 oder neuer (empfohlen: 8.2+) |
| PHP-Erweiterung | `pdo_sqlite` (bei manitu standardmäßig aktiv) |
| Webserver | Apache mit `.htaccess` (bei manitu Standard) |
| Schreibrechte | auf dem Verzeichnis `data/` |

Per SSH schnell geprüft:

```bash
php -v
php -m | grep -i sqlite      # muss pdo_sqlite ausgeben
```

---

## 2. Subdomain anlegen

Im manitu-Kundenbereich die Subdomain `kinderarbeit.thiesreinhold.de` anlegen
und auf ein eigenes Verzeichnis zeigen lassen, zum Beispiel:

```
/htdocs/kinderarbeit/
```

Danach im selben Schritt **HTTPS aktivieren** (Let's Encrypt). Die mitgelieferte
`.htaccess` leitet HTTP automatisch auf HTTPS um, sobald ein Zertifikat vorhanden ist.

---

## 3. Dateien hochladen

### Variante A – per SSH (empfohlen)

```bash
# auf dem eigenen Rechner, im Projektverzeichnis
rsync -avz --delete \
      --exclude '.git' \
      --exclude 'data/*.sqlite*' \
      --exclude 'data/*.log' \
      ./ BENUTZER@SERVER:/htdocs/kinderarbeit/
```

Oder direkt auf dem Server aus Git holen:

```bash
cd /htdocs
git clone https://github.com/thiesitease/Kinderarbeit.git kinderarbeit
```

### Variante B – per FTP

Den gesamten Inhalt des Projektverzeichnisses in den Subdomain-Ordner
hochladen – **einschließlich der Datei `.htaccess`** (viele FTP-Programme
blenden Dateien mit führendem Punkt aus; entsprechende Option einschalten).

Das Verzeichnis `data/` darf leer sein, muss aber existieren.

---

## 4. Schreibrechte setzen

```bash
cd /htdocs/kinderarbeit
mkdir -p data
chmod 775 data
```

Die Datenbank legt sich beim ersten Aufruf selbst an.

---

## 5. Erster Aufruf

`https://kinderarbeit.thiesreinhold.de` öffnen. Es erscheint die Profilauswahl
mit Emilius, Julius, Bruno, Birgitta und Thies.

Start-PINs – gelten nur für die erste Anmeldung, danach fragt die Anwendung
sofort nach einer eigenen PIN:

| Profil | PIN |
|---|---|
| Emilius | `1111` |
| Julius | `2222` |
| Bruno | `3333` |
| Birgitta | `4444` |
| Thies | `5555` |

> Am besten meldet ihr euch direkt nacheinander mit allen fünf Profilen an und
> setzt die eigenen PINs. Solange das nicht passiert ist, weist die
> Elternübersicht oben darauf hin.

### Zugangslinks verschicken

Als Thies oder Birgitta unter **Familie** für jedes Kind einen persönlichen
Zugangslink erzeugen und per WhatsApp verschicken. Wer den Link öffnet, ist
sofort angemeldet – ohne PIN. Der Link gilt, bis er neu erzeugt oder
zurückgezogen wird.

Notfalls geht das auch per SSH:

```bash
php bin/zugangslink.php             # alle Links anzeigen
php bin/zugangslink.php Emilius     # neuen Link erzeugen
```

---

## 6. Prüfen, dass nichts nach außen sichtbar ist

Diese Adressen müssen einen Fehler liefern (404 oder 403), keine Inhalte:

```bash
curl -sI https://kinderarbeit.thiesreinhold.de/data/kinderarbeit.sqlite | head -1
curl -sI https://kinderarbeit.thiesreinhold.de/app/Database.php          | head -1
curl -sI https://kinderarbeit.thiesreinhold.de/data/                     | head -1
```

Falls dort etwas ausgeliefert wird, greift die `.htaccess` nicht – dann prüfen,
ob sie tatsächlich hochgeladen wurde und ob `AllowOverride` aktiv ist.

---

## 7. Optional: Cronjob für feste Ausgaben

Die Anwendung bucht fällige Ausgaben beim ersten Seitenaufruf des Tages selbst ab.
Wer auf Nummer sicher gehen will, richtet zusätzlich einen täglichen Cronjob ein:

```
0 6 * * * /usr/bin/php /htdocs/kinderarbeit/bin/cron.php >/dev/null 2>&1
```

---

## Datensicherung

Die gesamte Anwendung steckt in einer einzigen Datei: `data/kinderarbeit.sqlite`.

```bash
# Sicherung ziehen (konsistent, auch im laufenden Betrieb)
sqlite3 data/kinderarbeit.sqlite ".backup 'sicherung-$(date +%F).sqlite'"

# oder einfach herunterladen
scp BENUTZER@SERVER:/htdocs/kinderarbeit/data/kinderarbeit.sqlite ./
```

Eine tägliche Sicherung per Cron:

```
30 3 * * * sqlite3 /htdocs/kinderarbeit/data/kinderarbeit.sqlite ".backup '/htdocs/backups/kinderarbeit-$(date +\%F).sqlite'"
```

---

## Aktualisieren

```bash
cd /htdocs/kinderarbeit
git pull
```

Das Schema aktualisiert sich beim nächsten Aufruf selbst: fehlende Tabellen
werden angelegt, später hinzugekommene Spalten nachgezogen.
Die Datei `data/kinderarbeit.sqlite` wird dabei nie überschrieben – sie steht in
`.gitignore` und bleibt unangetastet.

---

## Wenn etwas nicht funktioniert

| Symptom | Ursache und Abhilfe |
|---|---|
| „Die Anwendung konnte nicht starten“ | Meldung auf der Seite lesen; Details stehen in `data/php-error.log` |
| „data/ ist nicht beschreibbar“ | `chmod 775 data` – notfalls `chmod 777 data` |
| „pdo_sqlite ist nicht aktiviert“ | im manitu-Panel eine PHP-Version mit SQLite wählen |
| Weiße Seite | PHP-Version zu alt; im Panel auf PHP 8.2 stellen |
| Das Design fehlt | `assets/` wurde nicht mit hochgeladen |
| Niemand kommt mehr rein | `php bin/reset-pin.php Thies 4711` per SSH |
| Ein Zugangslink ist in falsche Hände geraten | unter **Familie** „Neu erzeugen“ – der alte Link ist sofort tot |
| Zugangslink führt zu „Dieser Link gilt nicht mehr“ | er wurde neu erzeugt oder zurückgezogen; einen frischen verschicken |
| Beträge doppelt gebucht | sollte nicht passieren; `php bin/selftest.php` ausführen und melden |

---

## Umzug auf einen anderen Server

1. `data/kinderarbeit.sqlite` sichern
2. Dateien am neuen Ort hochladen
3. gesicherte Datenbank nach `data/` zurückspielen
4. `chmod 775 data`

Fertig – es gibt keine Konfigurationsdatei und keine Datenbank-Zugangsdaten.
