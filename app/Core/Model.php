<?php
declare(strict_types=1);
namespace App\Core;
use PDO;
abstract class Model {
    protected PDO $db;
    protected array $map;
    public function __construct() { $this->map=require dirname(__DIR__).'/Config/tables.php'; }
    protected function db(): PDO { return $this->db ??= Database::connection(); }
    protected function entity(string $key): ?array {
        $entity=$this->map[$key]??null;
        if(!is_array($entity) || empty($entity['table']) || empty($entity['columns'])) return null;
        $this->identifier((string)$entity['table']);
        foreach($entity['columns'] as $column) if($column!==null && $column!=='') $this->identifier((string)$column);
        return $entity;
    }
    protected function identifier(string $name): string {
        if(!preg_match('/^[A-Za-z0-9_.]+$/',$name)) throw new \InvalidArgumentException('Identificador SQL inválido.');
        return implode('.',array_map(fn($p)=>'['.$p.']',explode('.',$name)));
    }
    protected function select(string $key,array $fields,array $where=[],string $order='',int $limit=100): array {
        $e=$this->entity($key); if(!$e) return [];
        $select=[];
        foreach($fields as $alias=>$logical) {
            $col=$e['columns'][$logical]??null; if(!$col) continue;
            $select[]=$this->identifier($col).' AS '.$this->identifier($alias);
        }
        if(!$select) return [];
        $sql='SELECT TOP '.max(1,min(500,$limit)).' '.implode(',',$select).' FROM '.$this->identifier($e['table']);
        $clauses=[];$params=[];
        foreach($where as $logical=>$value) {
            $col=$e['columns'][$logical]??null; if(!$col) continue;
            $clauses[]=$this->identifier($col).' = ?'; $params[]=$value;
        }
        if($clauses) $sql.=' WHERE '.implode(' AND ',$clauses);
        if($order!=='') $sql.=' ORDER BY '.$this->identifier($e['columns'][$order]??'');
        $stmt=$this->db()->prepare($sql); $stmt->execute($params); return $stmt->fetchAll();
    }
    protected function scalar(string $key,string $sqlExpression,array $where=[]): mixed {
        $e=$this->entity($key); if(!$e) return null;
        if(!preg_match('/^[A-Za-z0-9_().,* +-]+$/',$sqlExpression)) throw new \InvalidArgumentException('Expressão inválida.');
        $sql='SELECT '.$sqlExpression.' FROM '.$this->identifier($e['table']);
        $clauses=[];$params=[];
        foreach($where as $logical=>$value) { $col=$e['columns'][$logical]??null; if(!$col) continue; $clauses[]=$this->identifier($col).' = ?'; $params[]=$value; }
        if($clauses) $sql.=' WHERE '.implode(' AND ',$clauses);
        $st=$this->db()->prepare($sql); $st->execute($params); return $st->fetchColumn();
    }
}
