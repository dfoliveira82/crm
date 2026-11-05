<?php
declare(strict_types=1);
namespace App\Core;
final class Request {
    public function method(): string { return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'); }
    public function path(): string {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
        if ($base !== '' && $base !== '.' && str_starts_with($path, $base)) $path = substr($path, strlen($base)) ?: '/';
        return '/' . trim($path, '/');
    }
    public function input(string $key, mixed $default=null): mixed { return $_POST[$key] ?? $default; }
    public function query(string $key, mixed $default=null): mixed { return $_GET[$key] ?? $default; }
    public function json(): array {
        $data = json_decode((string)file_get_contents('php://input'), true);
        return is_array($data) ? $data : [];
    }
    public function header(string $name): ?string {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$key] ?? null;
    }
}
