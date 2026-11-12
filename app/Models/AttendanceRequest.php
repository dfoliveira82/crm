<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class AttendanceRequest extends Model
{
    /**
     * Agrupa atendimentos por status para o período letivo informado.
     * O RM usa a tabela HATENDIMENTOBASE, os filtros por SPLETIVO e a regra
     * de status conforme a consulta validada pela equipe.
     */
    public function summaryForSemester(int $periodId, array $context = [], string $periodLabel = ''): array
    {
        $periodId = max(0, (int) $periodId);
        $periodLabel = $periodLabel !== '' ? $periodLabel : (string) $periodId;

        if ($periodId <= 0) {
            return ['semester' => $periodLabel, 'total' => 0, 'items' => []];
        }

        $codColigada = (int) ($context['CODCOLIGADA'] ?? 0);
        $codFilial = (int) ($context['CODFILIAL'] ?? 0);
        $codTipoCurso = (int) ($context['CODTIPOCURSO'] ?? 0);

        if ($codColigada <= 0 || $codFilial <= 0 || $codTipoCurso <= 0) {
            return ['semester' => $periodLabel, 'total' => 0, 'items' => []];
        }

        $sql = <<<'SQL'
SELECT
    SUM(CASE WHEN H.CODSTATUS IN ('r','o','f','u') THEN 1 ELSE 0 END) AS CONCLUIDO,
    SUM(CASE WHEN H.CODSTATUS IN ('c') THEN 1 ELSE 0 END) AS CANCELADO,
    SUM(CASE WHEN H.CODSTATUS IN ('e','g','t') THEN 1 ELSE 0 END) AS AGUARDANDO_ALUNO,
    SUM(CASE WHEN H.CODSTATUS IN ('a') THEN 1 ELSE 0 END) AS EM_ANDAMENTO,
    COUNT(H.CODATENDIMENTO) AS TOTAL_GERAL
FROM HATENDIMENTOBASE H
INNER JOIN SPLETIVO SPL
    ON H.CODCOLIGADA = SPL.CODCOLIGADA
INNER JOIN HLOCALENTREGA HLE
    ON H.CODLOCALENTREGA = HLE.CODLOCALENTREGA
   AND H.CODCOLIGADA = HLE.CODCOLIGADA
WHERE SPL.IDPERLET = :atd_idperlet
  AND SPL.CODCOLIGADA = :atd_spl_codcoligada
  AND SPL.CODFILIAL = :atd_spl_codfilial
  AND SPL.CODTIPOCURSO = :atd_spl_codtipocurso
  AND H.ABERTURA BETWEEN SPL.DTINICIO AND SPL.DTFIM
  AND HLE.DESCRICAO LIKE '%SECRETARIA%'
SQL;

        try {
            $statement = $this->db()->prepare($sql);
            $statement->execute([
                ':atd_idperlet' => $periodId,
                ':atd_spl_codcoligada' => $codColigada,
                ':atd_spl_codfilial' => $codFilial,
                ':atd_spl_codtipocurso' => $codTipoCurso,
            ]);
            $row = $statement->fetch();
        } catch (\Throwable) {
            return ['semester' => $periodLabel, 'total' => 0, 'items' => []];
        }

        if (!$row) {
            return ['semester' => $periodLabel, 'total' => 0, 'items' => []];
        }

        $items = [
            ['label' => 'Concluídas', 'value' => (int) ($row['CONCLUIDO'] ?? 0), 'color' => '#35c477'],
            ['label' => 'Em andamento', 'value' => (int) ($row['EM_ANDAMENTO'] ?? 0), 'color' => '#5793f7'],
            ['label' => 'Aguardando aluno', 'value' => (int) ($row['AGUARDANDO_ALUNO'] ?? 0), 'color' => '#f5a623'],
            ['label' => 'Cancelados', 'value' => (int) ($row['CANCELADO'] ?? 0), 'color' => '#f05252'],
        ];

        $total = 0;
        foreach ($items as $index => $item) {
            $value = (int) $item['value'];
            $total += $value;
            $items[$index]['percent'] = '0,0%';
        }

        if ($total === 0) {
            return ['semester' => $periodLabel, 'total' => 0, 'items' => []];
        }

        foreach ($items as $index => $item) {
            $items[$index]['percent'] = number_format(($item['value'] / $total) * 100, 1, ',', '.') . '%';
        }

        return [
            'semester' => $periodLabel,
            'total' => $total,
            'items' => $items,
        ];
    }

    public function findStudentCounts(array $context, int $idPerlet): array
    {
        try {
            $codColigada = (int) ($context['CODCOLIGADA'] ?? 0);
            $codFilial = (int) ($context['CODFILIAL'] ?? 0);
            $codTipoCurso = (int) ($context['CODTIPOCURSO'] ?? 0);

            if ($codColigada <= 0 || $codFilial <= 0 || $codTipoCurso <= 0 || $idPerlet <= 0) {
                return [
                    'current' => [
                        'CODPERLET' => '',
                        'IDPERLET' => $idPerlet,
                        'ALUNOS_EM_CURSO' => 0,
                        'ALUNOS_PREMATRICULA' => 0,
                        'ALUNOS_MATRICULA_ONLINE' => 0,
                    ],
                    'previous' => null,
                    'ALUNOS_EM_CURSO' => $this->buildComparison(0, null, null),
                    'ALUNOS_PREMATRICULA' => $this->buildComparison(0, null, null),
                    'ALUNOS_MATRICULA_ONLINE' => $this->buildComparison(0, null, null),
                ];
            }

            $sql = <<<'SQL'
SELECT
    SPL.CODPERLET,
    SPL.IDPERLET,
    COUNT(DISTINCT CASE WHEN S.DESCRICAO = 'Em curso' THEN SM.RA END) AS ALUNOS_EM_CURSO,
    COUNT(DISTINCT CASE WHEN S.DESCRICAO = 'PreMatricula' THEN SM.RA END) AS ALUNOS_PREMATRICULA,
    COUNT(DISTINCT CASE WHEN S.DESCRICAO = 'Matricula OnLine' THEN SM.RA END) AS ALUNOS_MATRICULA_ONLINE
FROM SMATRICPL SM
INNER JOIN SSTATUS S
    ON S.CODSTATUS = SM.CODSTATUS
   AND S.CODCOLIGADA = SM.CODCOLIGADA
INNER JOIN SPLETIVO SPL
    ON SM.IDPERLET = SPL.IDPERLET
   AND SM.CODCOLIGADA = SPL.CODCOLIGADA
   AND SM.CODFILIAL = SPL.CODFILIAL
WHERE SM.CODCOLIGADA = :sm_codcoligada
  AND SM.CODFILIAL = :sm_codfilial
  AND SPL.IDPERLET IN (
    :aluno_idperlet_atual,
    (
        SELECT PL.IDPERLETANT
        FROM SPLETIVO PL
        WHERE PL.IDPERLET = :aluno_idperlet_referencia
          AND PL.CODCOLIGADA = :periodo_codcoligada
          AND PL.CODFILIAL = :periodo_codfilial
          AND PL.CODTIPOCURSO = :periodo_codtipocurso
    )
  )
  AND SPL.CODCOLIGADA = :spl_codcoligada
  AND SPL.CODFILIAL = :spl_codfilial
  AND S.CODTIPOCURSO = :status_codtipocurso
  AND S.DESCRICAO IN ('Em curso', 'PreMatricula', 'Matricula OnLine')
GROUP BY SPL.CODPERLET, SPL.IDPERLET
ORDER BY SPL.IDPERLET DESC
SQL;

            $statement = $this->db()->prepare($sql);
            $statement->execute([
                ':sm_codcoligada' => $codColigada,
                ':sm_codfilial' => $codFilial,
                ':aluno_idperlet_atual' => $idPerlet,
                ':aluno_idperlet_referencia' => $idPerlet,
                ':periodo_codcoligada' => $codColigada,
                ':periodo_codfilial' => $codFilial,
                ':periodo_codtipocurso' => $codTipoCurso,
                ':spl_codcoligada' => $codColigada,
                ':spl_codfilial' => $codFilial,
                ':status_codtipocurso' => $codTipoCurso,
            ]);

            $rows = $statement->fetchAll();
            $current = null;
            $previous = null;

            foreach ($rows as $row) {
                $normalized = [
                    'CODPERLET' => (string) ($row['CODPERLET'] ?? ''),
                    'IDPERLET' => (int) ($row['IDPERLET'] ?? 0),
                    'ALUNOS_EM_CURSO' => (int) ($row['ALUNOS_EM_CURSO'] ?? 0),
                    'ALUNOS_PREMATRICULA' => (int) ($row['ALUNOS_PREMATRICULA'] ?? 0),
                    'ALUNOS_MATRICULA_ONLINE' => (int) ($row['ALUNOS_MATRICULA_ONLINE'] ?? 0),
                ];

                if ($normalized['IDPERLET'] === $idPerlet) {
                    $current = $normalized;
                } else {
                    $previous = $normalized;
                }
            }

            $current ??= [
                'CODPERLET' => '',
                'IDPERLET' => $idPerlet,
                'ALUNOS_EM_CURSO' => 0,
                'ALUNOS_PREMATRICULA' => 0,
                'ALUNOS_MATRICULA_ONLINE' => 0,
            ];

            return [
                'current' => $current,
                'previous' => $previous,
                'ALUNOS_EM_CURSO' => $this->buildComparison(
                    $current['ALUNOS_EM_CURSO'],
                    $previous['ALUNOS_EM_CURSO'] ?? null,
                    $previous['CODPERLET'] ?? null
                ),
                'ALUNOS_PREMATRICULA' => $this->buildComparison(
                    $current['ALUNOS_PREMATRICULA'],
                    $previous['ALUNOS_PREMATRICULA'] ?? null,
                    $previous['CODPERLET'] ?? null
                ),
                'ALUNOS_MATRICULA_ONLINE' => $this->buildComparison(
                    $current['ALUNOS_MATRICULA_ONLINE'],
                    $previous['ALUNOS_MATRICULA_ONLINE'] ?? null,
                    $previous['CODPERLET'] ?? null
                ),
            ];
        } catch (\Throwable $e) {
            error_log('[AttendanceRequest] Falha na comparação entre semestres: ' . $e->getMessage());
            return [
                'current' => [
                    'CODPERLET' => '',
                    'IDPERLET' => $idPerlet,
                    'ALUNOS_EM_CURSO' => 0,
                    'ALUNOS_PREMATRICULA' => 0,
                    'ALUNOS_MATRICULA_ONLINE' => 0,
                ],
                'previous' => null,
                'ALUNOS_EM_CURSO' => $this->buildComparison(0, null, null),
                'ALUNOS_PREMATRICULA' => $this->buildComparison(0, null, null),
                'ALUNOS_MATRICULA_ONLINE' => $this->buildComparison(0, null, null),
            ];
        }
    }

    private function buildComparison(int $currentValue, ?int $previousValue, ?string $previousLabel): array
    {
        if ($previousValue === null || $previousValue <= 0) {
            return [
                'percent' => 0.0,
                'direction' => 'neutral',
                'text' => '0,0% vs período anterior',
            ];
        }

        $deltaPercent = (($currentValue - $previousValue) / $previousValue) * 100;
        $direction = $deltaPercent < 0 ? 'down' : 'neutral';
        $display = $deltaPercent < 0 ? '↓ ' : '↑ ';
        $label = $previousLabel !== null && $previousLabel !== '' ? $previousLabel : 'período anterior';

        return [
            'percent' => round($deltaPercent, 1),
            'direction' => $direction,
            'text' => $display . number_format(abs($deltaPercent), 1, ',', '.') . '% vs ' . $label,
        ];
    }
}
