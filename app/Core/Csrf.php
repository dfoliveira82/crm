<?php
declare(strict_types=1);
namespace App\Core;
final class Csrf {
    public static function token(): string {
        if (!Session::get('_csrf')) Session::put('_csrf', bin2hex(random_bytes(32)));
        return (string)Session::get('_csrf');
    }
    public static function verify(?string $token): bool {
        if (!is_string($token) || $token === '') {
            return false;
        }

        // A validação nunca deve criar um token. Ele precisa ter sido
        // previamente gerado e armazenado durante a renderização do formulário.
        $storedToken = Session::get('_csrf');
        return is_string($storedToken)
            && $storedToken !== ''
            && hash_equals($storedToken, $token);
    }
}
