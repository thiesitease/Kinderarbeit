<?php
declare(strict_types=1);

/**
 * Setzt eine PIN neu – der Rettungsanker, falls sich niemand mehr anmelden kann.
 *
 *     php bin/reset-pin.php Thies 4711
 *
 * Die betroffene Person muss die PIN beim naechsten Anmelden selbst aendern.
 * Ohne Argumente werden alle Profile aufgelistet.
 */

require dirname(__DIR__) . '/app/cli.php';

$name = $argv[1] ?? null;
$pin  = $argv[2] ?? null;

if ($name === null || $pin === null) {
    echo "Aufruf: php bin/reset-pin.php <Name> <neue PIN>\n\n";
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

$error = null;
if (!validate_pin($pin, $error)) {
    fwrite(STDERR, $error . "\n");
    exit(1);
}

Users::setPin((int)$user['id'], $pin, true);
Users::unlock((int)$user['id']);

echo "Neue PIN für {$user['name']} gesetzt: {$pin}\n";
echo "Beim nächsten Anmelden wird nach einer eigenen PIN gefragt.\n";
