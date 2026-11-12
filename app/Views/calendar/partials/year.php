<div class="calendar-view calendar-view--year" data-view="year">
    <div class="calendar-year-title"><strong><?= e($selectedDate->format('Y')) ?></strong><span>Visão anual</span></div>
    <div class="calendar-year-grid-layout">
        <?php for ($month = 1; $month <= 12; $month++): $monthStart = $selectedDate->setDate((int) $selectedDate->format('Y'), $month, 1); $leading = (int) $monthStart->format('w'); $total = (int) $monthStart->modify('last day of this month')->format('j'); ?>
            <section class="calendar-year-month"><h3><?= e(ucfirst($monthNames[$month])) ?></h3><div class="calendar-year-weekdays"><?php foreach (['D', 'S', 'T', 'Q', 'Q', 'S', 'S'] as $weekday): ?><span><?= e($weekday) ?></span><?php endforeach; ?></div><div class="calendar-year-grid">
                <?php for ($i = 0; $i < 42; $i++): $day = $i - $leading + 1; ?>
                    <?php if ($day < 1 || $day > $total): ?><span class="calendar-year-day is-outside"></span>
                    <?php else: $date = $monthStart->setDate((int) $selectedDate->format('Y'), $month, $day); $key = $date->format('Y-m-d'); ?><button class="calendar-year-day <?= $key === $todayString ? 'is-today' : '' ?>" type="button" data-calendar-month-day="<?= e($key) ?>" onclick="window.location.href='<?= e(url('calendar?date=' . $key . '&view=day')) ?>'"><?= e((string) $day) ?></button><?php endif; ?>
                <?php endfor; ?>
            </div></section>
        <?php endfor; ?>
    </div>
</div>
