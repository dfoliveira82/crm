<?php
declare(strict_types=1);
use App\Core\{Env,Request,Response,Router,Session};
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/app/Core/helpers.php';
Env::load(dirname(__DIR__).'/.env');
Session::start();
date_default_timezone_set('America/Sao_Paulo');
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(self), microphone=(), geolocation=()');
try {
    $router=new Router(); $register=require dirname(__DIR__).'/app/Config/routes.php'; $register($router);
    $router->dispatch(new Request())->send();
} catch(Throwable $e) {
    error_log('[APP] '.get_class($e).': '.$e->getMessage());
    http_response_code(500);
    if(Env::get('APP_ENV')==='local') echo '<pre>'.e($e->getMessage()).'</pre>';
    else echo 'Erro interno. Tente novamente.';
}
