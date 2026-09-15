<?php
declare(strict_types=1);

defined('KINDERARBEIT') || exit;

/**
 * Web-Push nach RFC 8291 (Verschlüsselung) und RFC 8292 (VAPID).
 *
 * Bewusst selbst geschrieben statt per Composer: das Hosting hat keinen
 * Composer, und alle benötigten Bausteine stecken schon in PHP – openssl für
 * die Kurve P-256 und AES-GCM, hash_hkdf für die Schlüsselableitung.
 *
 * Die Verschlüsselung ist gegen den Testvektor aus RFC 8291, Anhang A geprüft
 * (siehe bin/selftest.php). Wer hier etwas ändert, muss diesen Test bestehen –
 * ein Fehler fällt sonst erst auf, wenn niemand mehr Benachrichtigungen bekommt.
 */
final class WebPush
{
    /** Länge eines Datensatzes laut RFC 8188. */
    private const RECORD_SIZE = 4096;

    /** Fester Vorspann eines SubjectPublicKeyInfo für die Kurve P-256. */
    private const SPKI_PREFIX = "\x30\x59\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01"
                              . "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\x03\x42\x00";

    // ------------------------------------------------------------ Kodierung

    public static function b64url(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    public static function b64urlDecode(string $text): string
    {
        $padded = strtr($text, '-_', '+/');
        $rest   = strlen($padded) % 4;
        if ($rest !== 0) {
            $padded .= str_repeat('=', 4 - $rest);
        }
        return (string)base64_decode($padded, true);
    }

    // ------------------------------------------------------------- Schlüssel

    /**
     * Neues VAPID-Schlüsselpaar. Der private Teil wird als PEM gespeichert,
     * der öffentliche als Rohpunkt – den braucht der Browser beim Anmelden.
     *
     * @return array{private_pem: string, public: string}
     */
    public static function generateKeys(): array
    {
        $key = openssl_pkey_new([
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        if ($key === false) {
            throw new RuntimeException('Schlüsselpaar konnte nicht erzeugt werden.');
        }

        openssl_pkey_export($key, $pem);
        $details = openssl_pkey_get_details($key);

        return [
            'private_pem' => $pem,
            'public'      => self::b64url(self::point($details)),
        ];
    }

    /** Öffentlicher Punkt (unkomprimiert, 65 Byte) aus den Schlüsseldetails. */
    private static function point(array $details): string
    {
        return "\x04"
            . str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT)
            . str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);
    }

    /** Öffentlichen Schlüssel aus einem Rohpunkt bauen, damit openssl ihn versteht. */
    private static function publicKeyFromPoint(string $point): OpenSSLAsymmetricKey
    {
        $pem = "-----BEGIN PUBLIC KEY-----\n"
             . chunk_split(base64_encode(self::SPKI_PREFIX . $point), 64, "\n")
             . "-----END PUBLIC KEY-----\n";

        $key = openssl_pkey_get_public($pem);
        if ($key === false) {
            throw new RuntimeException('Der öffentliche Schlüssel des Empfängers ist unbrauchbar.');
        }
        return $key;
    }

    /**
     * Privaten Schlüssel aus Rohwerten bauen (SEC1). Wird nur vom Testvektor
     * gebraucht; im Betrieb entstehen die Schlüssel direkt in openssl.
     */
    public static function privateKeyFromRaw(string $d, string $point): OpenSSLAsymmetricKey
    {
        $der = "\x30\x77"
             . "\x02\x01\x01"
             . "\x04\x20" . str_pad($d, 32, "\x00", STR_PAD_LEFT)
             . "\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"
             . "\xa1\x44\x03\x42\x00" . $point;

        $pem = "-----BEGIN EC PRIVATE KEY-----\n"
             . chunk_split(base64_encode($der), 64, "\n")
             . "-----END EC PRIVATE KEY-----\n";

        $key = openssl_pkey_get_private($pem);
        if ($key === false) {
            throw new RuntimeException('Der private Schlüssel ist unbrauchbar.');
        }
        return $key;
    }

    // -------------------------------------------------------- Verschlüsselung

    /**
     * Nachricht für ein Abonnement verschlüsseln (RFC 8291).
     *
     * $salt und $serverKey sind nur für den Testvektor gedacht – im Betrieb
     * müssen beide zufällig sein, sonst wäre die Verschlüsselung wertlos.
     */
    public static function encrypt(
        string $plaintext,
        string $p256dh,
        string $auth,
        ?string $salt = null,
        ?OpenSSLAsymmetricKey $serverKey = null
    ): string {
        $empfaenger = self::b64urlDecode($p256dh);   // 65 Byte
        $geheimnis  = self::b64urlDecode($auth);     // 16 Byte
        $salt       = $salt ?? random_bytes(16);

        $serverKey = $serverKey ?? openssl_pkey_new([
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        $absender = self::point(openssl_pkey_get_details($serverKey));

        // Gemeinsames Geheimnis über die Kurve
        $shared = openssl_pkey_derive(self::publicKeyFromPoint($empfaenger), $serverKey, 32);
        if ($shared === false) {
            throw new RuntimeException('Der Schlüsselaustausch ist fehlgeschlagen.');
        }

        // RFC 8291, Abschnitt 3.4: erst das Geheimnis mit dem auth-Wert
        // verdichten, daraus dann Schlüssel und Nonce ableiten.
        $prk   = hash_hkdf('sha256', $shared, 32, "WebPush: info\x00" . $empfaenger . $absender, $geheimnis);
        $cek   = hash_hkdf('sha256', $prk, 16, "Content-Encoding: aes128gcm\x00", $salt);
        $nonce = hash_hkdf('sha256', $prk, 12, "Content-Encoding: nonce\x00", $salt);

        // 0x02 schliesst den letzten Datensatz ab (RFC 8188).
        $tag = '';
        $cipher = openssl_encrypt($plaintext . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
        if ($cipher === false) {
            throw new RuntimeException('Die Verschlüsselung ist fehlgeschlagen.');
        }

        $kopf = $salt . pack('N', self::RECORD_SIZE) . chr(strlen($absender)) . $absender;

        return $kopf . $cipher . $tag;
    }

    // --------------------------------------------------------------- VAPID

    /** DER-kodierte Signatur in die von JOSE erwartete Form R||S bringen. */
    private static function signatureToRaw(string $der): string
    {
        $pos = 2;
        if (ord($der[1]) > 0x80) {           // lange Längenangabe
            $pos += ord($der[1]) & 0x7f;
        }

        $teil = static function (string $der, int &$pos): string {
            $pos++;                           // 0x02 INTEGER
            $len = ord($der[$pos++]);
            $wert = substr($der, $pos, $len);
            $pos += $len;
            return str_pad(ltrim($wert, "\x00"), 32, "\x00", STR_PAD_LEFT);
        };

        return $teil($der, $pos) . $teil($der, $pos);
    }

    /** Authorization-Kopfzeile für einen Push-Dienst (RFC 8292). */
    public static function vapidHeader(string $endpoint, string $privatePem, string $publicKey, string $subject): string
    {
        $teile = parse_url($endpoint);
        $aud   = ($teile['scheme'] ?? 'https') . '://' . ($teile['host'] ?? '');

        $kopf  = self::b64url((string)json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $nutz  = self::b64url((string)json_encode([
            'aud' => $aud,
            'exp' => time() + 12 * 3600,
            'sub' => $subject,
        ]));

        $key = openssl_pkey_get_private($privatePem);
        if ($key === false) {
            throw new RuntimeException('Der VAPID-Schlüssel ist unbrauchbar.');
        }

        $der = '';
        if (!openssl_sign($kopf . '.' . $nutz, $der, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Die Signatur ist fehlgeschlagen.');
        }

        $jwt = $kopf . '.' . $nutz . '.' . self::b64url(self::signatureToRaw($der));

        return 'vapid t=' . $jwt . ', k=' . $publicKey;
    }

    // --------------------------------------------------------------- Senden

    /**
     * Eine fertig vorbereitete Anfrage an einen Push-Dienst.
     *
     * Getrennt vom Absenden, weil sendMany() dieselben Handles braucht, nur
     * eben alle gleichzeitig.
     *
     * @param array{endpoint:string, p256dh:string, auth:string} $abo
     * @return CurlHandle
     */
    private static function request(array $abo, string $payload, string $privatePem, string $publicKey, string $subject, int $ttl)
    {
        $koerper = self::encrypt($payload, (string)$abo['p256dh'], (string)$abo['auth']);

        $ch = curl_init((string)$abo['endpoint']);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $koerper,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/octet-stream',
                'Content-Encoding: aes128gcm',
                'Content-Length: ' . strlen($koerper),
                'TTL: ' . $ttl,
                'Urgency: normal',
                'Authorization: ' . self::vapidHeader((string)$abo['endpoint'], $privatePem, $publicKey, $subject),
            ],
        ]);

        return $ch;
    }

    /**
     * Eine Nachricht zustellen.
     *
     * @return array{status:int, body:string}
     */
    public static function send(array $abo, string $payload, string $privatePem, string $publicKey, string $subject, int $ttl = 86400): array
    {
        $ch      = self::request($abo, $payload, $privatePem, $publicKey, $subject, $ttl);
        $antwort = curl_exec($ch);
        $status  = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $fehler  = curl_error($ch);
        curl_close($ch);

        return [
            'status' => $status,
            'body'   => $antwort === false ? $fehler : (string)$antwort,
        ];
    }

    /**
     * Mehrere Abonnements gleichzeitig beliefern.
     *
     * Nacheinander waere es einfacher, aber jede Zustellung dauert ein paar
     * hundert Millisekunden – und gesendet wird mitten im Seitenaufruf, weil
     * das Hosting keine Hintergrundprozesse erlaubt. Parallel kostet der
     * ganze Versand nur noch so viel Zeit wie die langsamste Zustellung.
     *
     * @param  array<int, array{endpoint:string, p256dh:string, auth:string}> $abos
     * @return array<int, array{status:int, body:string}> mit denselben Schluesseln wie $abos
     */
    public static function sendMany(array $abos, string $payload, string $privatePem, string $publicKey, string $subject, int $ttl = 86400): array
    {
        if (!$abos) {
            return [];
        }

        $multi    = curl_multi_init();
        $handles  = [];
        $ergebnis = [];

        foreach ($abos as $schluessel => $abo) {
            try {
                $ch = self::request($abo, $payload, $privatePem, $publicKey, $subject, $ttl);
            } catch (Throwable $exception) {
                // Ein unbrauchbarer Schluessel darf die anderen nicht aufhalten.
                $ergebnis[$schluessel] = ['status' => 0, 'body' => $exception->getMessage()];
                continue;
            }
            $handles[$schluessel] = $ch;
            curl_multi_add_handle($multi, $ch);
        }

        do {
            $status = curl_multi_exec($multi, $laufend);
            if ($laufend) {
                curl_multi_select($multi, 1.0);
            }
        } while ($laufend && $status === CURLM_OK);

        foreach ($handles as $schluessel => $ch) {
            $antwort = curl_multi_getcontent($ch);
            $fehler  = curl_error($ch);
            $ergebnis[$schluessel] = [
                'status' => (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
                'body'   => $antwort === null || $antwort === false ? $fehler : (string)$antwort,
            ];
            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
        }

        curl_multi_close($multi);

        return $ergebnis;
    }
}
