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

echo "\nZugangslinks\n";
check('Zu Beginn kein Link',          $emilius['access_token'], null);

$token = Users::createToken($childId);
check('Token ist 32 Zeichen lang',    strlen($token), 32);
check('Token ist hexadezimal',        (bool)preg_match('/^[a-f0-9]{32}$/', $token), true);

$found = Users::findByToken($token);
check('Token findet das Profil',      (int)($found['id'] ?? 0), $childId);
check('Unbekannter Token findet nichts', Users::findByToken(str_repeat('f', 32)), null);
check('Zu kurzer Token findet nichts',   Users::findByToken('abc'), null);
check('Token anderer Form findet nichts', Users::findByToken('../../etc/passwd'), null);

$second = Users::createToken($childId);
check('Neuer Token unterscheidet sich', $second === $token, false);
check('Alter Token gilt nicht mehr',    Users::findByToken($token), null);
check('Neuer Token gilt',               (int)(Users::findByToken($second)['id'] ?? 0), $childId);

Users::clearToken($childId);
check('Zurueckgezogener Token gilt nicht mehr', Users::findByToken($second), null);

$tokenA = Users::createToken($childId);
$tokenB = Users::createToken((int)$julius['id']);
check('Zwei Profile, zwei Token',     $tokenA === $tokenB, false);
check('Jeder Token trifft sein Profil', (int)Users::findByToken($tokenB)['id'], (int)$julius['id']);

check('Spalte access_token vorhanden',
      in_array('access_token', $pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_COLUMN, 1), true), true);

echo "\nBuchungen aendern und loeschen\n";
$balance  = Ledger::balance($childId);
$manualId = Ledger::book($childId, 500, 'Taschengeld extra', 'bonus', 'manual', null, (int)$thies['id']);
check('Buchung angelegt',             Ledger::balance($childId), $balance + 500);

$entry = Ledger::find($manualId);
check('Buchung wiedergefunden',       $entry['description'], 'Taschengeld extra');
check('Von Hand erfasst',             Ledger::isManual($entry), true);
check('Art fuer das Formular',        Ledger::kindOf($entry), 'bonus');

$lastMonth = month_shift(current_month(), -1);
check('Aendern klappt', Ledger::update($manualId, [
    'child_id'     => $childId,
    'amount_cents' => -800,
    'description'  => 'Doch eine Auszahlung',
    'category'     => 'payout',
    'booked_at'    => $lastMonth . '-05 12:00:00',
], (int)$thies['id']), true);

$entry = Ledger::find($manualId);
check('Betrag geaendert',             (int)$entry['amount_cents'], -800);
check('Text geaendert',               $entry['description'], 'Doch eine Auszahlung');
check('Art geaendert',                $entry['category'], 'payout');
check('Monat mitgezogen',             $entry['booked_month'], $lastMonth);
check('Aenderung ist vermerkt',       (int)$entry['updated_by'], (int)$thies['id']);
check('Guthaben nach dem Aendern',    Ledger::balance($childId), $balance - 800);
check('Zaehlt im alten Monat',        Ledger::monthSummary($childId, $lastMonth)['payouts'], 800);

check('Loeschen klappt',              Ledger::delete($manualId, (int)$thies['id']), true);
check('Guthaben wieder wie vorher',   Ledger::balance($childId), $balance);
check('Geloeschte Buchung ist weg',   Ledger::find($manualId), null);
check('Zweites Loeschen scheitert',   Ledger::delete($manualId, (int)$thies['id']), false);

echo "\nGutschrift und Meldung bleiben zusammen\n";

/** Die Buchung, die zu einer Meldung gehoert. */
$ledgerFor = static function (int $completionId, string $category) use ($pdo): int {
    $stmt = $pdo->prepare(
        "SELECT id FROM ledger WHERE ref_type = 'completion' AND ref_id = :id AND category = :category"
    );
    $stmt->execute(['id' => $completionId, 'category' => $category]);
    return (int)$stmt->fetchColumn();
};

