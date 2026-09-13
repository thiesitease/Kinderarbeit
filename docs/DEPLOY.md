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

Danach im selben Schritt **HTTPS aktivieren** (Let's Encrypt). Das ist kein
optionaler Schritt: Die mitgelieferte `.htaccess` leitet jeden Aufruf auf HTTPS
um, und ohne eigenes Zertifikat für die Subdomain zeigt der Browser dann eine
Sicherheitswarnung statt der Anwendung.

So lässt sich prüfen, ob das Zertifikat schon passt:

```bash
curl -sI https://kinderarbeit.thiesreinhold.de/ | head -1
```

Kommt stattdessen „SSL: no alternative certificate subject name matches“, ist
noch das Standardzertifikat des Hosters aktiv (`*.manitu.net`) – dann im
Kundenbereich für diese Subdomain ein Zertifikat ausstellen lassen.

Die Prüfdateien, mit denen Let's Encrypt die Domain bestätigt, liegen unter
`/.well-known/acme-challenge/`. Diesen Pfad nimmt die `.htaccess` bewusst von
der HTTPS-Weiterleitung aus – sonst könnte das Zertifikat gar nicht erst
ausgestellt werden.

---

## 3. Dateien hochladen

### Variante A – einmalig von Hand per SSH

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

### Variante B – automatisch über GitHub (empfohlen für den Dauerbetrieb)

Einmal einrichten, danach veröffentlicht jeder Push von allein.
Der Schlüssel liegt dabei verschlüsselt bei GitHub; niemand außer dir
bekommt ihn je zu sehen.

> In allen folgenden Befehlen stehen `BENUTZER` und `SERVER` für die echten
> Werte aus dem manitu-Kundenbereich – die müssen eingesetzt werden.

**1. Schlüsselpaar nur für das Veröffentlichen erzeugen**

Ohne Passwort, damit die Automatik ihn benutzen kann.

*macOS und Linux:*

```bash
ssh-keygen -t ed25519 -C "github-deploy kinderarbeit" -f ~/.ssh/kinderarbeit_deploy -N ""
```

*Windows (PowerShell):* PowerShell löst die Tilde bei fremden Programmen
**nicht** auf – deshalb `$HOME` verwenden, sonst landet der Schlüssel im
aktuellen Verzeichnis:

```powershell
mkdir -Force "$HOME\.ssh" | Out-Null
ssh-keygen -t ed25519 -C "github-deploy kinderarbeit" -f "$HOME\.ssh\kinderarbeit_deploy" -N '""'
```

Es entstehen zwei Dateien: `kinderarbeit_deploy` (privat, bleibt bei dir und
kommt gleich als Secret zu GitHub) und `kinderarbeit_deploy.pub` (öffentlich,
kommt auf den Server).

> **Ist er versehentlich im Projektverzeichnis gelandet?** Dann suchen und
> wegräumen – ein privater Schlüssel hat in einem Git-Verzeichnis nichts zu
> suchen:
>
> ```powershell
> Get-ChildItem -Path . -Filter "kinderarbeit_deploy*" -Recurse -Force | Select-Object FullName
> ```
>
> Die `.gitignore` fängt diesen Fall zwar ab, aber besser trotzdem löschen und
> mit dem Befehl oben neu erzeugen.

**2. Öffentlichen Schlüssel auf dem Server erlauben**

> **Bei manitu geht das über den Kundenbereich, nicht über die Kommandozeile.**
> Der Server dort erlaubt ausschließlich die Anmeldung per Schlüssel, kein
> Passwort – erkennbar an der Meldung `Permission denied (publickey).` Damit
> lässt sich der Schlüssel nicht per SSH hinterlegen, denn genau dafür bräuchte
> man ja schon einen funktionierenden Zugang.
>
> Stattdessen im manitu-Kundenbereich den Bereich für SSH-Schlüssel öffnen und
> dort den Inhalt von `kinderarbeit_deploy.pub` einfügen – eine einzige Zeile,
> die mit `ssh-ed25519 AAAA…` beginnt und mit dem Kommentar endet.
>
> Der Benutzername hat bei manitu die Form `webspace\benutzer`, also zum
> Beispiel `pete\thies`. Genau so gehört er in das Secret `SSH_USER` – mit
> Backslash, ohne Anführungszeichen.

Auf Servern, die auch Passwörter zulassen, geht es direkt von der Kommandozeile:

*macOS und Linux:*

```bash
ssh-copy-id -i ~/.ssh/kinderarbeit_deploy.pub BENUTZER@SERVER
```

*Windows (PowerShell):* `ssh-copy-id` gibt es dort nicht. Diese zwei Zeilen
machen dasselbe:

```powershell
$pub = (Get-Content "$HOME\.ssh\kinderarbeit_deploy.pub" -Raw).Trim()
ssh BENUTZER@SERVER "mkdir -p ~/.ssh; chmod 700 ~/.ssh; echo '$pub' >> ~/.ssh/authorized_keys; chmod 600 ~/.ssh/authorized_keys"
```

Dabei fragt der Server noch einmal nach deinem normalen Passwort – danach
nicht mehr.

Kurz prüfen, dass es klappt:

```bash
ssh -i ~/.ssh/kinderarbeit_deploy BENUTZER@SERVER 'echo Verbindung steht && pwd'
```

```powershell
ssh -i "$HOME\.ssh\kinderarbeit_deploy" BENUTZER@SERVER 'echo Verbindung steht && pwd'
```

Das `pwd` verrät gleich den Pfad, den du für `DEPLOY_PATH` brauchst.

**3. Secrets bei GitHub hinterlegen**

Im Repository: **Settings → Secrets and variables → Actions →
New repository secret**. Vier Stück sind nötig:

| Name | Inhalt |
|---|---|
| `SSH_HOST` | Servername aus dem manitu-Kundenbereich, z. B. `ssh.manitu.de` |
| `SSH_USER` | Benutzername beim Hoster |
| `SSH_KEY` | der **gesamte** Inhalt von `~/.ssh/kinderarbeit_deploy` – mit den Zeilen `-----BEGIN …` und `-----END …` |
| `DEPLOY_PATH` | Verzeichnis **genau dieser Subdomain**, bei manitu z. B. `/home/sites/site100029489/web/kinderarbeit.thiesreinhold.de` |

Zwei weitere sind freiwillig:

| Name | Wofür |
|---|---|
| `SSH_PORT` | nur falls der Server nicht auf Port 22 hört |
| `SSH_KNOWN_HOSTS` | Serverschlüssel – siehe Schritt 5 |

Den privaten Schlüssel bekommst du so in die Zwischenablage:

```bash
pbcopy < ~/.ssh/kinderarbeit_deploy             # macOS
xclip -sel clip < ~/.ssh/kinderarbeit_deploy    # Linux
```

```powershell
Get-Content "$HOME\.ssh\kinderarbeit_deploy" -Raw | Set-Clipboard   # Windows
```

> **Wichtig: `DEPLOY_PATH` muss auf das Verzeichnis der Subdomain zeigen**,
> nicht auf das darüberliegende `web/`, in dem alle Websites des Pakets
> nebeneinander liegen. Dort würde das Übertragen mit `--delete` die anderen
> Seiten löschen. Der Workflow prüft das Zielverzeichnis deshalb vorher und
> bricht ab, wenn er dort fremde Dateien findet – aber verlass dich nicht
> allein darauf.
>
> So findest du den richtigen Pfad auf dem Server:
>
> ```bash
> grep -rl "kinderarbeit.thiesreinhold.de" ~/../.. --include="index.html" 2>/dev/null
> ```

**4. Veröffentlichen**

Ab jetzt läuft bei jedem Push auf den Hauptbranch automatisch:

1. **Logik prüfen** – Syntax aller PHP-Dateien und der Selbsttest
2. **Auf den Server übertragen** – `rsync` mit `--delete`; die Datenbank in
   `data/` ist ausgenommen und bleibt unangetastet. Danach läuft der
   Selbsttest noch einmal auf dem Server
3. **Seite von außen prüfen** – ist die Startseite erreichbar, und sind
   `data/` und `app/` wirklich abgeschottet?

Von Hand starten geht unter **Actions → Veröffentlichen → Run workflow**.

Solange die Secrets fehlen, wird nur geprüft und der Deploy übersprungen –
der Lauf bleibt grün und sagt im Protokoll, was noch fehlt.

**5. Absichern: Serverschlüssel festnageln**

Ohne `SSH_KNOWN_HOSTS` übernimmt der erste Lauf den Serverschlüssel ungeprüft.
Er schreibt ihn dafür ins Protokoll, zwischen zwei Markierungen:

```
---8<--- SSH_KNOWN_HOSTS ---8<---
ssh.manitu.de ssh-ed25519 AAAAC3Nza…
--->8--------------------->8---
```

Diese Zeilen als Secret `SSH_KNOWN_HOSTS` hinterlegen. Ab dann prüft jeder
Lauf, dass er wirklich mit deinem Server spricht. Alternativ lokal holen:

```bash
ssh-keyscan SERVER
```

### Variante C – per FTP

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
| GitHub-Lauf bricht bei „SSH vorbereiten“ ab | Schlüssel unvollständig kopiert – `SSH_KEY` muss die Zeilen `-----BEGIN` und `-----END` enthalten |
| GitHub-Lauf meldet „Permission denied (publickey)“ | öffentlicher Schlüssel fehlt in `~/.ssh/authorized_keys` auf dem Server, oder `SSH_USER` stimmt nicht |
| GitHub-Lauf meldet „Host key verification failed“ | `SSH_KNOWN_HOSTS` passt nicht mehr zum Server – Secret löschen, einmal laufen lassen, neuen Wert aus dem Protokoll übernehmen |
| GitHub-Lauf: „Network is unreachable“ | `SSH_HOST` enthält etwas anderes als den reinen Hostnamen (kein `https://`, kein Pfad, kein Port), oder der Server ist nur über IPv6 erreichbar – GitHub-Runner können kein IPv6 |
| GitHub-Lauf: „ssh-keyscan kam leer zurück“ | Falscher Port – manche Hoster nutzen nicht 22. Richtigen Wert als `SSH_PORT` hinterlegen |
| GitHub-Lauf oder lokal: „Permission denied (publickey)“ | Gute Nachricht – Host, Port und Netzwerk stimmen, nur der Schlüssel wird nicht akzeptiert. Er fehlt auf dem Server (Schritt 2) oder `SSH_USER` ist unvollständig (bei manitu mit Backslash: `webspace\benutzer`) |
| GitHub-Lauf bricht bei „Verbindung testen“ ab | Die Zeile direkt über der Fehlermeldung nennt die Ursache – der Lauf listet die drei häufigsten Fälle gleich mit auf |
| PowerShell: „ssh-copy-id wurde nicht als Name eines Cmdlet erkannt“ | Das gibt es unter Windows nicht – die beiden PowerShell-Zeilen aus Schritt 2 benutzen |
| Schlüssel liegt im Projektverzeichnis statt unter `.ssh` | PowerShell löst `~` nicht auf; mit `$HOME` statt `~` neu erzeugen |
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
