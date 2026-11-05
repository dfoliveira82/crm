<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\{Database, Model};
use PDO;
use Throwable;

/** Consultas somente leitura ao cadastro do usuário no TOTVS RM. */
final class RmUserProfile extends Model
{
    public function findByLogin(string $login): ?array
    {
        $login = trim($login);
        if ($login === '') return null;

        $pdo = $this->db();
        $sql = <<<'SQL'
SELECT TOP 1
    U.CODUSUARIO,
    U.NOME AS NOMEUSUARIO,
    U.EMAIL,
    CAST(G.IMAGEM AS VARBINARY(MAX)) AS IMAGEM
FROM GUSUARIO U
LEFT JOIN PPESSOA P ON P.CODUSUARIO = U.CODUSUARIO
LEFT JOIN GIMAGEM G ON G.ID = P.IDIMAGEM
WHERE U.CODUSUARIO = :login
SQL;
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':login' => $login]);
        $row = $stmt->fetch();
        if (!$row) return null;

        $image = $this->normalizeImage($row['IMAGEM'] ?? null);
        $contexts = $this->findContexts($login);

        return [
            'code' => trim((string)($row['CODUSUARIO'] ?? $login)),
            'name' => trim((string)($row['NOMEUSUARIO'] ?? '')),
            'email' => trim((string)($row['EMAIL'] ?? '')),
            'avatar_data_uri' => $image['data_uri'] ?? '',
            'contexts' => $contexts,
        ];
    }

    /**
     * Define o contexto inicial a partir do vínculo funcional ativo do usuário.
     * Só escolhe automaticamente quando todos os vínculos ativos apontam para
     * a mesma combinação de coligada/filial. A permissão continua sendo validada
     * pela lista de contextos de SUSUARIOFILIAL.
     */
    public function findDefaultContextByActiveEmployment(string $login): ?array
    {
        $login = trim($login);
        if ($login === '') return null;

        $sql = <<<'SQL'
SELECT DISTINCT
    PF.CODCOLIGADA,
    PF.CODFILIAL
FROM GUSUARIO U
INNER JOIN PPESSOA P ON P.CODUSUARIO = U.CODUSUARIO
INNER JOIN PFUNC PF ON PF.CODPESSOA = P.CODIGO
WHERE U.CODUSUARIO = :login
  AND PF.CHAPA IS NOT NULL
  AND PF.CODSITUACAO = 'A'
ORDER BY PF.CODCOLIGADA, PF.CODFILIAL
SQL;
        $stmt = $this->db()->prepare($sql);
        $stmt->execute([':login' => $login]);
        $locations = $stmt->fetchAll();

        // Mais de uma combinação ativa é ambígua: não escolhe uma chapa arbitrariamente.
        if (count($locations) !== 1) return null;

        $codColigada = (int)($locations[0]['CODCOLIGADA'] ?? 0);
        $codFilial = (int)($locations[0]['CODFILIAL'] ?? 0);
        if ($codColigada <= 0 || $codFilial <= 0) return null;

        // O contexto só pode ser definido se também estiver autorizado no RM.
        foreach ($this->findContexts($login) as $context) {
            if ($context['CODCOLIGADA'] === $codColigada && $context['CODFILIAL'] === $codFilial) {
                // Se houver mais de um tipo de curso autorizado para a mesma filial,
                // utiliza o primeiro da ordenação estável de findContexts().
                return $context;
            }
        }

        return null;
    }

    public function findContexts(string $login): array
    {
        $sql = <<<'SQL'
SELECT DISTINCT
    F.CODCOLIGADA,
    F.CODFILIAL,
    T.CODTIPOCURSO,
    CASE
        WHEN F.CODFILIAL = 2 AND F.CODCOLIGADA = 1 THEN CONVERT(NVARCHAR(10), F.CODFILIAL) + ' - SISTEMA ALFA'
        WHEN F.CODFILIAL = 1 AND F.CODCOLIGADA = 1 THEN CONVERT(NVARCHAR(10), F.CODFILIAL) + ' - FUPAC'
        WHEN F.CODFILIAL = 5 AND F.CODCOLIGADA = 1 THEN CONVERT(NVARCHAR(10), F.CODFILIAL) + ' - SISTEMA ALFA'
        ELSE CONVERT(NVARCHAR(10), F.CODFILIAL) + ' - ' + F.NOME
    END AS FILIAL,
    CONVERT(NVARCHAR(10), T.CODTIPOCURSO) + ' - ' + T.NOME AS TIPOCURSO,
    CASE
        WHEN F.CODFILIAL = 2 AND F.CODCOLIGADA = 1 THEN 'FADIPA - Faculdade de Ipatinga'
        WHEN F.CODFILIAL = 1 AND F.CODCOLIGADA = 1 THEN 'FADIPA - Faculdade de Direito'
        WHEN F.CODFILIAL = 5 AND F.CODCOLIGADA = 1 THEN 'FACIPA - Fac. Ciênc. da Saude de Ipatinga'
        ELSE CONVERT(NVARCHAR(10), F.CODFILIAL) + ' - ' + F.NOME
    END AS NOMEFACULDADE
FROM GUSUARIO U
INNER JOIN SUSUARIOFILIAL UF ON UF.CODUSUARIO = U.CODUSUARIO
INNER JOIN GFILIAL F ON F.CODCOLIGADA = UF.CODCOLIGADA AND F.CODFILIAL = UF.CODFILIAL
INNER JOIN STIPOCURSO T ON T.CODTIPOCURSO = UF.CODTIPOCURSO
WHERE U.CODUSUARIO = :login
ORDER BY F.CODCOLIGADA, F.CODFILIAL, T.CODTIPOCURSO
SQL;
        $stmt = $this->db()->prepare($sql);
        $stmt->execute([':login' => $login]);
        return array_map(static fn(array $r): array => [
            'CODCOLIGADA' => (int)$r['CODCOLIGADA'],
            'CODFILIAL' => (int)$r['CODFILIAL'],
            'CODTIPOCURSO' => (int)$r['CODTIPOCURSO'],
            'FILIAL' => trim((string)$r['FILIAL']),
            'TIPOCURSO' => trim((string)$r['TIPOCURSO']),
            'NOMEFACULDADE' => trim((string)$r['NOMEFACULDADE']),
        ], $stmt->fetchAll());
    }

    public function findProfiles(string $login, int $codColigada): array
    {
        $sql = <<<'SQL'
SELECT DISTINCT GP.CODPERFIL, GP.NOME
FROM GUSRPERFIL GUP
INNER JOIN GPERFIL GP
    ON GP.CODPERFIL = GUP.CODPERFIL
   AND GP.CODSISTEMA = GUP.CODSISTEMA
WHERE GUP.CODUSUARIO = :login
  AND GP.CODSISTEMA = 'S'
  AND GUP.CODCOLIGADA = :codcoligada
ORDER BY GP.NOME
SQL;
        $stmt = $this->db()->prepare($sql);
        $stmt->execute([':login' => $login, ':codcoligada' => $codColigada]);
        return array_map(static fn(array $r): array => [
            'code' => trim((string)($r['CODPERFIL'] ?? '')),
            'name' => trim((string)($r['NOME'] ?? '')),
        ], $stmt->fetchAll());
    }

    public function findAllowedContext(string $login, int $codColigada, int $codFilial, int $codTipoCurso): ?array
    {
        foreach ($this->findContexts($login) as $context) {
            if ($context['CODCOLIGADA'] === $codColigada
                && $context['CODFILIAL'] === $codFilial
                && $context['CODTIPOCURSO'] === $codTipoCurso) {
                return $context;
            }
        }
        return null;
    }

    private function normalizeImage(mixed $image): ?array
    {
        if (is_resource($image)) $image = stream_get_contents($image);
        if (!is_string($image) || $image === '') return null;

        $mime = '';
        if (function_exists('finfo_open') && function_exists('finfo_buffer')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $detected = finfo_buffer($finfo, $image);
                finfo_close($finfo);
                if (is_string($detected)) $mime = $detected;
            }
        }
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            $mime = match (true) {
                str_starts_with($image, "\xFF\xD8\xFF") => 'image/jpeg',
                str_starts_with($image, "\x89PNG\r\n\x1A\n") => 'image/png',
                str_starts_with($image, 'GIF87a'), str_starts_with($image, 'GIF89a') => 'image/gif',
                str_starts_with($image, 'RIFF') && substr($image, 8, 4) === 'WEBP' => 'image/webp',
                default => '',
            };
        }
        if ($mime === '') return null;
        return ['data_uri' => 'data:' . $mime . ';base64,' . base64_encode($image)];
    }
}
