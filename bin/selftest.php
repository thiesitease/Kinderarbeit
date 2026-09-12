<?php
declare(strict_types=1);

/**
 * Selbsttest der Rechenlogik – laeuft ohne Webserver:
 *
 *     php bin/selftest.php
 *
 * Legt eine temporaere Datenbank an, prueft Betragsrechnung, Bestaetigungen
 * und die automatische Abbuchung und raeumt anschliessend auf.
 */

if (PHP_SAPI !== 'cli') {
    exit('Dieses Skript laeuft nur auf der Kommandozeile.');
}

define('KINDERARBEIT', 'selftest');

$root = dirname(__DIR__);
$tmp  = sys_get_temp_dir() . '/kinderarbeit-selftest-' . getmypid();
@mkdir($tmp, 0775, true);

define('APP_ROOT', $root);
define('APP_DIR', $root . '/app');
define('DATA_DIR', $tmp);
define('DB_FILE', $tmp . '/test.sqlite');

date_default_timezone_set('Europe/Berlin');
mb_internal_encoding('UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', '1');

foreach (['helpers', 'Money', 'Database', 'Billing', 'Repo/Users', 'Repo/Tasks', 'Repo/Completions', 'Repo/Ledger', 'Repo/Expenses'] as $file) {
    require APP_DIR . '/' . $file . '.php';
}

$passed = 0;
$failed = 0;

function check(string $label, mixed $actual, mixed $expected): void
{
    global $passed, $failed;
    if ($actual === $expected) {
        $passed++;
        echo "  \033[32m✓\033[0m {$label}\n";
        return;
    }
    $failed++;
    echo "  \033[31m✗\033[0m {$label}\n";
    echo "      erwartet: " . var_export($expected, true) . "\n";
    echo "      erhalten: " . var_export($actual, true) . "\n";
}

echo "\nBetraege einlesen\n";
check('"2,50" ergibt 250 Cent',      Money::parse('2,50'), 250);
check('"2.50" ergibt 250 Cent',      Money::parse('2.50'), 250);
check('"5" ergibt 500 Cent',         Money::parse('5'), 500);
check('"0,05" ergibt 5 Cent',        Money::parse('0,05'), 5);
check('"1.234,56" ergibt 123456',    Money::parse('1.234,56'), 123456);
check('"19,90 €" ergibt 1990',       Money::parse('19,90 €'), 1990);
check('" 3,00 " ergibt 300',         Money::parse(' 3,00 '), 300);
check('"-2,50" ergibt -250',         Money::parse('-2,50'), -250);
check('"abc" ist ungueltig',         Money::parse('abc'), null);
check('"" ist ungueltig',            Money::parse(''), null);
check('"2,555" ist ungueltig',       Money::parse('2,555'), null);
check('"1,2,3" ist ungueltig',       Money::parse('1,2,3'), null);

echo "\nBetraege ausgeben\n";
check('250 Cent',                    Money::format(250), '2,50 €');
check('123456 Cent mit Tausender',   Money::format(123456), '1.234,56 €');
check('0 Cent',                      Money::format(0), '0,00 €');
check('-1990 Cent',                  Money::format(-1990), '−19,90 €');
check('250 Cent mit Vorzeichen',     Money::format(250, true), '+2,50 €');
check('Formularwert aus 1990',       Money::forInput(1990), '19,90');

echo "\nMonatsrechnung\n";
check('Monat +1 ueber Jahreswechsel', month_shift('2025-12', 1), '2026-01');
check('Monat -1 ueber Jahreswechsel', month_shift('2026-01', -1), '2025-12');
check('Monat +14',                    month_shift('2025-01', 14), '2026-03');
check('Beschriftung',                 month_label('2026-03'), 'März 2026');

echo "\nDatenbank und Aufgabenablauf\n";
$pdo = Database::pdo();
check('Fuenf Profile angelegt',       count(Users::all()), 5);
check('Drei Kinder',                  count(Users::children()), 3);
check('Zwei Elternzugaenge',          count(Users::parents()), 2);
check('Vier Beispielaufgaben',        count(Tasks::all()), 4);

$emilius = Users::findByName('Emilius');
$thies   = Users::findByName('Thies');
$childId = (int)$emilius['id'];
$task    = Tasks::find(1);

check('Startguthaben ist 0',          Ledger::balance($childId), 0);

$completionId = Completions::submit($task, $childId);
check('Meldung liegt zur Pruefung',   Completions::pendingCount($childId), 1);
check('Noch keine Gutschrift',        Ledger::balance($childId), 0);
check('Offener Betrag',               Completions::pendingAmount($childId), 500);

