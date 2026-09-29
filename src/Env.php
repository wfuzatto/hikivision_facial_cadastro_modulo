<?php
declare(strict_types=1);

namespace App;

final class Env
{
    private static array $values = [];

    public static function load(string $path): void
    {
        if (!is_file($path)) return;
        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (strlen($value) >= 2 && (($value[0] === '"' && $value[-1] === '"') || ($value[0] === "'" && $value[-1] === "'"))) {
                $value = substr($value, 1, -1);
            }
            self::$values[$key] = $value;
            if (getenv($key) === false) putenv($key . '=' . $value);
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = self::$values[$key] ?? getenv($key);
        return ($value === false || $value === null || $value === '') ? $default : (string)$value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        return $value === null ? $default : filter_var($value, FILTER_VALIDATE_BOOL);
    }

    public static function int(string $key, int $default): int
    {
        $value = self::get($key);
        return $value === null ? $default : (int)$value;
    }

    public static function required(string $key): string
    {
        $value = self::get($key);
        if ($value === null) throw new \RuntimeException("Variável de ambiente obrigatória ausente: {$key}");
        return $value;
    }
}
