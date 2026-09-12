<?php
declare(strict_types=1);

defined('KINDERARBEIT') || exit;

final class Tasks
{
    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM tasks WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /** Alle Aufgaben fuer die Elternverwaltung. */
    public static function all(bool $includeInactive = true): array
    {
        $sql = 'SELECT t.*, u.name AS assigned_name, u.emoji AS assigned_emoji
                  FROM tasks t
             LEFT JOIN users u ON u.id = t.assigned_to';
        if (!$includeInactive) {
            $sql .= ' WHERE t.is_active = 1';
        }
        $sql .= ' ORDER BY t.is_active DESC, t.sort_order, t.title';
        return Database::pdo()->query($sql)->fetchAll();
    }

    /**
     * Aufgaben, die ein bestimmtes Kind sehen soll:
     * alle offenen Aufgaben ohne feste Zuordnung plus die ihm zugewiesenen.
     * Einmal-Aufgaben verschwinden, sobald sie eingereicht oder bestätigt sind.
     */
    public static function forChild(int $childId): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT t.*,
                    (SELECT COUNT(*) FROM completions c
                      WHERE c.task_id = t.id AND c.child_id = :child AND c.status = 'pending') AS pending_count,
                    (SELECT MAX(c.created_at) FROM completions c
                      WHERE c.task_id = t.id AND c.child_id = :child AND c.status = 'approved') AS last_approved_at
               FROM tasks t
              WHERE t.is_active = 1
                AND (t.assigned_to IS NULL OR t.assigned_to = :child)
                AND NOT (
                        t.kind = 'once'
                    AND EXISTS (SELECT 1 FROM completions c
                                 WHERE c.task_id = t.id AND c.status IN ('pending','approved'))
                )
           ORDER BY t.sort_order, t.title"
        );
        $stmt->execute(['child' => $childId]);
        return $stmt->fetchAll();
    }

    public static function create(array $data): int
    {
        $pdo = Database::pdo();
        $nextOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM tasks')->fetchColumn();

        $pdo->prepare(
            'INSERT INTO tasks (title, description, emoji, amount_cents, kind, assigned_to, is_active, sort_order, created_by, created_at)
             VALUES (:title, :description, :emoji, :amount, :kind, :assigned_to, 1, :sort_order, :created_by, :created_at)'
        )->execute([
            'title'       => $data['title'],
            'description' => $data['description'] ?? '',
            'emoji'       => $data['emoji'] ?? '⭐',
            'amount'      => $data['amount_cents'],
            'kind'        => $data['kind'] ?? 'repeatable',
            'assigned_to' => $data['assigned_to'] ?? null,
            'sort_order'  => $nextOrder,
            'created_by'  => $data['created_by'],
            'created_at'  => now(),
        ]);

        return (int)$pdo->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        Database::pdo()->prepare(
            'UPDATE tasks
                SET title = :title, description = :description, emoji = :emoji,
                    amount_cents = :amount, kind = :kind, assigned_to = :assigned_to
              WHERE id = :id'
        )->execute([
            'title'       => $data['title'],
            'description' => $data['description'] ?? '',
            'emoji'       => $data['emoji'] ?? '⭐',
            'amount'      => $data['amount_cents'],
            'kind'        => $data['kind'] ?? 'repeatable',
            'assigned_to' => $data['assigned_to'] ?? null,
            'id'          => $id,
        ]);
    }

    public static function setActive(int $id, bool $active): void
    {
        Database::pdo()->prepare('UPDATE tasks SET is_active = :active WHERE id = :id')
            ->execute(['active' => $active ? 1 : 0, 'id' => $id]);
    }

    /**
     * Aufgaben werden nie hart geloescht, solange Buchungen daran haengen –
     * die Historie soll nachvollziehbar bleiben.
     */
    public static function delete(int $id): bool
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM completions WHERE task_id = :id');
        $stmt->execute(['id' => $id]);
        if ((int)$stmt->fetchColumn() > 0) {
            self::setActive($id, false);
            return false;
        }
        Database::pdo()->prepare('DELETE FROM tasks WHERE id = :id')->execute(['id' => $id]);
        return true;
    }

    /** Aufgabe in der Liste nach oben/unten schieben. */
    public static function move(int $id, int $direction): void
    {
        $pdo = Database::pdo();
        $task = self::find($id);
        if (!$task) {
            return;
        }
        $comparison = $direction < 0 ? '<' : '>';
        $order      = $direction < 0 ? 'DESC' : 'ASC';
        $stmt = $pdo->prepare(
            "SELECT id, sort_order FROM tasks
              WHERE sort_order {$comparison} :order AND is_active = :active
           ORDER BY sort_order {$order} LIMIT 1"
        );
        $stmt->execute(['order' => (int)$task['sort_order'], 'active' => (int)$task['is_active']]);
        $neighbour = $stmt->fetch();
        if (!$neighbour) {
            return;
        }
        $update = $pdo->prepare('UPDATE tasks SET sort_order = :order WHERE id = :id');
        $update->execute(['order' => (int)$neighbour['sort_order'], 'id' => $id]);
        $update->execute(['order' => (int)$task['sort_order'], 'id' => (int)$neighbour['id']]);
    }
}
