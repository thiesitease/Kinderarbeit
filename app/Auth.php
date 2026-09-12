<?php
declare(strict_types=1);

defined('KINDERARBEIT') || exit;

/**
 * Anmeldung per PIN. Bewusst einfach gehalten: Profil antippen, PIN eingeben.
 * Gegen Raten schuetzt eine Sperre nach mehreren Fehlversuchen.
 */
final class Auth
{
    public const MAX_ATTEMPTS   = 5;
    public const LOCK_MINUTES   = 10;
    public const MIN_PIN_LENGTH = 4;
    public const MAX_PIN_LENGTH = 10;

    private static ?array $user = null;

    /** Aktuell angemeldeter Benutzer oder null. */
    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }
        $id = (int)($_SESSION['user_id'] ?? 0);
        if ($id <= 0) {
            return null;
        }
        $user = Users::find($id);
        if (!$user || (int)$user['is_active'] !== 1) {
            self::logout();
            return null;
        }
        return self::$user = $user;
    }

    public static function id(): int
    {
        return (int)(self::user()['id'] ?? 0);
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isParent(): bool
    {
        return (self::user()['role'] ?? '') === 'parent';
    }

    public static function isChild(): bool
    {
        return (self::user()['role'] ?? '') === 'child';
    }

    /**
     * Muss der Benutzer seine Standard-PIN noch aendern?
     *
     * Wer ueber den persoenlichen Link hereinkommt, braucht keine PIN – der
     * Link ist bereits der Nachweis. Sonst waere der bequeme Zugang genau
     * das nicht mehr.
     */
    public static function mustChangePin(): bool
    {
        if (!empty($_SESSION['via_link'])) {
            return false;
        }
        return (int)(self::user()['must_change_pin'] ?? 0) === 1;
    }

    /** Wurde diese Sitzung ueber einen Zugangslink geoeffnet? */
    public static function viaLink(): bool
    {
        return !empty($_SESSION['via_link']);
    }

    /**
     * Anmeldung ueber einen persoenlichen Zugangslink.
     * Der Token selbst ist der Nachweis, deshalb wird keine PIN abgefragt.
     */
    public static function attemptToken(string $token): bool
    {
        $user = Users::findByToken($token);
        if (!$user) {
            return false;
        }

        Users::recordTokenUse((int)$user['id']);
        session_regenerate_id(true);
        $_SESSION['user_id']  = (int)$user['id'];
        $_SESSION['via_link'] = true;
        self::$user = null;

        return true;
    }

    /**
     * Anmeldeversuch. Gibt bei Erfolg true zurueck, sonst false und setzt $error.
     */
    public static function attempt(int $userId, string $pin, ?string &$error = null): bool
    {
        $user = Users::find($userId);
        if (!$user || (int)$user['is_active'] !== 1) {
            $error = 'Dieses Profil gibt es nicht.';
            return false;
        }

        if (!empty($user['locked_until']) && strtotime((string)$user['locked_until']) > time()) {
            $minutes = max(1, (int)ceil((strtotime((string)$user['locked_until']) - time()) / 60));
            $error = 'Zu viele Fehlversuche. Bitte in ' . $minutes . ' Minute' . ($minutes === 1 ? '' : 'n') . ' erneut versuchen.';
            return false;
        }

        if (!password_verify($pin, (string)$user['pin_hash'])) {
            $attempts = (int)$user['failed_logins'] + 1;
            $lockedUntil = $attempts >= self::MAX_ATTEMPTS
                ? date('Y-m-d H:i:s', time() + self::LOCK_MINUTES * 60)
                : null;
            Users::recordFailedLogin((int)$user['id'], $attempts, $lockedUntil);

            $error = $lockedUntil
                ? 'PIN falsch. Das Profil ist jetzt für ' . self::LOCK_MINUTES . ' Minuten gesperrt.'
                : 'Die PIN stimmt nicht. Noch ' . (self::MAX_ATTEMPTS - $attempts) . ' Versuch'
                  . (self::MAX_ATTEMPTS - $attempts === 1 ? '' : 'e') . '.';
            return false;
        }

        // PIN-Hash bei Bedarf auf ein neueres Verfahren heben.
        if (password_needs_rehash((string)$user['pin_hash'], PASSWORD_DEFAULT)) {
            Users::setPin((int)$user['id'], $pin, (int)$user['must_change_pin'] === 1);
        }

        Users::recordSuccessfulLogin((int)$user['id']);
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        self::$user = null;

        return true;
    }

    public static function logout(): void
    {
        self::$user = null;
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
    }

    /** Seite nur fuer angemeldete Benutzer. */
    public static function requireLogin(): array
    {
        $user = self::user();
        if (!$user) {
            $_SESSION['intended'] = $_SERVER['REQUEST_URI'] ?? null;
            redirect('login');
        }
        return $user;
    }

    /** Seite nur fuer Eltern. */
    public static function requireParent(): array
    {
        $user = self::requireLogin();
        if ($user['role'] !== 'parent') {
            Flash::error('Diesen Bereich dürfen nur Mama und Papa öffnen.');
            redirect('start');
        }
        return $user;
    }

    /** Seite nur fuer Kinder. */
    public static function requireChild(): array
    {
        $user = self::requireLogin();
        if ($user['role'] !== 'child') {
            redirect('eltern');
        }
        return $user;
    }

    /** PIN-Regeln pruefen. */
    public static function validatePin(string $pin, ?string &$error = null): bool
    {
        return validate_pin($pin, $error, self::MIN_PIN_LENGTH, self::MAX_PIN_LENGTH);
    }
}
