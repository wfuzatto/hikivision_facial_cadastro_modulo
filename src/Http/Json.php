<?php
declare(strict_types=1);

namespace App\Http;

final class Json
{
    public static function body(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        if ($raw === '') return [];
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) throw new \InvalidArgumentException('JSON inválido.');
        return $decoded;
    }

    public static function send(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }
}
