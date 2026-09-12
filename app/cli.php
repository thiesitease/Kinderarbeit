<?php
declare(strict_types=1);

/**
 * Gemeinsame Initialisierung fuer die Werkzeuge in bin/.
 */

if (PHP_SAPI !== 'cli') {
    exit('Dieses Skript laeuft nur auf der Kommandozeile.');
}

if (!defined('KINDERARBEIT')) {
    define('KINDERARBEIT', 'cli');
}

$root = dirname(__DIR__);

define('APP_ROOT', $root);
define('APP_DIR', $root . '/app');
define('DATA_DIR', $root . '/data');
define('DB_FILE', DATA_DIR . '/kinderarbeit.sqlite');

date_default_timezone_set('Europe/Berlin');
mb_internal_encoding('UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', '1');

foreach ([
    'helpers', 'Money', 'Database', 'Billing',
    'Repo/Users', 'Repo/Tasks', 'Repo/Completions', 'Repo/Ledger', 'Repo/Expenses',
] as $file) {
    require APP_DIR . '/' . $file . '.php';
}
