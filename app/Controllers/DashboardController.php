<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\AttendanceRequest;
use App\Models\Event;
use App\Models\RmAcademicCatalog;
use App\Models\RmUserProfile;
use DateTimeImmutable;
use DateTimeZone;

final class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Session::get('user', []);
        $owner = is_array($user) ? trim((string) ($user['code'] ?? $user['username'] ?? '')) : '';
        $selectedPeriodId = (int) $request->query('period', 0);
        $upcomingEvents = $owner !== ''
            ? (new Event())->upcoming($owner, new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')), 4)
            : [];
        $activities = $this->mapActivities($upcomingEvents);
        $currentPeriod = $this->resolveCurrentAcademicPeriod($selectedPeriodId > 0 ? $selectedPeriodId : null);
        $periodOptions = [];
        $context = $this->activeContext();
        if ($context) {
            try {
                $periodOptions = (new RmAcademicCatalog())->listPeriods($context);
            } catch (\Throwable) {
                $periodOptions = [];
            }
        }
        $attendances = $this->buildAcademicAttendanceSummary($currentPeriod);
        $studentCounts = $this->buildAcademicEnrollmentSummary($currentPeriod);

        $comparisonPeriod = $attendances['semester'] ?? $this->guessCurrentAcademicPeriod();

        // Os indicadores educacionais continuam sendo referências da interface;
        // as próximas atividades já vêm da agenda persistida do usuário.
        $data = [
            'periodId' => $selectedPeriodId > 0 ? $selectedPeriodId : (int) ($currentPeriod['IDPERLET'] ?? 0),
            'user' => $user,
            'dashboard' => [
                'periodOptions' => $periodOptions,
                'metrics' => [
                    [
                        'title' => 'Alunos Ativos',
                        'value' => (int) (($studentCounts['current']['ALUNOS_EM_CURSO'] ?? 0)),
                        'comparison' => (string) (($studentCounts['ALUNOS_EM_CURSO']['text'] ?? '0,0% vs período anterior')),
                        'direction' => (string) (($studentCounts['ALUNOS_EM_CURSO']['direction'] ?? 'neutral')),
                        'icon' => 'fa-users',
                        'tone' => 'blue',
                        'href' => 'ava/gestao-de-alunos',
                    ],
                    [
                        'title' => 'Pré-Matrículas',
                        'value' => (int) (($studentCounts['current']['ALUNOS_PREMATRICULA'] ?? 0)),
                        'comparison' => (string) (($studentCounts['ALUNOS_PREMATRICULA']['text'] ?? '0,0% vs período anterior')),
                        'direction' => (string) (($studentCounts['ALUNOS_PREMATRICULA']['direction'] ?? 'neutral')),
                        'icon' => 'fa-file-lines',
                        'tone' => 'green',
                        'href' => 'educacional/gestao-de-processo-seletivo',
                    ],
                    [
                        'title' => 'Matrículas OnLine',
                        'value' => (int) (($studentCounts['current']['ALUNOS_MATRICULA_ONLINE'] ?? 0)),
                        'comparison' => (string) (($studentCounts['ALUNOS_MATRICULA_ONLINE']['text'] ?? '0,0% vs período anterior')),
                        'direction' => (string) (($studentCounts['ALUNOS_MATRICULA_ONLINE']['direction'] ?? 'neutral')),
                        'icon' => 'fa-globe',
                        'tone' => 'orange',
                        'href' => 'educacional/gestao-de-matriculas',
                    ],
                ],
                'attendances' => $attendances,
                'activities' => $activities,
            ],
        ];

        return $this->render('dashboard/index', $data);
    }

    private function resolveCurrentAcademicPeriod(?int $forcedPeriodId = null): array
    {
        $context = $this->activeContext();
        if (!$context) {
            return ['CODPERLET' => $this->guessCurrentAcademicPeriod(), 'DESCRICAO' => 'Semestre letivo atual'];
        }

        try {
            $periods = (new RmAcademicCatalog())->listPeriods($context);
            if ($periods === []) {
                return ['CODPERLET' => $this->guessCurrentAcademicPeriod(), 'DESCRICAO' => 'Semestre letivo atual'];
            }

            if ($forcedPeriodId !== null && $forcedPeriodId > 0) {
                foreach ($periods as $period) {
                    if ((int) ($period['IDPERLET'] ?? 0) === $forcedPeriodId) {
                        return $period;
                    }
                }
            }

            $today = new DateTimeImmutable('today', new DateTimeZone('America/Sao_Paulo'));
            $selected = null;
            foreach ($periods as $period) {
                $start = $period['DTINICIO'] ?? null;
                $end = $period['DTFIM'] ?? null;
                if ($start === null || $end === null) {
                    continue;
                }

                try {
                    $startDate = new DateTimeImmutable((string) $start, new DateTimeZone('America/Sao_Paulo'));
                    $endDate = new DateTimeImmutable((string) $end, new DateTimeZone('America/Sao_Paulo'));
                    if ($today >= $startDate && $today <= $endDate) {
                        $selected = $period;
                        break;
                    }
                } catch (\Throwable) {
                    continue;
                }
            }

            return $selected ?? $periods[0];
        } catch (\Throwable) {
            return ['CODPERLET' => $this->guessCurrentAcademicPeriod(), 'DESCRICAO' => 'Semestre letivo atual'];
        }
    }

    private function buildAcademicAttendanceSummary(array $period): array
    {
        $periodLabel = $this->periodLabel($period);
        $periodId = (int) ($period['IDPERLET'] ?? 0);
        $context = $this->activeContext();
        $summary = (new AttendanceRequest())->summaryForSemester($periodId, $context, $periodLabel);
        return [
            'total' => (int) ($summary['total'] ?? 0),
            'semester' => (string) ($summary['semester'] ?? $periodLabel),
            'items' => is_array($summary['items'] ?? null) ? $summary['items'] : [],
        ];
    }

    private function buildAcademicEnrollmentSummary(array $period): array
    {
        $periodId = (int) ($period['IDPERLET'] ?? 0);
        $context = $this->activeContext();
        if ($periodId <= 0 || $context === []) {
            return [
                'current' => [
                    'CODPERLET' => '',
                    'IDPERLET' => $periodId,
                    'ALUNOS_EM_CURSO' => 0,
                    'ALUNOS_PREMATRICULA' => 0,
                    'ALUNOS_MATRICULA_ONLINE' => 0,
                ],
                'previous' => null,
                'ALUNOS_EM_CURSO' => ['percent' => 0.0, 'direction' => 'neutral', 'text' => '0,0% vs período anterior'],
                'ALUNOS_PREMATRICULA' => ['percent' => 0.0, 'direction' => 'neutral', 'text' => '0,0% vs período anterior'],
                'ALUNOS_MATRICULA_ONLINE' => ['percent' => 0.0, 'direction' => 'neutral', 'text' => '0,0% vs período anterior'],
            ];
        }

        return (new AttendanceRequest())->findStudentCounts($context, $periodId);
    }

    private function formatDeltaComparison(float $deltaPercent): string
    {
        $formatted = number_format(abs($deltaPercent), 1, ',', '.');
        $arrow = $deltaPercent < 0 ? '↓' : '↑';
        return $arrow . ' ' . $formatted . '% vs semestre anterior';
    }

    private function periodLabel(array $period): string
    {
        $code = trim((string) ($period['CODPERLET'] ?? ''));
        if ($code !== '') {
            return $code;
        }

        $description = trim((string) ($period['DESCRICAO'] ?? ''));
        if ($description !== '') {
            return $description;
        }

        return $this->guessCurrentAcademicPeriod();
    }

    private function guessCurrentAcademicPeriod(): string
    {
        $year = (int) date('Y');
        $month = (int) date('n');
        $semester = $month <= 6 ? 1 : 2;
        return sprintf('%d/%d', $year, $semester);
    }

    private function activeContext(): array
    {
        $context = Session::get('active_context', []);
        if (is_array($context) && isset($context['CODCOLIGADA'], $context['CODFILIAL'], $context['CODTIPOCURSO'])) {
            return $context;
        }

        $user = Session::get('user', []);
        $login = is_array($user) ? trim((string) ($user['code'] ?? $user['username'] ?? '')) : '';
        return $login !== '' ? ((new RmUserProfile())->findDefaultContextByActiveEmployment($login) ?? []) : [];
    }

    private function mapActivities(array $events): array
    {
        $months = [1 => 'JAN', 'FEV', 'MAR', 'ABR', 'MAI', 'JUN', 'JUL', 'AGO', 'SET', 'OUT', 'NOV', 'DEZ'];
        $tones = ['blue', 'green', 'orange', 'purple'];
        $activities = [];
        foreach ($events as $index => $event) {
            try {
                $start = new DateTimeImmutable((string) $event['start'], new DateTimeZone('America/Sao_Paulo'));
                $end = new DateTimeImmutable((string) $event['end'], new DateTimeZone('America/Sao_Paulo'));
            } catch (\Throwable) {
                continue;
            }
            $sameDay = $start->format('Y-m-d') === $end->format('Y-m-d');
            $period = $sameDay
                ? $start->format('d/m/Y H:i') . ' às ' . $end->format('H:i')
                : $start->format('d/m/Y H:i') . ' a ' . $end->format('d/m/Y H:i');
            $activities[] = [
                'day' => $start->format('d'),
                'month' => $months[(int) $start->format('n')],
                'title' => (string) ($event['title'] ?? 'Evento'),
                'period' => $period,
                'tone' => $tones[$index % count($tones)],
            ];
        }
        return $activities;
    }
}
