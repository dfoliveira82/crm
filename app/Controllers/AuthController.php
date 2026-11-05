<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Controller,Request,Response,Session};
use App\Services\{AuthService,LoginThrottle};
use App\Services\Rm\RmException;
final class AuthController extends Controller {
    public function showLogin(Request $request): Response { return $this->render('auth/login',['error'=>Session::pullFlash('error'),'oldUser'=>Session::pullFlash('old_user')],'auth'); }
    public function login(Request $request): Response {
        $username=trim((string)$request->input('username','')); $password=(string)$request->input('password','');
        Session::put('_old',['username'=>$username]);
        if($username==='' || $password==='') { Session::flash('error','Informe usuário e senha.'); Session::flash('old_user',$username); return $this->redirect('/login'); }
        $throttle=new LoginThrottle();
        if($throttle->blocked($username)) { Session::flash('error','Muitas tentativas. Aguarde antes de tentar novamente.'); Session::flash('old_user',$username); return $this->redirect('/login'); }
        try {
            if(!(new AuthService())->attempt($username,$password)) { $throttle->fail($username); Session::flash('error','Usuário ou senha inválidos'); Session::flash('old_user',$username); return $this->redirect('/login'); }
            $throttle->clear($username); Session::forget('_old'); return $this->redirect('/');
        } catch(RmException $e) {
            error_log('[RM LOGIN] '.($e->unavailable?'unavailable':'rejected').' status='.$e->httpStatus);
            $throttle->fail($username); Session::flash('error',$e->unavailable?'Não foi possível validar agora, tente novamente':'Usuário ou senha inválidos'); Session::flash('old_user',$username); return $this->redirect('/login');
        }
    }
    public function logout(Request $request): Response { Session::destroy(); return $this->redirect('/login'); }
}
