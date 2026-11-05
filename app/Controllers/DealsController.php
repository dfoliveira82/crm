<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Controller,Request,Response,Session};
use App\Models\Deal;
final class DealsController extends Controller {
    public function pipeline(Request $r): Response { return $this->render('pages/placeholder',['title'=>'Pipeline','description'=>'Visão dos negócios por estágio.','items'=>(new Deal())->pipeline((string)(Session::get('user')['code']??''))]); }
    public function attention(Request $r): Response { return $this->json(['items'=>(new Deal())->attention((string)(Session::get('user')['code']??''))]); }
}
