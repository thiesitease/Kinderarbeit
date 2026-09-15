<?php
declare(strict_types=1);

defined('KINDERARBEIT') || exit;

/**
 * Push-Benachrichtigungen: Abonnements verwalten und Nachrichten verschicken.
 *
 * Ein Abonnement gehoert zu einem Geraet, nicht zu einer Person – wer das
 * Handy und den Rechner anmeldet, hat zwei davon. Deshalb gibt es keine
 * Spalte "an/aus" beim Profil: vorhandenes Abo heisst an, geloescht heisst aus.
 *
 * Verschickt wird mitten im Seitenaufruf. Das Hosting erlaubt keine
 * Hintergrundprozesse (siehe CLAUDE.md), und ein Cronjob waere fuer die paar
 * Nachrichten am Tag uebertrieben. Damit eine langsame oder kaputte
 * Gegenstelle niemanden aufhaelt, laufen alle Zustellungen parallel und
 * jeder Fehler wird geschluckt und geloggt – eine Bestaetigung darf nie
 * daran scheitern, dass Google gerade nicht erreichbar ist.
 */
final class Push
{
    /**
     * Kennung des Absenders fuer den Push-Dienst, wenn die eigene Adresse
     * nicht zu ermitteln ist. Sie dient nur der Rueckfrage durch den Dienst
     * und wird nirgends aufgerufen – deshalb eine neutrale Kennung statt der
     * echten Adresse, die nicht ins Repository gehoert.
     */
    private const SUBJECT_FALLBACK = 'https://kinderarbeit.invalid/';

    // ------------------------------------------------------------ Schluessel

    /**
     * Das VAPID-Schluesselpaar. Es entsteht beim ersten Bedarf und bleibt
     * danach unveraendert: ein neues Paar wuerde alle bestehenden
     * Abonnements ungueltig machen.
     *
     * @return array{private: string, public: string, subject: string}
     */
    public static function keys(): array
    {
        $privat = Billing::setting('push_private_key');
        $public = Billing::setting('push_public_key');

        if ($privat === null || $public === null) {
            $paar   = WebPush::generateKeys();
            $privat = $paar['private_pem'];
            $public = $paar['public'];

            Billing::setSetting('push_private_key', $privat);
            Billing::setSetting('push_public_key', $public);
            Billing::setSetting('push_subject', self::subject());
        }

        return [
            'private' => $privat,
            'public'  => $public,
            'subject' => Billing::setting('push_subject') ?? self::subject(),
        ];
    }

    /** Der oeffentliche Schluessel, den der Browser zum Anmelden braucht. */
    public static function publicKey(): string
    {
        return self::keys()['public'];
    }

    /** Push ist nur moeglich, wenn openssl die Kurve P-256 und AES-GCM kann. */
    public static function isAvailable(): bool
    {
        return extension_loaded('openssl')
            && extension_loaded('curl')
            && function_exists('openssl_pkey_derive')
            && in_array('aes-128-gcm', openssl_get_cipher_methods(), true);
    }

    /** Absenderkennung: die Adresse der Anwendung, solange keine andere gespeichert ist. */
    private static function subject(): string
    {
        if (PHP_SAPI !== 'cli' && !empty($_SERVER['HTTP_HOST'])) {
            return base_url();
        }

        // Auf der Kommandozeile gibt es keinen Hostnamen. Wer von dort aus
        // pushen will, setzt KINDERARBEIT_URL.
        $eigene = trim((string)getenv('KINDERARBEIT_URL'));
        return $eigene !== '' ? rtrim($eigene, '/') . '/' : self::SUBJECT_FALLBACK;
    }

    // ----------------------------------------------------------- Abonnements

    /**
     * Ein Abonnement speichern. Meldet sich dasselbe Geraet erneut an – etwa
     * weil der Browser den Endpunkt erneuert hat – wird der vorhandene
     * Eintrag aktualisiert statt ein zweiter angelegt.
     */
    public static function save(int $userId, string $endpoint, string $p256dh, string $auth, string $agent = ''): void
    {
        Database::pdo()->prepare(
            'INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth, user_agent, created_at)
             VALUES (:user, :endpoint, :p256dh, :auth, :agent, :now)
             ON CONFLICT (endpoint) DO UPDATE SET
                 user_id    = excluded.user_id,
                 p256dh     = excluded.p256dh,
                 auth       = excluded.auth,
                 user_agent = excluded.user_agent,
                 failures   = 0'
        )->execute([
            'user'     => $userId,
            'endpoint' => $endpoint,
            'p256dh'   => $p256dh,
            'auth'     => $auth,
            'agent'    => mb_substr($agent, 0, 200),
            'now'      => now(),
        ]);
    }

    /** Ein Abonnement entfernen – nur das eigene, damit niemand fremde abmeldet. */
    public static function remove(int $userId, string $endpoint): void
    {
        Database::pdo()->prepare('DELETE FROM push_subscriptions WHERE user_id = :user AND endpoint = :endpoint')
            ->execute(['user' => $userId, 'endpoint' => $endpoint]);
    }

    /** Alle Geraete einer Person abmelden. */
    public static function removeAll(int $userId): int
    {
        $stmt = Database::pdo()->prepare('DELETE FROM push_subscriptions WHERE user_id = :user');
        $stmt->execute(['user' => $userId]);
        return $stmt->rowCount();
    }

