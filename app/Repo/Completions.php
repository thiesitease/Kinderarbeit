<?php
declare(strict_types=1);

defined('KINDERARBEIT') || exit;

/**
 * Eine "Completion" ist die Meldung eines Kindes, dass es eine Aufgabe erledigt hat.
 * Sie liegt den Eltern zur Wiedervorlage vor und wird erst mit der Bestaetigung
 * zu einer Gutschrift auf dem Konto des Kindes.
 */
final class Completions
{
    /** Kleinster und groesster Zuschlagsfaktor, in Zehnteln. */
    public const FAKTOR_MIN = 10;
    public const FAKTOR_MAX = 30;

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT c.*, u.name AS child_name, u.emoji AS child_emoji, u.color AS child_color,
                    d.name AS decided_by_name
               FROM completions c
               JOIN users u ON u.id = c.child_id
          LEFT JOIN users d ON d.id = c.decided_by
              WHERE c.id = :id'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Kind meldet eine Aufgabe als erledigt. Titel und Betrag werden als Kopie
     * mitgespeichert, damit spaetere Aenderungen an der Aufgabe die Historie
     * nicht rueckwirkend verfaelschen.
     *
     * $stars sind 0 bis 3: womit das Kind sagt, dass es besonders schwer war.
     * Was daraus wird, entscheiden die Eltern beim Bestaetigen - die Sterne
     * allein aendern den Betrag nicht.
     */
    public static function submit(array $task, int $childId, string $note = '', int $stars = 0): int
    {
        $pdo = Database::pdo();
        $pdo->prepare(
            'INSERT INTO completions (task_id, child_id, title, emoji, amount_cents, base_cents, stars, status, note, created_at)
             VALUES (:task_id, :child_id, :title, :emoji, :amount, :amount, :stars, \'pending\', :note, :created_at)'
        )->execute([
            'task_id'    => (int)$task['id'],
            'child_id'   => $childId,
            'title'      => $task['title'],
            'emoji'      => $task['emoji'],
            'amount'     => (int)$task['amount_cents'],
            'stars'      => max(0, min(3, $stars)),
            'note'       => $note,
            'created_at' => now(),
        ]);

        return (int)$pdo->lastInsertId();
    }

    /**
     * Betrag mit Zuschlag, aus ganzzahligen Zehnteln - nirgends Fliesskomma.
     * Gerundet wird kaufmaennisch: 0,50 € mal 1,5 sind 0,75 €.
     */
    public static function withFactor(int $baseCents, int $faktorZehntel): int
    {
        $faktorZehntel = max(self::FAKTOR_MIN, min(self::FAKTOR_MAX, $faktorZehntel));
        return intdiv($baseCents * $faktorZehntel + 5, 10);
    }

    /** Grundbetrag einer Meldung - aeltere Zeilen haben noch keinen. */
    public static function baseAmount(array $row): int
    {
        return (int)($row['base_cents'] ?? $row['amount_cents']);
    }

    /** Die Sterne einer Meldung als Text – leer, wenn das Kind keine gesetzt hat. */
    public static function starLabel(array $row): string
    {
        return str_repeat('⭐', max(0, min(3, (int)($row['stars'] ?? 0))));
    }

    /**
     * Der gewaehrte Zuschlag in Cent. Er wird nicht gespeichert, sondern ergibt
     * sich aus der Differenz – so kann er gar nicht erst neben dem Betrag
     * veralten, wenn eine Buchung spaeter im Verlauf geaendert wird.
     */
    public static function surcharge(array $row): int
    {
        return (int)$row['amount_cents'] - self::baseAmount($row);
    }

    /** Offene Meldungen – die Wiedervorlage der Eltern. */
    public static function pending(?int $childId = null): array
    {
        $sql = "SELECT c.*, u.name AS child_name, u.emoji AS child_emoji, u.color AS child_color
                  FROM completions c
                  JOIN users u ON u.id = c.child_id
                 WHERE c.status = 'pending'";
        $params = [];
        if ($childId) {
            $sql .= ' AND c.child_id = :child';
            $params['child'] = $childId;
        }
        $sql .= ' ORDER BY c.created_at ASC';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function pendingCount(?int $childId = null): int
    {
        $sql = "SELECT COUNT(*) FROM completions WHERE status = 'pending'";
        $params = [];
        if ($childId) {
            $sql .= ' AND child_id = :child';
            $params['child'] = $childId;
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /** Summe der noch nicht bestaetigten Betraege eines Kindes. */
    public static function pendingAmount(int $childId): int
    {
        $stmt = Database::pdo()->prepare(
            "SELECT COALESCE(SUM(amount_cents), 0) FROM completions WHERE status = 'pending' AND child_id = :child"
        );
        $stmt->execute(['child' => $childId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Eltern bestaetigen eine Meldung: Status setzen und Betrag gutschreiben.
     * Beides passiert in einer Transaktion, damit keine Gutschrift ohne
     * Bestaetigung (oder umgekehrt) entstehen kann.
     */
    public static function approve(int $id, int $parentId, string $note = '', int $faktorZehntel = self::FAKTOR_MIN): bool
    {
        return (bool)Database::transaction(function (PDO $pdo) use ($id, $parentId, $note, $faktorZehntel) {
            // Nur wirklich offene Meldungen bestaetigen – schuetzt vor Doppelklicks
            // und davor, dass beide Eltern gleichzeitig auf "Bestätigen" tippen.
            $stmt = $pdo->prepare(
                "UPDATE completions
                    SET status = 'approved', decided_by = :parent, decided_at = :now, decision_note = :note
                  WHERE id = :id AND status = 'pending'"
            );
            $stmt->execute(['parent' => $parentId, 'now' => now(), 'note' => $note, 'id' => $id]);

            if ($stmt->rowCount() === 0) {
                return false;
            }

            $completion = $pdo->prepare('SELECT * FROM completions WHERE id = :id');
            $completion->execute(['id' => $id]);
            $row = $completion->fetch();

            // Der Zuschlag wird beim Bestaetigen fest eingerechnet. amount_cents
            // traegt danach den gutgeschriebenen Betrag - Ruecknahme, Aendern und
            // Loeschen lesen genau diese Spalte und bleiben dadurch unveraendert.
            $grund   = self::baseAmount($row);
            $betrag  = self::withFactor($grund, $faktorZehntel);
            $zuschlag = $betrag - $grund;

            if ($zuschlag !== 0) {
                $pdo->prepare('UPDATE completions SET amount_cents = :amount WHERE id = :id')
                    ->execute(['amount' => $betrag, 'id' => $id]);
            }

            Ledger::book(
                (int)$row['child_id'],
                $betrag,
                $zuschlag > 0
                    ? $row['title'] . ' (×' . Money::factorLabel($faktorZehntel) . ')'
                    : $row['title'],
                'task',
                'completion',
                $id,
                $parentId
            );

            return true;
        });
    }

    /** Eltern lehnen eine Meldung ab – es wird nichts gutgeschrieben. */
    public static function reject(int $id, int $parentId, string $note = ''): bool
    {
        $stmt = Database::pdo()->prepare(
            "UPDATE completions
                SET status = 'rejected', decided_by = :parent, decided_at = :now, decision_note = :note
              WHERE id = :id AND status = 'pending'"
        );
        $stmt->execute(['parent' => $parentId, 'now' => now(), 'note' => $note, 'id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Eine bereits bestaetigte Meldung zurueckziehen: die Gutschrift wird durch
     * eine Gegenbuchung ausgeglichen, die Historie bleibt vollstaendig.
     */
    public static function revoke(int $id, int $parentId, string $note = ''): bool
    {
        return (bool)Database::transaction(function (PDO $pdo) use ($id, $parentId, $note) {
            $stmt = $pdo->prepare("SELECT * FROM completions WHERE id = :id AND status = 'approved'");
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch();
            if (!$row) {
                return false;
            }

            $pdo->prepare(
                "UPDATE completions
                    SET status = 'rejected', decided_by = :parent, decided_at = :now, decision_note = :note
                  WHERE id = :id"
            )->execute(['parent' => $parentId, 'now' => now(), 'note' => $note, 'id' => $id]);

            Ledger::book(
                (int)$row['child_id'],
                -(int)$row['amount_cents'],
                'Rücknahme: ' . $row['title'],
                'correction',
                'completion',
                $id,
                $parentId
            );

            return true;
        });
    }

    /** Meldungen eines Kindes (Verlauf). */
    public static function forChild(int $childId, ?string $status = null, int $limit = 100): array
    {
        $sql = 'SELECT c.*, d.name AS decided_by_name
                  FROM completions c
             LEFT JOIN users d ON d.id = c.decided_by
                 WHERE c.child_id = :child';
        $params = ['child' => $childId];

        if ($status !== null) {
            $sql .= ' AND c.status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY c.created_at DESC LIMIT ' . max(1, $limit);

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Bereits entschiedene Meldungen fuer die Elternansicht. */
    public static function decided(int $limit = 60): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT c.*, u.name AS child_name, u.emoji AS child_emoji, u.color AS child_color,
                    d.name AS decided_by_name
               FROM completions c
               JOIN users u ON u.id = c.child_id
          LEFT JOIN users d ON d.id = c.decided_by
              WHERE c.status <> 'pending'
           ORDER BY c.decided_at DESC LIMIT " . max(1, $limit)
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Wie viele Aufgaben hat ein Kind in einem Monat bestaetigt bekommen? */
    public static function approvedCountForMonth(int $childId, string $month): int
    {
        $stmt = Database::pdo()->prepare(
            "SELECT COUNT(*) FROM completions
              WHERE child_id = :child AND status = 'approved' AND strftime('%Y-%m', decided_at) = :month"
        );
        $stmt->execute(['child' => $childId, 'month' => $month]);
        return (int)$stmt->fetchColumn();
    }
}
