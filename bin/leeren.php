<?php
declare(strict_types=1);

/**
 * Raeumt die Datenbank auf.
 *
 *     php bin/leeren.php                 nur anzeigen, was drinsteht
 *     php bin/leeren.php verlauf         Buchungen und Meldungen loeschen
 *     php bin/leeren.php alles           zurueck auf Werkszustand
 *
 * Mit --ja laeuft es ohne Rueckfrage – fuer den Aufruf ueber SSH.
 *
 * Vor jedem Loeschen entsteht eine Sicherung neben der Datenbank. Sie liegt
 * in data/ und ist damit ueber die .htaccess gesperrt, in der .gitignore und
 * vom rsync des Workflows ausgenommen.
 *
 * Das gibt es bewusst nur hier und nicht im Elternbereich: das Journal wird
 * sonst nirgends geloescht, sondern nur fortgeschrieben (siehe CLAUDE.md).
 * Ein Knopf dafuer, einen Fingerbreit neben dem Taschengeld der Kinder,
 * waere die falsche Stelle.
 */

require dirname(__DIR__) . '/app/cli.php';

$modus  = $argv[1] ?? null;
$ohneRueckfrage = in_array('--ja', $argv, true);

if ($modus !== null && !in_array($modus, ['verlauf', 'alles'], true)) {
    fwrite(STDERR, "Unbekannt: \"{$modus}\". Erlaubt sind \"verlauf\" und \"alles\".\n");
    exit(1);
}

// ---------------------------------------------------------------- Bestand

/**
 * Auf eine Breite auffuellen - nach Zeichen, nicht nach Bytes.
 *
 * printf("%-20s") zaehlt Bytes. Jeder Umlaut belegt in UTF-8 zwei davon, und
 * schon steht die Spalte daneben schief. mb_str_pad() gibt es erst ab PHP 8.3,
 * auf dem Server laeuft 8.2.
 */
$spalte = static function (string $text, int $breite, bool $rechts = false): string {
    $text = mb_substr($text, 0, $breite);
    $luft = str_repeat(' ', max(0, $breite - mb_strlen($text)));
    return $rechts ? $luft . $text : $text . $luft;
};

if (!file_exists(DB_FILE)) {
    echo "Es gibt noch keine Datenbank – beim nächsten Seitenaufruf entsteht sie neu.\n";
    exit(0);
}

$pdo    = Database::pdo();
$zaehle = static fn (string $tabelle): int =>
    (int)$pdo->query('SELECT COUNT(*) FROM ' . $tabelle)->fetchColumn();

$bestand = [
    'Buchungen im Journal'   => $zaehle('ledger'),
    'Gemeldete Aufgaben'     => $zaehle('completions'),
    'Abgebuchte Ausgaben'    => $zaehle('expense_bookings'),
    'Aufgaben'               => $zaehle('tasks'),
    'Feste Ausgaben'         => $zaehle('expenses'),
    'Profile'                => $zaehle('users'),
    'Angemeldete Geräte'     => $zaehle('remember_tokens'),
    'Benachrichtigungs-Abos' => $zaehle('push_subscriptions'),
];

echo "\nWas in der Datenbank steht\n";
echo str_repeat('─', 52) . "\n";
foreach ($bestand as $was => $wieviel) {
    echo '  ' . $spalte($was, 26) . ' ' . $spalte((string)$wieviel, 6, true) . "\n";
}

echo "\nKontostände\n";
echo str_repeat('─', 52) . "\n";
foreach (Users::children() as $kind) {
    echo '  ' . $spalte((string)$kind['name'], 26) . ' '
       . $spalte(Money::format(Ledger::balance((int)$kind['id'])), 10, true) . "\n";
}