check('Bestaetigung klappt',          Completions::approve($completionId, (int)$thies['id']), true);
check('Guthaben nach Bestaetigung',   Ledger::balance($childId), 500);
check('Zweite Bestaetigung scheitert', Completions::approve($completionId, (int)$thies['id']), false);
check('Guthaben unveraendert',        Ledger::balance($childId), 500);

$rejectId = Completions::submit(Tasks::find(2), $childId);
check('Ablehnung klappt',             Completions::reject($rejectId, (int)$thies['id'], 'zu kurz'), true);
check('Keine Gutschrift nach Ablehnung', Ledger::balance($childId), 500);

$revokeId = Completions::submit(Tasks::find(3), $childId);
Completions::approve($revokeId, (int)$thies['id']);
check('Guthaben nach dritter Aufgabe', Ledger::balance($childId), 550);
check('Ruecknahme klappt',            Completions::revoke($revokeId, (int)$thies['id'], 'doch nicht'), true);
check('Gegenbuchung gleicht aus',     Ledger::balance($childId), 500);

echo "\nFeste Ausgaben und Abbuchung\n";
$startMonth = month_shift(current_month(), -2);
$expenseId  = Expenses::create([
    'child_id'     => $childId,
    'title'        => 'Beitrag Fitnessstudio',
    'amount_cents' => 1990,
    'day_of_month' => 1,
    'start_month'  => $startMonth,
    'created_by'   => (int)$thies['id'],
]);

$booked = Billing::run(true);
check('Drei Monate nachgeholt',       $booked, 3);
check('Guthaben nach Abbuchungen',    Ledger::balance($childId), 500 - 3 * 1990);
check('Kein zweiter Durchlauf',       Billing::run(true), 0);
check('Monatliche Belastung',         Expenses::monthlyTotal($childId), 1990);

$summary = Ledger::monthSummary($childId, current_month());
check('Verdient im Monat (brutto)',   $summary['earned'], 550);
check('Ruecknahme als Korrektur',     $summary['corrections'], -50);
check('Feste Ausgaben im Monat',      $summary['expenses'], 1990);
check('Rest im Monat',                $summary['net'], 500 - 1990);
check(
    'Monatskacheln gehen auf',
    $summary['earned'] + $summary['bonus'] + $summary['corrections'] - $summary['expenses'] - $summary['payouts'],
    $summary['net']
);

Expenses::setActive($expenseId, false);
check('Pausierte Ausgabe bucht nicht', Billing::run(true), 0);

echo "\nAuszahlungen und Historie\n";
Ledger::book($childId, -300, 'Bar ausgezahlt', 'payout', 'manual', null, (int)$thies['id']);
check('Auszahlung mindert Guthaben',  Ledger::balance($childId), 500 - 3 * 1990 - 300);
$summary = Ledger::monthSummary($childId, current_month());
check('Auszahlung in der Monatssumme', $summary['payouts'], 300);
check(
    'Monatskacheln gehen weiterhin auf',
    $summary['earned'] + $summary['bonus'] + $summary['corrections'] - $summary['expenses'] - $summary['payouts'],
    $summary['net']
);
check('Historie ist vollstaendig',    count(Ledger::forChild($childId)) >= 5, true);

echo "\nEinmalige Aufgaben\n";
$onceId = Tasks::create([
    'title' => 'Keller aufräumen', 'amount_cents' => 1000,
    'kind' => 'once', 'created_by' => (int)$thies['id'],
]);
$before = count(Tasks::forChild($childId));
Completions::submit(Tasks::find($onceId), $childId);
check('Einmal-Aufgabe verschwindet',  count(Tasks::forChild($childId)), $before - 1);

echo "\nZuweisung an ein Kind\n";
$julius = Users::findByName('Julius');
Tasks::create([
    'title' => 'Auto waschen', 'amount_cents' => 300,
    'kind' => 'repeatable', 'assigned_to' => (int)$julius['id'], 'created_by' => (int)$thies['id'],
]);
$titles = array_column(Tasks::forChild($childId), 'title');
check('Emilius sieht fremde Aufgabe nicht', in_array('Auto waschen', $titles, true), false);
$titles = array_column(Tasks::forChild((int)$julius['id']), 'title');
check('Julius sieht seine Aufgabe',   in_array('Auto waschen', $titles, true), true);

// Aufraeumen
foreach (glob($tmp . '/*') ?: [] as $file) {
    @unlink($file);
}
@rmdir($tmp);

echo "\n" . str_repeat('─', 44) . "\n";
printf("  Bestanden: %d    Fehler: %d\n", $passed, $failed);
echo str_repeat('─', 44) . "\n\n";

exit($failed === 0 ? 0 : 1);
