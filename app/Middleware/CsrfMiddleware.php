<?php
declare(strict_types=1);
namespace App\Middleware;
use App\Core\{Csrf,Request,Response};
final class CsrfMiddleware {
    public function __invoke(Request $request): void {
        $token=$request->input('_token') ?? $request->header('X-CSRF-Token');
        if(!$token && str_contains((string)$request->header('Content-Type'),'application/json')) $token=$request->json()['_token']??null;
        if(!Csrf::verify(is_string($token)?$token:null)) { Response::json(['error'=>'csrf_invalid'],419)->send(); exit; }
    }
}
