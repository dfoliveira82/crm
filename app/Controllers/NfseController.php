<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Controller, Request, Response};
use App\Services\Rm\{RmConnection, RmFinancialService, RmReceivableRepository};
use App\Models\Nfse;
use Throwable;

final class NfseController extends Controller
{
    // -------------------------------------------------------------------------
    // PAINEL PRINCIPAL
    // -------------------------------------------------------------------------

    public function index(Request $request): Response
    {
        $configured    = true;
        $connOk        = false;
        $error         = '';
        $indicators    = [];
        $rows          = [];
        $totalRows     = 0;
        $paginas       = 1;
        $pagina        = 1;
        $statusCodes   = [];
        $connDiag      = [];

        // Filtros vindos da query string
        $filters = $this->extractFilters($request);
        $pagina  = (int) ($filters['pagina'] ?? 1);

        try {
            // 1. Teste de conexão
            $connDiag = RmConnection::test();
            $connOk   = $connDiag['ok'] ?? false;

            if (!$connOk) {
                $msg = $connDiag['error'] ?? 'Falha na conexão com o SQL Server do TOTVS RM.';
                if (str_contains($msg, 'não configurada') || str_contains($msg, 'RM_DB_')) {
                    $configured = false;
                }
                $error = $connOk ? '' : $msg;
            } else {
                // 2. Indicadores do painel
                $svc        = new RmFinancialService();
                $indicators = $svc->panelIndicators();

                // 3. Lançamentos paginados
                $result  = $svc->getRepository()->findReceivables($filters);
                $rows    = $result['rows'];
                $totalRows = $result['total'];
                $paginas   = $result['paginas'];

                // 4. Mapa de códigos de situação (diagnóstico)
                $statusCodes = (new \App\Models\RmNfseReadModel())->statusCodeSummary();
            }
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'não configurada') || str_contains($msg, 'RM_DB_')) {
                $configured = false;
            }
            error_log('[NFS-e/RM] ' . $msg);
            $error = 'Não foi possível consultar o banco do TOTVS RM. Verifique a conexão e os privilégios de leitura.';
        }

        return $this->render('nfse/index', [
            'title'       => 'NFS-e · Notas Fiscais de Serviço',
            'configured'  => $configured,
            'connOk'      => $connOk,
            'connDiag'    => $connDiag,
            'error'       => $error,
            'indicators'  => $indicators,
            'rows'        => $rows,
            'totalRows'   => $totalRows,
            'paginas'     => $paginas,
            'pagina'      => $pagina,
            'filters'     => $filters,
            'statusCodes' => $statusCodes,
        ]);
    }

    // -------------------------------------------------------------------------
    // SINCRONIZAÇÃO AJAX
    // -------------------------------------------------------------------------

    /** POST /api/nfse/sync – dispara sincronização RM → NFS_COBRANCA */
    public function sync(Request $request): Response
    {
        try {
            $svc   = new RmFinancialService();
            $stats = $svc->sync();
            return $this->json(['ok' => true, 'stats' => $stats]);
        } catch (Throwable $e) {
            error_log('[NFS-e/sync] ' . $e->getMessage());
            return $this->json(['ok' => false, 'error' => 'Falha na sincronização. Verifique os logs.'], 500);
        }
    }

    // -------------------------------------------------------------------------
    // DETALHES DE UM LANÇAMENTO (AJAX)
    // -------------------------------------------------------------------------

    /** GET /api/nfse/lancamento?codcoligada=1&idlan=123 */
    public function lancamento(Request $request): Response
    {
        $codColigada = (int)    ($request->query('codcoligada') ?? 0);
        $idlan       = (string) ($request->query('idlan')       ?? '');

        if ($codColigada <= 0 || $idlan === '') {
            return $this->json(['ok' => false, 'error' => 'Parâmetros inválidos.'], 400);
        }

        try {
            $repo   = new RmReceivableRepository(
                \App\Services\Rm\RmConnection::pdo()
            );
            $lan    = $repo->findByIdlan($codColigada, $idlan);
            $baixas = $repo->findBaixas($codColigada, $idlan);

            if (!$lan) {
                return $this->json(['ok' => false, 'error' => 'Lançamento não encontrado.'], 404);
            }

            return $this->json(['ok' => true, 'lancamento' => $lan, 'baixas' => $baixas]);
        } catch (Throwable $e) {
            error_log('[NFS-e/lancamento] ' . $e->getMessage());
            return $this->json(['ok' => false, 'error' => 'Falha ao consultar o lançamento.'], 500);
        }
    }

    // -------------------------------------------------------------------------
    // DIAGNÓSTICO DE CONEXÃO (AJAX)
    // -------------------------------------------------------------------------

    /** GET /api/nfse/diagnostico */
    public function diagnostico(Request $request): Response
    {
        try {
            $conn   = RmConnection::test();
            $tables = RmConnection::checkTables();
            return $this->json(['ok' => true, 'connection' => $conn, 'tables' => $tables]);
        } catch (Throwable $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // -------------------------------------------------------------------------
    // HELPER
    // -------------------------------------------------------------------------

    private function extractFilters(Request $request): array
    {
        return [
            'data_inicio'         => $request->query('data_inicio') ?? '',
            'data_fim'            => $request->query('data_fim')    ?? '',
            'codcfo'              => $request->query('codcfo')      ?? '',
            'situacao_financeira' => $request->query('situacao_financeira') ?? '',
            'situacao_emissao'    => $request->query('situacao_emissao')    ?? '',
            'pagina'              => max(1, (int) ($request->query('pagina') ?? 1)),
            'por_pagina'          => 50,
        ];
    }
}
