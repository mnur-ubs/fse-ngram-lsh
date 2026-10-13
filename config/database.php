<?php

declare(strict_types=1);

namespace Fkse\Config;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $instance = null;

    public const DEFAULT_SECRET_KEY = 'kunci_rahasia_rekam_medis_123';

    public static function getConnection(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = (int) (getenv('DB_PORT') ?: 3306);
        $db   = getenv('DB_NAME') ?: 'master_icd_x';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') !== false ? (string) getenv('DB_PASS') : 'xNSXX93keQD0a66s8UXmCTNj0874RsNd';
        $charset = 'utf8mb4';

        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            self::$instance = new PDO($dsn, $user, $pass, $options);
            return self::$instance;
        } catch (PDOException $e) {
            throw new \RuntimeException('Database connection failed: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    public static function getSecretKey(): string
    {
        return getenv('FKSE_SECRET_KEY') ?: self::DEFAULT_SECRET_KEY;
    }
}
