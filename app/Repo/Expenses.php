<?php
declare(strict_types=1);

defined('KINDERARBEIT') || exit;

/**
 * Regelmaessige monatliche Ausgaben eines Kindes, z. B. der Beitrag fuers Fitnessstudio.
 * Sie werden automatisch vom Konto abgebucht (siehe Billing).
 */
final class Expenses
{
    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT e.*, u.name AS child_name, u.emoji AS child_emoji, u.color AS child_color
               FROM expenses e
               JOIN users u ON u.id = e.child_id
              WHERE e.id = :id'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Feste Ausgaben auflisten.
     *
     * $onlyRunning laesst weg, was zurzeit nicht mehr laeuft: pausierte und
     * solche, deren Endmonat vorbei ist. Ohne die zweite Bedingung stuende
     * eine ausgelaufene Ausgabe beim Kind weiter als laufende Belastung da -
     * Billing::run() bucht sie laengst nicht mehr ab.
     */
    public static function all(?int $childId = null, bool $onlyRunning = false): array
    {
        $sql = 'SELECT e.*, u.name AS child_name, u.emoji AS child_emoji, u.color AS child_color
                  FROM expenses e
                  JOIN users u ON u.id = e.child_id
                 WHERE 1 = 1';
        $params = [];

        if ($childId) {
            $sql .= ' AND e.child_id = :child';
            $params['child'] = $childId;
        }
        if ($onlyRunning) {
            $sql .= ' AND e.is_active = 1 AND (e.end_month IS NULL OR e.end_month >= :monat)';
            $params['monat'] = current_month();
        }
        $sql .= ' ORDER BY e.is_active DESC, u.sort_order, e.day_of_month, e.title';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Summe der monatlichen Belastung eines Kindes - nur was noch laeuft. */
    public static function monthlyTotal(int $childId): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COALESCE(SUM(amount_cents), 0) FROM expenses
              WHERE child_id = :child AND is_active = 1
                AND (end_month IS NULL OR end_month >= :monat)'
        );
        $stmt->execute(['child' => $childId, 'monat' => current_month()]);
        return (int)$stmt->fetchColumn();
    }

    /** Monatliche Belastung aller Kinder als id => Cent. */
    public static function monthlyTotals(): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT child_id, COALESCE(SUM(amount_cents), 0) AS total
               FROM expenses
              WHERE is_active = 1 AND (end_month IS NULL OR end_month >= :monat)
              GROUP BY child_id'
        );
        $stmt->execute(['monat' => current_month()]);
        $rows = $stmt->fetchAll();

        $totals = [];
        foreach ($rows as $row) {
            $totals[(int)$row['child_id']] = (int)$row['total'];
        }
        return $totals;
    }

    public static function create(array $data): int
    {
        $pdo = Database::pdo();
        $pdo->prepare(
            'INSERT INTO expenses (child_id, title, emoji, amount_cents, day_of_month, start_month, end_month, is_active, created_by, created_at)
             VALUES (:child, :title, :emoji, :amount, :day, :start_month, :end_month, 1, :created_by, :created_at)'
        )->execute([
            'child'       => $data['child_id'],
            'title'       => $data['title'],
            'emoji'       => $data['emoji'] ?? '💳',
            'amount'      => $data['amount_cents'],
            'day'         => $data['day_of_month'] ?? 1,
            'start_month' => $data['start_month'] ?? current_month(),
            'end_month'   => $data['end_month'] ?? null,
            'created_by'  => $data['created_by'],
            'created_at'  => now(),
        ]);

        return (int)$pdo->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        Database::pdo()->prepare(
            'UPDATE expenses
                SET child_id = :child, title = :title, emoji = :emoji, amount_cents = :amount,
                    day_of_month = :day, end_month = :end_month
              WHERE id = :id'
        )->execute([
            'child'     => $data['child_id'],
            'title'     => $data['title'],
            'emoji'     => $data['emoji'] ?? '💳',
            'amount'    => $data['amount_cents'],
            'day'       => $data['day_of_month'] ?? 1,
            'end_month' => $data['end_month'] ?? null,
            'id'        => $id,
        ]);
    }

    public static function setActive(int $id, bool $active): void
    {
        Database::pdo()->prepare('UPDATE expenses SET is_active = :active WHERE id = :id')
            ->execute(['active' => $active ? 1 : 0, 'id' => $id]);
    }

    /** Ausgabe entfernen; bereits erfolgte Abbuchungen bleiben im Journal stehen. */
    public static function delete(int $id): void
    {
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM expense_bookings WHERE expense_id = :id')->execute(['id' => $id]);
        $pdo->prepare('DELETE FROM expenses WHERE id = :id')->execute(['id' => $id]);
    }

    /** Wurde diese Ausgabe im angegebenen Monat schon abgebucht? */
    public static function isBooked(int $expenseId, string $month): bool
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM expense_bookings WHERE expense_id = :id AND month = :month'
        );
        $stmt->execute(['id' => $expenseId, 'month' => $month]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
