<?php
declare(strict_types=1);
namespace App\Middleware;
use App\Core\{Request,Response,Session};
final class AuthMiddleware {
    public function __invoke(Request $request): void {
        if(!Session::get('user')) {
            if(str_starts_with($request->path(),'/api/')) { Response::json(['error'=>'unauthorized'],401)->send(); exit; }
            Response::redirect('/login')->send(); exit;
        }
    }
}
