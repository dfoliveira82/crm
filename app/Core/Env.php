<?php
declare(strict_types=1);
namespace App\Core;
final class Env {
    private static array $values = [];
    public static function load(string $file): void {
        if (!is_file($file)) return;
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
            [$key, $value] = explode('=', $line, 2);
            $value = trim(trim($value), "\"'");
            self::$values[trim($key)] = $value;
        }
    }
    public static function get(string $key, mixed $default = null): mixed {
        return self::$values[$key] ?? $_ENV[$key] ?? getenv($key) ?: $default;
    }
    public static function bool(string $key, bool $default = false): bool {
        $v = self::get($key, $default ? 'true' : 'false');
        return filter_var($v, FILTER_VALIDATE_BOOLEAN);
    }
}
