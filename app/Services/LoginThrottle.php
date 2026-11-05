<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\{Env,Session};
final class LoginThrottle {
    public function blocked(string $username): bool {
        $key=$this->key($username); $entry=Session::get($key,[]);
        return is_array($entry) && ($entry['until']??0)>time();
    }
    public function fail(string $username): void {
        $key=$this->key($username); $entry=Session::get($key,['count'=>0,'until'=>0]);
        $entry['count']++;
        if($entry['count']>=max(1,(int)Env::get('LOGIN_MAX_ATTEMPTS',5))) $entry['until']=time()+max(1,(int)Env::get('LOGIN_LOCK_MIN',15))*60;
        Session::put($key,$entry);
    }
    public function clear(string $username): void { Session::forget($this->key($username)); }
    private function key(string $username): string { return '_login_'.hash('sha256',($_SERVER['REMOTE_ADDR']??'unknown').'|'.mb_strtolower($username)); }
}