// Aufgaben und feste Ausgaben beim Namen nennen. Eine blosse Anzahl beantwortet
// die Frage nicht, die man hier meistens hat: Steht da noch etwas drin, das
// niemand angelegt hat?
$aufgaben = $pdo->query('SELECT title, amount_cents, is_active FROM tasks ORDER BY sort_order, id')->fetchAll();
if ($aufgaben) {
    echo "\nAufgaben\n";
    echo str_repeat('─', 52) . "\n";
    foreach ($aufgaben as $aufgabe) {
        echo '  ' . $spalte((string)$aufgabe['title'], 26) . ' '
           . $spalte(Money::format((int)$aufgabe['amount_cents']), 10, true)
           . ((int)$aufgabe['is_active'] === 1 ? '' : '  (pausiert)') . "\n";
    }
}

$posten = $pdo->query(
    'SELECT e.title, e.amount_cents, u.name AS kind
       FROM expenses e JOIN users u ON u.id = e.child_id
      ORDER BY u.sort_order, e.id'
)->fetchAll();
if ($posten) {
    echo "\nFeste Ausgaben\n";
    echo str_repeat('─', 52) . "\n";
    foreach ($posten as $eintrag) {
        echo '  ' . $spalte((string)$eintrag['title'], 22) . ' '
           . $spalte((string)$eintrag['kind'], 9) . ' '
           . $spalte(Money::format(-(int)$eintrag['amount_cents']), 10, true) . "\n";
    }
}

echo "\n";

if ($modus === null) {
    echo "Es wurde nichts gelöscht. Zum Aufräumen:\n\n";
    echo "  php bin/leeren.php verlauf   Buchungen und Meldungen weg,\n";
    echo "                               Profile, PINs, Zugangslinks und Aufgaben bleiben\n";
    echo "  php bin/leeren.php alles     zurück auf Werkszustand\n\n";
    exit(0);
}

// ---------------------------------------------------------------- Rückfrage

if ($modus === 'verlauf') {
    echo "Gelöscht werden alle Buchungen, alle gemeldeten Aufgaben und die\n";
    echo "abgebuchten festen Ausgaben. Danach stehen alle Konten auf 0,00 €.\n\n";
    echo "Erhalten bleiben: Profile, PINs, Zugangslinks, Handynummern, angemeldete\n";
    echo "Geräte, Benachrichtigungen, die Aufgabenliste und die festen Ausgaben.\n\n";
    echo "Die festen Ausgaben starten neu ab " . month_label(month_shift(current_month(), 1)) . ".\n";
    echo "Der laufende Monat ist schon halb vorbei – die Kinder hatten noch keine\n";
    echo "Gelegenheit, etwas zu verdienen, und sollen nicht sofort im Minus stehen.\n";
    echo "Wer das anders will, ändert den Startmonat unter „Ausgaben“.\n\n";
} else {
    echo "Gelöscht wird die ganze Datenbank. Beim nächsten Seitenaufruf entsteht\n";
    echo "sie neu – mit den Start-PINs aus der Voreinstellung.\n\n";
    echo "Weg sind damit auch: alle Zugangslinks, alle angemeldeten Geräte, alle\n";
    echo "Benachrichtigungen, die Handynummern, eigene Aufgaben und Ausgaben.\n";
    echo "Die Links, die ihr verschickt habt, gelten dann nicht mehr.\n\n";
}

if (!$ohneRueckfrage) {
    echo 'Wirklich? Dann "ja" eingeben: ';
    $antwort = trim((string)fgets(STDIN));
    if (strtolower($antwort) !== 'ja') {
        echo "Abgebrochen – es wurde nichts gelöscht.\n";
        exit(1);
    }
    echo "\n";
}

// ---------------------------------------------------------------- Sicherung

$sicherung = DATA_DIR . '/sicherung-' . date('Ymd-His') . '.sqlite';

