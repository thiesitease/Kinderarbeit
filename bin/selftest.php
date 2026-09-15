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

foreach (['helpers', 'Money', 'Phone', 'Database', 'Billing', 'WebPush', 'Repo/Users', 'Repo/Tasks', 'Repo/Completions', 'Repo/Ledger', 'Repo/Expenses', 'Repo/Push'] as $file) {
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

echo "\nHandynummern\n";
check('"0171 1234567"',              Phone::normalize('0171 1234567'), '491711234567');
check('"+49 171 1234567"',           Phone::normalize('+49 171 1234567'), '491711234567');
check('"0049 171 1234567"',          Phone::normalize('0049 171 1234567'), '491711234567');
check('"+49-171-123 45 67"',         Phone::normalize('+49-171-123 45 67'), '491711234567');
check('"(0171) 1234567"',            Phone::normalize('(0171) 1234567'), '491711234567');
check('"491711234567"',              Phone::normalize('491711234567'), '491711234567');
check('Oesterreich "+43 664 1234567"', Phone::normalize('+43 664 1234567'), '436641234567');
check('Leereingabe',                 Phone::normalize(''), null);
check('Nur Buchstaben',              Phone::normalize('keine Nummer'), null);
check('Zu kurz',                     Phone::normalize('0171 12'), null);
check('Zu lang',                     Phone::normalize('+49 171 123456789012345'), null);

check('Anzeige deutsch',             Phone::format('491711234567'), '+49 171 1234567');
check('Anzeige ausländisch',         Phone::format('436641234567'), '+436641234567');
check('Anzeige ohne Nummer',         Phone::format(null), '');

check('Link enthaelt die Nummer',
      str_starts_with((string)Phone::waLink('491711234567', 'Hallo'), 'https://wa.me/491711234567?text='), true);
check('Text wird kodiert',
      str_contains((string)Phone::waLink('491711234567', 'Hallo Welt & mehr'), 'Hallo%20Welt%20%26%20mehr'), true);
check('Ohne Nummer kein Link',       Phone::waLink(null, 'Hallo'), null);

$emiliusId = (int)Users::findByName('Emilius')['id'];
check('Zu Beginn keine Nummer',      Users::find($emiliusId)['phone'], null);
Users::setPhone($emiliusId, '491711234567');
check('Nummer gespeichert',          Users::find($emiliusId)['phone'], '491711234567');
check('Noch kein Elternteil erreichbar', count(Users::parentsWithPhone()), 0);
Users::setPhone((int)Users::findByName('Thies')['id'], '491715550000');
check('Ein Elternteil erreichbar',   count(Users::parentsWithPhone()), 1);
Users::setPhone($emiliusId, null);
check('Nummer wieder entfernt',      Users::find($emiliusId)['phone'], null);

check('Spalte phone vorhanden',
      in_array('phone', $pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_COLUMN, 1), true), true);

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

echo "\nWeb-Push: Verschluesselung (RFC 8291, Anhang A)\n";

// Der Testvektor aus dem Standard. Stimmt die Ausgabe damit Byte fuer Byte
// ueberein, versteht auch jeder Browser die verschickten Nachrichten.
$vektor = [
    'klartext'  => 'V2hlbiBJIGdyb3cgdXAsIEkgd2FudCB0byBiZSBhIHdhdGVybWVsb24',
    'salz'      => 'DGv6ra1nlYgDCS1FRnbzlw',
    'empfaenger'=> 'BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcxaOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4',
    'absender'  => 'BP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8',
    'geheim'    => 'yfWPiYE-n46HLnH0KqZOF1fJJU3MYrct3AELtAQ-oRw',
    'auth'      => 'BTBZMqHH6r4Tts7J_aSIgg',
    'erwartet'  => 'DGv6ra1nlYgDCS1FRnbzlwAAEABBBP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLoc'
                 . 'InmYWAmS6TlzAC8wEqKK6PBru3jl7A_yl95bQpu6cVPTpK4Mqgkf1CXztLVBSt2Ks3oZwbuwXPXLWyouBWLV'
                 . 'WGNWQexSgSxsj_Qulcy4a-fN',
];

