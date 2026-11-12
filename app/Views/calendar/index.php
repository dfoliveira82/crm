<?php
$hours = range(7, 19);
$monthNames = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
$calendarMonthStart = $selectedDate->modify('first day of this month');
$calendarMonthEnd = $selectedDate->modify('last day of this month');
$leadingDays = (int) $calendarMonthStart->format('w');
$daysInMonth = (int) $calendarMonthEnd->format('j');
$monthTitle = ucfirst($monthNames[(int) $calendarMonthStart->format('n')]) . ' de ' . $calendarMonthStart->format('Y');
$miniWeeks = (int) ceil(($leadingDays + $daysInMonth) / 7);
$timeTop = (($currentMinutes - (7 * 60)) / 60) * 60;
$timeTop = max(0, min(count($hours) * 60, $timeTop));
$viewParam = '&view=' . rawurlencode($calendarView);
$previousDate = $calendarView === 'week' ? $selectedDate->modify('-7 days') : ($calendarView === 'month' ? $selectedDate->modify('-1 month') : ($calendarView === 'year' ? $selectedDate->modify('-1 year') : $selectedDate->modify('-1 day')));
$nextDate = $calendarView === 'week' ? $selectedDate->modify('+7 days') : ($calendarView === 'month' ? $selectedDate->modify('+1 month') : ($calendarView === 'year' ? $selectedDate->modify('+1 year') : $selectedDate->modify('+1 day')));
$previousDateUrl = url('calendar?date=' . $previousDate->format('Y-m-d') . $viewParam);
$nextDateUrl = url('calendar?date=' . $nextDate->format('Y-m-d') . $viewParam);
$todayUrl = url('calendar?date=' . $todayString . $viewParam);
$previousMonthUrl = url('calendar?date=' . $calendarMonthStart->modify('-1 month')->format('Y-m-d') . $viewParam);
$nextMonthUrl = url('calendar?date=' . $calendarMonthStart->modify('+1 month')->format('Y-m-d') . $viewParam);
$viewLabels = ['day' => 'Dia', 'week' => 'Semana', 'month' => 'Mês', 'year' => 'Ano'];
?>
<div class="calendar-app" id="calendar-root"
    data-date="<?= e($selectedDateString) ?>"
    data-today="<?= e($todayString) ?>"
    data-current-minutes="<?= e((string) $currentMinutes) ?>"
    data-view="<?= e($calendarView) ?>"
    data-events-url="<?= e(url('api/calendar/events')) ?>"
    data-save-url="<?= e(url('api/calendar/events')) ?>"
    data-csrf-token="<?= e(App\Core\Csrf::token()) ?>">
    <header class="calendar-header">
        <div class="calendar-brand-area">
            
            <a class="calendar-brand" href="<?= e(url()) ?>" aria-label="Voltar ao dashboard">
                <span class="calendar-brand-mark">C</span>
                <span>Calendário de Eventos</span>
            </a>
        </div>

        <div class="calendar-date-navigation" aria-label="Navegação de datas">
            <button class="calendar-today-button" type="button" data-calendar-today onclick="window.location.href='<?= e($todayUrl) ?>'">Hoje</button>
            <button class="calendar-icon-button" type="button" data-calendar-prev aria-label="Dia anterior" onclick="window.location.href='<?= e($previousDateUrl) ?>'"><i class="fa-solid fa-chevron-left"></i></button>
            <button class="calendar-icon-button" type="button" data-calendar-next aria-label="Próximo dia" onclick="window.location.href='<?= e($nextDateUrl) ?>'"><i class="fa-solid fa-chevron-right"></i></button>
            <h1 data-calendar-date-label><?= e($displayDate) ?></h1>
        </div>

        <div class="calendar-header-actions">
            <button class="calendar-icon-button" type="button" aria-label="Pesquisar"><i class="fa-solid fa-magnifying-glass"></i></button>
            <button class="calendar-icon-button" type="button" aria-label="Ajuda"><i class="fa-regular fa-circle-question"></i></button>
            <button class="calendar-icon-button" type="button" aria-label="Configurações"><i class="fa-solid fa-gear"></i></button>
            <div class="calendar-menu-wrap">
                <button class="calendar-view-button" type="button" data-calendar-view-toggle data-calendar-direct-toggle aria-haspopup="menu" aria-expanded="false" aria-controls="calendar-view-menu"
                    onclick="event.stopPropagation(); const menu=document.getElementById('calendar-view-menu'); menu.hidden=!menu.hidden; this.setAttribute('aria-expanded', String(!menu.hidden));">
                    <span data-calendar-view-label><?= e($viewLabels[$calendarView]) ?></span><i class="fa-solid fa-caret-down" aria-hidden="true"></i>
                </button>
                <div class="calendar-popover calendar-view-menu" id="calendar-view-menu" role="menu" hidden>
                    <?php foreach ([['day', 'Dia', 'D'], ['week', 'Semana', 'W'], ['month', 'Mês', 'M'], ['year', 'Ano', 'Y']] as [$value, $label, $shortcut]): ?>
                        <a class="calendar-menu-item <?= $value === $calendarView ? 'is-selected' : '' ?>" href="<?= e(url('calendar?date=' . $selectedDateString . '&view=' . $value)) ?>" role="menuitem" data-calendar-view="<?= e($value) ?>">
                            <span><?= e($label) ?></span><kbd><?= e($shortcut) ?></kbd>
                        </a>
                    <?php endforeach; ?>
                    
                </div>
            </div>
            <div class="calendar-segmented-actions" aria-label="Atalhos da agenda">
                <button class="is-active" type="button" aria-label="Calendário"><i class="fa-regular fa-calendar-days"></i></button>
                <button type="button" aria-label="Tarefas concluídas"><i class="fa-regular fa-circle-check"></i></button>
            </div>
        </div>
    </header>

    <div class="calendar-body">
        <aside class="calendar-sidebar" aria-label="Ferramentas da agenda">
            <div class="calendar-create-wrap">
                <button class="calendar-create-button" type="button" data-calendar-create-toggle data-calendar-open-event aria-label="Criar evento"
                    onclick="document.getElementById('calendar-event-modal')?.removeAttribute('hidden'); document.querySelector('#calendar-event-modal [name=title]')?.focus();">
                    <i class="" aria-hidden="true"></i>
                    <span>Criar</span>
                    <i class="" aria-hidden="true"></i>
                </button>
                <div class="calendar-popover calendar-create-menu" id="calendar-create-menu" role="menu" hidden>
                    <button class="calendar-menu-item" type="button" role="menuitem" data-calendar-create="event"><span>Evento</span><i class="fa-solid fa-chevron-right"></i></button>
                    <button class="calendar-menu-item" type="button" role="menuitem" data-calendar-create="task"><span>Tarefa</span><i class="fa-solid fa-chevron-right"></i></button>
                </div>
            </div>

            <section class="mini-calendar" aria-label="Calendário mensal">
                <div class="mini-calendar-heading">
                    <strong><?= e($monthTitle) ?></strong>
                    <div><button type="button" aria-label="Mês anterior" onclick="window.location.href='<?= e($previousMonthUrl) ?>'"><i class="fa-solid fa-chevron-left"></i></button><button type="button" aria-label="Próximo mês" onclick="window.location.href='<?= e($nextMonthUrl) ?>'"><i class="fa-solid fa-chevron-right"></i></button></div>
                </div>
                <div class="mini-calendar-weekdays" aria-hidden="true">
                    <?php foreach (['D', 'S', 'T', 'Q', 'Q', 'S', 'S'] as $weekday): ?><span><?= e($weekday) ?></span><?php endforeach; ?>
                </div>
                <div class="mini-calendar-grid">
                    <?php for ($i = 0; $i < $miniWeeks * 7; $i++):
                        $day = $i - $leadingDays + 1;
                        $isCurrent = $day === (int) $selectedDate->format('j');
                        $isToday = $selectedDateString === $todayString && $isCurrent;
                    ?>
                        <?php if ($day < 1 || $day > $daysInMonth): ?><span class="is-outside"></span>
                        <?php else:
                            $miniDate = $calendarMonthStart->setDate((int) $selectedDate->format('Y'), (int) $selectedDate->format('n'), $day)->format('Y-m-d');
                        ?><button type="button" class="<?= $isCurrent ? 'is-selected ' : '' ?><?= $isToday ? 'is-today' : '' ?>" data-mini-day="<?= e($miniDate) ?>" onclick="window.location.href='<?= e(url('calendar?date=' . $miniDate)) ?>'"><?= e((string) $day) ?></button><?php endif; ?>
                    <?php endfor; ?>
                </div>
            </section>
        </aside>

        <main class="calendar-main" aria-label="Agenda <?= e($viewLabels[$calendarView]) ?>" data-calendar-view-container>
            <?php if ($calendarView === 'week'): ?>
                <?php require __DIR__ . '/partials/week.php'; ?>
            <?php elseif ($calendarView === 'month'): ?>
                <?php require __DIR__ . '/partials/month.php'; ?>
            <?php elseif ($calendarView === 'year'): ?>
                <?php require __DIR__ . '/partials/year.php'; ?>
            <?php else: ?>
                <?php require __DIR__ . '/partials/day.php'; ?>
            <?php endif; ?>
        </main>
    </div>

    <div class="calendar-modal" id="calendar-event-modal" data-calendar-modal hidden>
        <div class="calendar-modal-backdrop" data-calendar-modal-close onclick="document.getElementById('calendar-event-modal')?.setAttribute('hidden', '')"></div>
        <form class="calendar-modal-dialog" data-calendar-form method="post" action="<?= e(url('api/calendar/events')) ?>">
            <?= csrf_field() ?>
            <header>
                <div><span class="calendar-modal-kicker" data-calendar-modal-type>EVENTO</span>
                    <h2>Adicionar à agenda</h2>
                </div><button type="button" aria-label="Fechar" data-calendar-modal-close onclick="document.getElementById('calendar-event-modal')?.setAttribute('hidden', '')"><i class="fa-solid fa-xmark"></i></button>
            </header>
            <label>Título<input type="text" name="title" placeholder="Título do evento" required></label>
            <label>Data<input type="date" name="date" value="<?= e($selectedDateString) ?>" required></label>
            <div class="calendar-form-row"><label>Horário de início<input type="time" name="time" value="10:00" required></label><label>Horário de término<input type="time" name="end_time" value="11:00" required></label></div>
            <label>Local<input type="text" name="location" placeholder="Informe o local do evento"></label>
            <label>Descrição<textarea name="description" rows="3" placeholder="Adicione detalhes (opcional)"></textarea></label>
            <footer><button type="button" class="calendar-secondary-button" data-calendar-modal-close onclick="document.getElementById('calendar-event-modal')?.setAttribute('hidden', '')">Cancelar</button><button type="submit" class="calendar-primary-button">Salvar</button></footer>
        </form>
    </div>
</div>