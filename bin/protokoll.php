<?php
declare(strict_types=1);

/**
 * Zeigt das Ende des Fehlerprotokolls.
 *
 *     php bin/protokoll.php          die letzten 40 Zeilen
 *     php bin/protokoll.php 100      die letzten 100
 *     php bin/protokoll.php --leeren Protokoll leeren
 *
 * Die Anwendung gibt Fehler nie an Besucher aus, sondern schreibt sie nach
 * data/php-error.log. Ohne dieses Werkzeug kaeme man an die Datei nur ueber
 * eine SSH-Sitzung - und genau dann, wenn etwas klemmt, will man schnell
 * nachsehen koennen.
 */

require dirname(__DIR__) . '/app/cli.php';

$datei = DATA_DIR . '/php-error.log';

if (in_array('--leeren', $argv, true)) {
    if (is_file($datei)) {
        file_put_contents($datei, '');
        echo "Das Fehlerprotokoll ist geleert.\n";
    } else {
        echo "Es gibt kein Fehlerprotokoll.\n";
    }
    exit(0);
}

if (!is_file($datei)) {
    echo "Es gibt kein Fehlerprotokoll – bisher ist nichts schiefgegangen.\n";
    exit(0);
}

$anzahl = isset($argv[1]) && ctype_digit($argv[1]) ? max(1, (int)$argv[1]) : 40;
$zeilen = preg_split('/\R/', trim((string)file_get_contents($datei))) ?: [];
$zeilen = array_filter($zeilen, static fn (string $z): bool => $z !== '');

printf(
    "Fehlerprotokoll: %d Zeile%s, %s KB, zuletzt %s\n",
    count($zeilen),
    count($zeilen) === 1 ? '' : 'n',
    number_format(filesize($datei) / 1024, 1, ',', '.'),
    date('d.m.Y H:i', (int)filemtime($datei))
);
echo str_repeat('─', 72) . "\n";

foreach (array_slice($zeilen, -$anzahl) as $zeile) {
    echo $zeile . "\n";
}
echo "\n";
