<?php
declare(strict_types=1);

defined('KINDERARBEIT') || exit;

/**
 * Bucht die regelmaessigen Ausgaben automatisch ab.
 *
 * Auf einfachem Webhosting gibt es nicht immer einen Cronjob, deshalb laeuft
 * die Pruefung beim ersten Seitenaufruf des Tages mit. Doppelbuchungen sind
 * durch den eindeutigen Schluessel (expense_id, month) ausgeschlossen.
 */
final class Billing
{
    /**
     * Faellige Abbuchungen nachholen.
     *
     * @return int Anzahl der neu angelegten Buchungen
     */
    public static function run(bool $force = false): int
    {
        $today = date('Y-m-d');
        if (!$force && self::setting('last_billing_run') === $today) {
            return 0;
        }

        $expenses = Database::pdo()
            ->query('SELECT * FROM expenses WHERE is_active = 1')
            ->fetchAll();

        $booked = 0;
        foreach ($expenses as $expense) {
            foreach (self::dueMonths($expense) as $month) {
                if (self::bookMonth($expense, $month)) {
                    $booked++;
                }
            }
        }

        self::setSetting('last_billing_run', $today);
        return $booked;
    }

    /**
     * Alle Monate, fuer die eine Ausgabe faellig ist – vom Startmonat bis heute.
     * Der laufende Monat zaehlt erst ab dem eingestellten Buchungstag.
     */
    private static function dueMonths(array $expense): array
    {
        $month   = (string)$expense['start_month'];
        $current = current_month();
        $endsAt  = $expense['end_month'] ?: null;

        $months = [];
        $guard  = 0;

        while ($month <= $current && $guard++ < 240) {
            if ($endsAt !== null && $month > $endsAt) {
                break;
            }
            if ($month < $current || self::dayReached((int)$expense['day_of_month'], $month)) {
                $months[] = $month;
            }
            $month = month_shift($month, 1);
        }

        return $months;
    }

    /** Ist der Buchungstag im angegebenen Monat schon erreicht? */
    private static function dayReached(int $dayOfMonth, string $month): bool
    {
        return (int)date('j') >= self::effectiveDay($dayOfMonth, $month);
    }

    /** Der 31. wird in kuerzeren Monaten auf den letzten Tag gelegt. */
    private static function effectiveDay(int $dayOfMonth, string $month): int
    {
        $daysInMonth = (int)date('t', (int)strtotime($month . '-01 12:00:00'));
        return max(1, min($dayOfMonth, $daysInMonth));
    }

    /** Eine einzelne Abbuchung anlegen, falls sie noch fehlt. */
    private static function bookMonth(array $expense, string $month): bool
    {
        if (Expenses::isBooked((int)$expense['id'], $month)) {
            return false;
        }

        try {
            return (bool)Database::transaction(function (PDO $pdo) use ($expense, $month) {
                $day = self::effectiveDay((int)$expense['day_of_month'], $month);
                $bookedAt = $month . '-' . str_pad((string)$day, 2, '0', STR_PAD_LEFT) . ' 06:00:00';

                $ledgerId = Ledger::book(
                    (int)$expense['child_id'],
                    -abs((int)$expense['amount_cents']),
                    $expense['title'],
                    'expense',
                    'expense',
                    (int)$expense['id'],
                    (int)($expense['created_by'] ?? 0) ?: null,
                    $bookedAt
                );

                $pdo->prepare(
                    'INSERT INTO expense_bookings (expense_id, month, ledger_id, created_at)
                     VALUES (:expense, :month, :ledger, :created_at)'
                )->execute([
                    'expense'    => (int)$expense['id'],
                    'month'      => $month,
                    'ledger'     => $ledgerId,
                    'created_at' => now(),
                ]);

                return true;
            });
        } catch (PDOException $exception) {
            // Verletzter UNIQUE-Schluessel = ein paralleler Aufruf war schneller.
            return false;
        }
    }

    public static function setting(string $key, ?string $default = null): ?string
    {
        $stmt = Database::pdo()->prepare('SELECT value FROM settings WHERE key = :key');
        $stmt->execute(['key' => $key]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : (string)$value;
    }

    public static function setSetting(string $key, string $value): void
    {
        Database::pdo()->prepare(
            'INSERT INTO settings (key, value) VALUES (:key, :value)
             ON CONFLICT (key) DO UPDATE SET value = excluded.value'
        )->execute(['key' => $key, 'value' => $value]);
    }
}