check('base64url hin und zurueck',
      WebPush::b64url(WebPush::b64urlDecode($vektor['salz'])), $vektor['salz']);
check('Salz ist 16 Byte',
      strlen(WebPush::b64urlDecode($vektor['salz'])), 16);

$serverSchluessel = WebPush::privateKeyFromRaw(
    WebPush::b64urlDecode($vektor['geheim']),
    WebPush::b64urlDecode($vektor['absender'])
);

$verschluesselt = WebPush::encrypt(
    WebPush::b64urlDecode($vektor['klartext']),
    $vektor['empfaenger'],
    $vektor['auth'],
    WebPush::b64urlDecode($vektor['salz']),
    $serverSchluessel
);

check('Nachricht entspricht dem Testvektor', WebPush::b64url($verschluesselt), $vektor['erwartet']);
check('Erste 16 Byte sind das Salz',         substr($verschluesselt, 0, 16), WebPush::b64urlDecode($vektor['salz']));
check('Datensatzlaenge steht im Kopf',       unpack('N', substr($verschluesselt, 16, 4))[1], 4096);
check('Laenge des Absenderpunkts steht da',  ord($verschluesselt[20]), 65);
check('Absenderpunkt steht im Kopf',         substr($verschluesselt, 21, 65), WebPush::b64urlDecode($vektor['absender']));

// Zufaelliges Salz: dieselbe Nachricht darf nie zweimal gleich aussehen.
$zufall1 = WebPush::encrypt('hallo', $vektor['empfaenger'], $vektor['auth']);
$zufall2 = WebPush::encrypt('hallo', $vektor['empfaenger'], $vektor['auth']);
check('Zweimal verschluesselt ergibt Verschiedenes', $zufall1 === $zufall2, false);
check('Laenge stimmt (Kopf + Text + 0x02 + Pruefsumme)', strlen($zufall1), 86 + 5 + 1 + 16);

echo "\nWeb-Push: VAPID\n";

$paar = WebPush::generateKeys();
check('Oeffentlicher Schluessel ist 65 Byte', strlen(WebPush::b64urlDecode($paar['public'])), 65);
check('Oeffentlicher Schluessel ist ein unkomprimierter Punkt',
      WebPush::b64urlDecode($paar['public'])[0], "\x04");
check('Privater Schluessel ist ein PEM',
      str_starts_with($paar['private_pem'], '-----BEGIN'), true);

$kopfzeile = WebPush::vapidHeader(
    'https://fcm.googleapis.com/fcm/send/abcdef',
    $paar['private_pem'],
    $paar['public'],
    'https://kinderarbeit.example.de/'
);

check('Kopfzeile beginnt mit "vapid "', str_starts_with($kopfzeile, 'vapid t='), true);
check('Kopfzeile nennt den oeffentlichen Schluessel',
      str_contains($kopfzeile, ', k=' . $paar['public']), true);

$jwt = substr($kopfzeile, strlen('vapid t='), strpos($kopfzeile, ', k=') - strlen('vapid t='));
$teile = explode('.', $jwt);
check('JWT hat drei Teile', count($teile), 3);

$jwtKopf = json_decode(WebPush::b64urlDecode($teile[0]), true);
check('JWT nennt ES256', $jwtKopf['alg'] ?? null, 'ES256');
check('JWT ist als JWT ausgewiesen', $jwtKopf['typ'] ?? null, 'JWT');

