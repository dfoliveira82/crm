<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\{Database,Env};
use PDO;

final class FacialEnrollment
{
    private function excludedFunctions(): array
    {
        $raw = trim((string)Env::get('PONTO_FUNCOES_NAO_ADMINISTRATIVAS', 'PROFESSOR,DOCENTE'));
        if ($raw === '') return [];
        return array_values(array_unique(array_filter(array_map('trim', explode(',', $raw)))));
    }

    private function employeeSql(bool $includeSearch = false): array
    {
        $sql = <<<'SQL'
SELECT TOP 30
    PFUNC.CODCOLIGADA,
    PFUNC.CHAPA,
    PFUNC.CODPESSOA,
    PFUNC.CODSITUACAO,
    PFUNC.CODFUNCAO,
    PFUNCAO.NOME AS NOMEFUNCAO,
    PPESSOA.CPF,
    PPESSOA.NOME AS NOMEFUNCIONARIO,
    CASE WHEN EXISTS (
        SELECT 1 FROM dbo.PONTO_FACIAL PFAC
        WHERE PFAC.CODCOLIGADA = PFUNC.CODCOLIGADA
          AND PFAC.CHAPA = PFUNC.CHAPA
          AND PFAC.STATUS = 'ATIVO'
    ) THEN 1 ELSE 0 END AS HAS_FACE
FROM PFUNC
INNER JOIN PPESSOA ON PPESSOA.CODIGO = PFUNC.CODPESSOA
LEFT JOIN PFUNCAO
    ON PFUNCAO.CODCOLIGADA = PFUNC.CODCOLIGADA
   AND PFUNCAO.CODIGO = PFUNC.CODFUNCAO
WHERE PFUNC.CODSITUACAO = 'A'
  AND PFUNC.CHAPA IS NOT NULL
SQL;
        $params = [];
        foreach ($this->excludedFunctions() as $i => $term) {
            $key = ':excluded_'.$i;
            $sql .= "\n  AND UPPER(ISNULL(PFUNCAO.NOME, '')) NOT LIKE UPPER($key)";
            $params[$key] = '%'.$term.'%';
        }
        if ($includeSearch) {
            $sql .= "\n  AND (PFUNC.CHAPA LIKE :search_chapa OR UPPER(PPESSOA.NOME) LIKE UPPER(:search_name))";
        }
        $sql .= "\nORDER BY PPESSOA.NOME ASC, PFUNC.CODCOLIGADA ASC, PFUNC.CHAPA ASC";
        return [$sql,$params];
    }

    public function searchEmployees(string $query): array
    {
        [$sql,$params] = $this->employeeSql(true);
        $params[':search_chapa'] = '%'.$query.'%';
        $params[':search_name'] = '%'.$query.'%';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return array_map(function(array $row): array {
            $employee = $this->presentEmployee($row);
            unset($employee['CPF']); // CPF completo permanece somente no servidor.
            return $employee;
        }, $stmt->fetchAll());
    }

    public function findEligibleEmployee(int $codColigada, string $chapa): ?array
    {
        [$sql,$params] = $this->employeeSql(false);
        // A consulta principal limita a 30 resultados; para validar um vínculo
        // específico usamos os mesmos critérios sem limite/ordenação.
        $sql = str_replace('SELECT TOP 30', 'SELECT TOP 1', $sql);
        $sql = preg_replace('/ORDER BY PPESSOA\.NOME ASC, PFUNC\.CODCOLIGADA ASC, PFUNC\.CHAPA ASC$/', '', $sql);
        $sql .= "\n  AND PFUNC.CODCOLIGADA = :codcoligada AND PFUNC.CHAPA = :chapa";
        $params[':codcoligada'] = $codColigada;
        $params[':chapa'] = $chapa;
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ? $this->presentEmployee($row) : null;
    }

    private function presentEmployee(array $row): array
    {
        $cpf = preg_replace('/\D+/', '', (string)($row['CPF'] ?? '')) ?? '';
        $masked = strlen($cpf) === 11 ? substr($cpf,0,3).'.***.***-'.substr($cpf,-2) : 'Não informado';
        return [
            'CODCOLIGADA'=>(int)($row['CODCOLIGADA'] ?? 0),
            'CHAPA'=>trim((string)($row['CHAPA'] ?? '')),
            'CODPESSOA'=>(int)($row['CODPESSOA'] ?? 0),
            'CODFUNCAO'=>trim((string)($row['CODFUNCAO'] ?? '')),
            'NOMEFUNCAO'=>trim((string)($row['NOMEFUNCAO'] ?? '')),
            'CPF'=>$cpf,
            'CPF_MASKED'=>$masked,
            'NOMEFUNCIONARIO'=>trim((string)($row['NOMEFUNCIONARIO'] ?? '')),
            'HAS_FACE'=>(bool)($row['HAS_FACE'] ?? false),
        ];
    }


