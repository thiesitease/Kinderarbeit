<?php
declare(strict_types=1);

/**
 * Kinderarbeit – Taschengeld fuer erledigte Aufgaben.
 * Einstiegspunkt fuer alle Seitenaufrufe.
 */

define('KINDERARBEIT', '1.1.0');

require __DIR__ . '/app/bootstrap.php';

app_start_session();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

require APP_DIR . '/Controller/AuthController.php';
require APP_DIR . '/Controller/ChildController.php';
require APP_DIR . '/Controller/ParentController.php';
require APP_DIR . '/Controller/TaskController.php';
require APP_DIR . '/Controller/ExpenseController.php';
require APP_DIR . '/Controller/LedgerController.php';
require APP_DIR . '/Controller/FamilyController.php';

// Datenbank oeffnen (legt sie beim ersten Aufruf an) und faellige Abbuchungen nachholen.
Database::pdo();
Billing::run();

// Persoenlicher Zugangslink (?z=...). Nach der Anmeldung wird sofort
// weitergeleitet, damit der Token nicht in der Adresszeile stehen bleibt.
$token = param('z');
if ($token !== '') {
    if (Auth::attemptToken($token)) {
        $user = Auth::user();
        Flash::success('Hallo ' . $user['name'] . '!');
    } else {
        Flash::error('Dieser Link gilt nicht mehr. Bitte Mama oder Papa um einen neuen.');
    }
    redirect('start');
}

$page = param('p', 'start');

View::share('page', $page);
View::share('me', Auth::user());

try {
    switch ($page) {
        // --- Anmeldung -----------------------------------------------------
        case 'login':          AuthController::login();            break;
        case 'logout':         AuthController::logout();           break;
        case 'pin':            AuthController::changePin();        break;

        // --- Startseite ----------------------------------------------------
        case 'start':
            if (!Auth::check()) {
                redirect('login');
            }
            if (Auth::mustChangePin()) {
                redirect('pin');
            }
            Auth::isParent() ? redirect('eltern') : redirect('kind');
            // no break – redirect() beendet das Skript

        // --- Kinderbereich -------------------------------------------------
        case 'kind':           ChildController::dashboard();       break;
        case 'kind-erledigt':  ChildController::complete();        break;
        case 'kind-konto':     ChildController::account();         break;
        case 'kind-verlauf':   ChildController::history();         break;

        // --- Elternbereich -------------------------------------------------
        case 'eltern':         ParentController::dashboard();      break;
        case 'pruefen':        ParentController::decide();         break;
        case 'kinder':         ParentController::childrenOverview(); break;
        case 'kind-detail':    ParentController::childDetail();    break;

        // --- Aufgabenverwaltung --------------------------------------------
        case 'aufgaben':       TaskController::index();            break;
        case 'aufgabe-form':   TaskController::form();             break;
        case 'aufgabe-save':   TaskController::save();             break;
        case 'aufgabe-aktion': TaskController::action();           break;

        // --- Feste Ausgaben -------------------------------------------------
        case 'ausgaben':       ExpenseController::index();         break;
        case 'ausgabe-form':   ExpenseController::form();          break;
        case 'ausgabe-save':   ExpenseController::save();          break;
        case 'ausgabe-aktion': ExpenseController::action();        break;

        // --- Buchungen und Verlauf ------------------------------------------
        case 'buchung':        LedgerController::form();           break;
        case 'buchung-save':   LedgerController::save();           break;
        case 'buchung-aktion': LedgerController::action();         break;
        case 'verlauf':        LedgerController::history();        break;

        // --- Familie und Einstellungen ---------------------------------------
        case 'familie':        FamilyController::index();          break;
        case 'familie-aktion': FamilyController::action();         break;

        default:
            http_response_code(404);
            View::page('error', [
                'title'   => 'Seite nicht gefunden',
                'message' => 'Diese Seite gibt es nicht. Vielleicht hilft ein Klick auf „Start“.',
            ]);
    }
} catch (Throwable $exception) {
    error_log('[Kinderarbeit] ' . $exception->getMessage() . ' @ ' . $exception->getFile() . ':' . $exception->getLine());
    http_response_code(500);
    View::page('error', [
        'title'   => 'Da ist etwas schiefgegangen',
        'message' => 'Die Seite konnte nicht geladen werden. Bitte noch einmal versuchen.',
    ]);
}
