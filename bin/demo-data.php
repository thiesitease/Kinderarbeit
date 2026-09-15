<?php
declare(strict_types=1);

/**
 * Legt einen realistischen Beispiel-Datenbestand an – nuetzlich zum Ausprobieren.
 *
 *     php bin/demo-data.php            (nur wenn die Datenbank noch leer ist)
 *     php bin/demo-data.php --force    (bestehende Datenbank ueberschreiben)
 *
 * ACHTUNG: --force loescht die vorhandene Datenbank vollstaendig.
 */

if (PHP_SAPI !== 'cli') {
    exit('Dieses Skript laeuft nur auf der Kommandozeile.');
}

define('KINDERARBEIT', 'demo');
$root = dirname(__DIR__);

define('APP_ROOT', $root);
define('APP_DIR', $root . '/app');
define('DATA_DIR', $root . '/data');
define('DB_FILE', DATA_DIR . '/kinderarbeit.sqlite');

date_default_timezone_set('Europe/Berlin');
mb_internal_encoding('UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', '1');

foreach (['helpers', 'Money', 'Phone', 'Database', 'Billing', 'Remember', 'Repo/Users', 'Repo/Tasks', 'Repo/Completions', 'Repo/Ledger', 'Repo/Expenses'] as $file) {
    require APP_DIR . '/' . $file . '.php';
}

$force = in_array('--force', $argv, true);

if (file_exists(DB_FILE) && !$force) {
    exit("Es gibt bereits eine Datenbank. Mit --force wird sie geloescht und neu aufgebaut.\n");
}
if ($force) {
    foreach (glob(DATA_DIR . '/kinderarbeit.sqlite*') ?: [] as $file) {
        @unlink($file);
    }
}

$pdo = Database::pdo();

$emilius = Users::findByName('Emilius');
$julius  = Users::findByName('Julius');
$bruno   = Users::findByName('Bruno');
$birgitta = Users::findByName('Birgitta');
$thies   = Users::findByName('Thies');

