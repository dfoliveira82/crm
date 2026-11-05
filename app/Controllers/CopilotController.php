<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Controller,Request,Response,Session};
use App\Models\Suggestion;
use App\Services\CopilotService;
final class CopilotController extends Controller {
    public function suggestions(Request $r): Response { return $this->json(['items'=>(new Suggestion())->active((string)(Session::get('user')['code']??''))]); }
    public function dismiss(Request $r,array $p): Response {
        $id=(string)($p['id']??''); $owner=(string)(Session::get('user')['code']??'');
        try { $done=(new Suggestion())->dismiss($id,$owner); } catch(\Throwable $e) { $done=false; }
        if(!$done) { $dismissed=Session::get('dismissed_suggestions',[]); $dismissed[]=$id; Session::put('dismissed_suggestions',array_unique($dismissed)); }
        return $this->json(['ok'=>true]);
    }
    public function action(Request $r,array $p): Response { return $this->json(['ok'=>true,'message'=>'Done']); }
    public function chat(Request $r): Response { $message=trim((string)($r->json()['message']??'')); if($message==='') return $this->json(['error'=>'message_required'],422); return $this->json(['reply'=>(new CopilotService())->reply($message)]); }
}
