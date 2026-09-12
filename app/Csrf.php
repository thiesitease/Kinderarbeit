<?php
declare(strict_types=1);

defined('KINDERARBEIT') || exit;

/** Schutz vor Cross-Site-Request-Forgery bei allen Formularen. */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = random_token();
        }
        return $_SESSION['csrf_token'];
    }

    /** Verstecktes Formularfeld. */
    public static function field(): string
    {
        return '<input type="hidden" name="csrf" value="' . e(self::token()) . '">';
    }

    public static function isValid(?string $token): bool
    {
        return is_string($token)
            && !empty($_SESSION['csrf_token'])
            && hash_equals((string)$_SESSION['csrf_token'], $token);
    }

    /** Bei ungueltigem Token abbrechen. */
    public static function check(): void
    {
        if (!self::isValid($_POST['csrf'] ?? null)) {
            http_response_code(400);
            exit('Sicherheitsprüfung fehlgeschlagen. Bitte die Seite neu laden und erneut versuchen.');
        }
    }
}