/** Eine Meldung mit frei waehlbarem Zeitpunkt einstellen. */
$submitAt = static function (int $taskId, array $child, string $when): int {
    $task = Tasks::find($taskId);
    $pdo  = Database::pdo();
    $pdo->prepare(
        'INSERT INTO completions (task_id, child_id, title, emoji, amount_cents, status, note, created_at)
         VALUES (:task, :child, :title, :emoji, :amount, \'pending\', \'\', :created)'
    )->execute([
        'task'   => $taskId,
        'child'  => (int)$child['id'],
        'title'  => $task['title'],
        'emoji'  => $task['emoji'],
        'amount' => (int)$task['amount_cents'],
        'created'=> $when,
    ]);
    return (int)$pdo->lastInsertId();
};

/** Eine Meldung bestaetigen und dabei den Buchungszeitpunkt setzen. */
$approveAt = static function (int $completionId, array $parent, string $when): void {
    $pdo = Database::pdo();
    $pdo->prepare(
        "UPDATE completions SET status = 'approved', decided_by = :p, decided_at = :when WHERE id = :id"
    )->execute(['p' => (int)$parent['id'], 'when' => $when, 'id' => $completionId]);

    $row = $pdo->prepare('SELECT * FROM completions WHERE id = :id');
    $row->execute(['id' => $completionId]);
    $completion = $row->fetch();

    Ledger::book(
        (int)$completion['child_id'],
        (int)$completion['amount_cents'],
        $completion['title'],
        'task',
        'completion',
        $completionId,
        (int)$parent['id'],
        $when
    );
};

$day = static fn (int $daysAgo, string $time = '16:30:00'): string
    => date('Y-m-d', strtotime("-{$daysAgo} days")) . ' ' . $time;

// --- Weitere Aufgaben --------------------------------------------------------
Tasks::create(['title' => 'Müll rausbringen', 'emoji' => '🗑️', 'amount_cents' => 80,
    'description' => 'Gelbe Tonne und Papier.', 'created_by' => (int)$thies['id']]);
Tasks::create(['title' => 'Auto saugen', 'emoji' => '🚗', 'amount_cents' => 400,
    'description' => 'Innenraum komplett.', 'created_by' => (int)$birgitta['id']]);
Tasks::create(['title' => 'Fahrräder putzen', 'emoji' => '🚲', 'amount_cents' => 250,
    'kind' => 'once', 'created_by' => (int)$birgitta['id']]);
Tasks::create(['title' => 'Hühner füttern', 'emoji' => '🐔', 'amount_cents' => 100,
    'description' => 'Morgens vor der Schule.', 'assigned_to' => (int)$bruno['id'], 'created_by' => (int)$thies['id']]);

// --- Uebertrag aus der Zeit vor der Anwendung --------------------------------
Ledger::book((int)$emilius['id'], 6000, 'Übertrag aus dem Sparschwein', 'correction', 'manual', null,
    (int)$thies['id'], month_shift(current_month(), -1) . '-28 10:00:00');
Ledger::book((int)$julius['id'], 4500, 'Übertrag aus dem Sparschwein', 'correction', 'manual', null,
    (int)$thies['id'], month_shift(current_month(), -1) . '-28 10:00:00');
Ledger::book((int)$bruno['id'], 4000, 'Übertrag aus dem Sparschwein', 'correction', 'manual', null,
    (int)$thies['id'], month_shift(current_month(), -1) . '-28 10:00:00');

// --- Feste Ausgaben ----------------------------------------------------------
$startMonth = current_month();
Expenses::create(['child_id' => (int)$emilius['id'], 'title' => 'Beitrag Fitnessstudio',
    'emoji' => '🏋️', 'amount_cents' => 1990, 'day_of_month' => 1,
    'start_month' => $startMonth, 'created_by' => (int)$thies['id']]);
Expenses::create(['child_id' => (int)$emilius['id'], 'title' => 'Handyvertrag',
    'emoji' => '📱', 'amount_cents' => 999, 'day_of_month' => 5,
    'start_month' => $startMonth, 'created_by' => (int)$birgitta['id']]);
Expenses::create(['child_id' => (int)$julius['id'], 'title' => 'Fußballverein',
    'emoji' => '⚽', 'amount_cents' => 1200, 'day_of_month' => 1,
    'start_month' => $startMonth, 'created_by' => (int)$birgitta['id']]);
Expenses::create(['child_id' => (int)$bruno['id'], 'title' => 'Musikschule',
    'emoji' => '🎹', 'amount_cents' => 1500, 'day_of_month' => 3,
    'start_month' => $startMonth, 'created_by' => (int)$thies['id']]);

// --- Erledigte und bestaetigte Aufgaben --------------------------------------
$history = [
    // Emilius maeht woechentlich den Rasen und verdient damit seinen Studiobeitrag.
    [1, $emilius, 24, $thies],   [3, $emilius, 22, $birgitta], [2, $emilius, 19, $birgitta],
    [1, $emilius, 17, $thies],   [4, $emilius, 15, $thies],   [3, $emilius, 13, $birgitta],
    [1, $emilius, 11, $thies],   [6, $emilius, 9, $birgitta],  [3, $emilius, 8, $birgitta],
    [1, $emilius, 5, $thies],    [2, $emilius, 4, $birgitta],  [3, $emilius, 2, $birgitta],

    [2, $julius, 23, $birgitta],  [5, $julius, 20, $thies],    [4, $julius, 17, $birgitta],
    [6, $julius, 14, $thies],    [2, $julius, 12, $thies],    [4, $julius, 10, $birgitta],
    [5, $julius, 9, $birgitta],   [2, $julius, 6, $thies],     [6, $julius, 5, $birgitta],
    [4, $julius, 2, $birgitta],

    [8, $bruno, 21, $thies],     [3, $bruno, 18, $birgitta],   [8, $bruno, 16, $thies],
    [8, $bruno, 14, $thies],     [4, $bruno, 12, $birgitta],   [5, $bruno, 11, $birgitta],
    [8, $bruno, 9, $thies],      [3, $bruno, 8, $birgitta],    [8, $bruno, 7, $thies],
    [4, $bruno, 5, $thies],      [8, $bruno, 3, $birgitta],    [3, $bruno, 2, $birgitta],
];

foreach ($history as [$taskId, $child, $daysAgo, $parent]) {
    $submittedAt = $day($daysAgo, sprintf('%02d:%02d:00', random_int(8, 17), random_int(0, 59)));
    // Eltern bestaetigen meist noch am selben Abend.
    $decidedAt = min(
        strtotime($submittedAt) + random_int(1200, 14400),
        strtotime(date('Y-m-d', strtotime($submittedAt)) . ' 21:30:00')
    );
    $id = $submitAt($taskId, $child, $submittedAt);
    $approveAt($id, $parent, date('Y-m-d H:i:s', $decidedAt));
}

// --- Eine abgelehnte Meldung --------------------------------------------------
$rejected = $submitAt(2, $julius, $day(13, '17:10:00'));
$pdo->prepare(
    "UPDATE completions SET status = 'rejected', decided_by = :p, decided_at = :when,
            decision_note = 'War nur eine ganz kurze Runde um den Block.' WHERE id = :id"
)->execute(['p' => (int)$thies['id'], 'when' => $day(13, '19:05:00'), 'id' => $rejected]);

// --- Offene Meldungen für die Wiedervorlage -----------------------------------
$submitAt(1, $emilius, $day(0, '15:20:00'));
$submitAt(3, $julius,  $day(0, '17:45:00'));
$submitAt(8, $bruno,   $day(1, '07:30:00'));
$submitAt(4, $julius,  $day(1, '18:15:00'));

// --- Auszahlungen und ein Bonus ------------------------------------------------
Ledger::book((int)$emilius['id'], -1500, 'Bar ausgezahlt', 'payout', 'manual', null, (int)$thies['id'],
    month_shift(current_month(), -1) . '-27 12:00:00');
Ledger::book((int)$julius['id'],   -800, 'Kino mit Freunden', 'payout', 'manual', null, (int)$birgitta['id'], $day(9, '14:30:00'));
Ledger::book((int)$bruno['id'],     500, 'Zeugnis-Bonus', 'bonus', 'manual', null, (int)$birgitta['id'], $day(12, '18:00:00'));

// --- Feste Ausgaben nachbuchen ---------------------------------------------------
$booked = Billing::run(true);

// --- Handynummern fuer die WhatsApp-Knoepfe ---------------------------------------
// Erfundene Nummern aus dem Bereich, den die Bundesnetzagentur fuer Film und
// Fernsehen reserviert hat - so ruft ein Klick im Beispielbestand niemanden an.
Users::setPhone((int)$thies['id'],    Phone::normalize('0152 28817001'));
Users::setPhone((int)$birgitta['id'], Phone::normalize('0152 28817002'));
Users::setPhone((int)$emilius['id'],  Phone::normalize('0152 28817003'));
Users::setPhone((int)$julius['id'],   Phone::normalize('0152 28817004'));
Users::setPhone((int)$bruno['id'],    Phone::normalize('0152 28817005'));

// --- Ein Zugangslink, damit man sieht, wie er aussieht ----------------------------
Users::createToken((int)$emilius['id']);

// --- Eltern haben ihre PIN bereits gesetzt ---------------------------------------
Users::setPin((int)$thies['id'], '8642', false);
Users::setPin((int)$birgitta['id'], '3579', false);
Users::setPin((int)$emilius['id'], '7788', false);
Users::setPin((int)$julius['id'], '5566', false);
Users::setPin((int)$bruno['id'], '9182', false);

echo "Beispieldaten angelegt.\n\n";
printf("  %-10s %-12s %s\n", 'Profil', 'PIN', 'Guthaben');
echo '  ' . str_repeat('─', 40) . "\n";
foreach (Users::all() as $user) {
    $pin = ['Emilius' => '7788', 'Julius' => '5566', 'Bruno' => '9182', 'Birgitta' => '3579', 'Thies' => '8642'][$user['name']] ?? '?';
    $balance = $user['role'] === 'child' ? Money::format(Ledger::balance((int)$user['id'])) : '–';
    printf("  %-10s %-12s %s\n", $user['name'], $pin, $balance);
}
printf("\n  %d Abbuchungen nachgeholt, %d Meldungen offen.\n\n", $booked, Completions::pendingCount());
