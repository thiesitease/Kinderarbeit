<?php
declare(strict_types=1);

defined('KINDERARBEIT') || exit;

/**
 * Alle Betraege werden intern als ganzzahlige Cent gespeichert –
 * damit gibt es keine Rundungsfehler bei Fliesskommazahlen.
 */
final class Money
{
    /** 250 => "2,50 €" */
    public static function format(int $cents, bool $withSign = false): string
    {
        $sign = '';
        if ($withSign) {
            $sign = $cents > 0 ? '+' : ($cents < 0 ? '−' : '');
        } elseif ($cents < 0) {
            $sign = '−';
        }
        $abs = abs($cents);
        return $sign . number_format($abs / 100, 2, ',', '.') . ' €';
    }

    /** 250 => "2,50" (ohne Waehrungszeichen, fuer Formularfelder) */
    public static function forInput(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '');
    }

    /**
     * Nutzereingabe in Cent umwandeln. Akzeptiert "2,50", "2.50", "2", "1.234,56", "€ 3,00".
     * Gibt null zurueck, wenn die Eingabe keine gueltige Zahl ist.
     */
    public static function parse(string $input): ?int
    {
        $value = trim($input);
        $value = str_replace(["\u{00a0}", ' ', '€', 'EUR', 'eur'], '', $value);
        if ($value === '') {
            return null;
        }

        $negative = str_starts_with($value, '-') || str_starts_with($value, '−');
        $value = ltrim($value, '-−+');

        $hasComma = str_contains($value, ',');
        $hasDot   = str_contains($value, '.');

        if ($hasComma && $hasDot) {
            // "1.234,56" – Punkt ist Tausendertrenner
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif ($hasComma) {
            $value = str_replace(',', '.', $value);
        }

        if (!preg_match('/^\d+(\.\d{1,2})?$/', $value)) {
            return null;
        }

        $cents = (int)round(((float)$value) * 100);
        return $negative ? -$cents : $cents;
    }
}
