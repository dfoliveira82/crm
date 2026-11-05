<?php
declare(strict_types=1);
namespace App\Models;
final class SessionUser {
    public function __construct(public readonly string $code,public readonly string $name,public readonly string $email='',public readonly string $role='') {}
    public static function fromArray(array $data): self { return new self((string)($data['code']??''),(string)($data['name']??'Usuário'),(string)($data['email']??''),(string)($data['role']??'')); }
    public function initials(): string { $parts=preg_split('/\s+/',trim($this->name)) ?: []; return strtoupper(substr($parts[0]??'U',0,1).substr($parts[count($parts)-1]??'',0,1)); }
}
