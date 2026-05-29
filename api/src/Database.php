<?php
namespace App;
use PDO, PDOException;

/**
 * Database — PDO singleton.
 * Використовує стандартні ? плейсхолдери (не PostgreSQL-нативні $1,$2)
 * — так pdo_pgsql коректно працює на WampServer/Windows.
 */
class Database
{
    private static ?PDO $pdo = null;

    public static function get(): PDO
    {
        if (self::$pdo) return self::$pdo;
        try {
            self::$pdo = new PDO(
                sprintf('pgsql:host=%s;port=%s;dbname=%s', DB_HOST, DB_PORT, DB_NAME),
                DB_USER,
                DB_PASSWORD,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['error' => 'DB connection failed: ' . $e->getMessage()]));
        }
        return self::$pdo;
    }

    public static function query(string $sql, array $p = []): array
    {
        $s = self::get()->prepare($sql); $s->execute($p); return $s->fetchAll();
    }

    public static function one(string $sql, array $p = []): ?array
    {
        $s = self::get()->prepare($sql); $s->execute($p);
        $r = $s->fetch(); return $r === false ? null : $r;
    }

    public static function exec(string $sql, array $p = []): int
    {
        $s = self::get()->prepare($sql); $s->execute($p); return $s->rowCount();
    }

    public static function insert(string $sql, array $p = []): int
    {
        $s = self::get()->prepare($sql . ' RETURNING id');
        $s->execute($p); return (int) $s->fetchColumn();
    }
}
