<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Controller, Request, Response, Session};
use App\Models\{RmAcademicCatalog, RmClassParticipants, RmUserProfile};
use App\Services\{MoodleClient, MoodleParticipantIntegrator};
use Throwable;

final class ClassManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $context = $this->activeContext();
        $search = trim((string) $request->query('q', ''));
        $periodId = (int) $request->query('period', 0);
        $periodId = $periodId > 0 ? $periodId : null;
        $courseId = trim((string) $request->query('course', ''));
        $groups = [];
        $periods = [];
        $courses = [];
        $loadError = '';

        try {
            if (!$context) throw new \RuntimeException('Nenhum contexto acadêmico ativo foi encontrado.');
            $catalog = new RmAcademicCatalog();
            $periods = $catalog->listPeriods($context);
            $courses = $catalog->listCourses($context, $periodId);
            $allowedCourses = array_map(static fn(array $course): string => trim((string) $course['CODCURSO']), $courses);
            if ($courseId !== '' && !in_array($courseId, $allowedCourses, true)) $courseId = '';
            $groups = $this->groupClasses($catalog->listClasses($context, $search, $periodId, $courseId, true));
            $ids = [];
            foreach ($groups as $group) foreach ($group['disciplines'] as $discipline) $ids[] = $discipline['id_turma_disc'];
            $counts = (new RmClassParticipants())->counts((int) $context['CODCOLIGADA'], $ids);
            foreach ($groups as &$group) foreach ($group['disciplines'] as &$discipline) {
                $count = $counts[(int) $discipline['id_turma_disc']] ?? [];
                $discipline['student_count'] = (int) ($count['students'] ?? 0);
                $discipline['teacher_count'] = (int) ($count['teachers'] ?? 0);
            }
            unset($discipline, $group);
        } catch (Throwable $exception) {
            error_log('[ClassManagement] Falha ao carregar participantes RM: ' . $exception->getMessage());
            $loadError = 'Não foi possível carregar as turmas e participantes do RM.';
        }

        return $this->render('educacional/gestao-turmas', compact('context', 'groups', 'periods', 'periodId', 'courses', 'courseId', 'search', 'loadError'));
    }

    public function participants(Request $request): Response
    {
        try {
            $context = $this->activeContext();
            $selected = array_values(array_unique(array_filter(array_map('intval', (array) ($request->json()['disciplines'] ?? [])))));
            if (!$context || $selected === []) return $this->json(['error' => 'invalid_selection'], 422);
            $allowed = $this->allowedDisciplines($context);
            $result = [];
            $model = new RmClassParticipants();
            foreach ($selected as $idTurmaDisc) {
                if (!isset($allowed[$idTurmaDisc])) continue;
                $result[] = [
                    'id_turma_disc' => $idTurmaDisc,
                    'discipline' => $allowed[$idTurmaDisc]['name'],
                    'students' => $model->students((int) $context['CODCOLIGADA'], $idTurmaDisc),
                    'teachers' => $model->teachers((int) $context['CODCOLIGADA'], $idTurmaDisc),
                ];
            }
            if (count($result) !== count($selected)) return $this->json(['error' => 'discipline_not_allowed'], 403);
            return $this->json(['status' => 'ok', 'disciplines' => $result]);
        } catch (Throwable $exception) {
            error_log('[ClassManagement] Falha ao carregar participantes: ' . $exception->getMessage());
            return $this->json(['error' => 'participants_load_failed', 'message' => $exception->getMessage()], 500);
        }
    }

    public function enrolmentStatus(Request $request): Response
    {
        try {
            $context = $this->activeContext();
            $selected = array_values(array_unique(array_filter(array_map('intval', (array) ($request->json()['disciplines'] ?? [])))));
            if (!$context || $selected === []) return $this->json(['error' => 'invalid_selection'], 422);

            $allowed = $this->allowedDisciplines($context);
            $participantModel = new RmClassParticipants();
            $moodle = new MoodleClient();
            $result = [];

            foreach ($selected as $idTurmaDisc) {
                if (!isset($allowed[$idTurmaDisc])) return $this->json(['error' => 'discipline_not_allowed'], 403);
                $discipline = $allowed[$idTurmaDisc];
                $students = $participantModel->students((int) $context['CODCOLIGADA'], $idTurmaDisc);
                $total = count($students);
                $expected = [];
                foreach ($students as $student) {
                    $ra = trim((string) ($student['RA'] ?? ''));
                    if ($ra !== '') $expected['rm:aluno:' . (int) $context['CODCOLIGADA'] . ':' . $ra] = true;
                }
                $total = count($expected);

                if (empty($discipline['integrated_rm'])) {
                    $result[] = [
                        'id_turma_disc' => $idTurmaDisc,
                        'total_rm' => $total,
                        'added' => 0,
                        'pending' => $total,
                        'integration_status' => 'course_pending',
                    ];
                    continue;
                }

                try {
                    $course = $moodle->findCourse((string) $discipline['idnumber']);
                    $courseId = (int) ($course['id'] ?? 0);
                    if ($courseId <= 0) {
                        $result[] = [
                            'id_turma_disc' => $idTurmaDisc,
                            'total_rm' => $total,
                            'added' => 0,
                            'pending' => $total,
                            'integration_status' => 'course_not_found',
                        ];
                        continue;
                    }

                    $enrolled = $moodle->getEnrolledUsers($courseId);
                    $enrolledIds = [];
                    foreach ($enrolled as $user) {
                        $idnumber = trim((string) ($user['idnumber'] ?? ''));
                        if ($idnumber !== '') $enrolledIds[$idnumber] = true;
                    }
                    $added = count(array_intersect_key($expected, $enrolledIds));
                    $result[] = [
                        'id_turma_disc' => $idTurmaDisc,
                        'total_rm' => $total,
                        'added' => $added,
                        'pending' => max(0, $total - $added),
                        'integration_status' => $added >= $total ? 'complete' : 'partial',
                    ];
                } catch (Throwable $exception) {
                    $result[] = [
                        'id_turma_disc' => $idTurmaDisc,
                        'total_rm' => $total,
                        'added' => null,
                        'pending' => null,
                        'integration_status' => 'unavailable',
                        'message' => $exception->getMessage(),
                    ];
                }
            }

            return $this->json(['status' => 'ok', 'disciplines' => $result]);
        } catch (Throwable $exception) {
            error_log('[ClassManagement] Falha ao consultar matrículas Moodle: ' . $exception->getMessage());
            return $this->json(['error' => 'enrolment_status_failed', 'message' => $exception->getMessage()], 502);
        }
    }

    public function integrate(Request $request): Response
    {
        try {
            $context = $this->activeContext();
            $selected = array_values(array_unique(array_filter(array_map('intval', (array) ($request->json()['disciplines'] ?? [])))));
            if (!$context || $selected === []) return $this->json(['error' => 'invalid_selection', 'message' => 'Selecione ao menos uma disciplina.'], 422);
            $allowed = $this->allowedDisciplines($context);
            $participantModel = new RmClassParticipants();
            $disciplines = [];
            foreach ($selected as $idTurmaDisc) {
                if (!isset($allowed[$idTurmaDisc])) return $this->json(['error' => 'discipline_not_allowed'], 403);
                $discipline = $allowed[$idTurmaDisc];
                $discipline['students'] = $participantModel->students((int) $context['CODCOLIGADA'], $idTurmaDisc);
                $discipline['teachers'] = $participantModel->teachers((int) $context['CODCOLIGADA'], $idTurmaDisc);
                $disciplines[] = $discipline;
            }
            $summary = (new MoodleParticipantIntegrator())->synchronize($disciplines);
            return $this->json(['status' => 'integrated', 'message' => 'Alunos e professores integrados nas disciplinas selecionadas.', 'summary' => $summary]);
        } catch (Throwable $exception) {
            error_log('[ClassManagement] Falha na integração de participantes: ' . $exception->getMessage());
            return $this->json(['error' => 'participants_integration_failed', 'message' => $exception->getMessage()], 502);
        }
    }

    private function allowedDisciplines(array $context): array
    {
        $rows = (new RmAcademicCatalog())->listClasses($context, null, null, null, true);
        $allowed = [];
        foreach ($rows as $row) {
            $id = (int) ($row['IDTURMADISC'] ?? 0);
            if ($id <= 0) continue;
            $allowed[$id] = [
                'id_turma_disc' => $id,
                'idnumber' => 'rm:disciplina:' . (int) $row['CODCOLIGADA'] . ':' . $id . ':' . (string) $row['CODDISC'],
                'cod_coligada' => (int) $row['CODCOLIGADA'],
                'name' => (string) ($row['DISCIPLINA_NOME'] ?? $row['CODDISC']),
                'integrated_rm' => strtoupper(trim((string) ($row['INTEGRADO_MOODLE'] ?? ''))) === 'SIM',
            ];
        }
        return $allowed;
    }

    private function groupClasses(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $classId = implode(':', [(int) $row['CODCOLIGADA'], (int) $row['IDPERLET'], (string) $row['CODTURMA'], (string) ($row['CODCURSO'] ?? 'SEM-CURSO')]);
            $turmaName = $this->displayTurmaName($row);
            $turnoValue = $turmaName;
            $disciplineName = trim((string) ($row['DISCIPLINA_NOME'] ?? ''));
            if ($disciplineName !== '') $turnoValue .= ' ' . $disciplineName;
            $turno = $this->classifyTurno($turnoValue);
            if (!isset($groups[$classId])) {
                $groups[$classId] = [
                    'idnumber' => 'rm:turma:' . $classId,
                    'turma' => $turmaName,
                    'curso' => (string) ($row['CURSO_NOME'] ?? 'Curso não informado'),
                    'periodo' => (string) ($row['PERIODO'] ?? $row['CODPERLET'] ?? ''),
                    'period_id' => (int) $row['IDPERLET'],
                    'course_id' => (string) ($row['CODCURSO'] ?? ''),
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
            $id = (int) ($row['IDTURMADISC'] ?? 0);
            if ($id > 0) $groups[$classId]['disciplines'][$id] = [
                'id_turma_disc' => $id,
                'idnumber' => 'rm:disciplina:' . (int) $row['CODCOLIGADA'] . ':' . $id . ':' . (string) $row['CODDISC'],
                'cod_coligada' => (int) $row['CODCOLIGADA'],
                'name' => (string) ($row['DISCIPLINA_NOME'] ?? $row['CODDISC']),
                'turno_group' => $turno['key'],
                'turno_label' => $turno['label'],
                'turno_rank' => $turno['rank'],
                'integrated_rm' => strtoupper(trim((string) ($row['INTEGRADO_MOODLE'] ?? ''))) === 'SIM',
            ];
        }
        foreach ($groups as &$group) {
            $group['disciplines'] = array_values($group['disciplines']);
            usort($group['disciplines'], static function (array $left, array $right): int {
                return [$left['turno_rank'], $left['name']] <=> [$right['turno_rank'], $right['name']];
            });
        }
        unset($group);
        uasort($groups, static function (array $left, array $right): int {
            return [$left['turno_rank'], $left['curso'], $left['turma']] <=> [$right['turno_rank'], $right['curso'], $right['turma']];
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

    private function activeContext(): array
    {
        $context = Session::get('active_context', []);
        if (is_array($context) && isset($context['CODCOLIGADA'], $context['CODFILIAL'], $context['CODTIPOCURSO'])) return $context;
        $user = Session::get('user', []);
        $login = is_array($user) ? trim((string) ($user['code'] ?? $user['username'] ?? '')) : '';
        return $login !== '' ? ((new RmUserProfile())->findDefaultContextByActiveEmployment($login) ?? []) : [];
    }
}
