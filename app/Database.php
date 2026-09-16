<?php
declare(strict_types=1);

defined('KINDERARBEIT') || exit;

/**
 * Duenne Schicht um PDO/SQLite: Verbindung, Schema-Migrationen und Erst-Befuellung.
 */
final class Database
{
    private static ?PDO $pdo = null;

    /** Die Familie, mit der die Datenbank beim ersten Start angelegt wird. */
    public const SEED_USERS = [
        ['name' => 'Emilius', 'role' => 'child',  'emoji' => '🦊', 'color' => '#e8590c', 'pin' => '1111'],
        ['name' => 'Julius',  'role' => 'child',  'emoji' => '🦁', 'color' => '#1c7ed6', 'pin' => '2222'],
        ['name' => 'Bruno',   'role' => 'child',  'emoji' => '🐻', 'color' => '#2f9e44', 'pin' => '3333'],
        ['name' => 'Birgitta', 'role' => 'parent', 'emoji' => '🌷', 'color' => '#c2255c', 'pin' => '4444'],
        ['name' => 'Thies',   'role' => 'parent', 'emoji' => '⚓', 'color' => '#5f3dc4', 'pin' => '5555'],
    ];

    /** Beispielaufgaben fuer den ersten Start. */
    public const SEED_TASKS = [
        ['title' => 'Rasen mähen',          'emoji' => '🌱', 'amount' => 500, 'description' => 'Vorgarten und Garten hinter dem Haus.'],
        ['title' => 'Mit dem Hund gehen',   'emoji' => '🐕', 'amount' => 150, 'description' => 'Mindestens eine große Runde.'],
        ['title' => 'Spülmaschine ausräumen','emoji' => '🍽️', 'amount' => 50,  'description' => 'Alles an seinen Platz einräumen.'],
        ['title' => 'Staubsaugen',          'emoji' => '🧹', 'amount' => 200, 'description' => 'Wohnzimmer, Flur und Treppe.'],
    ];

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        if (!extension_loaded('pdo_sqlite')) {
            self::fail('Die PHP-Erweiterung "pdo_sqlite" ist nicht aktiviert. Bitte im Hosting-Panel einschalten.');
        }

        if (!is_dir(DATA_DIR) && !@mkdir(DATA_DIR, 0775, true) && !is_dir(DATA_DIR)) {
            self::fail('Das Verzeichnis "data/" konnte nicht angelegt werden.');
        }
        if (!is_writable(DATA_DIR)) {
            self::fail('Das Verzeichnis "data/" ist nicht beschreibbar (chmod 775 setzen).');
        }

        $isNew = !file_exists(DB_FILE);

        try {
            $pdo = new PDO('sqlite:' . DB_FILE, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $exception) {
            self::fail('Die Datenbank konnte nicht geöffnet werden: ' . $exception->getMessage());
        }

        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');

        self::$pdo = $pdo;

        self::migrate($pdo);
        if ($isNew) {
            @chmod(DB_FILE, 0664);
            self::seed($pdo);
        }

        return $pdo;
    }

