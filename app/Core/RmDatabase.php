<?php
declare(strict_types=1);
namespace App\Core;

use PDO;

/**
 * Conexão separada e somente leitura para o banco do TOTVS RM.
 * Utiliza SQL Server via PDO (driver sqlsrv ou dblib) configurado por variáveis de ambiente.
 */
final class RmDatabase
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host     = trim((string) (Env::get('RM_DB_HOST') ?: Env::get('DB_HOST', '')));
        $port     = trim((string) (Env::get('RM_DB_PORT') ?: Env::get('DB_PORT', '1433')));
        $database = trim((string) (Env::get('RM_DB_DATABASE') ?: Env::get('RM_DB_NAME') ?: Env::get('DB_NAME', '')));
        $user     = (string) (Env::get('RM_DB_USERNAME') ?? Env::get('RM_DB_USER') ?? Env::get('DB_USER', ''));
        $password = (string) (Env::get('RM_DB_PASSWORD') ?? Env::get('RM_DB_PASS') ?? Env::get('DB_PASS', ''));
        $driver   = strtolower(trim((string) Env::get('RM_DB_DRIVER', 'sqlsrv')));

        if ($host === '' || $database === '' || $user === '') {
            throw new \RuntimeException(
                'Conexão SQL do TOTVS RM não configurada. Preencha RM_DB_HOST, RM_DB_DATABASE (ou RM_DB_NAME), RM_DB_USERNAME (ou RM_DB_USER) e RM_DB_PASSWORD no .env.'
            );
        }

        $encrypt = Env::bool('RM_DB_ENCRYPT', true) ? '1' : '0';
        $trust   = Env::bool('RM_DB_TRUST_SERVER_CERTIFICATE', true) ? '1' : '0';

        if ($driver === 'dblib') {
            $dsn = "dblib:host={$host}:{$port};dbname={$database};charset=utf8";
        } else {
            // Padrão Microsoft Drivers for PHP for SQL Server (sqlsrv)
            $dsn = "sqlsrv:Server={$host},{$port};Database={$database};Encrypt={$encrypt};TrustServerCertificate={$trust}";
        }

        self::$pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return self::$pdo;
    }

    /**
     * Permite redefinir a conexão (útil para testes ou reconexão com novas credenciais)
     */
    public static function reset(): void
    {
        self::$pdo = null;
    }
}
