<div class="calendar-view calendar-view--columns" data-view="day" style="--calendar-columns:1">
    <div class="calendar-day-heading calendar-day-heading--columns">
        <div class="calendar-timezone-label"><?= e($timezoneLabel) ?><small>/ AM</small></div>
        <div class="calendar-day-headings">
            <div class="calendar-selected-day is-current"><span><?= e($dayOfWeek) ?></span><strong><?= e($selectedDate->format('j')) ?></strong></div>
        </div>
    </div>
    <div class="calendar-grid calendar-grid--columns" data-calendar-grid>
        <div class="calendar-time-axis">
            <?php foreach ($hours as $hour): ?><span><?= e($hour > 12 ? ($hour - 12) . ' PM' : ($hour === 12 ? '12 PM' : $hour . ' AM')) ?></span><?php endforeach; ?>
        </div>
        <div class="calendar-grid-content">
            <div class="calendar-columns-grid">
                <div class="calendar-column" data-column-date="<?= e($selectedDateString) ?>">
                    <?php foreach ($hours as $hour): ?><button class="calendar-slot" type="button" data-calendar-slot data-time="<?= e(sprintf('%02d:00', $hour)) ?>" aria-label="Criar evento às <?= e(sprintf('%02d:00', $hour)) ?>"></button><?php endforeach; ?>
                </div>
            </div>
            <div class="calendar-events" data-calendar-events>
                <?php foreach ($events as $event): ?><article class="calendar-event" style="--event-top: <?= e((string) ($event['top'] ?? 0)) ?>px; --event-height: <?= e((string) ($event['height'] ?? 48)) ?>px"><strong><?= e($event['title'] ?? 'Evento') ?></strong><span><?= e($event['time'] ?? '') ?></span></article><?php endforeach; ?>
            </div>
            <div class="calendar-current-time" style="top: <?= e((string) $timeTop) ?>px" data-calendar-current-time><span><?= e($currentTimeLabel) ?></span></div>
        </div>
    </div>
</div>
