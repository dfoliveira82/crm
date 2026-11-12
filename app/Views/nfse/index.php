<?php
declare(strict_types=1);

/**
 * View do painel NFS-e – Etapa 3
 * Variáveis disponíveis:
 *   $configured  bool      – credenciais RM configuradas no .env
 *   $connOk      bool      – conexão SQL Server estabelecida com sucesso
 *   $connDiag    array     – diagnóstico de conexão
 *   $error       string    – mensagem de erro (vazia se ok)
 *   $indicators  array     – contadores do painel
 *   $rows        array     – lançamentos paginados (já enriquecidos)
 *   $totalRows   int       – total de registros para paginação
 *   $paginas     int       – total de páginas
 *   $pagina      int       – página atual
 *   $filters     array     – filtros aplicados
 *   $statusCodes array     – mapa de códigos RM (diagnóstico)
 */

use App\Core\Env;

$money = static fn ($v): string => 'R$&nbsp;' . number_format((float)($v ?? 0), 2, ',', '.');
$pct   = static fn (float $part, float $total): string =>
    $total > 0 ? number_format($part / $total * 100, 1) . '%' : '0%';
$dt    = static function ($v): string {
    if (!$v) return '—';
    try { return (new DateTime((string) $v))->format('d/m/Y'); } catch (Throwable) { return (string) $v; }
};

// Badges de situação financeira
$badgeSituacao = static function (string $situacao): string {
    return match ($situacao) {
        'QUITADO'   => '<span class="nfse-badge nfse-badge--quitado"><i class="fa-solid fa-circle-check"></i> Quitado</span>',
        'PARCIAL'   => '<span class="nfse-badge nfse-badge--parcial"><i class="fa-solid fa-circle-half-stroke"></i> Parcial</span>',
        'PENDENTE'  => '<span class="nfse-badge nfse-badge--pendente"><i class="fa-regular fa-clock"></i> Pendente</span>',
        'CANCELADO' => '<span class="nfse-badge nfse-badge--cancelado"><i class="fa-solid fa-ban"></i> Cancelado</span>',
        default     => '<span class="nfse-badge">' . htmlspecialchars($situacao, ENT_QUOTES, 'UTF-8') . '</span>',
    };
};

