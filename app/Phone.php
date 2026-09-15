<?php
declare(strict_types=1);

defined('KINDERARBEIT') || exit;

/**
 * Handynummern für die WhatsApp-Knöpfe.
 *
 * Gespeichert wird immer die internationale Form ohne Pluszeichen und ohne
 * Trennzeichen, also "491711234567" – genau so erwartet sie wa.me. Angezeigt
 * wird lesbar, eingegeben darf alles werden, was Leute so schreiben.
 */
final class Phone
{
    /** Vorwahl, wenn jemand eine Nummer ohne Länderangabe eintippt. */
    public const DEFAULT_COUNTRY = '49';

    /**
     * Eingabe in die Speicherform bringen.
     *
     * Akzeptiert "0171 1234567", "+49 171 1234567", "0049-171-1234567",
     * "49 (171) 1234567". Gibt null zurück, wenn daraus keine plausible
     * Nummer wird.
     */
    public static function normalize(string $input, ?string &$error = null): ?string
    {
        $raw = trim($input);
        if ($raw === '') {
            return null;
        }

        // Alles ausser Ziffern und einem führenden Plus verwerfen.
        $plus   = str_starts_with($raw, '+');
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits === '') {
            $error = 'Das sieht nicht nach einer Telefonnummer aus.';
            return null;
        }

        if ($plus) {
            // "+49171…" – schon international.
            $number = $digits;
        } elseif (str_starts_with($digits, '00')) {
            // "0049171…" – die alte Schreibweise für das Pluszeichen.
            $number = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            // "0171…" – nationale Schreibweise, führende Null entfällt.
            $number = self::DEFAULT_COUNTRY . substr($digits, 1);
        } else {
            // "171…" oder bereits "49171…"
            $number = str_starts_with($digits, self::DEFAULT_COUNTRY)
                ? $digits
                : self::DEFAULT_COUNTRY . $digits;
        }

        // Kürzeste sinnvolle internationale Nummer hat rund 8 Stellen,
        // die längste nach E.164 fünfzehn.
        $length = strlen($number);
        if ($length < 8 || $length > 15) {
            $error = 'Die Nummer wirkt unvollständig. Bitte mit Vorwahl angeben, z. B. 0171 1234567.';
            return null;
        }

        return $number;
    }

    /** Speicherform lesbar machen: "491711234567" => "+49 171 1234567" */
    public static function format(?string $number): string
    {
        if (!$number) {
            return '';
        }
        if (str_starts_with($number, self::DEFAULT_COUNTRY) && strlen($number) > 6) {
            $rest = substr($number, 2);
            return '+' . self::DEFAULT_COUNTRY . ' ' . substr($rest, 0, 3) . ' ' . substr($rest, 3);
        }
        return '+' . $number;
    }

    /** Link, der WhatsApp mit Empfänger und fertigem Text öffnet. */
    public static function waLink(?string $number, string $message): ?string
    {
        if (!$number) {
            return null;
        }
        return 'https://wa.me/' . $number . '?text=' . rawurlencode($message);
    }
}
