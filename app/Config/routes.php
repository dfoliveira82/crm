<?php
declare(strict_types=1);
use App\Core\Router;
use App\Controllers\{AuthController,DashboardController,CopilotController,BookingsController,DealsController,PageController,FacialEnrollmentController,UsuarioController,CalendarController,CourseManagementController,ClassManagementController,AdIntegrationController,NfseController};
use App\Middleware\{AuthMiddleware,GuestMiddleware,CsrfMiddleware};
return static function(Router $r): void {
    $auth=new AuthMiddleware(); $guest=new GuestMiddleware(); $csrf=new CsrfMiddleware();
    $r->add('GET','/login',[new AuthController(),'showLogin'],[$guest]);
    $r->add('POST','/login',[new AuthController(),'login'],[$guest,$csrf]);
    $r->add('POST','/logout',[new AuthController(),'logout'],[$auth,$csrf]);
    $r->add('GET','/usuario/perfil',[new UsuarioController(),'perfil'],[$auth]);
    $r->add('POST','/usuario/contexto',[new UsuarioController(),'alterarContexto'],[$auth,$csrf]);
    $r->add('GET','/',[new DashboardController(),'index'],[$auth]);
    $r->add('GET','/calendar',[new CalendarController(),'index'],[$auth]);
    $r->add('GET','/agenda',[new CalendarController(),'index'],[$auth]);
    $r->add('GET','/api/calendar/events',[new CalendarController(),'events'],[$auth]);
    $r->add('POST','/api/calendar/events',[new CalendarController(),'store'],[$auth,$csrf]);
    $r->add('GET','/api/agenda/events',[new CalendarController(),'events'],[$auth]);
    $r->add('POST','/api/agenda/events',[new CalendarController(),'store'],[$auth,$csrf]);
    $r->add('GET','/educacional/gestao-de-cursos',[new CourseManagementController(),'index'],[$auth]);
    $r->add('GET','/ava/gestao-de-cursos',[new CourseManagementController(),'index'],[$auth]);
    $r->add('GET','/ava/gestao-de-turmas',[new ClassManagementController(),'index'],[$auth]);
    $r->add('GET','/ava/integracao-ad',[new AdIntegrationController(),'index'],[$auth]);
    $r->add('POST','/api/educacional/moodle/preview',[new CourseManagementController(),'preview'],[$auth,$csrf]);
    $r->add('POST','/api/educacional/moodle/integrate',[new CourseManagementController(),'integrate'],[$auth,$csrf]);
    $r->add('POST','/api/educacional/moodle/participants',[new ClassManagementController(),'participants'],[$auth,$csrf]);
    $r->add('POST','/api/educacional/moodle/participants/status',[new ClassManagementController(),'enrolmentStatus'],[$auth,$csrf]);
    $r->add('POST','/api/educacional/moodle/participants/integrate',[new ClassManagementController(),'integrate'],[$auth,$csrf]);
    $r->add('GET','/api/educacional/ad/participants',[new AdIntegrationController(),'participants'],[$auth]);
    $r->add('POST','/api/educacional/ad/participants',[new AdIntegrationController(),'participants'],[$auth,$csrf]);
    $r->add('POST','/api/educacional/ad/validate',[new AdIntegrationController(),'validateSelection'],[$auth,$csrf]);
    $r->add('POST','/api/educacional/ad/confirm',[new AdIntegrationController(),'confirmEnvironment'],[$auth,$csrf]);
    $r->add('POST','/api/educacional/ad/test-connection',[new AdIntegrationController(),'testConnection'],[$auth,$csrf]);
    $r->add('POST','/api/educacional/ad/integrate',[new AdIntegrationController(),'integrateStudents'],[$auth,$csrf]);
    $r->add('GET','/api/facial/employees',[new FacialEnrollmentController(),'employees'],[$auth]);
    $r->add('POST','/api/facial/register',[new FacialEnrollmentController(),'register'],[$auth,$csrf]);
    $r->add('POST','/api/facial/delete',[new FacialEnrollmentController(),'delete'],[$auth,$csrf]);
    $r->add('GET','/pipeline',[new DealsController(),'pipeline'],[$auth]);
    $r->add('GET', '/backoffice/gestao-financeira/nfse',           [new NfseController(), 'index'],      [$auth]);
    $r->add('POST','/api/nfse/sync',                               [new NfseController(), 'sync'],       [$auth, $csrf]);
    $r->add('GET', '/api/nfse/lancamento',                         [new NfseController(), 'lancamento'], [$auth]);
    $r->add('GET', '/api/nfse/diagnostico',                        [new NfseController(), 'diagnostico'],[$auth]);
    $pages = [
        'rh/gestao-de-ponto', 'rh/gestao-de-folha-de-pagamento', 'rh/gestao-de-pessoas', 'rh/gestao-sesmt',
        'educacional/avaliacao-e-pesquisa', 'educacional/atendimentos-e-aprovacoes',
        'educacional/gestao-bibliotecaria', 'educacional/gestao-educacional',
        'educacional/gestao-de-processo-seletivo',
        'backoffice/gestao-contabil', 'backoffice/gestao-de-compras', 'backoffice/gestao-financeira',
        'backoffice/gestao-fiscal', 'backoffice/gestao-patrimonial',
        'ava/gestao-de-alunos', 'ava/gestao-de-conteudo',
        'suporte/dashboard', 'suporte/listar-ticket', 'suporte/abrir-ticket',
        'suporte/alterar-senha', 'suporte/cadastros',
        'leads','copilot','contacts','reports','team','help'
    ];
    foreach ($pages as $page) $r->add('GET','/'.$page,[new PageController(),'show'],[$auth]);
    $r->add('GET','/api/bookings',[new BookingsController(),'index'],[$auth]);
    $r->add('GET','/api/copilot/suggestions',[new CopilotController(),'suggestions'],[$auth]);
    $r->add('DELETE','/api/copilot/suggestions/{id}',[new CopilotController(),'dismiss'],[$auth,$csrf]);
    $r->add('POST','/api/copilot/suggestions/{id}/action',[new CopilotController(),'action'],[$auth,$csrf]);
    $r->add('POST','/api/copilot/chat',[new CopilotController(),'chat'],[$auth,$csrf]);
    $r->add('GET','/api/deals/attention',[new DealsController(),'attention'],[$auth]);
};
