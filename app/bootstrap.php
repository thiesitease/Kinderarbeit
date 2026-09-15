<?php
declare(strict_types=1);

/**
 * Kinderarbeit – zentrale Initialisierung.
 * Wird ausschliesslich von der index.php im Web-Root eingebunden.
 */

if (!defined('KINDERARBEIT')) {
    http_response_code(403);
    exit('Direkter Aufruf nicht erlaubt.');
}

if (PHP_VERSION_ID < 80000) {
    exit('Kinderarbeit benoetigt mindestens PHP 8.0. Gefunden: ' . PHP_VERSION);
}

define('APP_ROOT', dirname(__DIR__));
define('APP_DIR', __DIR__);
define('DATA_DIR', APP_ROOT . '/data');
define('DB_FILE', DATA_DIR . '/kinderarbeit.sqlite');

date_default_timezone_set('Europe/Berlin');
mb_internal_encoding('UTF-8');
setlocale(LC_ALL, 'de_DE.UTF-8', 'de_DE', 'German');

// Fehler werden geloggt, aber nie an Besucher ausgegeben.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', DATA_DIR . '/php-error.log');

require APP_DIR . '/helpers.php';
require APP_DIR . '/Money.php';
require APP_DIR . '/Phone.php';
require APP_DIR . '/Database.php';
require APP_DIR . '/Csrf.php';
require APP_DIR . '/Remember.php';
require APP_DIR . '/Auth.php';
require APP_DIR . '/Flash.php';
require APP_DIR . '/Billing.php';
require APP_DIR . '/WebPush.php';
require APP_DIR . '/View.php';
require APP_DIR . '/Repo/Users.php';
require APP_DIR . '/Repo/Tasks.php';
require APP_DIR . '/Repo/Completions.php';
require APP_DIR . '/Repo/Ledger.php';
require APP_DIR . '/Repo/Expenses.php';
require APP_DIR . '/Repo/Push.php';

/** Session sicher starten (Cookie nur via HTTP, SameSite=Lax, Secure wenn HTTPS). */
function app_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // Eigenes Verzeichnis fuer die Sitzungsdateien. Auf geteiltem Webhosting
    // liegen sie sonst in einem gemeinsamen Verzeichnis, aus dem andere
    // Websites desselben Servers lesen koennen und das deren Aufraeumlaeufe
    // mitleeren. Wer das Verzeichnis uebernimmt, muss allerdings auch selbst
    // aufraeumen – deshalb die beiden gc-Werte: sonst blieben die Dateien
    // fuer immer liegen.
    $sitzungen = DATA_DIR . '/sessions';
    if (is_dir($sitzungen) || @mkdir($sitzungen, 0700, true)) {
        ini_set('session.gc_maxlifetime', (string)(60 * 60 * 24 * 30));
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '500');
        session_save_path($sitzungen);
    }

    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 30,
        'path'     => dirname($_SERVER['SCRIPT_NAME'] ?? '/') ?: '/',
        'httponly' => true,
        'secure'   => is_https(),
        'samesite' => 'Lax',
    ]);
    session_name('kinderarbeit');
    session_start();
}

