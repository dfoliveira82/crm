<?php
declare(strict_types=1);
namespace App\Services\Rm;

use PDO;
use Throwable;

/**
 * Repositório de lançamentos financeiros do TOTVS RM.
 *
 * Mapeamento e finalidade dos campos utilizados:
 *   FLAN.CODCOLIGADA      - Código da coligada no RM
 *   FLAN.IDLAN            - Identificador único do lançamento financeiro
 *   FLAN.CODFILIAL        - Código da filial
 *   FLAN.PAGREC           - 1 = A Receber, 2 = A Pagar
 *   FLAN.STATUSLAN        - Código de situação no RM (ex.: 0/1/Aberto, Baixado, Cancelado)
 *   FLAN.CODTDO           - Código do tipo de documento
 *   FLAN.NUMERODOCUMENTO  - Número do documento de cobrança / boleto
 *   FLAN.HISTORICO        - Histórico / descrição do lançamento (referência da mensalidade)
 *   FLAN.DATACRIACAO      - Data de inclusão do lançamento no RM
 *   FLAN.DATAEMISSAO      - Data de emissão da cobrança
 *   FLAN.DATAVENCIMENTO   - Data de vencimento da mensalidade
 *   FLAN.DATABAIXA        - Data da última baixa registrada no lançamento
 *   FLAN.DATACANCELAMENTO - Data de cancelamento do lançamento (quando cancelado)
 *   FLAN.DATACANCELBAIXA  - Data de estorno/cancelamento de baixa no lançamento
 *   FLAN.VALORORIGINAL    - Valor original nominal do lançamento
 *   FLAN.VALORBAIXADO     - Valor total amortizado/baixado segundo o RM
 *   FLAN.VALORDESCONTO    - Descontos concedidos no lançamento
 *   FLAN.VALORJUROS       - Juros aplicados
 *   FLAN.VALORMULTA       - Multa aplicada
 *   FLAN.CODCOLCFO        - Coligada do cliente (tomador)
 *   FLAN.CODCFO           - Código do cliente/fornecedor (tomador)
 *
 *   FLANBAIXA.IDBAIXA          - Identificador da baixa
 *   FLANBAIXA.DATABAIXA        - Data do pagamento/baixa
 *   FLANBAIXA.DATACANCELBAIXA  - Data de estorno/cancelamento (NULL = baixa ativa e válida)
 *   FLANBAIXA.VALORBAIXA       - Valor financeiro efetivamente pago pelo tomador
 *   FLANBAIXA.VALORORIGINAL    - Parcela do principal amortizada por esta baixa
 *   FLANBAIXA.VALORDESCONTO    - Desconto concedido nesta baixa
 *   FLANBAIXA.VALORJUROS       - Juros pagos nesta baixa
 *   FLANBAIXA.VALORMULTA       - Multa paga nesta baixa
 *   FLANBAIXA.STATUS           - Situação da baixa no RM
 *   FLANBAIXA.TIPOBAIXA        - Código do tipo de baixa
 *
 *   FCFO.NOME / NOMEFANTASIA   - Razão social / Nome do tomador
 *   FCFO.CGCCFO                - CPF ou CNPJ do tomador
 *   FCFO.EMAIL                 - E-mail para notificação/emissão
 *   FCFO.RUA, NUMERO, etc.     - Endereço cadastral para NFS-e
 *
 * REGRAS DE NEGÓCIO E SEGURANÇA:
 * - Conexão exclusivamente de LEITURA (SELECT).
 * - Quitação integral: somatório de baixas ativas (DATACANCELBAIXA IS NULL) cobre o valor original.
 * - Pagamento parcial: NÃO é elegível para NFS-e (ELEGIVEL_EMISSAO = false).
 * - Cancelamentos/estornos: identificados por DATACANCELAMENTO ou DATACANCELBAIXA.
 */
final class RmReceivableRepository
{
    public const TOLERANCE = 0.01; // Tolerância de 1 centavo para arredondamento

    public function __construct(private readonly PDO $pdo) {}