    /** Schema anlegen bzw. auf den aktuellen Stand bringen. */
    private static function migrate(PDO $pdo): void
    {
        $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS users (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            name            TEXT    NOT NULL UNIQUE,
            role            TEXT    NOT NULL CHECK (role IN ('parent','child')),
            pin_hash        TEXT    NOT NULL,
            must_change_pin INTEGER NOT NULL DEFAULT 0,
            emoji           TEXT    NOT NULL DEFAULT '🙂',
            color           TEXT    NOT NULL DEFAULT '#1c7ed6',
            sort_order      INTEGER NOT NULL DEFAULT 0,
            is_active       INTEGER NOT NULL DEFAULT 1,
            failed_logins   INTEGER NOT NULL DEFAULT 0,
            locked_until    TEXT,
            last_login_at   TEXT,
            access_token    TEXT UNIQUE,
            token_created_at TEXT,
            token_used_at   TEXT,
            phone           TEXT,
            created_at      TEXT    NOT NULL
        );

        CREATE TABLE IF NOT EXISTS tasks (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            title        TEXT    NOT NULL,
            description  TEXT    NOT NULL DEFAULT '',
            emoji        TEXT    NOT NULL DEFAULT '⭐',
            amount_cents INTEGER NOT NULL,
            kind         TEXT    NOT NULL DEFAULT 'repeatable' CHECK (kind IN ('repeatable','once')),
            assigned_to  INTEGER REFERENCES users(id) ON DELETE SET NULL,
            is_active    INTEGER NOT NULL DEFAULT 1,
            sort_order   INTEGER NOT NULL DEFAULT 0,
            created_by   INTEGER REFERENCES users(id) ON DELETE SET NULL,
            created_at   TEXT    NOT NULL
        );

        CREATE TABLE IF NOT EXISTS completions (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            task_id       INTEGER REFERENCES tasks(id) ON DELETE SET NULL,
            child_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            title         TEXT    NOT NULL,
            emoji         TEXT    NOT NULL DEFAULT '⭐',
            amount_cents  INTEGER NOT NULL,
            base_cents    INTEGER,
            stars         INTEGER NOT NULL DEFAULT 0,
            status        TEXT    NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','approved','rejected')),
            note          TEXT    NOT NULL DEFAULT '',
            created_at    TEXT    NOT NULL,
            decided_by    INTEGER REFERENCES users(id) ON DELETE SET NULL,
            decided_at    TEXT,
            decision_note TEXT    NOT NULL DEFAULT ''
        );

        CREATE TABLE IF NOT EXISTS ledger (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            child_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            amount_cents INTEGER NOT NULL,
            description  TEXT    NOT NULL,
            category     TEXT    NOT NULL CHECK (category IN ('task','expense','payout','bonus','correction')),
            ref_type     TEXT,
            ref_id       INTEGER,
            booked_at    TEXT    NOT NULL,
            booked_month TEXT    NOT NULL,
            created_by   INTEGER REFERENCES users(id) ON DELETE SET NULL,
            created_at   TEXT    NOT NULL,
            updated_by   INTEGER REFERENCES users(id) ON DELETE SET NULL,
            updated_at   TEXT
        );

        CREATE TABLE IF NOT EXISTS expenses (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            child_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            title        TEXT    NOT NULL,
            emoji        TEXT    NOT NULL DEFAULT '💳',
            amount_cents INTEGER NOT NULL,
            day_of_month INTEGER NOT NULL DEFAULT 1,
            start_month  TEXT    NOT NULL,
            end_month    TEXT,
            is_active    INTEGER NOT NULL DEFAULT 1,
            created_by   INTEGER REFERENCES users(id) ON DELETE SET NULL,
            created_at   TEXT    NOT NULL
        );

        -- ledger_id darf leer sein: wird eine Abbuchung im Verlauf geloescht,
        -- bleibt der Merker (expense_id, month) stehen und Billing bucht den
        -- Monat nicht noch einmal ab.
        CREATE TABLE IF NOT EXISTS expense_bookings (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            expense_id INTEGER NOT NULL REFERENCES expenses(id) ON DELETE CASCADE,
            month      TEXT    NOT NULL,
            ledger_id  INTEGER REFERENCES ledger(id) ON DELETE SET NULL,
            created_at TEXT    NOT NULL,
            UNIQUE (expense_id, month)
        );

        CREATE TABLE IF NOT EXISTS remember_tokens (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            selector     TEXT    NOT NULL UNIQUE,
            validator    TEXT    NOT NULL,
            via_link     INTEGER NOT NULL DEFAULT 0,
            user_agent   TEXT    NOT NULL DEFAULT '',
            created_at   TEXT    NOT NULL,
            last_used_at TEXT,
            expires_at   TEXT    NOT NULL
        );

        CREATE TABLE IF NOT EXISTS push_subscriptions (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            endpoint   TEXT    NOT NULL UNIQUE,
            p256dh     TEXT    NOT NULL,
            auth       TEXT    NOT NULL,
            user_agent TEXT    NOT NULL DEFAULT '',
            created_at TEXT    NOT NULL,
            last_ok_at TEXT,
            failures   INTEGER NOT NULL DEFAULT 0
        );

        CREATE TABLE IF NOT EXISTS settings (
            key   TEXT PRIMARY KEY,
            value TEXT NOT NULL
        );

        CREATE INDEX IF NOT EXISTS idx_completions_status ON completions (status, created_at);
        CREATE INDEX IF NOT EXISTS idx_completions_child  ON completions (child_id, created_at);
        CREATE INDEX IF NOT EXISTS idx_ledger_child       ON ledger (child_id, booked_at);
        CREATE INDEX IF NOT EXISTS idx_ledger_month       ON ledger (child_id, booked_month);
        CREATE INDEX IF NOT EXISTS idx_expenses_child     ON expenses (child_id, is_active);
        CREATE INDEX IF NOT EXISTS idx_push_user          ON push_subscriptions (user_id);
        CREATE INDEX IF NOT EXISTS idx_remember_user      ON remember_tokens (user_id);
        SQL);

        // Spalten, die erst spaeter dazugekommen sind, in bestehenden
        // Datenbanken nachziehen. CREATE TABLE IF NOT EXISTS allein genuegt dafuer nicht.
        self::addColumn($pdo, 'users', 'access_token', 'TEXT');
        self::addColumn($pdo, 'users', 'token_created_at', 'TEXT');
        self::addColumn($pdo, 'users', 'token_used_at', 'TEXT');
        self::addColumn($pdo, 'users', 'phone', 'TEXT');
        // Sterne des Kindes und der Betrag vor einem Zuschlag. amount_cents
        // bleibt ueberall "was gutgeschrieben wurde" - daran haengen Ruecknahme
        // und Bearbeiten; der Grundbetrag steht deshalb daneben.
        self::addColumn($pdo, 'completions', 'base_cents', 'INTEGER');
        self::addColumn($pdo, 'completions', 'stars', 'INTEGER NOT NULL DEFAULT 0');
        self::addColumn($pdo, 'ledger', 'updated_by', 'INTEGER');
        self::addColumn($pdo, 'ledger', 'updated_at', 'TEXT');

        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_users_token ON users (access_token) WHERE access_token IS NOT NULL');

