<?php
declare(strict_types=1);

/**
 * Nimmt die Abbuchungen der festen Ausgaben eines Monats zurueck, damit die
 * Abrechnung sie am eingestellten Tag neu anlegt.
 *
 *     php bin/neu-abbuchen.php                 zeigt nur, was zurueckginge
 *     php bin/neu-abbuchen.php --ja            laufender Monat
 *     php bin/neu-abbuchen.php 2026-09 --ja    ein bestimmter Monat
 *
 * Gedacht fuer den Fall, dass sich mitten im Monat etwas an einer festen
 * Ausgabe geaendert hat: Betrag, Titel oder Buchungstag. Der Anlass war die
 * Umstellung auf "am Monatsende" – der laufende Monat war am 1. schon
 * abgebucht, und die Konten standen von Anfang an im Minus.
 *
 * Von Hand im Verlauf geloeschte Abbuchungen bleiben geloescht. Vor dem
 * Zuruecknehmen entsteht eine Sicherung in data/ – dort ist sie ueber die
 * .htaccess gesperrt, in der .gitignore und vom rsync ausgenommen.
 */

require dirname(__DIR__) . '/app/cli.php';

$ohneRueckfrage = in_array('--ja', $argv, true);

$monat = current_month();
foreach (array_slice($argv, 1) as $argument) {
    if (preg_match('/^\d{4}-\d{2}$/', $argument)) {
        $monat = $argument;
    } elseif ($argument !== '--ja') {
        fwrite(STDERR, "Unbekannt: \"{$argument}\". Erwartet wird ein Monat wie 2026-09 und/oder --ja.\n");
        exit(1);
    }
}

if (!file_exists(DB_FILE)) {
    echo "Es gibt noch keine Datenbank – nichts zurückzunehmen.\n";
    exit(0);
}

$pdo = Database::pdo();

// --------------------------------------------------------------- Was ansteht

$stmt = $pdo->prepare(
    'SELECT e.title, u.name AS kind, l.amount_cents, l.booked_at
       FROM expense_bookings b
       JOIN expenses e ON e.id = b.expense_id
       JOIN users    u ON u.id = e.child_id
       JOIN ledger   l ON l.id = b.ledger_id
      WHERE b.month = :monat
   ORDER BY u.sort_order, e.id'
);
$stmt->execute(['monat' => $monat]);
$anstehend = $stmt->fetchAll();

echo "\nAbbuchungen im " . month_label($monat) . "\n";
echo str_repeat('─', 56) . "\n";

if (!$anstehend) {
    echo "  Keine, die zurückgenommen werden könnten.\n\n";
    echo "Entweder ist der Monat noch nicht abgebucht, oder die Buchungen\n";
    echo "wurden im Verlauf schon von Hand entfernt.\n\n";
    exit(0);
}

foreach ($anstehend as $zeile) {
    echo '  ' . spalte((string)$zeile['title'], 22) . ' '
       . spalte((string)$zeile['kind'], 9) . ' '
       . spalte(Money::format((int)$zeile['amount_cents']), 10, true)
       . '   gebucht am ' . format_date((string)$zeile['booked_at']) . "\n";
}
echo "\n";

if (!$ohneRueckfrage) {
    echo "Es wurde nichts geändert. Zum Zurücknehmen:\n\n";
    echo "  php bin/neu-abbuchen.php " . ($monat === current_month() ? '' : $monat . ' ') . "--ja\n\n";
    echo "Danach legt die Abrechnung sie am eingestellten Tag neu an – beim\n";
    echo "laufenden Monat also erst dann, wenn dieser Tag erreicht ist.\n\n";
    exit(0);
}

// --------------------------------------------------------------- Sicherung

$sicherung = DATA_DIR . '/sicherung-' . date('Ymd-His') . '.sqlite';

// Ueber SQLite kopieren und nicht mit copy(): so ist auch mitgenommen, was
// noch im WAL steht und noch nicht in der Hauptdatei gelandet ist.
try {
    $pdo->exec("VACUUM INTO '" . str_replace("'", "''", $sicherung) . "'");
} catch (PDOException $fehler) {
    $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
    if (!copy(DB_FILE, $sicherung)) {
        fwrite(STDERR, "Die Sicherung konnte nicht angelegt werden. Es wurde nichts geändert.\n");
        exit(1);
    }
}
@chmod($sicherung, 0664);
printf("Sicherung: %s (%s KB)\n\n", basename($sicherung), number_format(filesize($sicherung) / 1024, 0, ',', '.'));

// --------------------------------------------------------------- Zurücknehmen

$zurueck = Expenses::clearBookings($monat);
printf("%d Abbuchung%s zurückgenommen.\n\n", count($zurueck), count($zurueck) === 1 ? '' : 'en');

// Faellige Monate sofort nachziehen: ein vergangener Monat wird gleich wieder
// gebucht – mit dem heutigen Betrag und Titel. Der laufende wartet auf seinen Tag.
$neu = Billing::run(true);
if ($neu > 0) {
    printf("%d davon sofort neu gebucht (der Buchungstag liegt schon hinter uns).\n\n", $neu);
} else {
    echo "Neu gebucht wird am eingestellten Tag – beim Monatsende also am letzten\n";
    echo "Tag des Monats, beim ersten Seitenaufruf danach.\n\n";
}

echo "Kontostände\n";
echo str_repeat('─', 56) . "\n";
foreach (Users::children() as $kind) {
    echo '  ' . spalte((string)$kind['name'], 22) . ' '
       . spalte(Money::format(Ledger::balance((int)$kind['id'])), 10, true) . "\n";
}
echo "\n";
