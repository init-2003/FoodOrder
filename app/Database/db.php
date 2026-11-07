<?php
declare(strict_types=1);
require_once __DIR__ . '/../Services/log.php';
$autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (is_file($autoload)) require_once $autoload;

// POS timestamps are stored as local wall time without timezone information.
date_default_timezone_set('Asia/Bangkok');

if (!function_exists('loadEnv')) {
    function loadEnv(string $path): void {
        if (!file_exists($path)) {
            return;
        }
        if (class_exists(\Dotenv\Dotenv::class)) {
            \Dotenv\Dotenv::createUnsafeMutable(dirname($path), basename($path))->load();
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
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v, " \t\n\r\0\x0B\"'");
            putenv("$k=$v");
            $_ENV[$k] = $v;
            $_SERVER[$k] = $v;
        }
    }
}

loadEnv(dirname(__DIR__, 2) . '/.env');

class Database {
    private static ?PDO $pdo = null;
    private static bool $connectionAttempted = false;

    public static function getConnection(): ?PDO {
        if (self::$pdo !== null) {
            return self::$pdo;
        }
        if (self::$connectionAttempted) {
            return null;
        }
        self::$connectionAttempted = true;

        $host = getenv('DB_HOST') ?: '';
        $database = getenv('DB_DATABASE') ?: '';
        $username = getenv('DB_USERNAME') ?: '';
        $password = getenv('DB_PASSWORD') ?: '';

        if (!$host || !$database) {
            return null;
        }

        $drivers = [
            "odbc:Driver={ODBC Driver 18 for SQL Server};Server=$host;Database=$database;TrustServerCertificate=yes;Encrypt=optional;LoginTimeout=5",
            "odbc:Driver={SQL Server};Server=$host;Database=$database;LoginTimeout=5",
        ];

        foreach ($drivers as $dsn) {
            try {
                $pdo = new PDO($dsn, $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT => 5,
                ]);
                self::$pdo = $pdo;
                return $pdo;
            } catch (Throwable $e) {
                appLog('error', 'SQL Server connection failed', ['driver' => str_contains($dsn, 'ODBC Driver 18') ? 'ODBC Driver 18' : 'SQL Server', 'error' => $e->getMessage()]);
            }
        }

        return null;
    }

    public static function decodeThai(?string $hex): string {
        if ($hex === null || $hex === '') {
            return '';
        }
        if (ctype_xdigit($hex) && strlen($hex) % 2 === 0) {
            $bin = hex2bin($hex);
            if ($bin !== false) {
                $decoded = @mb_convert_encoding($bin, 'UTF-8', 'UTF-16LE');
                if ($decoded !== false) {
                    return $decoded;
                }
            }
        }
        return $hex;
    }

    public static function hexThai(string $text): string {
        $utf16le = mb_convert_encoding($text, 'UTF-16LE', 'UTF-8');
        return '0x' . bin2hex($utf16le);
    }
}

