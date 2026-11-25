<?php
declare(strict_types=1);

namespace NovaTech;

use Atk4\Data\Persistence;
use PDO;
use PDOStatement;

/**
 * Shared MariaDB access for both Atk4\Data Models and transactional PDO auth/audit operations.
 */
final class Db
{
    private static ?PDO $pdo = null;
    private static ?Persistence\Sql $persistence = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $c = Config::get()['db'];
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $c['host'],
                (int) $c['port'],
                $c['name']
            );
            self::$pdo = new PDO($dsn, (string) $c['user'], (string) $c['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            self::$pdo->exec("SET time_zone = '+07:00'");
        }

        return self::$pdo;
    }

    public static function persistence(): Persistence\Sql
    {
        if (self::$persistence === null) {
            $c = Config::get()['db'];
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $c['host'],
                (int) $c['port'],
                $c['name']
            );
            self::$persistence = new Persistence\Sql($dsn, (string) $c['user'], (string) $c['password']);
            self::$persistence->getConnection()->expr("SET time_zone = '+07:00'")->executeStatement();
        }

        return self::$persistence;
    }

    /**
     * @param list<mixed> $params
     */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt;
    }

    /**
     * @param list<mixed> $params
     *
     * @return list<array<string, mixed>>
     */
    public static function rows(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /**
     * @param list<mixed> $params
     *
     * @return array<string, mixed>|null
     */
    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    public static function clientIp(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'), 0, 45);
    }

    /**
     * @param array<string, mixed>|null $actor
     */
    public static function audit(string $action, string $object = '', string $result = 'success', ?array $actor = null): void
    {
        $u = $actor ?? ($_SESSION['_auth_actor'] ?? null);
        self::query(
            'INSERT INTO audit_logs (user_id, username, hanh_dong, doi_tuong, ip_nguon, ket_qua) VALUES (?, ?, ?, ?, ?, ?)',
            [
                $u['id'] ?? null,
                mb_substr((string) ($u['username'] ?? 'guest'), 0, 64),
                mb_substr($action, 0, 64),
                mb_substr($object, 0, 190),
                self::clientIp(),
                mb_substr($result, 0, 30),
            ]
        );
    }
}
