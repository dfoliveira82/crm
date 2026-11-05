<?php
declare(strict_types=1);
namespace App\Models;
use App\Core\Model;
final class Suggestion extends Model {
    public function active(string $owner): array { try { return $this->select('suggestions',['id'=>'id','text'=>'text','priority'=>'priority'],['owner'=>$owner], '', 20); } catch(\Throwable $e) { error_log('[Suggestion] '.$e->getMessage()); return []; } }
    public function dismiss(string $id,string $owner): bool { $e=$this->entity('suggestions'); if(!$e || empty($e['columns']['status'])) return false; $sql='UPDATE '.$this->identifier($e['table']).' SET '.$this->identifier($e['columns']['status']).' = ? WHERE '.$this->identifier($e['columns']['id']).' = ?'; $params=['DISMISSED',$id]; if(!empty($e['columns']['owner'])) { $sql.=' AND '.$this->identifier($e['columns']['owner']).' = ?'; $params[]=$owner; } $st=$this->db()->prepare($sql); return $st->execute($params); }
}
