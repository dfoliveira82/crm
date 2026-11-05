<?php
declare(strict_types=1);
namespace App\Core;
final class Session {
    public static function start(): void {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        session_name('SELVARSESSID');
        session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
        session_start();
        $minutes = max(5, (int)Env::get('SESSION_LIFETIME_MIN', 30));
        if (isset($_SESSION['_last']) && time() - (int)$_SESSION['_last'] > $minutes * 60) self::destroy();
        $_SESSION['_last'] = time();
    }
    public static function get(string $key, mixed $default=null): mixed { return $_SESSION[$key] ?? $default; }
    public static function put(string $key, mixed $value): void { $_SESSION[$key] = $value; }
    public static function forget(string $key): void { unset($_SESSION[$key]); }
    public static function flash(string $key, mixed $value): void { $_SESSION['_flash'][$key] = $value; }
    public static function pullFlash(string $key, mixed $default=null): mixed {
        $v = $_SESSION['_flash'][$key] ?? $default; unset($_SESSION['_flash'][$key]); return $v;
    }
    public static function regenerate(): void { session_regenerate_id(true); }
    public static function destroy(): void {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'] ?? '', (bool)$p['secure'], (bool)$p['httponly']);
        }
        session_destroy();
    }
}
