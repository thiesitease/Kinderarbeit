<?php
declare(strict_types=1);

defined('KINDERARBEIT') || exit;

final class Users
{
    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByName(string $name): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE name = :name COLLATE NOCASE');
        $stmt->execute(['name' => $name]);
        return $stmt->fetch() ?: null;
    }

    /** Alle aktiven Profile fuer die Anmeldeseite. */
    public static function all(): array
    {
        return Database::pdo()
            ->query('SELECT * FROM users WHERE is_active = 1 ORDER BY role DESC, sort_order, name')
            ->fetchAll();
    }

    public static function children(): array
    {
        return Database::pdo()
            ->query("SELECT * FROM users WHERE role = 'child' AND is_active = 1 ORDER BY sort_order, name")
            ->fetchAll();
    }

    public static function parents(): array
    {
        return Database::pdo()
            ->query("SELECT * FROM users WHERE role = 'parent' AND is_active = 1 ORDER BY sort_order, name")
            ->fetchAll();
    }

    /** Kinder als id => name. */
    public static function childrenMap(): array
    {
        $map = [];
        foreach (self::children() as $child) {
            $map[(int)$child['id']] = $child['name'];
        }
        return $map;
    }

    public static function setPin(int $id, string $pin, bool $mustChange = false): void
    {
        Database::pdo()->prepare(
            'UPDATE users
                SET pin_hash = :hash, must_change_pin = :must, failed_logins = 0, locked_until = NULL
              WHERE id = :id'
        )->execute([
            'hash' => password_hash($pin, PASSWORD_DEFAULT),
            'must' => $mustChange ? 1 : 0,
            'id'   => $id,
        ]);
    }

    public static function recordFailedLogin(int $id, int $attempts, ?string $lockedUntil): void
    {
        Database::pdo()->prepare(
            'UPDATE users SET failed_logins = :attempts, locked_until = :locked WHERE id = :id'
        )->execute(['attempts' => $attempts, 'locked' => $lockedUntil, 'id' => $id]);
    }

    public static function recordSuccessfulLogin(int $id): void
    {
        Database::pdo()->prepare(
            'UPDATE users SET failed_logins = 0, locked_until = NULL, last_login_at = :now WHERE id = :id'
        )->execute(['now' => now(), 'id' => $id]);
    }

    public static function updateProfile(int $id, string $emoji, string $color): void
    {
        Database::pdo()->prepare('UPDATE users SET emoji = :emoji, color = :color WHERE id = :id')
            ->execute(['emoji' => $emoji, 'color' => $color, 'id' => $id]);
    }

    /** Wer nutzt noch die voreingestellte Start-PIN? */
    public static function withDefaultPin(): array
    {
        return Database::pdo()
            ->query('SELECT * FROM users WHERE must_change_pin = 1 AND is_active = 1 ORDER BY sort_order')
            ->fetchAll();
    }

    /**
     * Persoenlichen Zugangslink erzeugen. Ein vorhandener Link wird dabei
     * ungueltig – wer den alten hat, kommt nicht mehr hinein.
     */
    public static function createToken(int $id): string
    {
        $bisher = self::find($id)['access_token'] ?? null;

        $token = random_token(16);
        Database::pdo()->prepare(
            'UPDATE users SET access_token = :token, token_created_at = :now, token_used_at = NULL WHERE id = :id'
        )->execute(['token' => $token, 'now' => now(), 'id' => $id]);

        // Der bisherige Link soll wirklich nicht mehr gelten. Ohne das
        // bliebe ein Geraet, das damit dauerhaft angemeldet wurde, drin –
        // und genau das ist der Fall, fuer den man den Link neu erzeugt.
        if ($bisher !== null) {
            Remember::forgetLinkDevices($id);
        }

        return $token;
    }

    /** Zugangslink zurueckziehen – samt der damit angemeldeten Geraete. */
    public static function clearToken(int $id): void
    {
        Database::pdo()->prepare(
            'UPDATE users SET access_token = NULL, token_created_at = NULL, token_used_at = NULL WHERE id = :id'
        )->execute(['id' => $id]);

        Remember::forgetLinkDevices($id);
    }

    /** Profil zu einem Zugangslink finden. */
    public static function findByToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE access_token = :token AND is_active = 1');
        $stmt->execute(['token' => $token]);
        return $stmt->fetch() ?: null;
    }

    /** Nutzung eines Zugangslinks festhalten. */
    public static function recordTokenUse(int $id): void
    {
        Database::pdo()->prepare(
            'UPDATE users SET token_used_at = :now, last_login_at = :now, failed_logins = 0, locked_until = NULL WHERE id = :id'
        )->execute(['now' => now(), 'id' => $id]);
    }

    /** Handynummer hinterlegen oder mit null entfernen. */
    public static function setPhone(int $id, ?string $number): void
    {
        Database::pdo()->prepare('UPDATE users SET phone = :phone WHERE id = :id')
            ->execute(['phone' => $number, 'id' => $id]);
    }

    /** Eltern, die per WhatsApp erreichbar sind. */
    public static function parentsWithPhone(): array
    {
        return array_values(array_filter(self::parents(), static fn (array $u): bool => !empty($u['phone'])));
    }

    /** Sperre eines Profils vorzeitig aufheben. */
    public static function unlock(int $id): void
    {
        Database::pdo()->prepare('UPDATE users SET failed_logins = 0, locked_until = NULL WHERE id = :id')
            ->execute(['id' => $id]);
    }

    public static function isLocked(array $user): bool
    {
        return !empty($user['locked_until']) && strtotime((string)$user['locked_until']) > time();
    }
}
