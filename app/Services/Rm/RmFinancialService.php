<?php
declare(strict_types=1);
namespace App\Services\Rm;

use App\Core\Database;
use PDO;
use Throwable;

/**
 * Serviço de sincronização: lê do TOTVS RM e persiste nas tabelas NFS_ da aplicação.
 * NUNCA grava no banco do RM. Toda escrita é no banco da aplicação (Database::connection()).
 */
final class RmFinancialService
{
    private RmReceivableRepository $repo;
    private PDO $appDb;

    public function __construct()
    {
        $this->repo  = new RmReceivableRepository(RmConnection::pdo());
        $this->appDb = Database::connection();
    }

    // -------------------------------------------------------------------------
    // SINCRONIZAÇÃO
    // -------------------------------------------------------------------------

    /**
     * Sincroniza lançamentos do RM com as tabelas NFS_COBRANCA e NFS_BAIXAS.
     * Idempotente: registros existentes são atualizados, novos são inseridos.
     *
     * @param array $filters Mesmos filtros aceitos por RmReceivableRepository::findReceivables()
     * @return array{sincronizados: int, inseridos: int, atualizados: int, erros: int, logs: string[]}
     */
    public function sync(array $filters = []): array
    {
        $stats = ['sincronizados' => 0, 'inseridos' => 0, 'atualizados' => 0, 'erros' => 0, 'logs' => []];

        // Buscar todas as páginas do RM
        $filters['por_pagina'] = 100;
        $filters['pagina']     = 1;

        do {
            $result = $this->repo->findReceivables($filters);
            foreach ($result['rows'] as $row) {
                try {
                    $this->upsertCobranca($row);
                    $stats['sincronizados']++;

                    $existente = $this->findCobranca((int) $row['CODCOLIGADA'], (string) $row['IDLAN']);
                    if ($existente) {
                        $this->syncBaixas($existente['ID'], $row);
                    }
                } catch (Throwable $e) {
                    $stats['erros']++;
                    $msg = "[SINC] CODCOLIGADA={$row['CODCOLIGADA']} IDLAN={$row['IDLAN']}: {$e->getMessage()}";
                    $stats['logs'][] = $msg;
                    $this->writeLog('ERROR', 'RmFinancialService::sync', $msg);
                }
            }
            $filters['pagina']++;
        } while ($filters['pagina'] <= $result['paginas']);

        $this->writeLog('INFO', 'RmFinancialService::sync', json_encode([
            'sincronizados' => $stats['sincronizados'],
            'erros'         => $stats['erros'],
        ]));

        return $stats;
    }

    // -------------------------------------------------------------------------
    // DASHBOARD E PAINEL
    // -------------------------------------------------------------------------

    /** Indicadores consolidados do painel. Combina dados do RM com NFS_COBRANCA. */
    public function panelIndicators(?int $codColigada = null): array
    {
        // Dados do RM (origem real)
        $rmIndicators = $this->repo->dashboardIndicators($codColigada);

        // Dados da nossa fila (emissões)
        $where  = $codColigada !== null ? 'WHERE c.CODCOLIGADA = :codcoligada' : '';
        $params = $codColigada !== null ? [':codcoligada' => $codColigada] : [];

        $sql = "SELECT
                    COUNT_BIG(1)                                                             AS TOTAL_NFS,
                    SUM(CASE WHEN c.ELEGIVEL_EMISSAO = 1 AND c.QUITADA_INTEGRAL = 1
                              AND f.ID IS NULL THEN 1 ELSE 0 END)                           AS AGUARDANDO_EMISSAO,
                    SUM(CASE WHEN f.STATUS = 'EMITIDA' THEN 1 ELSE 0 END)                   AS EMITIDAS,
                    SUM(CASE WHEN f.STATUS = 'ERRO'    THEN 1 ELSE 0 END)                   AS COM_ERRO,
                    SUM(CASE WHEN c.QUITADA_INTEGRAL = 1 THEN 1 ELSE 0 END)                 AS QUITADAS,
                    SUM(CASE WHEN c.ELEGIVEL_EMISSAO = 0
                              AND c.QUITADA_INTEGRAL = 0
                              AND c.VALOR_PAGO > 0    THEN 1 ELSE 0 END)                    AS PARCIAIS
                FROM dbo.NFS_COBRANCA c
                LEFT JOIN dbo.NFS_FILA f ON f.ID_COBRANCA = c.ID
                {$where}";

        $stmt = $this->appDb->prepare($sql);
        $stmt->execute($params);
        $nfs = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return array_merge($rmIndicators, [
            'total_nfs_cobrancas' => (int)   ($nfs['TOTAL_NFS']        ?? 0),
            'aguardando_emissao'  => (int)   ($nfs['AGUARDANDO_EMISSAO']?? 0),
            'emitidas'            => (int)   ($nfs['EMITIDAS']          ?? 0),
            'com_erro'            => (int)   ($nfs['COM_ERRO']          ?? 0),
            'nfs_quitadas'        => (int)   ($nfs['QUITADAS']          ?? 0),
            'nfs_parciais'        => (int)   ($nfs['PARCIAIS']          ?? 0),
        ]);
    }

