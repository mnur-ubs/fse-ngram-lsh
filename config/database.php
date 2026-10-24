<?php

declare(strict_types=1);

namespace Fkse\Config;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $instance = null;

    private static bool $envLoaded = false;

    public const DEFAULT_SECRET_KEY = 'kunci_rahasia_rekam_medis_123';

    /**
     * Loads KEY=VALUE pairs from the .env file in the PoC root into the process environment.
     * Variables that are already set in the real environment take precedence.
     */
    public static function loadEnv(?string $path = null): void
    {
        if (self::$envLoaded) {
            return;
        }
        self::$envLoaded = true;

        $path ??= dirname(__DIR__) . '/.env';
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]) {
                $value = substr($value, 1, -1);
            }
            if ($key !== '' && getenv($key) === false) {
                putenv("{$key}={$value}");
            }
        }
    }

    public static function getConnection(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        self::loadEnv();

        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = (int) (getenv('DB_PORT') ?: 3306);
        $db   = getenv('DB_NAME') ?: 'master_icd_x';
        $user = getenv('DB_USER') ?: 'root';
        $pass = (string) (getenv('DB_PASS') ?: '');
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
            throw new \RuntimeException(
                'Database connection failed: ' . $e->getMessage()
                . ' (copy .env.example to .env and fill in DB_* values)',
                (int) $e->getCode(),
                $e
            );
        }
    }

    public static function getSecretKey(): string
    {
        self::loadEnv();

        return getenv('FKSE_SECRET_KEY') ?: self::DEFAULT_SECRET_KEY;
    }
}
