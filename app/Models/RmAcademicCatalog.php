<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

final class RmAcademicCatalog extends Model
{
    public function listPeriods(array $context): array
    {
        $required = ['CODCOLIGADA', 'CODFILIAL', 'CODTIPOCURSO'];
        foreach ($required as $key) {
            if (!isset($context[$key])) return [];
        }

        $sql = <<<'SQL'
SELECT DISTINCT
    P.IDPERLET,
    P.CODPERLET,
    P.DESCRICAO,
    P.DTINICIO,
    P.DTFIM,
    P.ENCERRADO
FROM SPLETIVO P
INNER JOIN STURMA T
    ON T.CODCOLIGADA = P.CODCOLIGADA
   AND T.IDPERLET = P.IDPERLET
   AND T.CODFILIAL = P.CODFILIAL
   AND T.CODTIPOCURSO = P.CODTIPOCURSO
INNER JOIN STURMADISC TD
    ON TD.CODCOLIGADA = T.CODCOLIGADA
   AND TD.CODTURMA = T.CODTURMA
   AND TD.IDPERLET = T.IDPERLET
   AND TD.ATIVA = 'S'
WHERE P.CODCOLIGADA = :codcoligada
  AND P.CODFILIAL = :codfilial
  AND P.CODTIPOCURSO = :codtipocurso
  AND (T.TURMAENCERRADA IS NULL OR T.TURMAENCERRADA <> 'S')
ORDER BY P.DTINICIO DESC, P.CODPERLET DESC
SQL;

        $statement = $this->db()->prepare($sql);
        $statement->execute([
            ':codcoligada' => (int) $context['CODCOLIGADA'],
            ':codfilial' => (int) $context['CODFILIAL'],
            ':codtipocurso' => (int) $context['CODTIPOCURSO'],
        ]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listCourses(array $context, ?int $periodId = null): array
    {
        $required = ['CODCOLIGADA', 'CODFILIAL', 'CODTIPOCURSO'];
        foreach ($required as $key) {
            if (!isset($context[$key])) return [];
        }

        $sql = <<<'SQL'
SELECT DISTINCT
    HF.CODCURSO,
    C.NOME AS CURSO_NOME
FROM STURMA T
INNER JOIN SHABILITACAOFILIAL HF
    ON HF.CODCOLIGADA = T.CODCOLIGADA
   AND HF.IDHABILITACAOFILIAL = T.IDHABILITACAOFILIAL
INNER JOIN SCURSO C
    ON C.CODCOLIGADA = HF.CODCOLIGADA
   AND C.CODCURSO = HF.CODCURSO
   AND C.CODTIPOCURSO = HF.CODTIPOCURSO
INNER JOIN STURMADISC TD
    ON TD.CODCOLIGADA = T.CODCOLIGADA
   AND TD.CODTURMA = T.CODTURMA
   AND TD.IDPERLET = T.IDPERLET
   AND TD.ATIVA = 'S'
WHERE T.CODCOLIGADA = :codcoligada
  AND T.CODFILIAL = :codfilial
  AND T.CODTIPOCURSO = :codtipocurso
  AND (T.TURMAENCERRADA IS NULL OR T.TURMAENCERRADA <> 'S')
SQL;

        $params = [
            ':codcoligada' => (int) $context['CODCOLIGADA'],
            ':codfilial' => (int) $context['CODFILIAL'],
            ':codtipocurso' => (int) $context['CODTIPOCURSO'],
        ];
        if ($periodId !== null && $periodId > 0) {
            $sql .= '  AND T.IDPERLET = :idperlet' . PHP_EOL;
            $params[':idperlet'] = $periodId;
        }
        $sql .= ' ORDER BY C.NOME, HF.CODCURSO';

        $statement = $this->db()->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listClasses(array $context, ?string $search = null, ?int $periodId = null, ?string $courseId = null, bool $collapseManaged = false): array
    {
        $required = ['CODCOLIGADA', 'CODFILIAL', 'CODTIPOCURSO'];
        foreach ($required as $key) {
            if (!isset($context[$key])) return [];
        }

        $sql = <<<'SQL'
SELECT
    T.CODCOLIGADA,
    T.CODFILIAL,
    T.IDPERLET,
    P.CODPERLET,
    P.DESCRICAO AS PERIODO,
    P.DTINICIO AS PERIODO_INICIO,
    P.DTFIM AS PERIODO_FIM,
    P.ENCERRADO AS PERIODO_ENCERRADO,
    T.CODTIPOCURSO,
    TC.NOME AS TIPO_ENSINO,
    T.CODTURMA,
    COALESCE(NULLIF(T.NOME, ''), T.CODTURMA) AS TURMA_NOME,
    T.DTINICIAL AS TURMA_INICIO,
    T.DTFINAL AS TURMA_FIM,
    T.TURMAENCERRADA,
    HF.CODCURSO,
    C.NOME AS CURSO_NOME,
    TD.IDTURMADISC,
    TD.CODDISC,
    MC.INTEGRADOMOODLE AS INTEGRADO_MOODLE,
    COALESCE(NULLIF(D.NOME, ''), TD.CODDISC) AS DISCIPLINA_NOME,
    TD.DTINICIAL AS DISCIPLINA_INICIO,
    TD.DTFINAL AS DISCIPLINA_FIM
FROM STURMA T
INNER JOIN SPLETIVO P
    ON P.CODCOLIGADA = T.CODCOLIGADA
   AND P.IDPERLET = T.IDPERLET
LEFT JOIN STIPOCURSO TC
    ON TC.CODCOLIGADA = T.CODCOLIGADA
   AND TC.CODTIPOCURSO = T.CODTIPOCURSO
LEFT JOIN SHABILITACAOFILIAL HF
    ON HF.CODCOLIGADA = T.CODCOLIGADA
   AND HF.IDHABILITACAOFILIAL = T.IDHABILITACAOFILIAL
LEFT JOIN SCURSO C
    ON C.CODCOLIGADA = HF.CODCOLIGADA
   AND C.CODCURSO = HF.CODCURSO
   AND C.CODTIPOCURSO = HF.CODTIPOCURSO
INNER JOIN STURMADISC TD
    ON TD.CODCOLIGADA = T.CODCOLIGADA
   AND TD.CODTURMA = T.CODTURMA
   AND TD.IDPERLET = T.IDPERLET
   AND TD.ATIVA = 'S'
LEFT JOIN STURMADISCCOMPL MC
    ON MC.CODCOLIGADA = TD.CODCOLIGADA
   AND MC.IDTURMADISC = TD.IDTURMADISC
LEFT JOIN SDISCIPLINA D
    ON D.CODCOLIGADA = TD.CODCOLIGADA
   AND D.CODDISC = TD.CODDISC
WHERE T.CODCOLIGADA = :codcoligada
  AND T.CODFILIAL = :codfilial
  AND T.CODTIPOCURSO = :codtipocurso
  AND (T.TURMAENCERRADA IS NULL OR T.TURMAENCERRADA <> 'S')
SQL;

        $params = [
            ':codcoligada' => (int) $context['CODCOLIGADA'],
            ':codfilial' => (int) $context['CODFILIAL'],
            ':codtipocurso' => (int) $context['CODTIPOCURSO'],
        ];
        if ($periodId !== null && $periodId > 0) {
            $sql .= '  AND T.IDPERLET = :idperlet' . PHP_EOL;
            $params[':idperlet'] = $periodId;
        }
        if ($collapseManaged) {
            $sql .= <<<'SQL'
  AND (
      /* Mantém a turma gerencial com todas as suas disciplinas. */
      EXISTS (
          SELECT 1
            FROM STURMADISCGERENCIADA TG
           WHERE TG.CODCOLIGADA = TD.CODCOLIGADA
             AND TG.IDTURMADISC = TD.IDTURMADISC
      )
      /* Mantém também turmas que não são gerenciais nem gerenciadas. */
      OR NOT EXISTS (
          SELECT 1
            FROM STURMADISCGERENCIADA TG
           WHERE TG.CODCOLIGADA = TD.CODCOLIGADA
             AND TG.IDTURMADISCGERENCIADA = TD.IDTURMADISC
      )
  )
SQL;
        }
        $courseId = trim((string) $courseId);
        if ($courseId !== '') {
            $sql .= '  AND HF.CODCURSO = :codcurso' . PHP_EOL;
            $params[':codcurso'] = $courseId;
        }
        $search = trim((string) $search);
        if ($search !== '') {
            $sql .= <<<'SQL'
  AND (
      T.CODTURMA LIKE :search
      OR T.NOME LIKE :search
      OR C.NOME LIKE :search
      OR D.NOME LIKE :search
      OR P.DESCRICAO LIKE :search
  )
SQL;
            $params[':search'] = '%' . $search . '%';
        }
        $sql .= ' ORDER BY P.DTINICIO DESC, T.CODTURMA, D.NOME';

        $statement = $this->db()->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markMoodleIntegrated(array $courses): int
    {
        $items = [];
        foreach ($courses as $course) {
            $codColigada = (int) ($course['cod_coligada'] ?? 0);
            $idTurmaDisc = (int) ($course['id_turma_disc'] ?? 0);
            if ($codColigada > 0 && $idTurmaDisc > 0) {
                $items[$codColigada . ':' . $idTurmaDisc] = [$codColigada, $idTurmaDisc];
            }
        }
        if ($items === []) return 0;

        $statement = $this->db()->prepare(
            'UPDATE STURMADISCCOMPL
                SET INTEGRADOMOODLE = ?, RECMODIFIEDBY = ?, RECMODIFIEDON = SYSDATETIME()
              WHERE CODCOLIGADA = ? AND IDTURMADISC = ?'
        );
        $updated = 0;
        foreach ($items as [$codColigada, $idTurmaDisc]) {
            $statement->execute(['SIM', 'SGU-MOODLE', $codColigada, $idTurmaDisc]);
            $updated += $statement->rowCount();
        }
        return $updated;
    }
}