$balance = Ledger::balance($childId);
$meldung = Completions::submit(Tasks::find(4), $childId);
Completions::approve($meldung, (int)$thies['id']);
check('Gutschrift gebucht',           Ledger::balance($childId), $balance + 200);

$creditId = $ledgerFor($meldung, 'task');
check('Gutschrift gefunden',          $creditId > 0, true);
check('Gutschrift haengt an der Meldung', Ledger::isManual(Ledger::find($creditId)), false);

Ledger::update($creditId, [
    'child_id'     => $childId,
    'amount_cents' => 150,
    'description'  => 'Staubsaugen (kleine Runde)',
    'category'     => 'task',
    'booked_at'    => Ledger::find($creditId)['booked_at'],
], (int)$thies['id']);
check('Guthaben nach der Korrektur',  Ledger::balance($childId), $balance + 150);
check('Meldung fuehrt den neuen Betrag', (int)Completions::find($meldung)['amount_cents'], 150);

check('Ruecknahme klappt',            Completions::revoke($meldung, (int)$thies['id']), true);
check('Gegenbuchung gleicht genau aus', Ledger::balance($childId), $balance);

check('Gegenbuchung loeschen',        Ledger::delete($ledgerFor($meldung, 'correction'), (int)$thies['id']), true);
check('Bestaetigung gilt wieder',     Completions::find($meldung)['status'], 'approved');
check('Guthaben mit Gutschrift',      Ledger::balance($childId), $balance + 150);

check('Gutschrift loeschen',          Ledger::delete($creditId, (int)$thies['id']), true);
check('Meldung gilt als abgelehnt',   Completions::find($meldung)['status'], 'rejected');
check('Guthaben wie vor der Meldung', Ledger::balance($childId), $balance);

$zurueck = Completions::submit(Tasks::find(2), $childId);
Completions::approve($zurueck, (int)$thies['id']);
Completions::revoke($zurueck, (int)$thies['id']);
check('Nach der Ruecknahme unveraendert', Ledger::balance($childId), $balance);
check('Gutschrift loeschen',          Ledger::delete($ledgerFor($zurueck, 'task'), (int)$thies['id']), true);
check('Gegenbuchung faellt mit weg',  $ledgerFor($zurueck, 'correction'), 0);
check('Konto bleibt ausgeglichen',    Ledger::balance($childId), $balance);

echo "\nGeloeschte Abbuchung kommt nicht wieder\n";
Expenses::setActive($expenseId, true);
check('Nichts nachzuholen',           Billing::run(true), 0);

$stmt = $pdo->prepare('SELECT ledger_id FROM expense_bookings WHERE expense_id = :id AND month = :month');
$stmt->execute(['id' => $expenseId, 'month' => current_month()]);
$expenseLedgerId = (int)$stmt->fetchColumn();
check('Abbuchung des Monats gefunden', $expenseLedgerId > 0, true);

$balance = Ledger::balance($childId);
check('Abbuchung loeschen',           Ledger::delete($expenseLedgerId, (int)$thies['id']), true);
check('Guthaben steigt wieder',       Ledger::balance($childId), $balance + 1990);
check('Merker bleibt stehen',         Expenses::isBooked($expenseId, current_month()), true);
check('Abrechnung bucht nicht neu',   Billing::run(true), 0);
check('Guthaben bleibt',              Ledger::balance($childId), $balance + 1990);

$stmt->execute(['id' => $expenseId, 'month' => current_month()]);
check('Merker ohne Buchung',          $stmt->fetchColumn(), null);

// Aufraeumen
foreach (glob($tmp . '/*') ?: [] as $file) {
    @unlink($file);
}
@rmdir($tmp);

echo "\n" . str_repeat('─', 44) . "\n";
printf("  Bestanden: %d    Fehler: %d\n", $passed, $failed);
echo str_repeat('─', 44) . "\n\n";

exit($failed === 0 ? 0 : 1);
