<?php
$dashboard = $dashboard ?? [];
$metrics = $dashboard['metrics'] ?? [];
$attendance = $dashboard['attendances'] ?? ['total' => 0, 'items' => []];
$activities = $dashboard['activities'] ?? [];
$chartLabels = array_column($attendance['items'], 'label');
$chartValues = array_map(static fn(array $item): int => (int) $item['value'], $attendance['items']);
$chartColors = array_column($attendance['items'], 'color');
$userName = trim((string) (($user['name'] ?? $user['nome'] ?? 'Usuário')));
$firstName = explode(' ', $userName)[0] ?: 'Usuário';
$firstName = function_exists('mb_convert_case')
    ? mb_convert_case($firstName, MB_CASE_TITLE, 'UTF-8')
    : ucfirst($firstName);

// Saudação de acordo com o horário de Brasília.
$hour = (int) date('G');
$greeting = $hour >= 5 && $hour < 12
    ? 'Bom dia'
    : ($hour >= 12 && $hour < 18 ? 'Boa tarde' : 'Boa noite');

$weekDays = [
    'domingo', 'segunda-feira', 'terça-feira', 'quarta-feira',
    'quinta-feira', 'sexta-feira', 'sábado',
];
$months = [
    1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
    'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro',
];
$todayLabel = $weekDays[(int) date('w')] . ', ' . date('d') . ' de '
    . $months[(int) date('n')] . ' de ' . date('Y');
$quarter = (int) ceil((int) date('n') / 3);
$weekNumber = (int) date('W');
?>
<section class="dashboard-welcome" aria-label="Boas-vindas">
    <p class="dashboard-date"><i class="fa-regular fa-sun" aria-hidden="true"></i> <?= e($todayLabel) ?> · <?= e((string) $quarter) ?>º trimestre · Semana <?= e((string) $weekNumber) ?></p>
    <h1><?= e($greeting) ?>, <?= e($firstName) ?></h1>
    <p class="dashboard-welcome-subtitle">Acompanhe seus resultados, oportunidades e prioridades comerciais.</p>
</section>


<section class="facial-dashboard-entry" aria-label="Cadastro facial de funcionários">
    <div class="facial-entry-icon"><i class="fa-solid fa-face-viewfinder" aria-hidden="true"></i></div>
    <div class="facial-entry-copy">
        <span class="facial-entry-kicker">RECURSOS HUMANOS</span>
        <h2>Cadastro Facial dos Funcionários</h2>
        <p>Gerencie as referências faciais dos funcionários para identificação no registro de ponto.</p>
    </div>
    <button type="button" class="facial-entry-button" id="open-facial-enrollment">
        <i class="fa-solid fa-camera" aria-hidden="true"></i> Abrir cadastro facial
    </button>
</section>

<div class="facial-modal" id="facial-enrollment-modal" aria-hidden="true" hidden>
    <div class="facial-modal-backdrop" data-facial-close></div>
    <section class="facial-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="facial-modal-title">
        <header class="facial-modal-header">
            <div>
                <span class="facial-entry-kicker">RECURSOS HUMANOS · BIOMETRIA</span>
                <h2 id="facial-modal-title">Cadastro Facial dos Funcionários</h2>
                <p>Pesquise o funcionário, selecione o vínculo e faça três capturas faciais.</p>
            </div>
            <button type="button" class="facial-modal-close" data-facial-close aria-label="Fechar">&times;</button>
        </header>

        <div class="facial-modal-content">
            <div class="facial-enrollment-grid">
                <section class="facial-enrollment-panel">
                    <span class="facial-panel-kicker">FUNCIONÁRIO</span>
                    <h3>Localizar funcionário</h3>
                    <p>Pesquise por nome ou chapa. Somente vínculos ativos e administrativos são exibidos.</p>
                    <div class="facial-search-row">
                        <input id="facial-employee-search" type="search" autocomplete="off" placeholder="Nome ou chapa do funcionário">
                        <button type="button" id="facial-employee-search-button"><i class="fa-solid fa-magnifying-glass"></i> Pesquisar</button>
                    </div>
                    <div id="facial-employee-results" class="facial-employee-results" hidden></div>
                    <div id="facial-selected-employee" class="facial-selected-employee" hidden></div>
                    <button type="button" id="facial-delete-button" class="facial-delete-button" disabled>
                        <i class="fa-solid fa-trash-can" aria-hidden="true"></i> Excluir cadastro facial
                    </button>
                    <button type="button" id="facial-start-button" class="facial-start-button" disabled><i class="fa-solid fa-camera"></i> Iniciar cadastro facial</button>
                    <div id="facial-enrollment-status" class="facial-enrollment-status" role="status" hidden></div>
                </section>

                <aside class="facial-enrollment-info">
                    <div><i class="fa-solid fa-microchip"></i><span><strong>Processamento local</strong><small>Os descritores são gerados no navegador, sem serviço facial pago.</small></span></div>
                    <div><i class="fa-solid fa-user-check"></i><span><strong>Vínculo individual</strong><small>O cadastro fica associado à coligada e à chapa selecionadas.</small></span></div>
                    <div><i class="fa-solid fa-database"></i><span><strong>Armazenamento seguro</strong><small>O banco recebe vetores matemáticos, não fotografias do funcionário.</small></span></div>
                    <div><i class="fa-solid fa-shield-halved"></i><span><strong>Privacidade</strong><small>Descritores faciais são dados biométricos sensíveis. Restrinja o acesso ao cadastro e observe as regras internas de proteção de dados.</small></span></div>
                </aside>
            </div>

            <section id="facial-capture-panel" class="facial-capture-panel" hidden>
                <header>
                    <div><span class="facial-panel-kicker">CAPTURA</span><h3>Captura da referência facial</h3><p>Posicione o rosto dentro da marcação e faça pequenas mudanças naturais entre as capturas.</p></div>
                    <button type="button" id="facial-capture-close" aria-label="Fechar câmera">&times;</button>
                </header>
                <div id="facial-capture-root" class="facial-capture-root"></div>
            </section>
        </div>
    </section>
