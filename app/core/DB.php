<?php
/**
 * Zinkly Social Lite - database access (PDO). Works with MySQL / MariaDB and SQLite.
 * Every query uses prepared statements. Times are stored as Unix seconds (INTEGER) so both engines behave the same.
 */
final class DB
{
    private static ?PDO $pdo = null;
    private static string $driver = 'mysql';
    private static string $prefix = '';

    public static function connect(array $cfg): void
    {
        self::$driver = $cfg['driver'] ?? 'mysql';
        self::$prefix = preg_replace('/[^a-z0-9_]/i', '', (string) ($cfg['prefix'] ?? ''));
        $opts = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        if (self::$driver === 'sqlite') {
            self::$pdo = new PDO('sqlite:' . $cfg['path'], null, null, $opts);
            self::$pdo->exec('PRAGMA journal_mode = WAL');
            self::$pdo->exec('PRAGMA foreign_keys = ON');
            self::$pdo->exec('PRAGMA busy_timeout = 5000');
        } else {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $cfg['host'], (int) ($cfg['port'] ?? 3306), $cfg['name']);
            self::$pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], $opts);
            self::$pdo->exec("SET time_zone = '+00:00', sql_mode = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION'");
        }
    }

    /** Close the connection (needed before the SQLite file is replaced by a backup). */
    public static function disconnect(): void
    {
        self::$pdo = null;
    }

    public static function pdo(): PDO
    {
        if (!self::$pdo) {
            throw new RuntimeException('Database not connected');
        }
        return self::$pdo;
    }

    public static function driver(): string
    {
        return self::$driver;
    }

    /** Table name with the configured prefix: DB::t('users'). */
    public static function t(string $table): string
    {
        return self::$prefix . $table;
    }

    /** Replace {table} with the prefixed name. */
    private static function sql(string $sql): string
    {
        return preg_replace_callback('/\{([a-z_]+)\}/', fn ($m) => self::$prefix . $m[1], $sql);
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        $st = self::pdo()->prepare(self::sql($sql));
        $named = $params && array_values($params) !== $params;
        $i = 0;
        foreach ($params as $k => $v) {
            $type = (is_int($v) || is_bool($v)) ? PDO::PARAM_INT : (is_null($v) ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $st->bindValue($named ? ':' . ltrim((string) $k, ':') : ++$i, is_bool($v) ? (int) $v : $v, $type);
        }
        $st->execute();
        return $st;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $r = self::query($sql, $params)->fetch();
        return $r === false ? null : $r;
    }

    public static function value(string $sql, array $params = [])
    {
        $r = self::query($sql, $params)->fetchColumn();
        return $r === false ? null : $r;
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = 'INSERT INTO ' . self::t($table) . ' (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
        self::query($sql, array_values($data));
        return (int) self::pdo()->lastInsertId();
    }

    /** Insert unless a unique key already exists. Returns true when a row was added. */
    public static function insertIgnore(string $table, array $data): bool
    {
        $cols = array_keys($data);
        $verb = self::$driver === 'sqlite' ? 'INSERT OR IGNORE' : 'INSERT IGNORE';
        $sql = $verb . ' INTO ' . self::t($table) . ' (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
        return self::query($sql, array_values($data))->rowCount() > 0;
    }

    public static function update(string $table, array $data, string $where, array $params = []): int
    {
        $set = implode(',', array_map(fn ($c) => "$c = ?", array_keys($data)));
        return self::query('UPDATE ' . self::t($table) . " SET $set WHERE $where", array_merge(array_values($data), $params))->rowCount();
    }

    public static function delete(string $table, string $where, array $params = []): int
    {
        return self::query('DELETE FROM ' . self::t($table) . " WHERE $where", $params)->rowCount();
    }

    public static function transaction(callable $fn)
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $r = $fn();
            $pdo->commit();
            return $r;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** "?,?,?" for an IN (...) list. */
    public static function in(array $values): string
    {
        return $values ? implode(',', array_fill(0, count($values), '?')) : 'NULL';
    }

    /** Case-insensitive LIKE that works the same on both engines. */
    public static function like(string $column): string
    {
        return "LOWER($column) LIKE ? ESCAPE " . (self::$driver === 'sqlite' ? "'\\'" : "'\\\\'");
    }

    public static function escapeLike(string $s): string
    {
        return '%' . addcslashes(mb_strtolower($s), '%_\\') . '%';
    }
}