    // -------------------------------------------------------------------------
    // ACESSO AO REPOSITÓRIO
    // -------------------------------------------------------------------------

    public function getRepository(): RmReceivableRepository
    {
        return $this->repo;
    }

    // -------------------------------------------------------------------------
    // PERSISTÊNCIA NAS TABELAS NFS_
    // -------------------------------------------------------------------------

    private function upsertCobranca(array $row): void
    {
        $codColigada   = (int)    $row['CODCOLIGADA'];
        $idlan         = (string) $row['IDLAN'];
        $valorOriginal = (float)  ($row['VALORORIGINAL'] ?? 0);
        $valorPago     = (float)  ($row['SOMA_PAGO']     ?? 0);
        $desconto      = (float)  ($row['TOTAL_DESCONTOS'] ?? $row['VALORDESCONTO_TOTAL'] ?? $row['SOMA_DESCONTO_BAIXA'] ?? 0);
        $juros         = (float)  ($row['SOMA_JUROS_BAIXA']    ?? 0);
        $multa         = (float)  ($row['SOMA_MULTA_BAIXA']    ?? 0);
        $saldo         = (float)  ($row['SALDO']               ?? ($valorOriginal - $valorPago));
        $quitada       = ($row['SITUACAO_FINANCEIRA'] ?? '') === 'QUITADO' ? 1 : 0;
        $elegivel      = ($row['ELEGIVEL_EMISSAO']    ?? false) ? 1 : 0;
        $hash          = hash('sha256', $codColigada . '|' . $idlan . '|' . $valorOriginal . '|' . $valorPago);

        $nomeTomador = (string) ($row['TOMADOR_NOME'] ?? $row['TOMADOR_NOMEFANTASIA'] ?? '');
        $documento   = preg_replace('/\D/', '', (string) ($row['TOMADOR_DOCUMENTO'] ?? ''));
        $email       = (string) ($row['TOMADOR_EMAIL']     ?? '');
        $vencimento  = $row['DATAVENCIMENTO'] ?? null;
        $competencia = $vencimento ? substr((string) $vencimento, 0, 7) : null;

        $existing = $this->findCobranca($codColigada, $idlan);

        if ($existing) {
            // Atualizar
            $sql = "UPDATE dbo.NFS_COBRANCA SET
                        NOME_TOMADOR      = :nome,
                        DOCUMENTO_TOMADOR = :doc,
                        EMAIL_TOMADOR     = :email,
                        DATA_VENCIMENTO   = :venc,
                        COMPETENCIA       = :comp,
                        VALOR_ORIGINAL    = :vorig,
                        VALOR_PAGO        = :vpago,
                        VALOR_DESCONTO    = :desc,
                        VALOR_JUROS       = :juros,
                        VALOR_MULTA       = :multa,
                        SALDO_RM          = :saldo,
                        QUITADA_INTEGRAL  = :quitada,
                        ELEGIVEL_EMISSAO  = :elegivel,
                        HASH_ORIGEM       = :hash,
                        DT_ULTIMA_SINC    = SYSDATETIME(),
                        DT_ATUALIZACAO    = SYSDATETIME()
                    WHERE CODCOLIGADA = :codcoligada AND IDLAN_RM = :idlan";
        } else {
            // Inserir
            $sql = "INSERT INTO dbo.NFS_COBRANCA
                        (CODCOLIGADA, IDLAN_RM, CODCFO_RM, NOME_TOMADOR, DOCUMENTO_TOMADOR,
                         EMAIL_TOMADOR, COMPETENCIA, DATA_VENCIMENTO, VALOR_ORIGINAL, VALOR_PAGO,
                         VALOR_DESCONTO, VALOR_JUROS, VALOR_MULTA, VALOR_SERVICO, SALDO_RM,
                         QUITADA_INTEGRAL, ELEGIVEL_EMISSAO, HASH_ORIGEM, DT_ULTIMA_SINC)
                    VALUES
                        (:codcoligada, :idlan, :codcfo, :nome, :doc, :email, :comp, :venc,
                         :vorig, :vpago, :desc, :juros, :multa, :vorig, :saldo,
                         :quitada, :elegivel, :hash, SYSDATETIME())";
        }

        $params = [
            ':codcoligada' => $codColigada,
            ':idlan'       => $idlan,
            ':nome'        => $nomeTomador ?: null,
            ':doc'         => $documento   ?: null,
            ':email'       => $email       ?: null,
            ':venc'        => $vencimento  ?: null,
            ':comp'        => $competencia ?: null,
            ':vorig'       => $valorOriginal,
            ':vpago'       => $valorPago,
            ':desc'        => $desconto,
            ':juros'       => $juros,
            ':multa'       => $multa,
            ':saldo'       => $saldo,
            ':quitada'     => $quitada,
            ':elegivel'    => $elegivel,
            ':hash'        => $hash,
        ];

        if (!$existing) {
            $params[':codcfo'] = (string) ($row['CODCFO'] ?? '');
        }

        $this->appDb->prepare($sql)->execute($params);
    }