// Ueber SQLite kopieren und nicht mit copy(): so ist auch mitgenommen, was
// noch im WAL steht und noch nicht in der Hauptdatei gelandet ist.
try {
    $pdo->exec("VACUUM INTO '" . str_replace("'", "''", $sicherung) . "'");
} catch (PDOException $fehler) {
    // "VACUUM INTO" gibt es erst ab SQLite 3.27. Sonst eben von Hand –
    // vorher alles aus dem WAL in die Hauptdatei schreiben.
    $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
    if (!copy(DB_FILE, $sicherung)) {
        fwrite(STDERR, "Die Sicherung konnte nicht angelegt werden. Es wurde nichts gelöscht.\n");
        exit(1);
    }
}
@chmod($sicherung, 0664);

printf("Sicherung: %s (%s KB)\n\n", basename($sicherung), number_format(filesize($sicherung) / 1024, 0, ',', '.'));

// ---------------------------------------------------------------- Aufräumen

if ($modus === 'alles') {
    // Die Datei nur loeschen und nicht neu anlegen: auf dem Server laeuft der
    // Webserver unter einem anderen Benutzer als die Kommandozeile. Legt er
    // die Datenbank selbst an, gehoert sie ihm und er darf hineinschreiben.
    foreach ([DB_FILE, DB_FILE . '-wal', DB_FILE . '-shm'] as $datei) {
        if (file_exists($datei)) {
            unlink($datei);
        }
    }

    echo "Die Datenbank ist gelöscht.\n";
    echo "Beim nächsten Aufruf von kinderarbeit.thiesreinhold.de entsteht sie neu.\n\n";
    exit(0);
}

$geloescht = Database::transaction(static function (PDO $pdo): array {
    // expense_bookings haengt per Fremdschluessel am Journal und wuerde
    // ohnehin mitgehen; ausdruecklich geloescht liest es sich klarer.
    $anzahl = [
        'Abgebuchte Ausgaben' => $pdo->exec('DELETE FROM expense_bookings'),
        'Gemeldete Aufgaben'  => $pdo->exec('DELETE FROM completions'),
        'Buchungen'           => $pdo->exec('DELETE FROM ledger'),
    ];

    // Ohne das holt Billing::run() beim naechsten Seitenaufruf jeden
    // vergangenen Monat nach - die Abbuchungen waeren sofort wieder da.
    //
    // Der naechste Monat und nicht der laufende: sonst wuerde die Ausgabe
    // fuer einen Monat abgebucht, in dem niemand mehr etwas verdienen kann,
    // und die frisch geleerten Konten staenden gleich wieder im Minus.
    $abMonat = month_shift(current_month(), 1);
    $pdo->prepare('UPDATE expenses SET start_month = :monat WHERE start_month < :monat')
        ->execute(['monat' => $abMonat]);

    return $anzahl;
});

// Damit die festen Ausgaben nicht noch heute erneut abgebucht werden.
Billing::setSetting('last_billing_run', date('Y-m-d'));

// Platz freigeben und alles aus dem WAL in die Hauptdatei schreiben.
$pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
$pdo->exec('VACUUM');

// Der Webserver laeuft unter einem anderen Benutzer als die Kommandozeile.
// Ohne Gruppenschreibrecht koennte er die Datenbank danach nicht mehr aendern.
foreach ([DB_FILE, DB_FILE . '-wal', DB_FILE . '-shm'] as $datei) {
    if (file_exists($datei)) {
        @chmod($datei, 0664);
    }
}

echo "Gelöscht\n";
echo str_repeat('─', 52) . "\n";
foreach ($geloescht as $was => $wieviel) {
    echo '  ' . $spalte($was, 26) . ' ' . $spalte((string)$wieviel, 6, true) . "\n";
}

echo "\nKontostände\n";
echo str_repeat('─', 52) . "\n";
foreach (Users::children() as $kind) {
    echo '  ' . $spalte((string)$kind['name'], 26) . ' '
       . $spalte(Money::format(Ledger::balance((int)$kind['id'])), 10, true) . "\n";
}
echo "\nFeste Ausgaben starten ab " . month_label(month_shift(current_month(), 1)) . ".\n";
echo "Aufgaben, Profile und Zugangslinks sind unberührt.\n\n";
