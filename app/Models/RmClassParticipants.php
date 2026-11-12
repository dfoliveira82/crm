<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

final class RmClassParticipants extends Model
{
    public function students(int $codColigada, int $idTurmaDisc): array
    {
        $sql = <<<'SQL'
SELECT DISTINCT
    M.CODCOLIGADA,
    M.IDTURMADISC,
    M.RA,
    P.CODIGO AS CODPESSOA,
    P.NOME,
    COALESCE(NULLIF(P.EMAIL, ''), P.EMAILPESSOAL) AS EMAIL,
    P.CPF,
    S.DESCRICAO AS STATUS_MATRICULA
FROM SMATRICULA M
INNER JOIN SALUNO A
    ON A.CODCOLIGADA = M.CODCOLIGADA
   AND A.RA = M.RA
INNER JOIN PPESSOA P
    ON P.CODIGO = A.CODPESSOA
INNER JOIN SSTATUS S
    ON S.CODCOLIGADA = M.CODCOLIGADA
   AND S.CODSTATUS = M.CODSTATUS
WHERE M.CODCOLIGADA = :codcoligada
  AND M.IDTURMADISC = :idturmadisc
  AND S.DISCIPLINA = 'S'
ORDER BY P.NOME, M.RA
SQL;
        $statement = $this->db()->prepare($sql);
        $statement->execute([':codcoligada' => $codColigada, ':idturmadisc' => $idTurmaDisc]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function teachers(int $codColigada, int $idTurmaDisc): array
    {
        $sql = <<<'SQL'
SELECT DISTINCT
    PT.CODCOLIGADA,
    PT.IDTURMADISC,
    PT.CODPROF,
    PR.CODPESSOA,
    P.NOME,
    COALESCE(NULLIF(P.EMAIL, ''), P.EMAILPESSOAL) AS EMAIL,
    P.CPF
FROM SPROFESSORTURMA PT
INNER JOIN SPROFESSOR PR
    ON PR.CODCOLIGADA = PT.CODCOLIGADA
   AND PR.CODPROF = PT.CODPROF
INNER JOIN PPESSOA P
    ON P.CODIGO = PR.CODPESSOA
WHERE PT.CODCOLIGADA = :codcoligada
  AND PT.IDTURMADISC = :idturmadisc
  AND (PT.STATUS IS NULL OR PT.STATUS = 1)
ORDER BY P.NOME, PT.CODPROF
SQL;
        $statement = $this->db()->prepare($sql);
        $statement->execute([':codcoligada' => $codColigada, ':idturmadisc' => $idTurmaDisc]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveStudentMoodleId(int $codColigada, string $ra, int $moodleUserId): void
    {
        $ra = trim($ra);
        if ($codColigada <= 0 || $ra === '' || $moodleUserId <= 0) {
            throw new \InvalidArgumentException('Dados insuficientes para vincular o aluno ao Moodle.');
        }

        $sql = <<<'SQL'
IF EXISTS (
    SELECT 1
      FROM SALUNOCOMPL
     WHERE CODCOLIGADA = :cod_exists
       AND RA = :ra_exists
)
BEGIN
    UPDATE SALUNOCOMPL
       SET IDMOODLE = :moodle_update
     WHERE CODCOLIGADA = :cod_update
       AND RA = :ra_update
END
ELSE
BEGIN
    INSERT INTO SALUNOCOMPL (CODCOLIGADA, RA, IDMOODLE)
    VALUES (:cod_insert, :ra_insert, :moodle_insert)
END
SQL;
        $statement = $this->db()->prepare($sql);
        $statement->execute([
            ':cod_exists' => $codColigada,
            ':ra_exists' => $ra,
            ':moodle_update' => $moodleUserId,
            ':cod_update' => $codColigada,
            ':ra_update' => $ra,
            ':cod_insert' => $codColigada,
            ':ra_insert' => $ra,
            ':moodle_insert' => $moodleUserId,
        ]);
    }

    public function adStudents(int $codColigada, int $idTurmaDisc): array
    {
        $sql = <<<'SQL'
SELECT DISTINCT
    M.CODCOLIGADA,
    M.IDTURMADISC,
    M.RA,
    P.CODIGO AS CODPESSOA,
    P.NOME,
    COALESCE(NULLIF(P.EMAIL, ''), P.EMAILPESSOAL) AS EMAIL,
    C.EMAILAD,
    CASE
        WHEN TRIM(COALESCE(C.EMAILAD, '')) = '' THEN 'PENDENTE'
        ELSE 'INTEGRADO'
    END AS AD_STATUS
FROM SMATRICULA M
INNER JOIN SALUNO A
    ON A.CODCOLIGADA = M.CODCOLIGADA
   AND A.RA = M.RA
INNER JOIN PPESSOA P
    ON P.CODIGO = A.CODPESSOA
LEFT JOIN SALUNOCOMPL C
    ON C.CODCOLIGADA = M.CODCOLIGADA
   AND C.RA = M.RA
INNER JOIN SSTATUS S
    ON S.CODCOLIGADA = M.CODCOLIGADA
   AND S.CODSTATUS = M.CODSTATUS
WHERE M.CODCOLIGADA = :codcoligada
  AND M.IDTURMADISC = :idturmadisc
  AND S.DISCIPLINA = 'S'
  AND S.DESCRICAO IN ('Em curso', 'PreMatricula', 'Matricula OnLine')
ORDER BY P.NOME, M.RA
SQL;
        $statement = $this->db()->prepare($sql);
        $statement->execute([
            ':codcoligada' => $codColigada,
            ':idturmadisc' => $idTurmaDisc,
        ]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function classStudents(int $codColigada, int $idPerlet, string $codTurma): array
    {
        $codTurma = trim((string) $codTurma);
        if ($codColigada <= 0 || $idPerlet <= 0 || $codTurma === '') {
            return [];
        }

        $sql = <<<'SQL'
SELECT DISTINCT
    M.CODCOLIGADA,
    M.RA,
    P.CODIGO AS CODPESSOA,
    P.NOME,
    COALESCE(NULLIF(P.EMAIL, ''), P.EMAILPESSOAL) AS EMAIL,
    C.EMAILAD,
    CASE
        WHEN TRIM(COALESCE(C.EMAILAD, '')) = '' THEN 'PENDENTE'
        ELSE 'INTEGRADO'
    END AS AD_STATUS
FROM STURMA T
INNER JOIN STURMADISC TD
    ON TD.CODCOLIGADA = T.CODCOLIGADA
   AND TD.IDPERLET = T.IDPERLET
   AND TD.CODTURMA = T.CODTURMA
INNER JOIN SMATRICULA M
    ON M.CODCOLIGADA = TD.CODCOLIGADA
   AND M.IDTURMADISC = TD.IDTURMADISC
INNER JOIN SALUNO A
    ON A.CODCOLIGADA = M.CODCOLIGADA
   AND A.RA = M.RA
INNER JOIN PPESSOA P
    ON P.CODIGO = A.CODPESSOA
LEFT JOIN SALUNOCOMPL C
    ON C.CODCOLIGADA = M.CODCOLIGADA
   AND C.RA = M.RA
INNER JOIN SSTATUS S
    ON S.CODCOLIGADA = M.CODCOLIGADA
   AND S.CODSTATUS = M.CODSTATUS
WHERE T.CODCOLIGADA = :codcoligada
  AND T.IDPERLET = :idperlet
  AND T.CODTURMA = :codturma
  AND S.DISCIPLINA = 'S'
  AND S.DESCRICAO IN ('Em curso', 'PreMatricula', 'Matricula OnLine')
ORDER BY P.NOME, M.RA
SQL;
        $statement = $this->db()->prepare($sql);
        $statement->execute([
            ':codcoligada' => $codColigada,
            ':idperlet' => $idPerlet,
            ':codturma' => $codTurma,
        ]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function adStatusSummary(int $codColigada, array $idTurmaDiscs): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $idTurmaDiscs), static fn(int $id): bool => $id > 0)));
        if ($ids === []) return [];

        $result = [];
        foreach (array_chunk($ids, 900) as $batch) {
            $placeholders = implode(',', array_fill(0, count($batch), '?'));
            $params = array_merge([$codColigada], $batch);
            $sql = 'SELECT M.IDTURMADISC,
       COUNT(DISTINCT M.RA) AS TOTAL_ALUNOS,
       SUM(CASE WHEN TRIM(COALESCE(C.EMAILAD, \'\')) <> \'\' THEN 1 ELSE 0 END) AS ALUNOS_INTEGRADOS
FROM SMATRICULA M
LEFT JOIN SALUNOCOMPL C
    ON C.CODCOLIGADA = M.CODCOLIGADA
   AND C.RA = M.RA
INNER JOIN SSTATUS S
    ON S.CODCOLIGADA = M.CODCOLIGADA
   AND S.CODSTATUS = M.CODSTATUS
WHERE M.CODCOLIGADA = ?
  AND S.DISCIPLINA = \'S\'
  AND S.DESCRICAO IN (\'Em curso\', \'PreMatricula\', \'Matricula OnLine\')
  AND M.IDTURMADISC IN (' . $placeholders . ')
GROUP BY M.IDTURMADISC';
            $statement = $this->db()->prepare($sql);
            $statement->execute($params);
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $id = (int) ($row['IDTURMADISC'] ?? 0);
                if ($id <= 0) continue;
                $total = (int) ($row['TOTAL_ALUNOS'] ?? 0);
                $integrated = (int) ($row['ALUNOS_INTEGRADOS'] ?? 0);
                $result[$id] = [
                    'id_turma_disc' => $id,
                    'total' => $total,
                    'integrated' => $integrated,
                    'pending' => max(0, $total - $integrated),
                    'status' => $integrated >= $total ? 'integrado' : ($integrated > 0 ? 'parcial' : 'pendente'),
                ];
            }
        }
        return $result;
    }

    public function counts(int $codColigada, array $idTurmaDiscs): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $idTurmaDiscs), static fn(int $id): bool => $id > 0)));
        if ($ids === []) return [];
        $result = [];
        foreach (array_chunk($ids, 900) as $batch) {
            $placeholders = implode(',', array_fill(0, count($batch), '?'));
            $params = array_merge([$codColigada], $batch);
        $sql = 'SELECT M.IDTURMADISC, COUNT(DISTINCT M.RA) AS STUDENT_COUNT
                  FROM SMATRICULA M
                  INNER JOIN SSTATUS S ON S.CODCOLIGADA = M.CODCOLIGADA AND S.CODSTATUS = M.CODSTATUS
                 WHERE M.CODCOLIGADA = ? AND S.DISCIPLINA = \'S\' AND M.IDTURMADISC IN (' . $placeholders . ')
                 GROUP BY M.IDTURMADISC';
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[(int) $row['IDTURMADISC']]['students'] = (int) $row['STUDENT_COUNT'];
        }

        $teacherSql = 'SELECT PT.IDTURMADISC, COUNT(DISTINCT PT.CODPROF) AS TEACHER_COUNT
                         FROM SPROFESSORTURMA PT
                        WHERE PT.CODCOLIGADA = ? AND (PT.STATUS IS NULL OR PT.STATUS = 1)
                          AND PT.IDTURMADISC IN (' . $placeholders . ')
                        GROUP BY PT.IDTURMADISC';
        $statement = $this->db()->prepare($teacherSql);
        $statement->execute($params);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[(int) $row['IDTURMADISC']]['teachers'] = (int) $row['TEACHER_COUNT'];
        }
        }
        return $result;
    }
}