$jwtNutz = json_decode(WebPush::b64urlDecode($teile[1]), true);
check('Empfaenger ist der Push-Dienst ohne Pfad', $jwtNutz['aud'] ?? null, 'https://fcm.googleapis.com');
check('Absender ist gesetzt', $jwtNutz['sub'] ?? null, 'https://kinderarbeit.example.de/');
check('Ablauf liegt in der Zukunft', ($jwtNutz['exp'] ?? 0) > time(), true);
check('Ablauf liegt hoechstens 24 Stunden weg', ($jwtNutz['exp'] ?? 0) <= time() + 86400, true);

// Die Signatur wirklich pruefen: R||S zurueck nach DER und openssl fragen.
// Damit ist auch signatureToRaw() getestet, und nicht nur die Form.
$roh = WebPush::b64urlDecode($teile[2]);
check('Signatur ist 64 Byte', strlen($roh), 64);

$alsInteger = static function (string $zahl): string {
    $zahl = ltrim($zahl, "\x00");
    if ($zahl === '' || ord($zahl[0]) > 0x7f) {
        $zahl = "\x00" . $zahl;
    }
    return "\x02" . chr(strlen($zahl)) . $zahl;
};
$folge = $alsInteger(substr($roh, 0, 32)) . $alsInteger(substr($roh, 32, 32));
$der   = "\x30" . chr(strlen($folge)) . $folge;

check('Signatur ist gueltig',
      openssl_verify($teile[0] . '.' . $teile[1], $der, openssl_pkey_get_public(
          "-----BEGIN PUBLIC KEY-----\n"
          . chunk_split(base64_encode(
              "\x30\x59\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\x03\x42\x00"
              . WebPush::b64urlDecode($paar['public'])
            ), 64, "\n")
          . "-----END PUBLIC KEY-----\n"
      ), OPENSSL_ALGO_SHA256), 1);

check('Verfaelschte Signatur faellt auf',
      openssl_verify('x' . $teile[0] . '.' . $teile[1], $der, openssl_pkey_get_public(
          "-----BEGIN PUBLIC KEY-----\n"
          . chunk_split(base64_encode(
              "\x30\x59\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\x03\x42\x00"
              . WebPush::b64urlDecode($paar['public'])
            ), 64, "\n")
          . "-----END PUBLIC KEY-----\n"
      ), OPENSSL_ALGO_SHA256), 0);

echo "\nWeb-Push: Abonnements\n";

check('Spalte push_subscriptions vorhanden',
      in_array('push_subscriptions', $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN), true), true);

check('Gueltiger Endpunkt',            Push::isValidEndpoint('https://fcm.googleapis.com/fcm/send/abc'), true);
check('HTTP wird abgelehnt',           Push::isValidEndpoint('http://fcm.googleapis.com/fcm/send/abc'), false);
check('IP-Adresse wird abgelehnt',     Push::isValidEndpoint('https://192.168.1.10/push'), false);
check('localhost wird abgelehnt',      Push::isValidEndpoint('https://localhost/push'), false);
check('Hostname ohne Punkt abgelehnt', Push::isValidEndpoint('https://intern/push'), false);
check('Leerer Endpunkt abgelehnt',     Push::isValidEndpoint(''), false);
check('Zu langer Endpunkt abgelehnt',  Push::isValidEndpoint('https://push.example.com/' . str_repeat('a', 500)), false);
check('Unsinn wird abgelehnt',         Push::isValidEndpoint('javascript:alert(1)'), false);

check('Gueltiger Browser-Schluessel',  Push::isValidP256dh($vektor['empfaenger']), true);
check('Zu kurzer Schluessel abgelehnt',Push::isValidP256dh('abc'), false);
check('Komprimierter Punkt abgelehnt', Push::isValidP256dh(WebPush::b64url("\x02" . str_repeat("\x01", 64))), false);
check('Gueltiges Geheimnis',           Push::isValidAuth($vektor['auth']), true);
check('Zu kurzes Geheimnis abgelehnt', Push::isValidAuth(WebPush::b64url('kurz')), false);

