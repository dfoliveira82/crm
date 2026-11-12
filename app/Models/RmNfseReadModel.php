<?php
declare(strict_types=1);
namespace App\Models;

use App\Core\RmDatabase;
use PDO;

/** Consultas exclusivamente SELECT no financeiro do RM. */
final class RmNfseReadModel
{
    public function statusCodeSummary(): array
    {
        $sql = "
            SELECT 'FLAN' AS ORIGEM, CAST(PAGREC AS varchar(20)) AS CODIGO_1, CAST(STATUSLAN AS varchar(20)) AS CODIGO_2, COUNT_BIG(*) AS QUANTIDADE
            FROM dbo.FLAN
            WHERE DATACRIACAO >= DATEADD(MONTH, -12, GETDATE())
            GROUP BY PAGREC, STATUSLAN
            UNION ALL
            SELECT 'FLANBAIXA' AS ORIGEM, CAST(STATUS AS varchar(20)) AS CODIGO_1, CAST(TIPOBAIXA AS varchar(20)) AS CODIGO_2, COUNT_BIG(*) AS QUANTIDADE
            FROM dbo.FLANBAIXA
            WHERE DATABAIXA >= DATEADD(MONTH, -12, GETDATE())
               OR DATACANCELBAIXA >= DATEADD(MONTH, -12, GETDATE())
            GROUP BY STATUS, TIPOBAIXA
            ORDER BY ORIGEM, CODIGO_1, CODIGO_2;
        ";
        return RmDatabase::connection()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function latestReceivables(int $limit = 100): array
    {
        $limit = max(1, min(200, $limit));
        $sql = "
            SELECT TOP ({$limit})
                L.CODCOLIGADA,
                L.IDLAN,
                L.CODFILIAL,
                L.PAGREC,
                L.STATUSLAN,
                L.CODTDO,
                L.NUMERODOCUMENTO,
                L.HISTORICO,
                L.DATACRIACAO,
                L.DATAEMISSAO,
                L.DATAVENCIMENTO,
                L.DATABAIXA,
                L.DATACANCELAMENTO,
                L.DATACANCELBAIXA,
                L.VALORORIGINAL,
                L.VALORBAIXADO,
                L.VALORDESCONTO,
                L.VALORJUROS,
                L.VALORMULTA,
                L.CODCOLCFO,
                L.CODCFO,
                F.NOME AS TOMADOR_NOME,
                F.NOMEFANTASIA AS TOMADOR_NOMEFANTASIA,
                F.CGCCFO AS TOMADOR_DOCUMENTO,
                F.EMAIL AS TOMADOR_EMAIL,
                F.RUA AS TOMADOR_RUA,
                F.NUMERO AS TOMADOR_NUMERO,
                F.COMPLEMENTO AS TOMADOR_COMPLEMENTO,
                F.BAIRRO AS TOMADOR_BAIRRO,
                F.CIDADE AS TOMADOR_CIDADE,
                F.CODETD AS TOMADOR_UF,
                F.CEP AS TOMADOR_CEP,
                BX.QTD_BAIXAS,
                BX.QTD_BAIXAS_NAO_CANCELADAS,
                BX.SOMA_VALOR_BAIXA_NAO_CANCELADA,
                BX.SOMA_VALOR_ORIGINAL_BAIXA_NAO_CANCELADA,
                BX.SOMA_DESCONTOS_BAIXA_NAO_CANCELADA,
                BX.SOMA_JUROS_BAIXA_NAO_CANCELADA,
                BX.SOMA_MULTAS_BAIXA_NAO_CANCELADA,
                BX.ULTIMA_DATA_BAIXA
            FROM dbo.FLAN L
            LEFT JOIN dbo.FCFO F
                ON F.CODCOLIGADA = L.CODCOLCFO
               AND F.CODCFO = L.CODCFO
            OUTER APPLY (
                SELECT
                    COUNT_BIG(*) AS QTD_BAIXAS,
                    SUM(CASE WHEN B.DATACANCELBAIXA IS NULL THEN 1 ELSE 0 END) AS QTD_BAIXAS_NAO_CANCELADAS,
                    SUM(CASE WHEN B.DATACANCELBAIXA IS NULL THEN COALESCE(B.VALORBAIXA, 0) ELSE 0 END) AS SOMA_VALOR_BAIXA_NAO_CANCELADA,
                    SUM(CASE WHEN B.DATACANCELBAIXA IS NULL THEN COALESCE(B.VALORORIGINAL, 0) ELSE 0 END) AS SOMA_VALOR_ORIGINAL_BAIXA_NAO_CANCELADA,
                    SUM(CASE WHEN B.DATACANCELBAIXA IS NULL THEN COALESCE(B.VALORDESCONTO, 0) ELSE 0 END) AS SOMA_DESCONTOS_BAIXA_NAO_CANCELADA,
                    SUM(CASE WHEN B.DATACANCELBAIXA IS NULL THEN COALESCE(B.VALORJUROS, 0) ELSE 0 END) AS SOMA_JUROS_BAIXA_NAO_CANCELADA,
                    SUM(CASE WHEN B.DATACANCELBAIXA IS NULL THEN COALESCE(B.VALORMULTA, 0) ELSE 0 END) AS SOMA_MULTAS_BAIXA_NAO_CANCELADA,
                    MAX(CASE WHEN B.DATACANCELBAIXA IS NULL THEN B.DATABAIXA END) AS ULTIMA_DATA_BAIXA
                FROM dbo.FLANBAIXA B
                WHERE B.CODCOLIGADA = L.CODCOLIGADA
                  AND B.IDLAN = L.IDLAN
            ) BX
            ORDER BY L.DATACRIACAO DESC, L.CODCOLIGADA, L.IDLAN DESC;
        ";

        $stmt = RmDatabase::connection()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
