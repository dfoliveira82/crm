<?php
declare(strict_types=1);
namespace App\Services\Rm;

use App\Core\RmDatabase;
use PDO;
use Throwable;

/**
 * Fachada de conexão e diagnóstico para o banco do TOTVS RM.
 * Apenas leitura – nenhum INSERT/UPDATE/DELETE é executado aqui.
 */
final class RmConnection
{
    /** Verifica se a conexão pode ser estabelecida. */
    public static function test(): array
    {
        try {
            $pdo = RmDatabase::connection();
            $result = $pdo->query("SELECT @@VERSION AS VER, DB_NAME() AS DBNAME, GETDATE() AS NOW")->fetch(PDO::FETCH_ASSOC);
            return [
                'ok'      => true,
                'version' => $result['VER'] ?? '',
                'dbname'  => $result['DBNAME'] ?? '',
                'server_time' => $result['NOW'] ?? '',
            ];
        } catch (Throwable $e) {
            return [
                'ok'    => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verifica quais das tabelas esperadas existem no banco RM.
     * Útil para detectar instalações que usam schemas diferentes.
     */
    public static function checkTables(): array
    {
        $expected = ['FLAN', 'FLANBAIXA', 'FCFO'];
        $found    = [];
        $missing  = [];

        try {
            $pdo  = RmDatabase::connection();
            $stmt = $pdo->query(
                "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_TYPE = 'BASE TABLE'
                   AND TABLE_NAME IN ('FLAN','FLANBAIXA','FCFO')"
            );
            $rows  = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $found   = $rows;
            $missing = array_values(array_diff($expected, $rows));
        } catch (Throwable $e) {
            $missing = $expected;
        }

        return ['found' => $found, 'missing' => $missing];
    }

    /** Retorna a conexão PDO (somente leitura). */
    public static function pdo(): PDO
    {
        return RmDatabase::connection();
    }
}
