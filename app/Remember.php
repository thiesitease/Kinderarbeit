<?php
declare(strict_types=1);

defined('KINDERARBEIT') || exit;

/**
 * Dauerhaft angemeldet bleiben.
 *
 * Die PHP-Sitzung reicht dafuer nicht. Ihr Cookie lebt zwar 30 Tage, die
 * Sitzungsdatei auf dem Server raeumt PHP aber schon nach
 * `session.gc_maxlifetime` weg – in der Voreinstellung nach 24 Minuten
 * Untaetigkeit. Auf geteiltem Webhosting liegen die Sitzungen obendrein in
 * einem gemeinsamen Verzeichnis, das auch fremde Aufraeumlaeufe leeren.
 * Wer abends den Link bekommt, waere am naechsten Morgen wieder draussen.
 *
 * Deshalb ein eigenes Cookie mit eigenem Token. Kommt jemand ohne Sitzung,
 * aber mit gueltigem Token, wird die Sitzung stillschweigend neu aufgebaut.
 *
 * Aufbau des Tokens: `<selector>:<validator>`. Gesucht wird ueber den
 * selector – der ist kein Geheimnis und darf im Klartext in der Datenbank
 * stehen. Verglichen wird der validator, und zwar gegen seinen SHA-256-Hash
 * und in konstanter Zeit. Wer die Datenbank liest, kann sich damit nicht
 * anmelden; das ist derselbe Gedanke wie beim PIN-Hash.
 */
final class Remember
{
    public const COOKIE   = 'kinderarbeit_dauer';
    public const LIFETIME = 60 * 60 * 24 * 365;   // ein Jahr

    /** So selten wie moeglich schreiben: Ablauf nur einmal taeglich verlaengern. */
    private const REFRESH_AFTER = 60 * 60 * 24;

    /**
     * Dieses Geraet dauerhaft anmelden.
     *
     * $viaLink haelt fest, dass der Zugang ueber den persoenlichen Link kam –
     * dann wird auch spaeter nicht nach der PIN gefragt, sonst waere der
     * bequeme Zugang genau das nicht mehr.
     */
    public static function remember(int $userId, bool $viaLink = false): void
    {
        self::purgeExpired();

        // Meldet sich auf diesem Geraet jemand neu an, ist der bisherige
        // Token nichts mehr wert – sonst bliebe eine Zeile stehen, zu der
        // es kein Cookie mehr gibt.
        self::forget();

        $selector  = bin2hex(random_bytes(9));
        $validator = bin2hex(random_bytes(32));

        Database::pdo()->prepare(
            'INSERT INTO remember_tokens (user_id, selector, validator, via_link, user_agent, created_at, expires_at)
             VALUES (:user, :selector, :validator, :via_link, :agent, :now, :expires)'
        )->execute([
            'user'      => $userId,
            'selector'  => $selector,
            'validator' => hash('sha256', $validator),
            'via_link'  => $viaLink ? 1 : 0,
            'agent'     => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200),
            'now'       => now(),
            'expires'   => date('Y-m-d H:i:s', time() + self::LIFETIME),
        ]);