        self::relaxExpenseBookings($pdo);
    }

    /**
     * Aeltere Datenbanken haben expense_bookings.ledger_id als NOT NULL mit
     * ON DELETE CASCADE. Wird eine Abbuchung im Verlauf geloescht, faellt damit
     * auch der Merker fuer den Monat weg – und die naechste Abrechnung legt sie
     * sofort neu an. Deshalb die Tabelle einmalig umbauen.
     */
    private static function relaxExpenseBookings(PDO $pdo): void
    {
        $notNull = null;
        foreach ($pdo->query('PRAGMA table_info(expense_bookings)')->fetchAll() as $column) {
            if ($column['name'] === 'ledger_id') {
                $notNull = (int)$column['notnull'] === 1;
            }
        }
        if ($notNull !== true) {
            return; // Tabelle fehlt oder ist bereits umgestellt.
        }

        // Fremdschluessel waehrend des Umbaus aus – sonst raeumt SQLite beim
        // DROP der alten Tabelle die kopierten Zeilen gleich wieder weg.
        $pdo->exec('PRAGMA foreign_keys = OFF');
        try {
            $pdo->exec(<<<SQL
            CREATE TABLE expense_bookings_neu (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                expense_id INTEGER NOT NULL REFERENCES expenses(id) ON DELETE CASCADE,
                month      TEXT    NOT NULL,
                ledger_id  INTEGER REFERENCES ledger(id) ON DELETE SET NULL,
                created_at TEXT    NOT NULL,
                UNIQUE (expense_id, month)
            );
            INSERT INTO expense_bookings_neu (id, expense_id, month, ledger_id, created_at)
                 SELECT id, expense_id, month, ledger_id, created_at FROM expense_bookings;
            DROP TABLE expense_bookings;
            ALTER TABLE expense_bookings_neu RENAME TO expense_bookings;
            SQL);
        } finally {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
    }

    /** Eine Spalte ergaenzen, falls sie noch fehlt. */
    private static function addColumn(PDO $pdo, string $table, string $column, string $definition): void
    {
        $existing = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array($column, $existing, true)) {
            $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
        }
    }

    /** Familie und Beispielaufgaben anlegen. */
    private static function seed(PDO $pdo): void
    {
        $insertUser = $pdo->prepare(
            'INSERT INTO users (name, role, pin_hash, must_change_pin, emoji, color, sort_order, created_at)
             VALUES (:name, :role, :pin_hash, 1, :emoji, :color, :sort_order, :created_at)'
        );

        $order = 0;
        foreach (self::SEED_USERS as $user) {
            $insertUser->execute([
                'name'       => $user['name'],
                'role'       => $user['role'],
                'pin_hash'   => password_hash($user['pin'], PASSWORD_DEFAULT),
                'emoji'      => $user['emoji'],
                'color'      => $user['color'],
                'sort_order' => $order += 10,
                'created_at' => now(),
            ]);
        }

        $thies = (int)$pdo->query("SELECT id FROM users WHERE name = 'Thies'")->fetchColumn();

        $insertTask = $pdo->prepare(
            'INSERT INTO tasks (title, description, emoji, amount_cents, kind, assigned_to, sort_order, created_by, created_at)
             VALUES (:title, :description, :emoji, :amount, \'repeatable\', NULL, :sort_order, :created_by, :created_at)'
        );

        $order = 0;
        foreach (self::SEED_TASKS as $task) {
            $insertTask->execute([
                'title'       => $task['title'],
                'description' => $task['description'],
                'emoji'       => $task['emoji'],
                'amount'      => $task['amount'],
                'sort_order'  => $order += 10,
                'created_by'  => $thies,
                'created_at'  => now(),
            ]);
        }

        $pdo->prepare('INSERT INTO settings (key, value) VALUES (:k, :v)')
            ->execute(['k' => 'installed_at', 'v' => now()]);
    }

    /** Alles in einer Transaktion ausfuehren. */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    private static function fail(string $message): never
    {
        error_log('[Kinderarbeit] ' . $message);
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><title>Kinderarbeit – Fehler</title>';
        echo '<div style="font:16px/1.6 system-ui,sans-serif;max-width:40rem;margin:4rem auto;padding:0 1.5rem">';
        echo '<h1 style="font-size:1.4rem">Die Anwendung konnte nicht starten</h1>';
        echo '<p>' . e($message) . '</p>';
        echo '<p style="color:#666">Details stehen in <code>data/php-error.log</code>.</p></div>';
        exit;
    }
}
