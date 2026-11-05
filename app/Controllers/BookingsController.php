<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Controller,Request,Response,Session};
use App\Models\Booking;
final class BookingsController extends Controller {
    public function index(Request $r): Response { $range=(string)$r->query('range','weeks'); if(!in_array($range,['weeks','months'],true)) return $this->json(['error'=>'invalid_range'],422); return $this->json(['items'=>(new Booking())->series((string)(Session::get('user')['code']??''),$range)]); }
}
