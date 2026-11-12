<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Controller, Request, Response, Session};
use App\Models\{RmAcademicCatalog, RmUserProfile};
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class CourseManagementController extends Controller
{
    private const MONTHS = [1 => 'JAN', 'FEV', 'MAR', 'ABR', 'MAI', 'JUN', 'JUL', 'AGO', 'SET', 'OUT', 'NOV', 'DEZ'];

    public function index(Request $request): Response
    {
        $context = $this->activeContext();
        $search = trim((string) $request->query('q', ''));
        $periodId = (int) $request->query('period', 0);
        $periodId = $periodId > 0 ? $periodId : null;
        $courseId = trim((string) $request->query('course', ''));
        $courses = [];
        $periods = [];
        $groups = [];
        $loadError = '';

        try {
            if (!$context) throw new \RuntimeException('Nenhum contexto acadêmico ativo foi encontrado.');
            $catalog = new RmAcademicCatalog();
            $periods = $catalog->listPeriods($context);
            $courses = $catalog->listCourses($context, $periodId);
            $allowedCourses = array_map(static fn(array $course): string => trim((string) $course['CODCURSO']), $courses);
            if ($courseId !== '' && !in_array($courseId, $allowedCourses, true)) $courseId = '';
            $rows = $catalog->listClasses($context, $search, $periodId, $courseId, true);
            $groups = $this->applyIntegrationStatuses($this->groupClasses($rows));
        } catch (Throwable $exception) {
            error_log('[CourseManagement] Falha ao carregar turmas RM: ' . $exception->getMessage());
            $loadError = 'Não foi possível carregar as turmas do RM. Verifique o contexto e a conexão com o banco.';
        }

        return $this->render('educacional/gestao-cursos', [
            'context' => $context,
            'groups' => $groups,
            'periods' => $periods,
            'periodId' => $periodId,
            'courses' => $courses,
            'courseId' => $courseId,
            'search' => $search,
            'loadError' => $loadError,
        ]);
    }

    public function preview(Request $request): Response
    {
        $payload = $request->json();
        $selected = array_values(array_filter(array_map('strval', (array) ($payload['classes'] ?? []))));
        if ($selected === []) return $this->json(['error' => 'select_at_least_one_class'], 422);

        $context = $this->activeContext();
        if (!$context) return $this->json(['error' => 'academic_context_missing'], 422);

        $rows = (new RmAcademicCatalog())->listClasses($context, null, null, null, true);
        $groups = $this->groupClasses($rows);
        $allowed = [];
        foreach ($groups as $group) $allowed[$group['idnumber']] = $group;

        $hierarchy = [];
        foreach ($selected as $idnumber) {
            if (isset($allowed[$idnumber])) $hierarchy[] = $allowed[$idnumber];
        }
        if (count($hierarchy) !== count($selected)) return $this->json(['error' => 'class_not_allowed'], 403);

        return $this->json([
            'status' => 'preview',
            'message' => 'Estrutura preparada para a próxima etapa de integração com o Moodle.',
            'hierarchy' => $hierarchy,
        ]);
    }

    public function integrate(Request $request): Response
    {
        $payload = $request->json();
        $selected = array_values(array_unique(array_filter(array_map('strval', (array) ($payload['classes'] ?? [])))));
        if ($selected === []) return $this->json(['error' => 'select_at_least_one_class', 'message' => 'Selecione ao menos uma turma.'], 422);

        $context = $this->activeContext();
        if (!$context) return $this->json(['error' => 'academic_context_missing', 'message' => 'Nenhum contexto acadêmico ativo foi encontrado.'], 422);

        try {
            $groups = $this->selectedGroups($context, $selected);
            $result = (new \App\Services\MoodleAcademicIntegrator())->synchronize($groups);
            $courses = (array) ($result['courses'] ?? []);
            $updated = (new RmAcademicCatalog())->markMoodleIntegrated($courses);
            if ($updated < count($courses)) {
                throw new \RuntimeException('O Moodle foi atualizado, mas nem todas as disciplinas foram marcadas em STURMADISCCOMPL.');
            }
            unset($result['courses']);
            $result['rm_disciplines_marked'] = $updated;
            $groups = $this->applyIntegrationStatuses($groups);
            return $this->json([
                'status' => 'integrated',
                'message' => 'Turmas selecionadas integradas com sucesso no Moodle.',
                'summary' => $result,
                'hierarchy' => $groups,
            ]);
        } catch (Throwable $exception) {
            error_log('[CourseManagement] Falha na integração RM/Moodle: ' . $exception->getMessage());
            return $this->json([
                'error' => 'moodle_integration_failed',
                'message' => $exception->getMessage(),
            ], 502);
        }
    }

    private function selectedGroups(array $context, array $selected): array
    {
        $rows = (new RmAcademicCatalog())->listClasses($context, null, null, null, true);
        $groups = $this->groupClasses($rows);
        $allowed = [];
        foreach ($groups as $group) $allowed[$group['idnumber']] = $group;

        $result = [];
        foreach ($selected as $idnumber) {
            if (isset($allowed[$idnumber])) $result[] = $allowed[$idnumber];
        }
        if (count($result) !== count($selected)) {
            throw new \RuntimeException('Uma ou mais turmas selecionadas não estão disponíveis no contexto acadêmico atual.');
        }
        return $result;
    }

    private function applyIntegrationStatuses(array $groups): array
    {
        foreach ($groups as &$group) {
            $total = count((array) ($group['disciplines'] ?? []));
            $integrated = 0;
            foreach ($group['disciplines'] as &$discipline) {
                $discipline['sync_status'] = !empty($discipline['integrated_rm']) ? 'INTEGRADA' : 'PENDENTE';
                if ($discipline['sync_status'] === 'INTEGRADA') $integrated++;
            }
            unset($discipline);

            if ($total > 0 && $integrated === $total) {
                $group['status'] = 'Integrada';
            } elseif ($integrated > 0) {
                $group['status'] = 'Parcialmente integrada';
            }
        }
        unset($group);
        return $groups;
    }

    private function activeContext(): array
    {
        $context = Session::get('active_context', []);
        if (is_array($context) && isset($context['CODCOLIGADA'], $context['CODFILIAL'], $context['CODTIPOCURSO'])) {
            return $context;
        }

        $user = Session::get('user', []);
        $login = is_array($user) ? trim((string) ($user['code'] ?? $user['username'] ?? '')) : '';
        if ($login === '') return [];
        return (new RmUserProfile())->findDefaultContextByActiveEmployment($login) ?? [];
    }

    private function groupClasses(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $classId = implode(':', [
                (int) $row['CODCOLIGADA'],
                (int) $row['IDPERLET'],
                (string) $row['CODTURMA'],
                (string) ($row['CODCURSO'] ?? 'SEM-CURSO'),
            ]);
            $turmaName = $this->displayTurmaName($row);
            $turnoValue = $turmaName;
            $disciplineName = trim((string) ($row['DISCIPLINA_NOME'] ?? ''));
            if ($disciplineName !== '') $turnoValue .= ' ' . $disciplineName;
            $turno = $this->classifyTurno($turnoValue);
            if (!isset($groups[$classId])) {
                $groups[$classId] = [
                    'idnumber' => 'rm:turma:' . $classId,
                    'type_idnumber' => 'rm:tipo-ensino:' . (int) $row['CODCOLIGADA'] . ':' . (int) $row['CODTIPOCURSO'],
                    'period_idnumber' => 'rm:periodo:' . (int) $row['CODCOLIGADA'] . ':' . (int) $row['IDPERLET'],
                    'course_idnumber' => 'rm:curso:' . (int) $row['CODCOLIGADA'] . ':' . (int) $row['IDPERLET'] . ':' . (int) $row['CODTIPOCURSO'] . ':' . (string) ($row['CODCURSO'] ?? 'SEM-CURSO'),
                    'cod_coligada' => (int) $row['CODCOLIGADA'],
                    'cod_filial' => (int) $row['CODFILIAL'],
                    'id_perlet' => (int) $row['IDPERLET'],
                    'cod_perlet' => (string) ($row['CODPERLET'] ?? ''),
                    'periodo' => (string) ($row['PERIODO'] ?? $row['CODPERLET'] ?? ''),
                    'tipo_ensino' => (string) ($row['TIPO_ENSINO'] ?? 'Tipo de ensino não informado'),
                    'cod_tipo_curso' => (int) $row['CODTIPOCURSO'],
                    'cod_turma' => (string) $row['CODTURMA'],
                    'turma' => $turmaName,
                    'cod_curso' => (string) ($row['CODCURSO'] ?? ''),
                    'curso' => (string) ($row['CURSO_NOME'] ?? 'Curso não informado'),
                    'periodo_inicio' => $this->formatDate($row['PERIODO_INICIO'] ?? null),
                    'periodo_fim' => $this->formatDate($row['PERIODO_FIM'] ?? null),
                    'status' => $this->periodStatus($row['PERIODO_INICIO'] ?? null, $row['PERIODO_FIM'] ?? null, $row['PERIODO_ENCERRADO'] ?? null),
                    'turno_group' => $turno['key'],
                    'turno_label' => $turno['label'],
                    'turno_rank' => $turno['rank'],
                    'disciplines' => [],
                ];
            } elseif ($turno['rank'] < $groups[$classId]['turno_rank']) {
                $groups[$classId]['turno_group'] = $turno['key'];
                $groups[$classId]['turno_label'] = $turno['label'];
                $groups[$classId]['turno_rank'] = $turno['rank'];
            }

            $disciplineKey = (string) ($row['IDTURMADISC'] ?? '') . ':' . (string) ($row['CODDISC'] ?? '');
            if ($disciplineKey !== ':' && !isset($groups[$classId]['disciplines'][$disciplineKey])) {
                $groups[$classId]['disciplines'][$disciplineKey] = [
                    'idnumber' => 'rm:disciplina:' . (int) $row['CODCOLIGADA'] . ':' . (string) ($row['IDTURMADISC'] ?? '') . ':' . (string) ($row['CODDISC'] ?? ''),
                    'id_turma_disc' => (int) ($row['IDTURMADISC'] ?? 0),
                    'cod_disc' => (string) ($row['CODDISC'] ?? ''),
                    'name' => (string) ($row['DISCIPLINA_NOME'] ?? $row['CODDISC'] ?? 'Disciplina'),
                    'integrated_rm' => strtoupper(trim((string) ($row['INTEGRADO_MOODLE'] ?? ''))) === 'SIM',
                ];
            }
        }

        foreach ($groups as &$group) {
            $group['disciplines'] = array_values($group['disciplines']);
            $group['discipline_count'] = count($group['disciplines']);
        }
        unset($group);
        uasort($groups, static function (array $left, array $right): int {
            return [
                $left['turno_rank'],
                $left['curso'],
                $left['turma'],
            ] <=> [
                $right['turno_rank'],
                $right['curso'],
                $right['turma'],
            ];
        });
        return array_values($groups);
    }

    private function classifyTurno(string $turno): array
    {
        $normalized = strtoupper(trim($turno));
        $ascii = function_exists('iconv') ? iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized) : $normalized;
        $value = preg_replace('/[^A-Z0-9]+/', '', (string) ($ascii ?: $normalized)) ?: '';

        if (str_contains($value, 'ESPECIAL')) {
            return ['key' => 'especial', 'label' => 'Disciplinas especiais', 'rank' => 2];
        }
        if (str_contains($value, 'MATUT') || str_contains($value, 'MANH')) {
            return ['key' => 'matutino', 'label' => 'Matutino', 'rank' => 0];
        }
        if (str_contains($value, 'NOTUR') || str_contains($value, 'NOITE')) {
            return ['key' => 'noturno', 'label' => 'Noturno', 'rank' => 1];
        }
        return ['key' => 'outros', 'label' => 'Outros turnos', 'rank' => 3];
    }

    private function displayTurmaName(array $row): string
    {
        $code = trim((string) ($row['CODTURMA'] ?? ''));
        $name = trim((string) ($row['TURMA_NOME'] ?? ''));
        $periodCode = trim((string) ($row['CODPERLET'] ?? ''));
        $periodName = trim((string) ($row['PERIODO'] ?? ''));

        if ($name === '' || ($periodCode !== '' && strcasecmp($name, $periodCode) === 0)
            || ($periodName !== '' && strcasecmp($name, $periodName) === 0)
            || preg_match('/^\d{4}\/\d{1,2}$/', $name)) {
            return $code !== '' ? $code : $name;
        }
        return $name;
    }

    private function formatDate(mixed $value): string
    {
        if (!$value) return '';
        try {
            return (new DateTimeImmutable((string) $value))->format('d/m/Y');
        } catch (Throwable) {
            return '';
        }
    }

    private function periodStatus(mixed $start, mixed $end, mixed $closed): string
    {
        if (strtoupper(trim((string) $closed)) === 'S') return 'Encerrado';
        try {
            $today = new DateTimeImmutable('today', new DateTimeZone('America/Sao_Paulo'));
            $startDate = new DateTimeImmutable((string) $start);
            $endDate = new DateTimeImmutable((string) $end);
            if ($today < $startDate) return 'Programado';
            if ($today > $endDate) return 'Encerrado';
        } catch (Throwable) {
        }
        return 'Em andamento';
    }
}