    /**
     * Desativa o vínculo facial e apaga os dados biométricos armazenados.
     * A linha é preservada para manter a chave única e permitir novo cadastro.
     */
    public function delete(int $codColigada, string $chapa): bool
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(<<<'SQL'
UPDATE dbo.PONTO_FACIAL
SET CPF = '',
    DESCRITORES = 0x,
    DESCRITOR_COUNT = 1,
    STATUS = 'INATIVO',
    UPDATED_AT = SYSDATETIME()
WHERE CODCOLIGADA = :codcoligada
  AND CHAPA = :chapa
  AND STATUS = 'ATIVO'
SQL);
        $stmt->execute([
            ':codcoligada' => $codColigada,
            ':chapa' => $chapa,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function save(array $employee, array $descriptors): int
    {
        if (count($descriptors) < 2 || count($descriptors) > 3) {
            throw new \InvalidArgumentException('Quantidade de descritores inválida.');
        }
        $binary = '';
        foreach ($descriptors as $descriptor) {
            if (!is_string($descriptor) || strlen($descriptor) !== 512) {
                throw new \InvalidArgumentException('Descritor facial inválido.');
            }
            $binary .= $descriptor;
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $update = $pdo->prepare(<<<'SQL'
UPDATE dbo.PONTO_FACIAL
SET CPF = :cpf,
    DESCRITORES = :descritores,
    DESCRITOR_COUNT = :descriptor_count,
    STATUS = 'ATIVO',
    UPDATED_AT = SYSDATETIME()
WHERE CODCOLIGADA = :codcoligada AND CHAPA = :chapa
SQL);
            $update->bindValue(':cpf', $employee['CPF'], PDO::PARAM_STR);
            $update->bindParam(':descritores', $binary, PDO::PARAM_LOB, 0, PDO::SQLSRV_ENCODING_BINARY);
            $update->bindValue(':descriptor_count', count($descriptors), PDO::PARAM_INT);
            $update->bindValue(':codcoligada', $employee['CODCOLIGADA'], PDO::PARAM_INT);
            $update->bindValue(':chapa', $employee['CHAPA'], PDO::PARAM_STR);
            $update->execute();

            if ($update->rowCount() > 0) {
                $select = $pdo->prepare('SELECT TOP 1 IDPONTOFACIAL FROM dbo.PONTO_FACIAL WHERE CODCOLIGADA = :coligada AND CHAPA = :chapa ORDER BY IDPONTOFACIAL DESC');
                $select->execute([':coligada'=>$employee['CODCOLIGADA'],':chapa'=>$employee['CHAPA']]);
                $id = $select->fetchColumn();
            } else {
                $insert = $pdo->prepare(<<<'SQL'
INSERT INTO dbo.PONTO_FACIAL
    (CODCOLIGADA, CHAPA, CPF, DESCRITORES, DESCRITOR_COUNT, STATUS, CREATED_AT, UPDATED_AT)
OUTPUT INSERTED.IDPONTOFACIAL
VALUES
    (:codcoligada, :chapa, :cpf, :descritores, :descriptor_count, 'ATIVO', SYSDATETIME(), SYSDATETIME())
SQL);
                $insert->bindValue(':codcoligada', $employee['CODCOLIGADA'], PDO::PARAM_INT);
                $insert->bindValue(':chapa', $employee['CHAPA'], PDO::PARAM_STR);
                $insert->bindValue(':cpf', $employee['CPF'], PDO::PARAM_STR);
                $insert->bindParam(':descritores', $binary, PDO::PARAM_LOB, 0, PDO::SQLSRV_ENCODING_BINARY);
                $insert->bindValue(':descriptor_count', count($descriptors), PDO::PARAM_INT);
                $insert->execute();
                $id = $insert->fetchColumn();
            }
            if ($id === false) throw new \RuntimeException('Não foi possível recuperar o identificador do cadastro facial.');
            $pdo->commit();
            return (int)$id;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
