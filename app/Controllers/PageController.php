<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Controller,Request,Response};
final class PageController extends Controller {
    public function show(Request $r): Response {
        $path = trim($r->path(), '/');
        $labels = [
            'rh/gestao-de-ponto' => 'Gestão de Ponto',
            'rh/gestao-de-folha-de-pagamento' => 'Gestão de Folha de Pagamento',
            'rh/gestao-de-pessoas' => 'Gestão de Pessoas',
            'rh/gestao-sesmt' => 'Gestão SESMT',
            'educacional/avaliacao-e-pesquisa' => 'Avaliação e Pesquisa',
            'educacional/atendimentos-e-aprovacoes' => 'Atendimentos e Aprovações',
            'educacional/gestao-bibliotecaria' => 'Gestão Bibliotecária',
            'educacional/gestao-educacional' => 'Gestão Educacional',
            'educacional/gestao-de-processo-seletivo' => 'Gestão de Processo Seletivo',
            'backoffice/gestao-contabil' => 'Gestão Contábil',
            'backoffice/gestao-de-compras' => 'Gestão de Compras',
            'backoffice/gestao-financeira' => 'Gestão Financeira',
            'backoffice/gestao-fiscal' => 'Gestão Fiscal',
            'backoffice/gestao-patrimonial' => 'Gestão Patrimonial',
            'ava/gestao-de-alunos' => 'Gestão de Alunos',
            'ava/gestao-de-cursos' => 'Gestão de Cursos',
            'ava/gestao-de-turmas' => 'Gestão de Turmas',
            'ava/gestao-de-conteudo' => 'Gestão de Conteúdo',
            'suporte/dashboard' => 'Dashboard',
            'suporte/listar-ticket' => 'Listar Ticket',
            'suporte/abrir-ticket' => 'Abrir Ticket',
            'suporte/alterar-senha' => 'Alterar Senha',
            'suporte/cadastros' => 'Cadastros',
        ];
        $title = $labels[$path] ?? ucfirst(str_replace('-', ' ', basename($path)));
        return $this->render('pages/placeholder', [
            'title' => $title,
            'description' => 'Esta seção está preparada para receber os dados e funcionalidades do módulo.',
            'items' => []
        ]);
    }
}
