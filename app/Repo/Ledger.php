<?php
declare(strict_types=1);

defined('KINDERARBEIT') || exit;

/**
 * Das Konto jedes Kindes ist ein einfaches Buchungsjournal:
 * positive Betraege sind Gutschriften, negative sind Abbuchungen.
 * Der Kontostand ist immer die Summe aller Buchungen – nichts wird ueberschrieben.
 */
final class Ledger
{
    public const CATEGORY_LABELS = [
        'task'       => 'Aufgabe',
        'expense'    => 'Feste Ausgabe',
        'payout'     => 'Auszahlung',
        'bonus'      => 'Bonus',
        'correction' => 'Korrektur',
    ];

    public const CATEGORY_EMOJI = [
        'task'       => '✅',
        'expense'    => '💳',
        'payout'     => '💶',
        'bonus'      => '🎁',
        'correction' => '✏️',
    ];

    /** Eine Buchung anlegen und ihre ID zurueckgeben. */
    public static function book(
        int $childId,
        int $amountCents,
        string $description,
        string $category,
        ?string $refType = null,
        ?int $refId = null,
        ?int $createdBy = null,
        ?string $bookedAt = null
    ): int {
        $bookedAt = $bookedAt ?? now();
        $pdo = Database::pdo();
        $pdo->prepare(
            'INSERT INTO ledger (child_id, amount_cents, description, category, ref_type, ref_id, booked_at, booked_month, created_by, created_at)
             VALUES (:child, :amount, :description, :category, :ref_type, :ref_id, :booked_at, :booked_month, :created_by, :created_at)'
        )->execute([
            'child'        => $childId,
            'amount'       => $amountCents,
            'description'  => $description,
            'category'     => $category,
            'ref_type'     => $refType,
            'ref_id'       => $refId,
            'booked_at'    => $bookedAt,
            'booked_month' => substr($bookedAt, 0, 7),
            'created_by'   => $createdBy,
            'created_at'   => now(),
        ]);

        return (int)$pdo->lastInsertId();
    }

    /** Kontostand eines Kindes in Cent. */
    public static function balance(int $childId): int
    {
        $stmt = Database::pdo()->prepare('SELECT COALESCE(SUM(amount_cents), 0) FROM ledger WHERE child_id = :child');
        $stmt->execute(['child' => $childId]);
        return (int)$stmt->fetchColumn();
    }

    /** Kontostaende aller Kinder als id => Cent. */
    public static function balances(): array
    {
        $rows = Database::pdo()->query(
            'SELECT child_id, COALESCE(SUM(amount_cents), 0) AS balance FROM ledger GROUP BY child_id'
        )->fetchAll();

        $balances = [];
        foreach ($rows as $row) {
            $balances[(int)$row['child_id']] = (int)$row['balance'];
        }
        return $balances;
    }

    /**
     * Monatsauswertung eines Kindes.
     *
     * Die Kategorien decken alle Buchungen ab, deshalb geht die Rechnung
     * immer auf: earned + bonus + corrections - expenses - payouts = net.
     */
    public static function monthSummary(int $childId, string $month): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN amount_cents > 0 THEN amount_cents END), 0)                      AS income,
                COALESCE(SUM(CASE WHEN category = 'task'    AND amount_cents > 0 THEN amount_cents END), 0) AS earned,
                COALESCE(SUM(CASE WHEN category = 'bonus'   AND amount_cents > 0 THEN amount_cents END), 0) AS bonus,
                COALESCE(SUM(CASE WHEN category = 'expense' THEN -amount_cents END), 0)                 AS expenses,
                COALESCE(SUM(CASE WHEN category = 'payout'  THEN -amount_cents END), 0)                 AS payouts,
                COALESCE(SUM(CASE WHEN category = 'correction' THEN amount_cents END), 0)               AS corrections,
                COALESCE(SUM(CASE WHEN amount_cents < 0 THEN -amount_cents END), 0)                     AS spending,
                COALESCE(SUM(amount_cents), 0)                                                          AS net,
                COUNT(*)                                                                                AS entries
               FROM ledger
              WHERE child_id = :child AND booked_month = :month"
        );
        $stmt->execute(['child' => $childId, 'month' => $month]);
        $row = $stmt->fetch() ?: [];

        return [
            'income'   => (int)($row['income'] ?? 0),
            'earned'   => (int)($row['earned'] ?? 0),
            'bonus'    => (int)($row['bonus'] ?? 0),
            'expenses' => (int)($row['expenses'] ?? 0),
            'payouts'  => (int)($row['payouts'] ?? 0),
            'corrections' => (int)($row['corrections'] ?? 0),
            'spending' => (int)($row['spending'] ?? 0),
            'net'      => (int)($row['net'] ?? 0),
            'entries'  => (int)($row['entries'] ?? 0),
        ];
    }

    /** Buchungen eines Kindes, optional auf einen Monat begrenzt. */
    public static function forChild(int $childId, ?string $month = null, int $limit = 100): array
    {
        $sql = 'SELECT l.*, u.name AS created_by_name
                  FROM ledger l
             LEFT JOIN users u ON u.id = l.created_by
                 WHERE l.child_id = :child';
        $params = ['child' => $childId];

        if ($month !== null) {
            $sql .= ' AND l.booked_month = :month';
            $params['month'] = $month;
        }
        $sql .= ' ORDER BY l.booked_at DESC, l.id DESC LIMIT ' . max(1, $limit);

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Gesamte Familienhistorie, optional gefiltert. */
    public static function recent(?int $childId = null, ?string $month = null, int $limit = 200): array
    {
        $sql = 'SELECT l.*, c.name AS child_name, c.emoji AS child_emoji, c.color AS child_color,
                       u.name AS created_by_name
                  FROM ledger l
                  JOIN users c ON c.id = l.child_id
             LEFT JOIN users u ON u.id = l.created_by
                 WHERE 1 = 1';
        $params = [];

        if ($childId) {
            $sql .= ' AND l.child_id = :child';
            $params['child'] = $childId;
        }
        if ($month !== null) {
            $sql .= ' AND l.booked_month = :month';
            $params['month'] = $month;
        }
        $sql .= ' ORDER BY l.booked_at DESC, l.id DESC LIMIT ' . max(1, $limit);

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Monate, in denen ueberhaupt gebucht wurde (neueste zuerst). */
    public static function availableMonths(): array
    {
        $months = Database::pdo()
            ->query('SELECT DISTINCT booked_month FROM ledger ORDER BY booked_month DESC')
            ->fetchAll(PDO::FETCH_COLUMN);

        $current = current_month();
        if (!in_array($current, $months, true)) {
            array_unshift($months, $current);
        }
        return $months;
    }

    public static function label(string $category): string
    {
        return self::CATEGORY_LABELS[$category] ?? $category;
    }

    public static function emoji(string $category): string
    {
        return self::CATEGORY_EMOJI[$category] ?? '•';
    }
}