$pageBase = url('backoffice/gestao-financeira/nfse');
$qs       = static function (array $extra) use ($filters, $pageBase): string {
    $q = array_filter(array_merge($filters, $extra), static fn ($v) => $v !== '' && $v !== null && $v !== 0);
    return $pageBase . ($q ? '?' . http_build_query($q) : '');
};
?>
<link rel="stylesheet" href="<?= e(asset('css/nfse.css')) ?>">
<section class="nfse-page">

    <!-- CABEÇALHO -->
    <div class="nfse-heading">
        <div>
            <p class="eyebrow">BACKOFFICE / GESTÃO FINANCEIRA</p>
            <h1>Notas Fiscais de Serviço <span class="nfse-etapa-badge">Etapa 3</span></h1>
            <p class="nfse-subtitle">Integração financeira com o TOTVS RM · Validação de quitação integral · Preparação para emissão.</p>
        </div>
        <div class="nfse-heading-actions">
            <button class="nfse-btn nfse-btn--outline" id="btn-sync" type="button">
                <i class="fa-solid fa-rotate"></i> Sincronizar RM
            </button>
            <a class="nfse-btn nfse-btn--ghost" href="<?= e(url('backoffice/gestao-financeira')) ?>">
                <i class="fa-solid fa-arrow-left"></i> Gestão Financeira
            </a>
        </div>
    </div>

    <!-- BARRA DE MODO -->
    <div class="nfse-alert nfse-alert--info">
        <i class="fa-solid fa-shield-halved"></i>
        <div>
            <strong>Modo somente leitura</strong>
            <span>Esta etapa consulta o TOTVS RM sem executar INSERT, UPDATE ou DELETE. A emissão junto à Prefeitura de Ipatinga ainda não está habilitada.</span>
        </div>
    </div>

    <!-- ERRO / CONEXÃO -->
    <?php if ($error !== ''): ?>
        <div class="nfse-alert <?= $configured ? 'nfse-alert--error' : 'nfse-alert--warning' ?>">
            <i class="fa-solid <?= $configured ? 'fa-triangle-exclamation' : 'fa-plug-circle-exclamation' ?>"></i>
            <div>
                <strong><?= $configured ? 'Falha na consulta ao RM' : 'Conexão RM não configurada' ?></strong>
                <span><?= e($error) ?></span>
                <?php if (!$configured): ?>
                    <small>Configure <code>RM_DB_HOST</code>, <code>RM_DB_PORT</code>, <code>RM_DB_NAME</code>, <code>RM_DB_USER</code> e <code>RM_DB_PASS</code> no <code>.env</code>. Use um usuário SQL com permissão <strong>SELECT</strong> nas tabelas <code>dbo.FLAN</code>, <code>dbo.FLANBAIXA</code> e <code>dbo.FCFO</code>.</small>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- STATUS DA CONEXÃO -->
    <?php if ($connOk && isset($connDiag['dbname'])): ?>
        <div class="nfse-conn-status">
            <span class="nfse-conn-dot is-ok"></span>
            <span>Conectado a <strong><?= e($connDiag['dbname']) ?></strong></span>
            <span class="nfse-conn-sep">·</span>
            <span>Hora do servidor: <strong><?= e((string)($connDiag['server_time'] ?? '')) ?></strong></span>
        </div>
    <?php endif; ?>

    <!-- INDICADORES -->
    <?php if ($connOk && $indicators): ?>
        <?php
        $totalLan     = (int)   ($indicators['total_lancamentos'] ?? 0);
        $cancelados   = (int)   ($indicators['cancelados']        ?? 0);
        $pendentes    = (int)   ($indicators['pendentes']         ?? 0);
        $quitadas     = (int)   ($indicators['quitadas']          ?? 0);
        $parciais     = (int)   ($indicators['parciais']          ?? 0);
        $elegiveis    = (int)   ($indicators['elegiveis']         ?? $quitadas);
        $totalOrig    = (float) ($indicators['total_original']    ?? 0);
        $totalArrec   = (float) ($indicators['total_arrecadado']  ?? 0);
        ?>
        <div class="nfse-metrics nfse-metrics--v3">
            <article class="nfse-metric">
                <span class="nfse-metric-icon is-blue"><i class="fa-solid fa-file-invoice-dollar"></i></span>
                <div>
                    <small>Total de lançamentos</small>
                    <strong><?= number_format($totalLan, 0, ',', '.') ?></strong>
                    <small class="nfse-metric-sub">Contas a receber (RM)</small>
                </div>
            </article>
            <article class="nfse-metric">
                <span class="nfse-metric-icon is-green"><i class="fa-solid fa-circle-check"></i></span>
                <div>
                    <small>Integralmente quitadas</small>
                    <strong><?= number_format($quitadas, 0, ',', '.') ?></strong>
                    <small class="nfse-metric-sub">Saldo R$ 0,00</small>
                </div>
            </article>
            <article class="nfse-metric">
                <span class="nfse-metric-icon is-orange"><i class="fa-solid fa-circle-half-stroke"></i></span>
                <div>
                    <small>Parcialmente pagas</small>
                    <strong><?= number_format($parciais, 0, ',', '.') ?></strong>
                    <small class="nfse-metric-sub">Não elegíveis para emissão</small>
                </div>
            </article>
            <article class="nfse-metric">
                <span class="nfse-metric-icon is-gray"><i class="fa-regular fa-clock"></i></span>
                <div>
                    <small>Pendentes</small>
                    <strong><?= number_format($pendentes, 0, ',', '.') ?></strong>
                    <small class="nfse-metric-sub">Sem baixa registrada</small>
                </div>
            </article>
            <article class="nfse-metric">
                <span class="nfse-metric-icon is-red"><i class="fa-solid fa-ban"></i></span>
                <div>
                    <small>Cancelados / Estornados</small>
                    <strong><?= number_format($cancelados, 0, ',', '.') ?></strong>
                    <small class="nfse-metric-sub">Baixa ou lançamento cancelado</small>
                </div>
            </article>
            <article class="nfse-metric nfse-metric--highlight">
                <span class="nfse-metric-icon is-purple"><i class="fa-solid fa-paper-plane"></i></span>
                <div>
                    <small>Elegíveis para NFS-e</small>
                    <strong><?= number_format($elegiveis, 0, ',', '.') ?></strong>
                    <small class="nfse-metric-sub">Prontos para emissão</small>
                </div>
            </article>
        </div>
    <?php endif; ?>

    <!-- FILTROS -->
    <?php if ($connOk): ?>
        <section class="nfse-panel nfse-panel--filters">
            <div class="nfse-panel-heading">
                <div><h2><i class="fa-solid fa-filter"></i> Filtros</h2></div>
                <a href="<?= e($pageBase) ?>" class="nfse-btn nfse-btn--ghost nfse-btn--sm">Limpar</a>
            </div>
            <form class="nfse-filter-form" method="get" action="<?= e($pageBase) ?>">
                <div class="nfse-filter-row">
                    <label class="nfse-filter-group">
                        <span>Baixa de</span>
                        <input type="date" name="data_inicio" value="<?= e($filters['data_inicio'] ?? '') ?>">
                    </label>
                    <label class="nfse-filter-group">
                        <span>Baixa até</span>
                        <input type="date" name="data_fim" value="<?= e($filters['data_fim'] ?? '') ?>">
                    </label>
                    <label class="nfse-filter-group">
                        <span>Cliente</span>
                        <input type="text" name="codcfo" placeholder="Nome do cliente" maxlength="80"
                               value="<?= e($filters['codcfo'] ?? '') ?>">
                    </label>
                    <label class="nfse-filter-group">
                        <span>Situação financeira</span>
                        <select name="situacao_financeira">
                            <option value="">Todas</option>
                            <option value="QUITADO"  <?= ($filters['situacao_financeira'] ?? '') === 'QUITADO'  ? 'selected' : '' ?>>Quitado</option>
                            <option value="PARCIAL"  <?= ($filters['situacao_financeira'] ?? '') === 'PARCIAL'  ? 'selected' : '' ?>>Parcial</option>
                            <option value="PENDENTE" <?= ($filters['situacao_financeira'] ?? '') === 'PENDENTE' ? 'selected' : '' ?>>Pendente</option>
                            <option value="CANCELADO"<?= ($filters['situacao_financeira'] ?? '') === 'CANCELADO'? 'selected' : '' ?>>Cancelado</option>
                        </select>
                    </label>
                    <label class="nfse-filter-group">
                        <span>Situação de emissão</span>
                        <select name="situacao_emissao">
                            <option value="">Todas</option>
                            <option value="ELEGIVEL"    <?= ($filters['situacao_emissao'] ?? '') === 'ELEGIVEL'    ? 'selected' : '' ?>>Elegível</option>
                            <option value="NAO_ELEGIVEL"<?= ($filters['situacao_emissao'] ?? '') === 'NAO_ELEGIVEL'? 'selected' : '' ?>>Não elegível</option>
                        </select>
                    </label>
                    <div class="nfse-filter-actions">
                        <button class="nfse-btn nfse-btn--primary" type="submit">
                            <i class="fa-solid fa-magnifying-glass"></i> Filtrar
                        </button>
                    </div>
                </div>
            </form>
        </section>

        <!-- TABELA DE LANÇAMENTOS -->
        <section class="nfse-panel">
            <div class="nfse-panel-heading">
                <div>
                    <h2>Lançamentos financeiros · FLAN</h2>
                    <p>
                        <?= number_format($totalRows, 0, ',', '.') ?> registro(s) encontrado(s)
                        · Página <?= $pagina ?> de <?= $paginas ?>
                        · Apenas contas a receber (PAGREC = 1)
                    </p>
                </div>
                <span class="nfse-tag"><i class="fa-solid fa-database"></i> Origem: TOTVS RM</span>
            </div>

            <?php if (!$rows): ?>
                <div class="nfse-empty">
                    <i class="fa-solid fa-inbox"></i>
                    <strong>Nenhum lançamento encontrado</strong>
                    <span>Ajuste os filtros ou verifique se existem registros no período selecionado.</span>
                </div>
            <?php else: ?>
                <?php
                $courseGroups = [];
                foreach ($rows as $row) {
                    $courseName = (string) ($row['CURSO_NOME'] ?? 'Demais Lançamentos / Sem Curso');
                    $courseGroups[$courseName][] = $row;
                }
                ?>

                <?php foreach ($courseGroups as $courseName => $courseRows): ?>
                    <div class="nfse-course-group">
                        <div class="nfse-course-header">
                            <div>
                                <span class="nfse-course-kicker">Curso</span>
                                <strong><?= e($courseName) ?></strong>
                            </div>
                            <span class="nfse-course-count"><?= count($courseRows) ?> lançamento(s)</span>
                        </div>

                        <div class="nfse-table-wrap">
                            <table class="nfse-table">
                                <thead>
                                    <tr>
                                        <th>Cliente / Tomador</th>
                                        <th>IDLAN</th>
                                        <th>CPF / CNPJ</th>
                                        <th>Referência da Mensalidade</th>
                                        <th>Vencimento</th>
                                        <th class="nfse-number">Valor original</th>
                                        <th class="nfse-number">Total pago</th>
                                        <th class="nfse-number">Saldo restante</th>
                                        <th>Situação financeira</th>
                                        <th>Elegibilidade</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($courseRows as $row): ?>
                                    <?php
                                    $situacao  = (string) ($row['SITUACAO_FINANCEIRA'] ?? '');
                                    $elegivel  = (bool)   ($row['ELEGIVEL_EMISSAO']    ?? false);
                                    $valorOrig = (float)  ($row['VALORORIGINAL']       ?? 0);
                                    $somaPago  = (float)  ($row['SOMA_PAGO']           ?? 0);
                                    $saldo     = (float)  ($row['SALDO']               ?? ($valorOrig - $somaPago));
                                    ?>
                                    <tr class="<?= $elegivel ? 'nfse-tr--elegivel' : '' ?>">
                                        <td>
                                            <strong><?= e($row['TOMADOR_NOME'] ?: ($row['TOMADOR_NOMEFANTASIA'] ?? 'Não identificado')) ?></strong>
                                            <small>Col. <?= e($row['CODCOLIGADA']) ?> · Cód: <?= e($row['CODCFO'] ?? '—') ?></small>
                                        </td>
                                        <td>
                                            <strong><?= e($row['IDLAN']) ?></strong>
                                            <small><?= e($row['CODFILIAL'] ?? '—') ?> · <?= e($row['CODTDO'] ?? '—') ?></small>
                                        </td>
                                        <td><?= e($row['TOMADOR_DOCUMENTO'] ?? '—') ?></td>
                                        <td>
                                            <strong><?= e($row['NUMERODOCUMENTO'] ? 'Doc: ' . $row['NUMERODOCUMENTO'] : 'Mensalidade') ?></strong>
                                            <small class="nfse-text-truncate" title="<?= e($row['HISTORICO'] ?? '') ?>"><?= e(mb_strimwidth((string)($row['HISTORICO'] ?? '—'), 0, 32, '…')) ?></small>
                                        </td>
                                        <td><?= e($dt($row['DATAVENCIMENTO'] ?? null)) ?></td>
                                        <td class="nfse-number"><?= $money($valorOrig) ?></td>
                                        <td class="nfse-number">
                                            <?= $money($somaPago) ?>
                                            <?php if ((int)($row['QTD_BAIXAS_ATIVAS'] ?? 0) > 1): ?>
                                                <small><?= (int)$row['QTD_BAIXAS_ATIVAS'] ?> baixas</small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="nfse-number <?= $saldo <= 0.01 ? 'nfse-number--zero' : 'nfse-number--saldo' ?>">
                                            <?= $money($saldo) ?>
                                        </td>
                                        <td><?= $badgeSituacao($situacao) ?></td>
                                        <td>
                                            <?php if ($elegivel): ?>
                                                <span class="nfse-badge nfse-badge--elegivel"><i class="fa-solid fa-check"></i> Elegível</span>
                                            <?php else: ?>
                                                <span class="nfse-badge nfse-badge--bloqueado"><i class="fa-solid fa-xmark"></i> Não elegível</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="nfse-btn-detail nfse-btn-icon"
                                                    data-codcoligada="<?= e($row['CODCOLIGADA']) ?>"
                                                    data-idlan="<?= e($row['IDLAN']) ?>"
                                                    title="Ver detalhes e histórico de baixas">
                                                <i class="fa-solid fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- PAGINAÇÃO -->
                <?php if ($paginas > 1): ?>
                    <div class="nfse-pagination">
                        <?php if ($pagina > 1): ?>
                            <a href="<?= e($qs(['pagina' => $pagina - 1])) ?>" class="nfse-page-btn">
                                <i class="fa-solid fa-chevron-left"></i> Anterior
                            </a>
                        <?php else: ?>
                            <span class="nfse-page-btn is-disabled"><i class="fa-solid fa-chevron-left"></i> Anterior</span>
                        <?php endif; ?>

                        <span class="nfse-page-info">Página <?= $pagina ?> de <?= $paginas ?></span>

                        <?php if ($pagina < $paginas): ?>
                            <a href="<?= e($qs(['pagina' => $pagina + 1])) ?>" class="nfse-page-btn">
                                Próxima <i class="fa-solid fa-chevron-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="nfse-page-btn is-disabled">Próxima <i class="fa-solid fa-chevron-right"></i></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <!-- MAPA DE CÓDIGOS DE SITUAÇÃO (diagnóstico) -->
    <?php if ($statusCodes): ?>
        <section class="nfse-panel">
            <div class="nfse-panel-heading">
                <div>
                    <h2>Mapa dos códigos de situação encontrados no RM</h2>
                    <p>Contagem por combinação de campos nos últimos 12 meses. Use para confirmar os valores de PAGREC, STATUSLAN e TIPOBAIXA da sua instalação.</p>
                </div>
                <span class="nfse-tag is-warning"><i class="fa-solid fa-flask"></i> Diagnóstico</span>
            </div>
            <div class="nfse-table-wrap">
                <table class="nfse-table nfse-code-table">
                    <thead>
                        <tr><th>Origem</th><th>Campo 1</th><th>Campo 2</th><th>Quantidade</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($statusCodes as $code): ?>
                        <tr>
                            <td><strong><?= e($code['ORIGEM']) ?></strong></td>
                            <td><?= e($code['CODIGO_1'] ?? 'NULL') ?></td>
                            <td><?= e($code['CODIGO_2'] ?? 'NULL') ?></td>
                            <td><?= number_format((int)$code['QUANTIDADE'], 0, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="nfse-footnote">
                ⚠️ Os códigos acima são <strong>informativos</strong>. A elegibilidade para emissão de NFS-e usa apenas datas e valores (DATACANCELAMENTO, DATACANCELBAIXA, somatório das baixas ativas), sem depender destes códigos.
            </p>
        </section>
    <?php endif; ?>

    <!-- NOTA DE PRÓXIMO PASSO -->
    <div class="nfse-next-step">
        <i class="fa-solid fa-circle-info"></i>
        <span>
            <strong>Etapa 4 (próxima):</strong>
            Configurar os parâmetros fiscais (CNPJ prestador, inscrição municipal, código do serviço, alíquota ISS) e integrar com o provedor municipal de NFS-e da Prefeitura de Ipatinga.
        </span>
    </div>

</section>

<!-- MODAL DE DETALHES DO LANÇAMENTO -->
<div class="nfse-modal-overlay" id="modal-lancamento" hidden aria-modal="true" role="dialog" aria-labelledby="modal-lan-title" style="display: none;">
    <div class="nfse-modal">
        <div class="nfse-modal-header">
            <h3 id="modal-lan-title"><i class="fa-solid fa-file-invoice-dollar"></i> Detalhes do Lançamento</h3>
            <button class="nfse-modal-close" id="modal-close" type="button" aria-label="Fechar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="nfse-modal-body" id="modal-body">
            <div class="nfse-modal-loading"><i class="fa-solid fa-spinner fa-spin"></i> Carregando…</div>
        </div>
    </div>
</div>

<!-- TOAST -->
<div id="nfse-toast" class="nfse-toast" aria-live="polite"></div>

<script type="module">
/* NFS-e Etapa 3 – interações do painel */
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

// ----- SYNC ----------------------------------------------------------------
document.getElementById('btn-sync')?.addEventListener('click', async () => {
    const btn = document.getElementById('btn-sync');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sincronizando…';
    try {
        const res = await fetch('<?= e(url('api/nfse/sync')) ?>', {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken, 'Accept': 'application/json' },
        });
        const data = await res.json();
        if (data.ok) {
            showToast(`Sincronização concluída: ${data.stats?.sincronizados ?? 0} lançamento(s), ${data.stats?.erros ?? 0} erro(s).`, 'success');
            setTimeout(() => location.reload(), 1800);
        } else {
            showToast(data.error ?? 'Falha na sincronização.', 'error');
        }
    } catch {
        showToast('Erro de comunicação com o servidor.', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-rotate"></i> Sincronizar RM';
    }
});

// ----- DETALHE -------------------------------------------------------------
document.querySelectorAll('.nfse-btn-detail').forEach(btn => {
    btn.addEventListener('click', async () => {
        const codcoligada = btn.dataset.codcoligada;
        const idlan       = btn.dataset.idlan;
        openModal();
        try {
            const url  = `<?= e(url('api/nfse/lancamento')) ?>?codcoligada=${encodeURIComponent(codcoligada)}&idlan=${encodeURIComponent(idlan)}`;
            const res  = await fetch(url, { headers: { Accept: 'application/json' } });
            const data = await res.json();
            if (data.ok) {
                renderModal(data.lancamento, data.baixas);
            } else {
                document.getElementById('modal-body').innerHTML =
                    `<div class="nfse-modal-error"><i class="fa-solid fa-triangle-exclamation"></i> ${escHtml(data.error ?? 'Erro ao carregar.')}</div>`;
            }
        } catch {
            document.getElementById('modal-body').innerHTML =
                '<div class="nfse-modal-error"><i class="fa-solid fa-triangle-exclamation"></i> Erro de comunicação.</div>';
        }
    });
});

document.getElementById('modal-close')?.addEventListener('click', closeModal);
document.getElementById('modal-lancamento')?.addEventListener('click', e => {
    if (e.target === e.currentTarget) closeModal();
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

function openModal() {
    const m = document.getElementById('modal-lancamento');
    m.style.display = 'flex';
    m.classList.add('is-open');
    m.removeAttribute('hidden');
    document.getElementById('modal-body').innerHTML = '<div class="nfse-modal-loading"><i class="fa-solid fa-spinner fa-spin"></i> Carregando…</div>';
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    const m = document.getElementById('modal-lancamento');
    m.style.display = 'none';
    m.classList.remove('is-open');
    m.setAttribute('hidden', '');
    document.body.style.overflow = '';
}

function renderModal(lan, baixas) {
    const f = v => v == null || v === '' ? '—' : v;
    const m = v => 'R$ ' + parseFloat(v ?? 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
    const d = v => { if (!v) return '—'; const p = String(v).split('-'); return p.length === 3 ? `${p[2]}/${p[1]}/${p[0]}` : v; };

    const situBadge = {
        QUITADO:  '<span class="nfse-badge nfse-badge--quitado">Quitado</span>',
        PARCIAL:  '<span class="nfse-badge nfse-badge--parcial">Parcial</span>',
        PENDENTE: '<span class="nfse-badge nfse-badge--pendente">Pendente</span>',
        CANCELADO:'<span class="nfse-badge nfse-badge--cancelado">Cancelado</span>',
    }[lan.SITUACAO_FINANCEIRA] ?? lan.SITUACAO_FINANCEIRA;

    const eligBadge = lan.ELEGIVEL_EMISSAO
        ? '<span class="nfse-badge nfse-badge--elegivel"><i class="fa-solid fa-check"></i> Elegível para NFS-e</span>'
        : '<span class="nfse-badge nfse-badge--bloqueado"><i class="fa-solid fa-xmark"></i> Não elegível</span>';

    const descTotal = parseFloat(lan.TOTAL_DESCONTOS ?? lan.VALORDESCONTO_TOTAL ?? lan.SOMA_DESCONTO_BAIXA ?? lan.VALORDESCONTO ?? 0);
    const op3 = parseFloat(lan.VALOROP3 ?? 0);
    const op5 = parseFloat(lan.VALOROP5 ?? 0);
    const op6 = parseFloat(lan.VALOROP6 ?? 0);
    let descDetail = '';
    if (op3 > 0 || op5 > 0 || op6 > 0) {
        const parts = [];
        if (op3 > 0) parts.push(`Op3: ${m(op3)}`);
        if (op5 > 0) parts.push(`Op5: ${m(op5)}`);
        if (op6 > 0) parts.push(`Bolsa: ${m(op6)}`);
        descDetail = `<small class="nfse-muted" style="display:block;font-size:9px;margin-top:2px;">${parts.join(' · ')}</small>`;
    }

    let baixasHtml = '<p class="nfse-muted">Nenhuma baixa encontrada.</p>';
    if (baixas && baixas.length) {
        baixasHtml = `<div class="nfse-table-wrap">
            <table class="nfse-table">
                <thead><tr>
                    <th>ID Baixa</th><th>Data baixa</th><th>Valor pago</th>
                    <th>Desconto / Bolsa</th><th>Juros</th><th>Multa</th><th>Status</th><th>Situação</th>
                </tr></thead><tbody>` +
            baixas.map(b => {
                const ativa = !b.DATACANCELBAIXA;
                const bDesc = parseFloat(b.VALORDESCONTO ?? 0);
                const bOp3 = parseFloat(b.VALOROP3 ?? 0);
                const bOp5 = parseFloat(b.VALOROP5 ?? 0);
                const bOp6 = parseFloat(b.VALOROP6 ?? 0);
                let bDetail = '';
                if (bOp3 > 0 || bOp5 > 0 || bOp6 > 0) {
                    const bp = [];
                    if (bOp6 > 0) bp.push(`Bolsa: ${m(bOp6)}`);
                    else if (bOp3 > 0) bp.push(`Op3: ${m(bOp3)}`);
                    else if (bOp5 > 0) bp.push(`Op5: ${m(bOp5)}`);
                    bDetail = `<small class="nfse-muted" style="display:block;font-size:9px;">${bp.join(' ')}</small>`;
                }
                return `<tr>
                    <td>${escHtml(f(b.IDBAIXA))}</td>
                    <td>${escHtml(d(b.DATABAIXA))}</td>
                    <td class="nfse-number">${m(b.VALORBAIXA)}</td>
                    <td class="nfse-number"><strong>${m(bDesc)}</strong>${bDetail}</td>
                    <td class="nfse-number">${m(b.VALORJUROS)}</td>
                    <td class="nfse-number">${m(b.VALORMULTA)}</td>
                    <td>${escHtml(f(b.STATUS))} / ${escHtml(f(b.TIPOBAIXA))}</td>
                    <td>${ativa ? '<span class="nfse-badge nfse-badge--quitado">Ativa</span>' : '<span class="nfse-badge nfse-badge--cancelado">Cancelada ' + escHtml(d(b.DATACANCELBAIXA)) + '</span>'}</td>
                </tr>`;
            }).join('') +
            `</tbody></table></div>`;
    }

    document.getElementById('modal-body').innerHTML = `
        <div class="nfse-modal-grid">
            <div class="nfse-modal-section">
                <h4>Lançamento</h4>
                <dl>
                    <dt>IDLAN / Coligada</dt><dd><strong>${escHtml(f(lan.IDLAN))}</strong> · Coligada ${escHtml(f(lan.CODCOLIGADA))}</dd>
                    <dt>Tipo / Filial</dt><dd>${escHtml(f(lan.CODTDO))} · ${escHtml(f(lan.CODFILIAL))}</dd>
                    <dt>Histórico</dt><dd>${escHtml(f(lan.HISTORICO))}</dd>
                    <dt>Vencimento</dt><dd>${escHtml(d(lan.DATAVENCIMENTO))}</dd>
                    <dt>Situação financeira</dt><dd>${situBadge}</dd>
                    <dt>Elegibilidade</dt><dd>${eligBadge}</dd>
                </dl>
            </div>
            <div class="nfse-modal-section">
                <h4>Valores</h4>
                <dl>
                    <dt>Valor original</dt><dd class="nfse-number">${m(lan.VALORORIGINAL)}</dd>
                    <dt>Total pago (baixas ativas)</dt><dd class="nfse-number">${m(lan.SOMA_PAGO ?? lan.VALORBAIXADO)}</dd>
                    <dt>Saldo restante</dt><dd class="nfse-number">${m(lan.SALDO)}</dd>
                    <dt>Descontos / Bolsas</dt><dd class="nfse-number"><strong>${m(descTotal)}</strong>${descDetail}</dd>
                    <dt>Juros</dt><dd class="nfse-number">${m(lan.SOMA_JUROS_BAIXA ?? lan.VALORJUROS)}</dd>
                    <dt>Multas</dt><dd class="nfse-number">${m(lan.SOMA_MULTA_BAIXA ?? lan.VALORMULTA)}</dd>
                </dl>
            </div>
            <div class="nfse-modal-section">
                <h4>Tomador</h4>
                <dl>
                    <dt>Nome</dt><dd>${escHtml(f(lan.TOMADOR_NOME))}</dd>
                    <dt>CPF / CNPJ</dt><dd>${escHtml(f(lan.TOMADOR_DOCUMENTO))}</dd>
                    <dt>E-mail</dt><dd>${escHtml(f(lan.TOMADOR_EMAIL))}</dd>
                    <dt>Endereço</dt><dd>${escHtml([lan.TOMADOR_RUA, lan.TOMADOR_NUMERO, lan.TOMADOR_COMPLEMENTO, lan.TOMADOR_BAIRRO, lan.TOMADOR_CIDADE, lan.TOMADOR_UF].filter(Boolean).join(', ') || '—')}</dd>
                    <dt>CEP</dt><dd>${escHtml(f(lan.TOMADOR_CEP))}</dd>
                </dl>
            </div>
        </div>
        <div class="nfse-modal-section">
            <h4>Baixas (${baixas?.length ?? 0})</h4>
            ${baixasHtml}
        </div>
    `;
}

// ----- TOAST ---------------------------------------------------------------
function showToast(msg, type = 'info') {
    const t = document.getElementById('nfse-toast');
    t.textContent = msg;
    t.className   = `nfse-toast nfse-toast--${type} is-visible`;
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.classList.remove('is-visible'), 4500);
}

// ----- HELPERS -------------------------------------------------------------
function escHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, c =>
        ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
}
</script>
