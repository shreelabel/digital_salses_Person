<?php
declare(strict_types=1);

namespace SLC\Core;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Thin, safe PDO wrapper (the single DB gateway for the whole app).
 * - ONLY ever connects to the configured slc_ai_sales database.
 * - All queries use prepared statements (no string concatenation of user input).
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function connect(array $override = []): PDO
    {
        $c = array_merge(Config::db(), $override);
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $c['host'], $c['port'], $c['name'], $c['charset']
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];
        try {
            return new PDO($dsn, $c['user'], $c['pass'], $options);
        } catch (PDOException $e) {
            throw new PDOException(
                'Database connection failed. Run setup.php and verify DB credentials. ' .
                (Config::debug() ? $e->getMessage() : ''),
                (int) $e->getCode(),
                $e
            );
        }
    }

    public static function disconnect(): void
    {
        self::$pdo = null;
    }

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = self::connect();
        }
        return self::$pdo;
    }

    public static function isConnectionLost(\Throwable $e): bool
    {
        $msg = $e->getMessage();
        return str_contains($msg, '2006') ||
               str_contains($msg, '2013') ||
               stripos($msg, 'gone away') !== false ||
               stripos($msg, 'Lost connection') !== false ||
               stripos($msg, 'server closed the connection') !== false ||
               stripos($msg, 'Packets out of order') !== false;
    }

    /** Test whether the database + tables are ready. */
    public static function isReady(): bool
    {
        try {
            self::pdo()->query('SELECT 1 FROM slc_users LIMIT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        try {
            $stmt = self::pdo()->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            if (self::isConnectionLost($e)) {
                self::$pdo = null; // force fresh reconnection
                $stmt = self::pdo()->prepare($sql);
                $stmt->execute($params);
                return $stmt;
            }
            throw $e;
        }
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function fetchColumn(string $sql, array $params = []): mixed
    {
        $v = self::query($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public static function insert(string $table, array $data): int
    {
        unset($data['id']);
        $cols = array_keys($data);
        $placeholders = array_map(fn($c) => ':' . $c, $cols);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $cols),
            implode(', ', $placeholders)
        );
        $maxRetries = 5;
        while ($maxRetries > 0) {
            try {
                $pdo = self::pdo();
                $stmt = $pdo->prepare($sql);
                $stmt->execute($data);
                return (int) $pdo->lastInsertId();
            } catch (\PDOException $e) {
                if (self::isConnectionLost($e)) {
                    self::$pdo = null;
                    $maxRetries--;
                } elseif (self::autoHealMissingColumn($table, $e)) {
                    $maxRetries--;
                } else {
                    throw $e;
                }
            }
        }
        return 0;
    }

    public static function update(string $table, int $id, array $data): int
    {
        unset($data['id'], $data['created_at']);
        $set = [];
        foreach (array_keys($data) as $col) {
            $set[] = $col . ' = :' . $col;
        }
        $data['id'] = $id;
        $sql = sprintf('UPDATE %s SET %s WHERE id = :id', $table, implode(', ', $set));
        $maxRetries = 5;
        while ($maxRetries > 0) {
            try {
                $pdo = self::pdo();
                $stmt = $pdo->prepare($sql);
                $stmt->execute($data);
                return $stmt->rowCount();
            } catch (\PDOException $e) {
                if (self::isConnectionLost($e)) {
                    self::$pdo = null;
                    $maxRetries--;
                } elseif (self::autoHealMissingColumn($table, $e)) {
                    $maxRetries--;
                } else {
                    throw $e;
                }
            }
        }
        return 0;
    }

    private static function autoHealMissingColumn(string $table, \PDOException $e): bool
    {
        if (preg_match("/Unknown column '([^']+)'/i", $e->getMessage(), $m)) {
            $col = $m[1];
            $def = match ($col) {
                'assigned_at'        => 'DATETIME NULL DEFAULT NULL',
                'assigned_to'        => 'INT UNSIGNED NULL DEFAULT NULL',
                'raw_data'           => 'JSON NULL',
                'apollo_account_id',
                'apollo_contact_id',
                'import_batch_id'    => 'VARCHAR(100) NULL',
                default              => (str_ends_with($col, '_at') ? 'DATETIME NULL' : 'VARCHAR(255) NULL'),
            };
            try {
                self::pdo()->exec("ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$def}");
                return true;
            } catch (\Throwable) {
                return false;
            }
        }
        return false;
    }

    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $fn($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