</div>

<section class="education-dashboard" aria-label="Dashboard educacional">
    <div class="education-metrics">
        <?php foreach ($metrics as $metric): ?>
            <article class="education-card metric-card">
                <div class="metric-main">
                    <span class="metric-icon metric-icon--<?= e($metric['tone']) ?>">
                        <i class="fa-solid <?= e($metric['icon']) ?>" aria-hidden="true"></i>
                    </span>
                    <div class="metric-copy">
                        <h2><?= e($metric['title']) ?></h2>
                        <strong><?= e((string) $metric['value']) ?></strong>
                        <span class="metric-comparison metric-comparison--<?= e($metric['direction']) ?>">
                            <?php if ($metric['direction'] === 'down'): ?>
                                <i class="fa-solid fa-arrow-down" aria-hidden="true"></i>
                            <?php else: ?>
                                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            <?php endif; ?>
                            <?= e($metric['comparison']) ?>
                        </span>
                    </div>
                </div>
                <a class="education-card-link metric-link--<?= e($metric['tone']) ?>" href="<?= e(url($metric['href'])) ?>">
                    <span>Ver detalhes</span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            </article>
        <?php endforeach; ?>
    </div>

    <div class="education-content-grid">
        <article class="education-card attendance-card">
            <header class="education-card-heading">
                <h2>Atendimentos / Solicitações</h2>
                <a href="<?= e(url('educacional/atendimentos-e-aprovacoes')) ?>">Ver detalhes <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
            </header>

            <div class="attendance-content">
                <div class="attendance-chart-wrap">
                    <canvas id="attendance-donut" role="img" aria-label="Gráfico de rosca dos atendimentos e solicitações"></canvas>
                    <div class="attendance-chart-center" aria-hidden="true">
                        <span>Total</span>
                        <strong><?= e((string) $attendance['total']) ?></strong>
                    </div>
                </div>
                <div class="attendance-legend">
                    <?php foreach ($attendance['items'] as $item): ?>
                        <div class="attendance-legend-row">
                            <span class="legend-label"><i style="--legend-color: <?= e($item['color']) ?>"></i><?= e($item['label']) ?></span>
                            <span class="legend-value"><?= e((string) $item['value']) ?> (<?= e($item['percent']) ?>)</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <footer class="education-card-footer">
                <a href="<?= e(url('educacional/atendimentos-e-aprovacoes')) ?>">Ver todos atendimentos <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                <i class="fa-solid fa-arrow-right footer-trailing" aria-hidden="true"></i>
            </footer>
        </article>

        <article class="education-card activities-card">
            <header class="education-card-heading">
                <h2>Próximas Atividades</h2>
                <a href="<?= e(url('calendar')) ?>">Ver calendário <i class="fa-regular fa-calendar" aria-hidden="true"></i></a>
            </header>

            <div class="activity-list">
                <?php foreach ($activities as $activity): ?>
                    <div class="activity-row">
                        <time class="activity-date activity-date--<?= e($activity['tone']) ?>">
                            <strong><?= e($activity['day']) ?></strong>
                            <span><?= e($activity['month']) ?></span>
                        </time>
                        <i class="activity-dot activity-dot--<?= e($activity['tone']) ?>" aria-hidden="true"></i>
                        <div class="activity-copy">
                            <strong><?= e($activity['title']) ?></strong>
                            <span><?= e($activity['period']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </article>
    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
(() => {
    const canvas = document.getElementById('attendance-donut');
    if (!canvas || typeof Chart === 'undefined') return;

    const rootStyles = getComputedStyle(document.documentElement);
    const surfaceColor = rootStyles.getPropertyValue('--surface').trim() || '#ffffff';


    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($chartLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            datasets: [{
                data: <?= json_encode($chartValues) ?>,
                backgroundColor: <?= json_encode($chartColors) ?>,
                borderWidth: 2,
                borderColor: surfaceColor,
                hoverOffset: 3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: { legend: { display: false }, tooltip: { enabled: true } },
            animation: { duration: 450 }
        }
    });
})();
</script>

<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js" defer></script>
<script>
window.SELVAR_FACIAL = {
    csrfToken: <?= json_encode(\App\Core\Csrf::token(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    searchUrl: '/api/facial/employees',
    registerUrl: '/api/facial/register',
    deleteUrl: '/api/facial/delete',
    modelUrl: 'https://cdn.jsdelivr.net/gh/justadudewhohacks/face-api.js@0.22.2/weights'
};
</script>
<link rel="stylesheet" href="<?= e(asset('css/facial-enrollment.css')) ?>">
<script src="<?= e(asset('js/facial-enrollment.js')) ?>" defer></script>
