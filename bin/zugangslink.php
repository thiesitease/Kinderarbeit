<?php
declare(strict_types=1);

/**
 * Persoenliche Zugangslinks anzeigen oder neu erzeugen.
 *
 *     php bin/zugangslink.php                      alle Links anzeigen
 *     php bin/zugangslink.php Emilius              neuen Link fuer Emilius erzeugen
 *     php bin/zugangslink.php Emilius --zuruecknehmen
 *
 * Normalerweise macht man das im Elternbereich unter „Familie“. Dieses
 * Werkzeug hilft, wenn niemand mehr hineinkommt.
 */

require dirname(__DIR__) . '/app/cli.php';

// Die Adresse der Anwendung steht bewusst nicht im Repository. Ohne
// KINDERARBEIT_URL zeigt das Werkzeug nur den Teil hinter der Adresse.
$base = trim((string)getenv('KINDERARBEIT_URL'));
$base = $base !== '' ? rtrim($base, '/') . '/' : '…/';

$link = static fn (string $token): string => $base . '?z=' . $token;

$name   = $argv[1] ?? null;
$revoke = in_array('--zuruecknehmen', $argv, true);

if ($name === null) {
    echo "Persönliche Zugangslinks\n";
    echo str_repeat('─', 72) . "\n";
    foreach (Users::all() as $user) {
        printf("%-10s %s\n", $user['name'], $user['access_token'] ? $link((string)$user['access_token']) : '– kein Link –');
    }
    echo str_repeat('─', 72) . "\n";
    echo "Neuen Link erzeugen: php bin/zugangslink.php <Name>\n";
    echo "Volle Links:         KINDERARBEIT_URL=https://… php bin/zugangslink.php\n\n";
    exit(0);
}

$user = Users::findByName($name);
if (!$user) {
    fwrite(STDERR, "Es gibt kein Profil mit dem Namen \"{$name}\".\n");
    exit(1);
}

if ($revoke) {
    Users::clearToken((int)$user['id']);
    echo "Der Zugangslink von {$user['name']} wurde zurückgezogen.\n";
    exit(0);
}

$token = Users::createToken((int)$user['id']);
echo "Neuer Zugangslink für {$user['name']}:\n\n";
echo '  ' . $link($token) . "\n\n";
echo "Ein eventuell vorher verschickter Link funktioniert ab jetzt nicht mehr.\n";
