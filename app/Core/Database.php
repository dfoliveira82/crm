<?php
declare(strict_types=1);
namespace App\Core;
use PDO;
use PDOException;
final class Database {
    private static ?PDO $pdo = null;
    public static function connection(): PDO {
        if (self::$pdo) return self::$pdo;
        $host = (string)Env::get('DB_HOST', '');
        $db = (string)Env::get('DB_NAME', '');
        if ($host === '' || $db === '') throw new \RuntimeException('Banco não configurado.');
        $port = (string)Env::get('DB_PORT', '1433');
        $dsn = "sqlsrv:Server={$host},{$port};Database={$db};Encrypt=" . (Env::bool('DB_ENCRYPT', true) ? '1' : '0') . ";TrustServerCertificate=" . (Env::bool('DB_TRUST_SERVER_CERTIFICATE') ? '1' : '0');
        self::$pdo = new PDO($dsn, (string)Env::get('DB_USER'), (string)Env::get('DB_PASS'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return self::$pdo;
    }
}