$endpunktA = 'https://push.example.com/geraet-a';
$endpunktB = 'https://push.example.com/geraet-b';

Push::save($childId, $endpunktA, $vektor['empfaenger'], $vektor['auth'], 'Testbrowser');
check('Ein Geraet angemeldet',        count(Push::forUser($childId)), 1);

Push::save($childId, $endpunktB, $vektor['empfaenger'], $vektor['auth'], 'Zweiter Testbrowser');
check('Zwei Geraete angemeldet',      count(Push::forUser($childId)), 2);

// Dasselbe Geraet noch einmal: kein zweiter Eintrag, sondern eine Aktualisierung.
Push::save($childId, $endpunktA, $vektor['empfaenger'], $vektor['auth'], 'Testbrowser neu');
check('Erneute Anmeldung legt nichts doppelt an', count(Push::forUser($childId)), 2);

// Wechselt am selben Geraet die Person, wechselt auch der Eintrag mit.
Push::save((int)$julius['id'], $endpunktA, $vektor['empfaenger'], $vektor['auth'], 'Testbrowser');
check('Geraet gehoert jetzt jemand anderem', count(Push::forUser($childId)), 1);
check('Und taucht dort auf',                 count(Push::forUser((int)$julius['id'])), 1);

$zaehler = Push::deviceCounts();
check('Geraetezahl fuer das erste Kind', $zaehler[$childId] ?? 0, 1);
check('Geraetezahl fuer das zweite Kind', $zaehler[(int)$julius['id']] ?? 0, 1);

// Niemand meldet fremde Geraete ab.
Push::remove($childId, $endpunktA);
check('Fremdes Geraet bleibt angemeldet', count(Push::forUser((int)$julius['id'])), 1);

Push::remove((int)$julius['id'], $endpunktA);
check('Eigenes Geraet ist abgemeldet',    count(Push::forUser((int)$julius['id'])), 0);

Push::save($childId, $endpunktA, $vektor['empfaenger'], $vektor['auth']);
check('Alle Geraete abmelden gibt die Anzahl zurueck', Push::removeAll($childId), 2);
check('Danach ist niemand mehr angemeldet',            count(Push::forUser($childId)), 0);

// Ohne Abonnement wird nichts verschickt – und nichts geht kaputt.
check('Versand ohne Empfaenger meldet 0', Push::toUser($childId, ['title' => 'x', 'body' => 'y']), 0);
check('Versand an die Eltern meldet 0',   Push::toParents(['title' => 'x', 'body' => 'y']), 0);

// Das Schluesselpaar entsteht einmal und bleibt danach gleich.
$schluesselA = Push::publicKey();
$schluesselB = Push::publicKey();
check('Oeffentlicher Schluessel bleibt gleich', $schluesselA, $schluesselB);
check('Schluessel liegt in den Einstellungen',  Billing::setting('push_public_key'), $schluesselA);

// Wer geloescht wird, nimmt seine Abonnements mit – sonst bekaeme das
// naechste Profil mit derselben id fremde Benachrichtigungen.
$pdo->exec('PRAGMA foreign_keys = ON');
$bruno = Users::findByName('Bruno');
Push::save((int)$bruno['id'], 'https://push.example.com/bruno', $vektor['empfaenger'], $vektor['auth']);
check('Bruno hat ein Geraet',          count(Push::forUser((int)$bruno['id'])), 1);

$pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => (int)$bruno['id']]);
check('Geloeschtes Profil hat keine Abos mehr', count(Push::forUser((int)$bruno['id'])), 0);

// Aufraeumen
foreach (glob($tmp . '/*') ?: [] as $file) {
    @unlink($file);
}
@rmdir($tmp);

echo "\n" . str_repeat('─', 44) . "\n";
printf("  Bestanden: %d    Fehler: %d\n", $passed, $failed);
echo str_repeat('─', 44) . "\n\n";

exit($failed === 0 ? 0 : 1);
