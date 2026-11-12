<?php
$monthStart = $selectedDate->modify('first day of this month');
$monthEnd = $selectedDate->modify('last day of this month');
$leadingDays = (int) $monthStart->format('w');
$daysInMonth = (int) $monthEnd->format('j');
$eventsByDate = [];
foreach ($events as $event) $eventsByDate[substr((string) ($event['start'] ?? ''), 0, 10)][] = $event;
?>
<div class="calendar-view calendar-view--month" data-view="month">
    <div class="calendar-month-title"><strong><?= e(ucfirst($monthNames[(int) $monthStart->format('n')]) . ' de ' . $monthStart->format('Y')) ?></strong><span><?= e($timezoneLabel) ?></span></div>
    <div class="calendar-month-weekdays"><?php foreach (['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'] as $day): ?><span><?= e($day) ?></span><?php endforeach; ?></div>
    <div class="calendar-month-grid">
        <?php for ($i = 0; $i < 42; $i++): $day = $i - $leadingDays + 1; ?>
            <?php if ($day < 1 || $day > $daysInMonth): ?><div class="calendar-month-cell is-outside"></div>
            <?php else: $date = $monthStart->setDate((int) $monthStart->format('Y'), (int) $monthStart->format('n'), $day); $key = $date->format('Y-m-d'); ?>
                <button class="calendar-month-cell <?= $key === $todayString ? 'is-today ' : '' ?><?= $key === $selectedDateString ? 'is-selected' : '' ?>" type="button" data-calendar-month-day="<?= e($key) ?>" onclick="window.location.href='<?= e(url('calendar?date=' . $key . '&view=day')) ?>'"><span><?= e((string) $day) ?></span>
                    <?php foreach (array_slice($eventsByDate[$key] ?? [], 0, 3) as $event): ?><small><?= e(($event['time'] ?? '') . ' ' . ($event['title'] ?? 'Evento')) ?></small><?php endforeach; ?>
                </button>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
</div>
