<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Persistência do módulo NFS-e no banco da aplicação.
 * Não executa INSERT/UPDATE/DELETE em tabelas do TOTVS RM.
 */
final class Nfse
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function dashboard(?int $codColigada = null): array
    {
        $filter = $codColigada !== null && $codColigada > 0 ? ' WHERE c.CODCOLIGADA = :codcoligada' : '';
        $params = $filter !== '' ? [':codcoligada' => $codColigada] : [];

        $sql = "SELECT
            COUNT_BIG(1) AS TOTAL,
            SUM(CASE WHEN f.STATUS IN ('PENDENTE_REVISAO','AGUARDANDO_AUTORIZACAO') THEN 1 ELSE 0 END) AS PENDENTES,
            SUM(CASE WHEN f.STATUS = 'EMITIDA' THEN 1 ELSE 0 END) AS EMITIDAS,
            SUM(CASE WHEN f.STATUS = 'ERRO' THEN 1 ELSE 0 END) AS COM_ERRO,
            SUM(CASE WHEN f.STATUS IN ('AGUARDANDO_CONFIGURACAO','AGUARDANDO_DADOS') THEN 1 ELSE 0 END) AS AGUARDANDO
          FROM dbo.NFS_FILA f
          INNER JOIN dbo.NFS_COBRANCA c ON c.ID = f.ID_COBRANCA" . $filter;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int) ($row['TOTAL'] ?? 0),
            'pendentes' => (int) ($row['PENDENTES'] ?? 0),
            'emitidas' => (int) ($row['EMITIDAS'] ?? 0),
            'com_erro' => (int) ($row['COM_ERRO'] ?? 0),
            'aguardando' => (int) ($row['AGUARDANDO'] ?? 0),
        ];
    }

    public function recentQueue(?int $codColigada = null, int $limit = 30): array
    {
        $limit = max(1, min(100, $limit));
        $where = $codColigada !== null && $codColigada > 0 ? ' WHERE c.CODCOLIGADA = :codcoligada' : '';
        $sql = "SELECT TOP {$limit}
                    f.ID AS ID_FILA,
                    f.STATUS,
                    f.PRIORIDADE,
                    f.TENTATIVAS,
                    f.DT_CRIACAO,
                    f.DT_ATUALIZACAO,
                    f.MENSAGEM_STATUS,
                    c.CODCOLIGADA,
                    c.IDLAN_RM,
                    c.CODCFO_RM,
                    c.NOME_TOMADOR,
                    c.COMPETENCIA,
                    c.VALOR_SERVICO,
                    c.VALOR_PAGO,
                    c.VALOR_DESCONTO,
                    c.VALOR_JUROS,
                    c.VALOR_MULTA
                FROM dbo.NFS_FILA f
                INNER JOIN dbo.NFS_COBRANCA c ON c.ID = f.ID_COBRANCA" . $where . "
                ORDER BY f.DT_CRIACAO DESC, f.ID DESC";
        $stmt = $this->db->prepare($sql);
        if ($where !== '') {
            $stmt->bindValue(':codcoligada', $codColigada, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function schemaIsAvailable(): bool
    {
        $stmt = $this->db->query("SELECT CASE WHEN OBJECT_ID('dbo.NFS_FILA','U') IS NOT NULL AND OBJECT_ID('dbo.NFS_COBRANCA','U') IS NOT NULL THEN 1 ELSE 0 END");
        return (int) $stmt->fetchColumn() === 1;
    }
}
