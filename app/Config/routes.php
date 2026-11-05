<?php
declare(strict_types=1);
use App\Core\Router;
use App\Controllers\{AuthController,DashboardController,CopilotController,BookingsController,DealsController,PageController,FacialEnrollmentController,UsuarioController};
use App\Middleware\{AuthMiddleware,GuestMiddleware,CsrfMiddleware};
return static function(Router $r): void {
    $auth=new AuthMiddleware(); $guest=new GuestMiddleware(); $csrf=new CsrfMiddleware();
    $r->add('GET','/login',[new AuthController(),'showLogin'],[$guest]);
    $r->add('POST','/login',[new AuthController(),'login'],[$guest,$csrf]);
    $r->add('POST','/logout',[new AuthController(),'logout'],[$auth,$csrf]);
    $r->add('GET','/usuario/perfil',[new UsuarioController(),'perfil'],[$auth]);
    $r->add('POST','/usuario/contexto',[new UsuarioController(),'alterarContexto'],[$auth,$csrf]);
    $r->add('GET','/',[new DashboardController(),'index'],[$auth]);
    $r->add('GET','/api/facial/employees',[new FacialEnrollmentController(),'employees'],[$auth]);
    $r->add('POST','/api/facial/register',[new FacialEnrollmentController(),'register'],[$auth,$csrf]);
    $r->add('POST','/api/facial/delete',[new FacialEnrollmentController(),'delete'],[$auth,$csrf]);
    $r->add('GET','/pipeline',[new DealsController(),'pipeline'],[$auth]);
    $pages = [
        'rh/gestao-de-ponto', 'rh/gestao-de-folha-de-pagamento', 'rh/gestao-de-pessoas', 'rh/gestao-sesmt',
        'educacional/avaliacao-e-pesquisa', 'educacional/atendimentos-e-aprovacoes',
        'educacional/gestao-bibliotecaria', 'educacional/gestao-educacional',
        'educacional/gestao-de-processo-seletivo',
        'backoffice/gestao-contabil', 'backoffice/gestao-de-compras', 'backoffice/gestao-financeira',
        'backoffice/gestao-fiscal', 'backoffice/gestao-patrimonial',
        'ava/gestao-de-alunos', 'ava/gestao-de-cursos', 'ava/gestao-de-turmas', 'ava/gestao-de-conteudo',
        'suporte/dashboard', 'suporte/listar-ticket', 'suporte/abrir-ticket',
        'suporte/alterar-senha', 'suporte/cadastros',
        'leads','copilot','calendar','contacts','reports','team','help'
    ];
    foreach ($pages as $page) $r->add('GET','/'.$page,[new PageController(),'show'],[$auth]);
    $r->add('GET','/api/bookings',[new BookingsController(),'index'],[$auth]);
    $r->add('GET','/api/copilot/suggestions',[new CopilotController(),'suggestions'],[$auth]);
    $r->add('DELETE','/api/copilot/suggestions/{id}',[new CopilotController(),'dismiss'],[$auth,$csrf]);
    $r->add('POST','/api/copilot/suggestions/{id}/action',[new CopilotController(),'action'],[$auth,$csrf]);
    $r->add('POST','/api/copilot/chat',[new CopilotController(),'chat'],[$auth,$csrf]);
    $r->add('GET','/api/deals/attention',[new DealsController(),'attention'],[$auth]);
};
