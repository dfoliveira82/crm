<?php
declare(strict_types=1);
namespace App\Core;
final class Response {
    public function __construct(private string $body='', private int $status=200, private array $headers=['Content-Type'=>'text/html; charset=utf-8']) {}
    public static function html(string $body, int $status=200): self { return new self($body,$status); }
    public static function json(array $data, int $status=200): self { return new self((string)json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$status,['Content-Type'=>'application/json; charset=utf-8']); }
    public static function redirect(string $url, int $status=302): self { return new self('', $status, ['Location'=>$url]); }
    public function send(): void { http_response_code($this->status); foreach($this->headers as $k=>$v) header("$k: $v"); echo $this->body; }
}