        self::setCookie($selector . ':' . $validator, time() + self::LIFETIME);
    }

    /**
     * Sitzung aus dem Cookie wiederherstellen.
     *
     * @return array|null das Profil, wenn es geklappt hat
     */
    public static function attempt(): ?array
    {
        $eintrag = self::findByCookie();
        if (!$eintrag) {
            return null;
        }

        $user = Users::find((int)$eintrag['user_id']);
        if (!$user || (int)$user['is_active'] !== 1) {
            self::drop((int)$eintrag['id']);
            self::clearCookie();
            return null;
        }

        $_SESSION['user_id']  = (int)$user['id'];
        $_SESSION['via_link'] = (int)$eintrag['via_link'] === 1;

        self::refresh($eintrag);

        return $user;
    }

    /** Nur dieses Geraet abmelden. */
    public static function forget(): void
    {
        $eintrag = self::findByCookie();
        if ($eintrag) {
            self::drop((int)$eintrag['id']);
        }
        self::clearCookie();
    }

    /**
     * Alle Geraete einer Person abmelden.
     *
     * @return int Anzahl der abgemeldeten Geraete
     */
    public static function forgetAll(int $userId): int
    {
        $stmt = Database::pdo()->prepare('DELETE FROM remember_tokens WHERE user_id = :user');
        $stmt->execute(['user' => $userId]);
        return $stmt->rowCount();
    }

    /**
     * Nur die Geraete abmelden, die ueber den persoenlichen Link hereinkamen.
     *
     * Wird der Link zurueckgezogen oder neu erzeugt, soll der alte wirklich
     * nicht mehr gelten – auch nicht ueber ein Geraet, das damit dauerhaft
     * angemeldet wurde. Wer die PIN benutzt hat, bleibt angemeldet: sein
     * Zugang haengt nicht am Link.
     *
     * @return int Anzahl der abgemeldeten Geraete
     */
    public static function forgetLinkDevices(int $userId): int
    {
        $stmt = Database::pdo()->prepare(
            'DELETE FROM remember_tokens WHERE user_id = :user AND via_link = 1'
        );
        $stmt->execute(['user' => $userId]);
        return $stmt->rowCount();
    }

    /** Angemeldete Geraete je Person, als id => Anzahl. */
    public static function deviceCounts(): array
    {
        self::purgeExpired();

        $rows = Database::pdo()
            ->query('SELECT user_id, COUNT(*) AS anzahl FROM remember_tokens GROUP BY user_id')
            ->fetchAll();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int)$row['user_id']] = (int)$row['anzahl'];
        }
        return $counts;
    }

    // ------------------------------------------------------------- Innereien

    /** Passenden Eintrag zum Cookie finden – oder null. */
    private static function findByCookie(): ?array
    {
        $cookie = (string)($_COOKIE[self::COOKIE] ?? '');
        if (!str_contains($cookie, ':')) {
            return null;
        }

        [$selector, $validator] = explode(':', $cookie, 2);
        if (!preg_match('/^[a-f0-9]{18}$/', $selector) || !preg_match('/^[a-f0-9]{64}$/', $validator)) {
            return null;
        }

        $stmt = Database::pdo()->prepare(
            'SELECT * FROM remember_tokens WHERE selector = :selector AND expires_at > :now'
        );
        $stmt->execute(['selector' => $selector, 'now' => now()]);
        $eintrag = $stmt->fetch();
        if (!$eintrag) {
            return null;
        }

        // Konstante Laufzeit, damit sich der richtige Wert nicht erraten laesst.
        if (!hash_equals((string)$eintrag['validator'], hash('sha256', $validator))) {
            return null;
        }

        return $eintrag;
    }

    /**
     * Ablauf nachschieben, damit ein genutztes Geraet angemeldet bleibt.
     * Hoechstens einmal am Tag – sonst schriebe jeder Seitenaufruf.
     */
    private static function refresh(array $eintrag): void
    {
        $zuletzt = strtotime((string)($eintrag['last_used_at'] ?? '')) ?: 0;
        if (time() - $zuletzt < self::REFRESH_AFTER) {
            return;
        }

        Database::pdo()->prepare(
            'UPDATE remember_tokens SET last_used_at = :now, expires_at = :expires WHERE id = :id'
        )->execute([
            'now'     => now(),
            'expires' => date('Y-m-d H:i:s', time() + self::LIFETIME),
            'id'      => (int)$eintrag['id'],
        ]);

        // Das Cookie selbst laeuft sonst irgendwann ab, obwohl der Token noch gilt.
        self::setCookie((string)$_COOKIE[self::COOKIE], time() + self::LIFETIME);
    }

    private static function drop(int $id): void
    {
        Database::pdo()->prepare('DELETE FROM remember_tokens WHERE id = :id')->execute(['id' => $id]);
    }

    /** Abgelaufene Token wegraeumen. Laeuft nur beim Anmelden, nicht bei jedem Aufruf. */
    private static function purgeExpired(): void
    {
        Database::pdo()->prepare('DELETE FROM remember_tokens WHERE expires_at <= :now')
            ->execute(['now' => now()]);
    }

    /**
     * Cookie setzen. Sind die Kopfzeilen schon raus, ginge das nur noch mit
     * einer Warnung ins Leere – der Eintrag in der Datenbank zaehlt dann
     * trotzdem, und beim naechsten Aufruf sitzt das Cookie richtig.
     */
    private static function setCookie(string $wert, int $ablauf): void
    {
        $_COOKIE[self::COOKIE] = $wert;
        if (headers_sent()) {
            return;
        }

        setcookie(self::COOKIE, $wert, [
            'expires'  => $ablauf,
            'path'     => dirname($_SERVER['SCRIPT_NAME'] ?? '/') ?: '/',
            'httponly' => true,
            'secure'   => is_https(),
            'samesite' => 'Lax',
        ]);
    }

    private static function clearCookie(): void
    {
        unset($_COOKIE[self::COOKIE]);
        if (headers_sent()) {
            return;
        }

        setcookie(self::COOKIE, '', [
            'expires'  => time() - 42000,
            'path'     => dirname($_SERVER['SCRIPT_NAME'] ?? '/') ?: '/',
            'httponly' => true,
            'secure'   => is_https(),
            'samesite' => 'Lax',
        ]);
    }
}
