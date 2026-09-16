<?php
declare(strict_types=1);

/**
 * Setzt eine PIN neu – der Rettungsanker, falls sich niemand mehr anmelden kann.
 *
 *     php bin/reset-pin.php Thies 4711
 *     php bin/reset-pin.php Bruno --start    zurueck auf die Start-PIN
 *
 * Die betroffene Person muss die PIN beim naechsten Anmelden selbst aendern.
 * Ohne Argumente werden alle Profile aufgelistet.
 *
 * "--start" nimmt die PIN aus Database::SEED_USERS. Die steht ohnehin im
 * Quelltext und ist damit oeffentlich - deshalb taugt sie nur als Notnagel,
 * und deshalb laesst sich der Aufruf auch ueber den Wartungslauf ausloesen,
 * ohne dass irgendwo eine PIN im Klartext protokolliert wird.
 */

require dirname(__DIR__) . '/app/cli.php';

$name = $argv[1] ?? null;
$pin  = $argv[2] ?? null;

$startPin = false;

if ($name !== null && $pin === '--start') {
    $startPin = true;
    $pin = null;
    foreach (Database::SEED_USERS as $vorlage) {
        if (strcasecmp($vorlage['name'], $name) === 0) {
            $pin = $vorlage['pin'];
            break;
        }
    }
    if ($pin === null) {
        fwrite(STDERR, "Fuer \"{$name}\" gibt es keine Start-PIN.\n");
        exit(1);
    }
}

if ($name === null || $pin === null) {
    echo "Aufruf: php bin/reset-pin.php <Name> <neue PIN>\n";
    echo "        php bin/reset-pin.php <Name> --start\n\n";
    echo "Vorhandene Profile:\n";
    foreach (Users::all() as $user) {
        printf(
            "  %-10s %-12s %s\n",
            $user['name'],
            $user['role'] === 'parent' ? 'Eltern' : 'Kind',
            Users::isLocked($user) ? '(zurzeit gesperrt)' : ''
        );
    }
    echo "\n";
    exit(1);
}

$user = Users::findByName($name);
if (!$user) {
    fwrite(STDERR, "Es gibt kein Profil mit dem Namen \"{$name}\".\n");
    exit(1);
}

// Die Start-PINs bestehen absichtlich aus lauter gleichen Ziffern und fallen
// deshalb durch die eigene Pruefung. Genau das sollen sie: sie taugen nur,
// um wieder hineinzukommen, und muessen beim naechsten Anmelden gewechselt
// werden. Fuer alles andere gelten die Regeln.
if (!$startPin) {
    $error = null;
    if (!validate_pin($pin, $error)) {
        fwrite(STDERR, $error . "\n");
        exit(1);
    }
}

Users::setPin((int)$user['id'], $pin, true);
Users::unlock((int)$user['id']);

echo "Neue PIN für {$user['name']} gesetzt: {$pin}\n";
echo "Beim nächsten Anmelden wird nach einer eigenen PIN gefragt.\n";
