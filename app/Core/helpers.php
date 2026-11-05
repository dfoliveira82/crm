<?php
declare(strict_types=1);
use App\Core\Csrf;
use App\Core\Env;
use App\Core\Session;
function e(mixed $value): string { return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function url(string $path=''): string { return rtrim((string)Env::get('APP_URL',''),'/').'/'.ltrim($path,'/'); }
function asset(string $path): string { return url(''.'').'/'.ltrim($path,'/'); }
function money(mixed $value, string $currency='R$'): string { return $value===null ? '—' : $currency.' '.number_format((float)$value,2,',','.'); }
function old(string $key,mixed $default=''): mixed { return $_SESSION['_old'][$key]??$default; }
function csrf_field(): string { return '<input type="hidden" name="_token" value="'.e(Csrf::token()).'">'; }
function user_data(): array { $u=Session::get('user',[]); return is_array($u)?$u:[]; }