    /**
     * Retorna lançamentos financeiros paginados com tomador (FCFO) e baixas agregadas (FLANBAIXA).
     *
     * @param array{
     *   data_inicio?: string,
     *   data_fim?: string,
     *   codcoligada?: int|null,
     *   codcfo?: string,
     *   situacao_financeira?: string,
     *   situacao_emissao?: string,
     *   pagina?: int,
     *   por_pagina?: int,
     * } $filters
     */
    public function findReceivables(array $filters = []): array
    {
        $pagina    = max(1, (int) ($filters['pagina'] ?? 1));
        $porPagina = max(10, min(200, (int) ($filters['por_pagina'] ?? 50)));
        $offset    = ($pagina - 1) * $porPagina;

        [$whereSql, $params] = $this->buildFilterConditions($filters);

        // Subquery base com OUTER APPLY das baixas agregadas e vínculos educacionais (Curso)
        $baseFrom = "
            FROM dbo.FLAN L
            JOIN FLANBAIXA FL
              ON L.IDLAN = FL.IDLAN
             AND L.CODCOLIGADA = FL.CODCOLIGADA
             AND FL.DATACANCELBAIXA IS NULL
            JOIN FXCX
              ON FL.IDXCX = FXCX.IDXCX
             AND FL.CODCOLIGADA = FXCX.CODCOLIGADA
            LEFT JOIN dbo.FCFO F
                ON F.CODCOLIGADA = L.CODCOLCFO
               AND F.CODCFO      = L.CODCFO
            LEFT JOIN dbo.SLAN SL
                ON SL.CODCOLIGADA = L.CODCOLIGADA
               AND SL.IDLAN       = L.IDLAN
            LEFT JOIN dbo.SPARCELA SP
                ON SP.CODCOLIGADA = SL.CODCOLIGADA
               AND SP.IDPARCELA   = SL.IDPARCELA
            LEFT JOIN dbo.SCONTRATO SC
                ON SC.CODCOLIGADA = SP.CODCOLIGADA
               AND SC.CODCONTRATO = SP.CODCONTRATO
               AND SC.RA          = SP.RA
            LEFT JOIN dbo.SHABILITACAOFILIAL HF
                ON HF.CODCOLIGADA = SC.CODCOLIGADA
               AND HF.IDHABILITACAOFILIAL = SC.IDHABILITACAOFILIAL
            LEFT JOIN dbo.SCURSO C
                ON C.CODCOLIGADA = HF.CODCOLIGADA
               AND C.CODCURSO    = HF.CODCURSO
            OUTER APPLY (
                SELECT
                    COUNT_BIG(*) AS QTD_BAIXAS_TOTAL,
                    SUM(CASE WHEN B.DATACANCELBAIXA IS NULL THEN 1 ELSE 0 END) AS QTD_BAIXAS_ATIVAS,
                    SUM(CASE WHEN B.DATACANCELBAIXA IS NULL THEN COALESCE(B.VALORBAIXA, 0) ELSE 0 END) AS SOMA_PAGO,
                    SUM(CASE WHEN B.DATACANCELBAIXA IS NULL THEN COALESCE(B.VALORORIGINAL, 0) ELSE 0 END) AS SOMA_VALOR_ORIGINAL_BAIXADO,
                    SUM(CASE WHEN B.DATACANCELBAIXA IS NULL THEN (COALESCE(B.VALORDESCONTO, 0) + COALESCE(B.VALOROP3, 0) + COALESCE(B.VALOROP5, 0) + COALESCE(B.VALOROP6, 0)) ELSE 0 END) AS SOMA_DESCONTO_BAIXA,
                    SUM(CASE WHEN B.DATACANCELBAIXA IS NULL THEN COALESCE(B.VALORJUROS, 0) ELSE 0 END) AS SOMA_JUROS_BAIXA,
                    SUM(CASE WHEN B.DATACANCELBAIXA IS NULL THEN COALESCE(B.VALORMULTA, 0) ELSE 0 END) AS SOMA_MULTA_BAIXA,
                    MAX(CASE WHEN B.DATACANCELBAIXA IS NULL THEN B.DATABAIXA END) AS ULTIMA_DATA_BAIXA
                FROM dbo.FLANBAIXA B
                WHERE B.CODCOLIGADA = L.CODCOLIGADA
                  AND B.IDLAN       = L.IDLAN
            ) BX
        ";

        // Contagem exata no banco
        $countSql = "SELECT COUNT_BIG(*) AS TOTAL {$baseFrom} {$whereSql}";
        $stmtCount = $this->pdo->prepare($countSql);
        $stmtCount->execute($params);
        $totalRows = (int) ($stmtCount->fetchColumn() ?: 0);

        // Consulta com ordenação e paginação SQL Server
        $sql = "
            SELECT
                L.CODCOLIGADA,
                L.IDLAN,
                L.CODFILIAL,
                L.PAGREC,
                L.STATUSLAN,
                L.CODTDO,
                L.NUMERODOCUMENTO,
                L.HISTORICO,
                COALESCE(C.NOME, 'Demais Lançamentos / Sem Curso') AS CURSO_NOME,
                HF.CODCURSO,
                CONVERT(VARCHAR(10), L.DATACRIACAO, 120)       AS DATACRIACAO,
                CONVERT(VARCHAR(10), L.DATAEMISSAO, 120)       AS DATAEMISSAO,
                CONVERT(VARCHAR(10), L.DATAVENCIMENTO, 120)    AS DATAVENCIMENTO,
                CONVERT(VARCHAR(10), L.DATABAIXA, 120)         AS DATABAIXA,
                CONVERT(VARCHAR(10), L.DATACANCELAMENTO, 120)  AS DATACANCELAMENTO,
                CONVERT(VARCHAR(10), L.DATACANCELBAIXA, 120)   AS DATACANCELBAIXA,
                L.VALORORIGINAL,
                L.VALORBAIXADO,
                COALESCE(L.VALORDESCONTO, 0)                   AS VALORDESCONTO,
                COALESCE(L.VALOROP3, 0)                        AS VALOROP3,
                COALESCE(L.VALOROP5, 0)                        AS VALOROP5,
                COALESCE(L.VALOROP6, 0)                        AS VALOROP6,
                (COALESCE(L.VALORDESCONTO, 0) + COALESCE(L.VALOROP3, 0) + COALESCE(L.VALOROP5, 0) + COALESCE(L.VALOROP6, 0)) AS TOTAL_DESCONTOS_LANCAMENTO,
                COALESCE(L.VALORJUROS, 0)                      AS VALORJUROS,
                COALESCE(L.VALORMULTA, 0)                      AS VALORMULTA,
                L.CODCOLCFO,
                L.CODCFO,
                COALESCE(F.NOME, F.NOMEFANTASIA)              AS TOMADOR_NOME,
                F.NOMEFANTASIA                                 AS TOMADOR_NOMEFANTASIA,
                F.CGCCFO                                       AS TOMADOR_DOCUMENTO,
                F.EMAIL                                        AS TOMADOR_EMAIL,
                F.RUA                                          AS TOMADOR_RUA,
                F.NUMERO                                       AS TOMADOR_NUMERO,
                F.COMPLEMENTO                                  AS TOMADOR_COMPLEMENTO,
                F.BAIRRO                                       AS TOMADOR_BAIRRO,
                F.CIDADE                                       AS TOMADOR_CIDADE,
                F.CODETD                                       AS TOMADOR_UF,
                F.CEP                                          AS TOMADOR_CEP,
                COALESCE(BX.QTD_BAIXAS_TOTAL, 0)               AS QTD_BAIXAS_TOTAL,
                COALESCE(BX.QTD_BAIXAS_ATIVAS, 0)              AS QTD_BAIXAS_ATIVAS,
                COALESCE(BX.SOMA_PAGO, 0)                      AS SOMA_PAGO,
                COALESCE(BX.SOMA_VALOR_ORIGINAL_BAIXADO, 0)    AS SOMA_VALOR_ORIGINAL_BAIXADO,
                COALESCE(BX.SOMA_DESCONTO_BAIXA, 0)            AS SOMA_DESCONTO_BAIXA,
                COALESCE(BX.SOMA_JUROS_BAIXA, 0)               AS SOMA_JUROS_BAIXA,
                COALESCE(BX.SOMA_MULTA_BAIXA, 0)               AS SOMA_MULTA_BAIXA,
                CONVERT(VARCHAR(10), BX.ULTIMA_DATA_BAIXA, 120) AS ULTIMA_DATA_BAIXA
            {$baseFrom}
            {$whereSql}
            ORDER BY COALESCE(C.NOME, 'ZZZ') ASC, COALESCE(F.NOME, F.NOMEFANTASIA) ASC, L.DATAVENCIMENTO DESC, L.IDLAN DESC
            OFFSET {$offset} ROWS FETCH NEXT {$porPagina} ROWS ONLY
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Enriquecer registros com campos computados
        $rows = array_map([$this, 'enrichRow'], $rawRows);

        return [
            'rows'       => $rows,
            'total'      => $totalRows,
            'pagina'     => $pagina,
            'por_pagina' => $porPagina,
            'paginas'    => max(1, (int) ceil($totalRows / $porPagina)),
        ];
    }

