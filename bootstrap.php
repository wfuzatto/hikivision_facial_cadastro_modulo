<?php
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) return;
    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) require $file;
});

App\Env::load(__DIR__ . '/.env');
date_default_timezone_set(App\Env::get('APP_TIMEZONE', 'America/Sao_Paulo') ?? 'America/Sao_Paulo');