    /** @return array<int, array<string, mixed>> */
    public static function forUser(int $userId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM push_subscriptions WHERE user_id = :user ORDER BY created_at'
        );
        $stmt->execute(['user' => $userId]);
        return $stmt->fetchAll();
    }

    /** Anzahl angemeldeter Geraete je Person, als id => Anzahl. */
    public static function deviceCounts(): array
    {
        $rows = Database::pdo()
            ->query('SELECT user_id, COUNT(*) AS anzahl FROM push_subscriptions GROUP BY user_id')
            ->fetchAll();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int)$row['user_id']] = (int)$row['anzahl'];
        }
        return $counts;
    }

    // --------------------------------------------------------------- Versand

    /**
     * Nachricht an alle Geraete einer Person.
     *
     * @param array{title:string, body:string, url?:string, tag?:string} $nachricht
     * @return int Anzahl erfolgreicher Zustellungen
     */
    public static function toUser(int $userId, array $nachricht): int
    {
        return self::deliver(self::forUser($userId), $nachricht);
    }

    /** Nachricht an beide Eltern. */
    public static function toParents(array $nachricht): int
    {
        $abos = [];
        foreach (Users::parents() as $elternteil) {
            foreach (self::forUser((int)$elternteil['id']) as $abo) {
                $abos[] = $abo;
            }
        }
        return self::deliver($abos, $nachricht);
    }

    /** Nachricht an alle Kinder. */
    public static function toChildren(array $nachricht): int
    {
        $abos = [];
        foreach (Users::children() as $kind) {
            foreach (self::forUser((int)$kind['id']) as $abo) {
                $abos[] = $abo;
            }
        }
        return self::deliver($abos, $nachricht);
    }

    /**
     * Wirklich verschicken. Wirft nie – der Aufrufer steckt mitten in einer
     * Bestaetigung oder Meldung, und die ist wichtiger als die Nachricht.
     *
     * @param array<int, array<string, mixed>> $abos
     */
    private static function deliver(array $abos, array $nachricht): int
    {
        if (!$abos || !self::isAvailable()) {
            return 0;
        }

        try {
            // Ein Datensatz fasst 4096 Byte. So weit kommt hier nichts, aber
            // eine lange Begruendung der Eltern koennte es versuchen – und
            // auf dem Sperrbildschirm ist ohnehin nach zwei Zeilen Schluss.
            $keys    = self::keys();
            $payload = (string)json_encode([
                'title' => mb_substr((string)$nachricht['title'], 0, 100),
                'body'  => mb_substr((string)$nachricht['body'], 0, 300),
                'url'   => (string)($nachricht['url'] ?? url('start')),
                'tag'   => (string)($nachricht['tag'] ?? 'kinderarbeit'),
            ], JSON_UNESCAPED_UNICODE);

            $antworten = WebPush::sendMany($abos, $payload, $keys['private'], $keys['public'], $keys['subject']);
        } catch (Throwable $exception) {
            error_log('[Kinderarbeit] Push fehlgeschlagen: ' . $exception->getMessage());
            return 0;
        }

        $zugestellt = 0;
        foreach ($antworten as $schluessel => $antwort) {
            $abo    = $abos[$schluessel];
            $status = (int)$antwort['status'];

            if ($status >= 200 && $status < 300) {
                $zugestellt++;
                self::recordSuccess((int)$abo['id']);
                continue;
            }

            // 404/410: der Browser hat das Abo verworfen. Dann ist es
            // endgueltig weg und wird hier ebenfalls geloescht – sonst
            // sammeln sich tote Eintraege an, die jeden Versand verlangsamen.
            if ($status === 404 || $status === 410) {
                self::drop((int)$abo['id']);
                continue;
            }

            self::recordFailure((int)$abo['id']);
            error_log('[Kinderarbeit] Push an Abo ' . (int)$abo['id'] . ': HTTP ' . $status . ' ' . substr((string)$antwort['body'], 0, 200));
        }

        return $zugestellt;
    }

    private static function recordSuccess(int $id): void
    {
        Database::pdo()->prepare('UPDATE push_subscriptions SET last_ok_at = :now, failures = 0 WHERE id = :id')
            ->execute(['now' => now(), 'id' => $id]);
    }

    private static function recordFailure(int $id): void
    {
        Database::pdo()->prepare('UPDATE push_subscriptions SET failures = failures + 1 WHERE id = :id')
            ->execute(['id' => $id]);
    }

    private static function drop(int $id): void
    {
        Database::pdo()->prepare('DELETE FROM push_subscriptions WHERE id = :id')->execute(['id' => $id]);
    }

    // ------------------------------------------------------------- Pruefung

    /**
     * Endpunkt eines Abonnements pruefen.
     *
     * Der Server schickt spaeter selbst eine Anfrage dorthin, deshalb wird
     * hier nicht jede Adresse akzeptiert: nur HTTPS, nur richtige Hostnamen –
     * keine IP-Adressen und nichts, was auf den Server selbst zeigt.
     */
    public static function isValidEndpoint(string $endpoint): bool
    {
        if ($endpoint === '' || strlen($endpoint) > 500) {
            return false;
        }
        if (!filter_var($endpoint, FILTER_VALIDATE_URL)) {
            return false;
        }

        $teile = parse_url($endpoint);
        if (($teile['scheme'] ?? '') !== 'https' || empty($teile['host'])) {
            return false;
        }

        $host = strtolower((string)$teile['host']);
        if (filter_var($host, FILTER_VALIDATE_IP) || !str_contains($host, '.')) {
            return false;
        }

        return !in_array($host, ['localhost', 'localhost.localdomain'], true);
    }

    /** Der oeffentliche Schluessel des Browsers: 65 Byte, unkomprimierter Punkt. */
    public static function isValidP256dh(string $value): bool
    {
        $roh = WebPush::b64urlDecode($value);
        return strlen($roh) === 65 && $roh[0] === "\x04";
    }

    /** Das Anmeldegeheimnis des Browsers: 16 Byte. */
    public static function isValidAuth(string $value): bool
    {
        return strlen(WebPush::b64urlDecode($value)) === 16;
    }
}
