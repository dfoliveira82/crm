<?php
$weekDates = [];
$eventsByDate = [];
for ($i = 0; $i < 7; $i++) {
    $date = $rangeStart->modify('+' . $i . ' days');
    $weekDates[] = $date;
    $eventsByDate[$date->format('Y-m-d')] = [];
}
foreach ($events as $event) {
    $eventDate = substr((string) ($event['start'] ?? ''), 0, 10);
    if (isset($eventsByDate[$eventDate])) $eventsByDate[$eventDate][] = $event;
}
?>
<div class="calendar-view calendar-view--columns" data-view="week" style="--calendar-columns:7">
    <div class="calendar-day-heading calendar-day-heading--columns">
        <div class="calendar-timezone-label"><?= e($timezoneLabel) ?><small>/ AM</small></div>
        <div class="calendar-day-headings">
            <?php foreach ($weekDates as $date): ?><div class="calendar-selected-day <?= $date->format('Y-m-d') === $selectedDateString ? 'is-current' : '' ?>"><span><?= e(['DOM.', 'SEG.', 'TER.', 'QUA.', 'QUI.', 'SEX.', 'SÁB.'][(int) $date->format('w')]) ?></span><strong><?= e($date->format('j')) ?></strong></div><?php endforeach; ?>
        </div>
    </div>
    <div class="calendar-grid calendar-grid--columns" data-calendar-grid>
        <div class="calendar-time-axis">
            <?php foreach ($hours as $hour): ?><span><?= e($hour > 12 ? ($hour - 12) . ' PM' : ($hour === 12 ? '12 PM' : $hour . ' AM')) ?></span><?php endforeach; ?>
        </div>
        <div class="calendar-grid-content">
            <div class="calendar-columns-grid">
                <?php foreach ($weekDates as $date): ?><div class="calendar-column" data-column-date="<?= e($date->format('Y-m-d')) ?>"><?php foreach ($hours as $hour): ?><button class="calendar-slot" type="button" data-calendar-slot data-time="<?= e(sprintf('%02d:00', $hour)) ?>" aria-label="Criar evento às <?= e(sprintf('%02d:00', $hour)) ?>"></button><?php endforeach; ?></div><?php endforeach; ?>
            </div>
            <div class="calendar-events" data-calendar-events>
                <?php foreach ($weekDates as $columnIndex => $date): foreach ($eventsByDate[$date->format('Y-m-d')] as $event): ?><article class="calendar-event" style="--event-top: <?= e((string) ($event['top'] ?? 0)) ?>px; --event-height: <?= e((string) ($event['height'] ?? 48)) ?>px; left: calc((100% / 7 * <?= e((string) $columnIndex) ?>) + 4px); right: auto; width: calc(100% / 7 - 12px)"><strong><?= e($event['title'] ?? 'Evento') ?></strong><span><?= e($event['time'] ?? '') ?></span></article><?php endforeach; endforeach; ?>
            </div>
            <div class="calendar-current-time" style="top: <?= e((string) $timeTop) ?>px" data-calendar-current-time><span><?= e($currentTimeLabel) ?></span></div>
        </div>
    </div>
</div>
