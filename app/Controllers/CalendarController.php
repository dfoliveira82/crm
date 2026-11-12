<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Controller, Request, Response, Session};
use App\Models\Event;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class CalendarController extends Controller
{
    private const TIMEZONE = 'America/Sao_Paulo';

    private const MONTHS = [
        1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril',
        5 => 'maio', 6 => 'junho', 7 => 'julho', 8 => 'agosto',
        9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro',
    ];

    private const WEEKDAYS_SHORT = ['DOM.', 'SEG.', 'TER.', 'QUA.', 'QUI.', 'SEX.', 'SÁB.'];

    public function index(Request $request): Response
    {
        $timezone = new DateTimeZone(self::TIMEZONE);
        $today = new DateTimeImmutable('today', $timezone);
        $selectedDate = $this->parseDate((string) $request->query('date', $today->format('Y-m-d')), $today);
        $calendarView = (string) $request->query('view', 'day');
        if (!in_array($calendarView, ['day', 'week', 'month', 'year'], true)) $calendarView = 'day';
        $selectedDateString = $selectedDate->format('Y-m-d');
        $now = new DateTimeImmutable('now', $timezone);
        $currentMinutes = $selectedDateString === $now->format('Y-m-d')
            ? ((int) $now->format('G') * 60) + (int) $now->format('i')
            : 10 * 60 + 15;

        [$rangeStart, $rangeEnd] = $this->viewRange($selectedDate, $calendarView);
        $displayDate = $this->displayDate($selectedDate, $calendarView, $rangeStart, $rangeEnd);

        return $this->render('calendar/index', [
            'selectedDate' => $selectedDate,
            'selectedDateString' => $selectedDateString,
            'todayString' => $today->format('Y-m-d'),
            'timezoneLabel' => 'GMT-3',
            'displayDate' => $displayDate,
            'calendarView' => $calendarView,
            'rangeStart' => $rangeStart,
            'rangeEnd' => $rangeEnd,
            'dayOfWeek' => self::WEEKDAYS_SHORT[(int) $selectedDate->format('w')],
            'currentMinutes' => max(7 * 60, min(20 * 60, $currentMinutes)),
            'currentTimeLabel' => sprintf('%02d:%02d', intdiv($currentMinutes, 60), $currentMinutes % 60),
            'events' => $this->loadEvents($rangeStart->format('Y-m-d'), $rangeEnd->format('Y-m-d')),
        ], 'calendar');
    }

    public function events(Request $request): Response
    {
        $timezone = new DateTimeZone(self::TIMEZONE);
        $today = new DateTimeImmutable('today', $timezone);
        $date = $this->parseDate((string) $request->query('date', $today->format('Y-m-d')), $today);
        $view = (string) $request->query('view', 'day');
        if (!in_array($view, ['day', 'week', 'month', 'year', 'schedule', '4days'], true)) {
            return $this->json(['error' => 'invalid_view'], 422);
        }

        $start = $date->format('Y-m-d');
        $end = $start;
        if ($view === 'week') {
            $start = $date->modify('-' . (int) $date->format('w') . ' days')->format('Y-m-d');
            $end = $date->modify('+' . (6 - (int) $date->format('w')) . ' days')->format('Y-m-d');
        } elseif ($view === '4days') {
            $end = $date->modify('+3 days')->format('Y-m-d');
        } elseif ($view === 'month') {
            $start = $date->modify('first day of this month')->format('Y-m-d');
            $end = $date->modify('last day of this month')->format('Y-m-d');
        } elseif ($view === 'year') {
            $start = $date->modify('first day of January')->format('Y-m-d');
            $end = $date->modify('last day of December')->format('Y-m-d');
        } elseif ($view === 'schedule') {
            $end = $date->modify('+30 days')->format('Y-m-d');
        }

        return $this->json([
            'date' => $date->format('Y-m-d'),
            'view' => $view,
            'timezone' => 'GMT-3',
            'items' => $this->loadEvents($start, $end),
        ]);
    }

    public function store(Request $request): Response
    {
        $data = $request->json();
        $isJsonRequest = str_contains((string) $request->header('Content-Type'), 'application/json');
        if ($data === []) $data = $_POST;
        $user = Session::get('user', []);
        $owner = is_array($user) ? trim((string) ($user['code'] ?? $user['username'] ?? '')) : '';
        $title = trim((string) ($data['title'] ?? ''));
        $date = trim((string) ($data['date'] ?? ''));
        $time = trim((string) ($data['time'] ?? ''));
        $endTime = trim((string) ($data['end_time'] ?? ''));
        $location = trim((string) ($data['location'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));

        if ($owner === '' || $title === '' || $date === '' || $time === '' || $endTime === '') {
            return $this->json(['error' => 'title_date_time_required'], 422);
        }
        $length = static fn(string $value): int => function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
        if ($length($title) > 200 || $length($location) > 250) {
            return $this->json(['error' => 'field_too_long'], 422);
        }

        $timezone = new DateTimeZone(self::TIMEZONE);
        $startsAt = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $time, $timezone);
        $endsAt = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $endTime, $timezone);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$startsAt || !$endsAt || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $startsAt->format('Y-m-d H:i') !== $date . ' ' . $time || $endsAt->format('Y-m-d H:i') !== $date . ' ' . $endTime) {
            return $this->json(['error' => 'invalid_date_time'], 422);
        }
        if ($endsAt <= $startsAt) return $this->json(['error' => 'end_time_must_be_after_start'], 422);

        try {
            $id = (new Event())->create(
                $owner,
                $title,
                $startsAt->format('Y-m-d H:i:s'),
                $endsAt->format('Y-m-d H:i:s'),
                $location,
                $description
            );
            if (!$isJsonRequest) return $this->redirect(url('calendar'), 303);
            return $this->json(['id' => $id, 'message' => 'Evento salvo com sucesso.'], 201);
        } catch (Throwable $exception) {
            error_log('[Calendar] Falha ao salvar evento: ' . $exception->getMessage());
            return $this->json(['error' => 'calendar_save_failed'], 500);
        }
    }

    private function loadEvents(string $start, string $end): array
    {
        $user = Session::get('user', []);
        $owner = is_array($user) ? (string) ($user['code'] ?? $user['username'] ?? '') : '';
        return (new Event())->forRange($owner, $start, $end);
    }

    private function viewRange(DateTimeImmutable $date, string $view): array
    {
        return match ($view) {
            'week' => [$date->modify('-' . (int) $date->format('w') . ' days'), $date->modify('+' . (6 - (int) $date->format('w')) . ' days')],
            'month' => [$date->modify('first day of this month'), $date->modify('last day of this month')],
            'year' => [$date->modify('first day of January'), $date->modify('last day of December')],
            default => [$date, $date],
        };
    }

    private function displayDate(DateTimeImmutable $date, string $view, DateTimeImmutable $start, DateTimeImmutable $end): string
    {
        if ($view === 'year') return $date->format('Y');
        if ($view === 'month') return self::MONTHS[(int) $date->format('n')] . ' de ' . $date->format('Y');
        if ($view === 'week') {
            $startLabel = $start->format('j');
            $endLabel = $end->format('j') . ' de ' . self::MONTHS[(int) $end->format('n')] . ' de ' . $end->format('Y');
            return $startLabel . ' – ' . $endLabel;
        }
        return $date->format('j') . ' de ' . self::MONTHS[(int) $date->format('n')] . ' de ' . $date->format('Y');
    }

    private function parseDate(string $value, DateTimeImmutable $fallback): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone(self::TIMEZONE));
        return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value ? $date : $fallback;
    }
}
