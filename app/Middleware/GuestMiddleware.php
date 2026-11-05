<?php
declare(strict_types=1);
namespace App\Middleware;
use App\Core\{Request,Response,Session};
final class GuestMiddleware { public function __invoke(Request $request): void { if(Session::get('user')) { Response::redirect('/')->send(); exit; } } }