    private function syncBaixas(int $idCobranca, array $row): void
    {
        $baixas = $this->repo->findBaixas((int) $row['CODCOLIGADA'], (string) $row['IDLAN']);

        foreach ($baixas as $b) {
            $codColigada = (int)    $b['CODCOLIGADA'];
            $idlan       = (string) $b['IDLAN'];
            $idbaixa     = (string) $b['IDBAIXA'];
            $estornada   = ($b['DATACANCELBAIXA'] !== null) ? 1 : 0;
            $hash        = hash('sha256', $codColigada . '|' . $idlan . '|' . $idbaixa);

            // Verificar se já existe
            $checkSql  = "SELECT COUNT(1) FROM dbo.NFS_BAIXAS
                          WHERE CODCOLIGADA = :c AND IDLAN_RM = :l AND IDBAIXA_RM = :b";
            $checkStmt = $this->appDb->prepare($checkSql);
            $checkStmt->execute([':c' => $codColigada, ':l' => $idlan, ':b' => $idbaixa]);
            $exists = (int) $checkStmt->fetchColumn() > 0;

            if ($exists) {
                $sql = "UPDATE dbo.NFS_BAIXAS SET
                            DATA_BAIXA  = :data,
                            VALOR_BAIXA = :valor,
                            STATUS_RM   = :status,
                            ESTORNADA   = :estornada,
                            DT_SINC     = SYSDATETIME()
                        WHERE CODCOLIGADA = :c AND IDLAN_RM = :l AND IDBAIXA_RM = :b";
            } else {
                $sql = "INSERT INTO dbo.NFS_BAIXAS
                            (ID_COBRANCA, CODCOLIGADA, IDLAN_RM, IDBAIXA_RM,
                             DATA_BAIXA, VALOR_BAIXA, STATUS_RM, ESTORNADA, HASH_ORIGEM)
                        VALUES
                            (:id_cob, :c, :l, :b, :data, :valor, :status, :estornada, :hash)";
            }

            $params = [
                ':c'         => $codColigada,
                ':l'         => $idlan,
                ':b'         => $idbaixa,
                ':data'      => $b['DATABAIXA']  ?? null,
                ':valor'     => (float) ($b['VALORBAIXA'] ?? 0),
                ':status'    => (string) ($b['STATUS'] ?? ''),
                ':estornada' => $estornada,
            ];
            if (!$exists) {
                $params[':id_cob'] = $idCobranca;
                $params[':hash']   = $hash;
            }

            $this->appDb->prepare($sql)->execute($params);
        }
    }

    private function findCobranca(int $codColigada, string $idlan): ?array
    {
        $stmt = $this->appDb->prepare(
            "SELECT ID, ELEGIVEL_EMISSAO FROM dbo.NFS_COBRANCA
             WHERE CODCOLIGADA = :c AND IDLAN_RM = :l"
        );
        $stmt->execute([':c' => $codColigada, ':l' => $idlan]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function writeLog(string $nivel, string $origem, string $mensagem): void
    {
        try {
            $this->appDb->prepare(
                "INSERT INTO dbo.NFS_LOG (NIVEL, ORIGEM, MENSAGEM) VALUES (:n, :o, :m)"
            )->execute([':n' => $nivel, ':o' => $origem, ':m' => mb_substr($mensagem, 0, 2000)]);
        } catch (Throwable) {
            // silencioso – log de log não pode derrubar o processo
        }
    }
}
