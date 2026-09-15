<?php
declare(strict_types=1);

defined('KINDERARBEIT') || exit;

/**
 * An- und Abmelden von Push-Benachrichtigungen.
 *
 * Beide Seiten antworten mit JSON, weil sie aus dem Browser heraus im
 * Hintergrund aufgerufen werden. Ohne JavaScript werden sie nie erreicht –
 * dann gibt es eben keine Benachrichtigungen, alles andere funktioniert.
 */
final class PushController
{
    /** Dieses Geraet fuer Benachrichtigungen anmelden. */
    public static function subscribe(): void
    {
        $me = self::begin();

        $endpoint = param('endpoint');
        $p256dh   = param('p256dh');
        $auth     = param('auth');

        if (!Push::isValidEndpoint($endpoint)) {
            self::json(['ok' => false, 'error' => 'Die Adresse des Push-Dienstes ist unbrauchbar.'], 400);
        }
        if (!Push::isValidP256dh($p256dh) || !Push::isValidAuth($auth)) {
            self::json(['ok' => false, 'error' => 'Die Schlüssel des Browsers sind unbrauchbar.'], 400);
        }

        Push::save((int)$me['id'], $endpoint, $p256dh, $auth, (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));

        self::json(['ok' => true, 'geraete' => count(Push::forUser((int)$me['id']))]);
    }

    /** Dieses Geraet wieder abmelden. */
    public static function unsubscribe(): void
    {
        $me = self::begin();

        Push::remove((int)$me['id'], param('endpoint'));

        self::json(['ok' => true, 'geraete' => count(Push::forUser((int)$me['id']))]);
    }

    /**
     * Gemeinsamer Auftakt: angemeldet, POST, gueltiges Token.
     *
     * Csrf::check() wuerde hier mit HTML antworten; an dieser Stelle waere das
     * fuer den Browser unlesbar, deshalb die eigene JSON-Antwort.
     */
    private static function begin(): array
    {
        $me = Auth::user();
        if (!$me) {
            // Nicht auf die Anmeldeseite umleiten: der Browser fragt hier im
            // Hintergrund und kann mit einer HTML-Seite nichts anfangen.
            self::json(['ok' => false, 'error' => 'Nicht angemeldet.'], 401);
        }

        if (!is_post()) {
            self::json(['ok' => false, 'error' => 'Nur per POST.'], 405);
        }
        if (!Csrf::isValid($_POST['csrf'] ?? null)) {
            self::json(['ok' => false, 'error' => 'Sicherheitsprüfung fehlgeschlagen. Bitte die Seite neu laden.'], 400);
        }

        return $me;
    }

    private static function json(array $daten, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo (string)json_encode($daten, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