    /**
     * Retorna indicadores agregados diretamente dos dados do RM para o painel de monitoramento.
     */
    public function dashboardIndicators(?int $codColigada = null): array
    {
        $params = [];
        $coligadaWhere = '';
        if ($codColigada !== null && $codColigada > 0) {
            $coligadaWhere = 'AND L.CODCOLIGADA = :codcoligada';
            $params[':codcoligada'] = $codColigada;
        }

        $sql = "
            SELECT
                COUNT_BIG(*) AS TOTAL_LANCAMENTOS,
                SUM(CASE
                    WHEN L.DATACANCELAMENTO IS NOT NULL OR L.DATACANCELBAIXA IS NOT NULL THEN 1
                    ELSE 0
                END) AS CANCELADOS,
                SUM(CASE
                    WHEN (L.DATACANCELAMENTO IS NULL AND L.DATACANCELBAIXA IS NULL)
                     AND (BX.SOMA_PAGO IS NULL OR BX.SOMA_PAGO <= 0) THEN 1
                    ELSE 0
                END) AS PENDENTES,
                SUM(CASE
                    WHEN (L.DATACANCELAMENTO IS NULL AND L.DATACANCELBAIXA IS NULL)
                     AND COALESCE(BX.SOMA_PAGO, 0) > 0
                     AND (L.VALORORIGINAL - (COALESCE(BX.SOMA_VALOR_ORIGINAL_BAIXADO, 0) + CASE WHEN COALESCE(BX.SOMA_VALOR_ORIGINAL_BAIXADO, 0) = 0 THEN COALESCE(BX.SOMA_PAGO, 0) + COALESCE(BX.SOMA_DESCONTO_BAIXA, 0) ELSE 0 END)) <= 0.01
                    THEN 1
                    ELSE 0
                END) AS QUITADAS,
                SUM(CASE
                    WHEN (L.DATACANCELAMENTO IS NULL AND L.DATACANCELBAIXA IS NULL)
                     AND COALESCE(BX.SOMA_PAGO, 0) > 0
                     AND (L.VALORORIGINAL - (COALESCE(BX.SOMA_VALOR_ORIGINAL_BAIXADO, 0) + CASE WHEN COALESCE(BX.SOMA_VALOR_ORIGINAL_BAIXADO, 0) = 0 THEN COALESCE(BX.SOMA_PAGO, 0) + COALESCE(BX.SOMA_DESCONTO_BAIXA, 0) ELSE 0 END)) > 0.01
                    THEN 1
                    ELSE 0
                END) AS PARCIAIS,
                SUM(CASE
                    WHEN (L.DATACANCELAMENTO IS NULL AND L.DATACANCELBAIXA IS NULL)
                     AND COALESCE(BX.SOMA_PAGO, 0) > 0
                     AND (L.VALORORIGINAL - (COALESCE(BX.SOMA_VALOR_ORIGINAL_BAIXADO, 0) + CASE WHEN COALESCE(BX.SOMA_VALOR_ORIGINAL_BAIXADO, 0) = 0 THEN COALESCE(BX.SOMA_PAGO, 0) + COALESCE(BX.SOMA_DESCONTO_BAIXA, 0) ELSE 0 END)) <= 0.01
                    THEN 1
                    ELSE 0
                END) AS ELEGIVEIS,
                SUM(BX.SOMA_PAGO)    AS TOTAL_ARRECADADO,
                SUM(L.VALORORIGINAL) AS TOTAL_ORIGINAL
            FROM dbo.FLAN L
            OUTER APPLY (
                SELECT
                    SUM(CASE WHEN B.DATACANCELBAIXA IS NULL THEN COALESCE(B.VALORBAIXA, 0) ELSE 0 END) AS SOMA_PAGO,
                    SUM(CASE WHEN B.DATACANCELBAIXA IS NULL THEN COALESCE(B.VALORORIGINAL, 0) ELSE 0 END) AS SOMA_VALOR_ORIGINAL_BAIXADO,
                    SUM(CASE WHEN B.DATACANCELBAIXA IS NULL THEN COALESCE(B.VALORDESCONTO, 0) ELSE 0 END) AS SOMA_DESCONTO_BAIXA
                FROM dbo.FLANBAIXA B
                WHERE B.CODCOLIGADA = L.CODCOLIGADA
                  AND B.IDLAN       = L.IDLAN
            ) BX
            WHERE L.PAGREC = 1
              {$coligadaWhere}
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $agg = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_lancamentos' => (int)   ($agg['TOTAL_LANCAMENTOS'] ?? 0),
            'cancelados'        => (int)   ($agg['CANCELADOS']        ?? 0),
            'pendentes'         => (int)   ($agg['PENDENTES']         ?? 0),
            'quitadas'          => (int)   ($agg['QUITADAS']          ?? 0),
            'parciais'          => (int)   ($agg['PARCIAIS']          ?? 0),
            'elegiveis'         => (int)   ($agg['ELEGIVEIS']         ?? 0),
            'total_arrecadado'  => (float) ($agg['TOTAL_ARRECADADO']  ?? 0),
            'total_original'    => (float) ($agg['TOTAL_ORIGINAL']    ?? 0),
        ];
    }

    /**
     * Consulta os dados de um lançamento específico no RM.
     */
    public function findByIdlan(int $codColigada, string $idlan): ?array
    {
        $sql = "
            SELECT
                L.CODCOLIGADA,
                L.IDLAN,
                L.CODFILIAL,
                L.PAGREC,
                L.STATUSLAN,
                L.CODTDO,
                L.NUMERODOCUMENTO,
                L.HISTORICO,
                COALESCE(C.NOME, 'Demais Lançamentos / Sem Curso') AS CURSO_NOME,
                HF.CODCURSO,
                CONVERT(VARCHAR(10), L.DATACRIACAO, 120)       AS DATACRIACAO,
                CONVERT(VARCHAR(10), L.DATAEMISSAO, 120)       AS DATAEMISSAO,
                CONVERT(VARCHAR(10), L.DATAVENCIMENTO, 120)    AS DATAVENCIMENTO,
                CONVERT(VARCHAR(10), L.DATABAIXA, 120)         AS DATABAIXA,
                CONVERT(VARCHAR(10), L.DATACANCELAMENTO, 120)  AS DATACANCELAMENTO,
                CONVERT(VARCHAR(10), L.DATACANCELBAIXA, 120)   AS DATACANCELBAIXA,
                L.VALORORIGINAL,
                L.VALORBAIXADO,
                COALESCE(L.VALORDESCONTO, 0)                   AS VALORDESCONTO,
                COALESCE(L.VALOROP3, 0)                        AS VALOROP3,
                COALESCE(L.VALOROP5, 0)                        AS VALOROP5,
                COALESCE(L.VALOROP6, 0)                        AS VALOROP6,
                (COALESCE(L.VALORDESCONTO, 0) + COALESCE(L.VALOROP3, 0) + COALESCE(L.VALOROP5, 0) + COALESCE(L.VALOROP6, 0)) AS TOTAL_DESCONTOS_LANCAMENTO,
                COALESCE(L.VALORJUROS, 0)                      AS VALORJUROS,
                COALESCE(L.VALORMULTA, 0)                      AS VALORMULTA,
                L.CODCOLCFO,
                L.CODCFO,
                COALESCE(F.NOME, F.NOMEFANTASIA)              AS TOMADOR_NOME,
                F.NOMEFANTASIA                                 AS TOMADOR_NOMEFANTASIA,
                F.CGCCFO                                       AS TOMADOR_DOCUMENTO,
                F.EMAIL                                        AS TOMADOR_EMAIL,
                F.RUA                                          AS TOMADOR_RUA,
                F.NUMERO                                       AS TOMADOR_NUMERO,
                F.COMPLEMENTO                                  AS TOMADOR_COMPLEMENTO,
                F.BAIRRO                                       AS TOMADOR_BAIRRO,
                F.CIDADE                                       AS TOMADOR_CIDADE,
                F.CODETD                                       AS TOMADOR_UF,
                F.CEP                                          AS TOMADOR_CEP,
                COALESCE(BX.QTD_BAIXAS_TOTAL, 0)               AS QTD_BAIXAS_TOTAL,
                COALESCE(BX.QTD_BAIXAS_ATIVAS, 0)              AS QTD_BAIXAS_ATIVAS,
                COALESCE(BX.SOMA_PAGO, 0)                      AS SOMA_PAGO,
                COALESCE(BX.SOMA_VALOR_ORIGINAL_BAIXADO, 0)    AS SOMA_VALOR_ORIGINAL_BAIXADO,
                COALESCE(BX.SOMA_DESCONTO_BAIXA, 0)            AS SOMA_DESCONTO_BAIXA,
                COALESCE(BX.SOMA_JUROS_BAIXA, 0)               AS SOMA_JUROS_BAIXA,
                COALESCE(BX.SOMA_MULTA_BAIXA, 0)               AS SOMA_MULTA_BAIXA,
                CONVERT(VARCHAR(10), BX.ULTIMA_DATA_BAIXA, 120) AS ULTIMA_DATA_BAIXA
            FROM dbo.FLAN L
            JOIN FLANBAIXA FL
              ON L.IDLAN = FL.IDLAN
             AND L.CODCOLIGADA = FL.CODCOLIGADA
             AND FL.DATACANCELBAIXA IS NULL
            JOIN FXCX
              ON FL.IDXCX = FXCX.IDXCX
             AND FL.CODCOLIGADA = FXCX.CODCOLIGADA
            LEFT JOIN dbo.FCFO F
                ON F.CODCOLIGADA = L.CODCOLCFO
               AND F.CODCFO      = L.CODCFO
            LEFT JOIN dbo.SLAN SL
                ON SL.CODCOLIGADA = L.CODCOLIGADA
               AND SL.IDLAN       = L.IDLAN
            LEFT JOIN dbo.SPARCELA SP
                ON SP.CODCOLIGADA = SL.CODCOLIGADA
               AND SP.IDPARCELA   = SL.IDPARCELA
            LEFT JOIN dbo.SCONTRATO SC
                ON SC.CODCOLIGADA = SP.CODCOLIGADA
               AND SC.CODCONTRATO = SP.CODCONTRATO
               AND SC.RA          = SP.RA
            LEFT JOIN dbo.SHABILITACAOFILIAL HF
                ON HF.CODCOLIGADA = SC.CODCOLIGADA
               AND HF.IDHABILITACAOFILIAL = SC.IDHABILITACAOFILIAL
            LEFT JOIN dbo.SCURSO C
                ON C.CODCOLIGADA = HF.CODCOLIGADA
               AND C.CODCURSO    = HF.CODCURSO
            OUTER APPLY (
                SELECT
                    COUNT_BIG(*) AS QTD_BAIXAS_TOTAL,
                    SUM(CASE WHEN B.DATACANCELBAIXA IS NULL THEN 1 ELSE 0 END) AS QTD_BAIXAS_ATIVAS,
                    SUM(CASE WHEN B.DATACANCELBAIXA IS NULL THEN COALESCE(B.VALORBAIXA, 0) ELSE 0 END) AS SOMA_PAGO,
                    SUM(CASE WHEN B.DATACANCELBAIXA IS NULL THEN COALESCE(B.VALORORIGINAL, 0) ELSE 0 END) AS SOMA_VALOR_ORIGINAL_BAIXADO,
                    SUM(CASE WHEN B.DATACANCELBAIXA IS NULL THEN (COALESCE(B.VALORDESCONTO, 0) + COALESCE(B.VALOROP3, 0) + COALESCE(B.VALOROP5, 0) + COALESCE(B.VALOROP6, 0)) ELSE 0 END) AS SOMA_DESCONTO_BAIXA,
                    SUM(CASE WHEN B.DATACANCELBAIXA IS NULL THEN COALESCE(B.VALORJUROS, 0) ELSE 0 END) AS SOMA_JUROS_BAIXA,
                    SUM(CASE WHEN B.DATACANCELBAIXA IS NULL THEN COALESCE(B.VALORMULTA, 0) ELSE 0 END) AS SOMA_MULTA_BAIXA,
                    MAX(CASE WHEN B.DATACANCELBAIXA IS NULL THEN B.DATABAIXA END) AS ULTIMA_DATA_BAIXA
                FROM dbo.FLANBAIXA B
                WHERE B.CODCOLIGADA = L.CODCOLIGADA
                  AND B.IDLAN       = L.IDLAN
            ) BX
            WHERE L.CODCOLIGADA = :codcoligada AND L.IDLAN = :idlan
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':codcoligada' => $codColigada, ':idlan' => $idlan]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->enrichRow($row) : null;
    }

    /**
     * Retorna todas as baixas de um lançamento no RM (ativas e canceladas).
     */
    public function findBaixas(int $codColigada, string $idlan): array
    {
        $sql = "
            SELECT
                B.IDBAIXA,
                B.IDLAN,
                B.CODCOLIGADA,
                CONVERT(VARCHAR(10), B.DATABAIXA, 120)       AS DATABAIXA,
                CONVERT(VARCHAR(10), B.DATACANCELBAIXA, 120) AS DATACANCELBAIXA,
                B.VALORBAIXA,
                COALESCE(B.VALORORIGINAL, 0)                 AS VALORORIGINAL,
                (COALESCE(B.VALORDESCONTO, 0) + COALESCE(B.VALOROP3, 0) + COALESCE(B.VALOROP5, 0) + COALESCE(B.VALOROP6, 0)) AS VALORDESCONTO,
                COALESCE(B.VALORDESCONTO, 0)                 AS VALORDESCONTO_PADRAO,
                COALESCE(B.VALOROP3, 0)                      AS VALOROP3,
                COALESCE(B.VALOROP5, 0)                      AS VALOROP5,
                COALESCE(B.VALOROP6, 0)                      AS VALOROP6,
                COALESCE(B.VALORJUROS, 0)                    AS VALORJUROS,
                COALESCE(B.VALORMULTA, 0)                    AS VALORMULTA,
                B.STATUS,
                B.TIPOBAIXA,
                B.USUARIO,
                B.CODCXA,
                B.CNABAUTENTICACAO
            FROM dbo.FLANBAIXA B
            WHERE B.CODCOLIGADA = :codcoligada
              AND B.IDLAN       = :idlan
              AND B.DATACANCELBAIXA IS NULL
            ORDER BY B.DATABAIXA ASC, B.IDBAIXA ASC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':codcoligada' => $codColigada, ':idlan' => $idlan]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Regra de quitação e cálculo do saldo restante:
     * - Cancelamento: se DATACANCELAMENTO ou DATACANCELBAIXA preenchido -> CANCELADO, ELEGIVEL = false
     * - Amortização do principal: soma do VALORORIGINAL das baixas ativas, ou (VALORBAIXA + DESCONTOS)
     * - Saldo: max(0, VALORORIGINAL - amortizado)
     * - Se amortizado <= 0 -> PENDENTE
     * - Se saldo <= TOLERANCE (0.01) -> QUITADO, ELEGIVEL_EMISSAO = true
     * - Senão -> PARCIAL, ELEGIVEL_EMISSAO = false
     */
    public function enrichRow(array $row): array
    {
        $valorOriginal = (float) ($row['VALORORIGINAL'] ?? 0);
        $somaPago      = (float) ($row['SOMA_PAGO'] ?? 0);
        $somaOrigBaixa = (float) ($row['SOMA_VALOR_ORIGINAL_BAIXADO'] ?? 0);
        
        $descontoLan   = (float) ($row['TOTAL_DESCONTOS_LANCAMENTO'] ?? (
            (float) ($row['VALORDESCONTO'] ?? 0) + (float) ($row['VALOROP3'] ?? 0) + (float) ($row['VALOROP5'] ?? 0) + (float) ($row['VALOROP6'] ?? 0)
        ));
        $descontoBaixa = (float) ($row['SOMA_DESCONTO_BAIXA'] ?? 0);
        $descontoTotal = max($descontoBaixa, $descontoLan);

        $cancelamento  = $row['DATACANCELAMENTO'] ?? null;
        $cancelBaixa   = $row['DATACANCELBAIXA']  ?? null;
        $baixasAtivas  = (int) ($row['QTD_BAIXAS_ATIVAS'] ?? 0);

        // Se o lançamento estiver cancelado no RM, mas houver pelo menos uma baixa ativa,
        // a situação financeira deve ser calculada pela baixa ativa e não pela baixa cancelada.
        if (($cancelamento !== null || $cancelBaixa !== null) && $baixasAtivas <= 0) {
            $row['SITUACAO_FINANCEIRA'] = 'CANCELADO';
            $row['ELEGIVEL_EMISSAO']    = false;
            $row['SALDO']               = $valorOriginal;
            $row['VALOR_EFETIVO_PAGO']  = $somaPago;
            $row['TOTAL_DESCONTOS']     = round($descontoTotal, 2);
            $row['VALORDESCONTO_TOTAL'] = round($descontoTotal, 2);
            return $row;
        }

        // Valor do principal liquidado (considera amortização ou valor pago + descontos concedidos)
        $amortizado = $somaOrigBaixa > 0 ? $somaOrigBaixa : ($somaPago + $descontoTotal);
        $saldo = max(0.0, $valorOriginal - $amortizado);

        if ($somaPago <= 0 && $amortizado <= 0) {
            $situacao = 'PENDENTE';
            $elegivel = false;
        } elseif ($saldo <= self::TOLERANCE) {
            $situacao = 'QUITADO';
            $elegivel = true;
            $saldo    = 0.0;
        } else {
            $situacao = 'PARCIAL';
            $elegivel = false;
        }

        $row['SITUACAO_FINANCEIRA'] = $situacao;
        $row['ELEGIVEL_EMISSAO']    = $elegivel;
        $row['SALDO']               = round($saldo, 2);
        $row['VALOR_EFETIVO_PAGO']  = round($somaPago, 2);
        $row['TOTAL_DESCONTOS']     = round($descontoTotal, 2);
        $row['VALORDESCONTO_TOTAL'] = round($descontoTotal, 2);

        return $row;
    }

    /**
     * Constrói cláusula WHERE parametrizada suportando todos os filtros em nível SQL.
     */
    private function buildFilterConditions(array $filters): array
    {
        $conds  = ['L.PAGREC = 1']; // Apenas recebíveis
        $params = [];

        if (!empty($filters['data_inicio'])) {
            $conds[] = "L.DATABAIXA >= :data_inicio";
            $params[':data_inicio'] = $filters['data_inicio'];
        }
        if (!empty($filters['data_fim'])) {
            $conds[] = "L.DATABAIXA <= :data_fim";
            $params[':data_fim'] = $filters['data_fim'];
        }
        if (!empty($filters['codcoligada'])) {
            $conds[] = "L.CODCOLIGADA = :codcoligada";
            $params[':codcoligada'] = (int) $filters['codcoligada'];
        }
        if (!empty($filters['codcfo'])) {
            $term = '%' . trim($filters['codcfo']) . '%';
            $conds[] = "(COALESCE(F.NOME, F.NOMEFANTASIA) LIKE :codcfo_nome OR F.CGCCFO LIKE :codcfo_doc OR L.CODCFO LIKE :codcfo)";
            $params[':codcfo']      = $term;
            $params[':codcfo_nome'] = $term;
            $params[':codcfo_doc']  = $term;
        }

        // Filtro de situação financeira em SQL
        // Regra correta: se houver pelo menos uma baixa ativa, o lançamento deve seguir a
        // situação da baixa ativa e desconsiderar baixas canceladas do mesmo lançamento.
        $amortizadoExpr = "(COALESCE(BX.SOMA_VALOR_ORIGINAL_BAIXADO, 0) + CASE WHEN COALESCE(BX.SOMA_VALOR_ORIGINAL_BAIXADO, 0) = 0 THEN COALESCE(BX.SOMA_PAGO, 0) + COALESCE(BX.SOMA_DESCONTO_BAIXA, 0) ELSE 0 END)";
        $baixaAtivaExpr = "(L.DATACANCELAMENTO IS NULL AND COALESCE(BX.QTD_BAIXAS_ATIVAS, 0) > 0)";

        if (!empty($filters['situacao_financeira'])) {
            $sf = strtoupper(trim($filters['situacao_financeira']));
            if ($sf === 'CANCELADO') {
                $conds[] = "(L.DATACANCELAMENTO IS NOT NULL OR (L.DATACANCELBAIXA IS NOT NULL AND COALESCE(BX.QTD_BAIXAS_ATIVAS, 0) <= 0))";
            } elseif ($sf === 'PENDENTE') {
                $conds[] = "({$baixaAtivaExpr} = 0 AND (BX.SOMA_PAGO IS NULL OR BX.SOMA_PAGO <= 0))";
            } elseif ($sf === 'QUITADO') {
                $conds[] = "({$baixaAtivaExpr} AND COALESCE(BX.SOMA_PAGO, 0) > 0 AND (L.VALORORIGINAL - {$amortizadoExpr}) <= 0.01)";
            } elseif ($sf === 'PARCIAL') {
                $conds[] = "({$baixaAtivaExpr} AND COALESCE(BX.SOMA_PAGO, 0) > 0 AND (L.VALORORIGINAL - {$amortizadoExpr}) > 0.01)";
            }
        }

        // Filtro de situação de emissão em SQL
        if (!empty($filters['situacao_emissao'])) {
            $se = strtoupper(trim($filters['situacao_emissao']));
            if ($se === 'ELEGIVEL') {
                $conds[] = "({$baixaAtivaExpr} AND COALESCE(BX.SOMA_PAGO, 0) > 0 AND (L.VALORORIGINAL - {$amortizadoExpr}) <= 0.01)";
            } elseif ($se === 'NAO_ELEGIVEL') {
                $conds[] = "NOT ({$baixaAtivaExpr} AND COALESCE(BX.SOMA_PAGO, 0) > 0 AND (L.VALORORIGINAL - {$amortizadoExpr}) <= 0.01)";
            }
        }

        $where = 'WHERE ' . implode(' AND ', $conds);
        return [$where, $params];
    }
}
