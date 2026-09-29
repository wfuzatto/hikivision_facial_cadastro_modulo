<?php
declare(strict_types=1);

namespace App;

final class Crypto
{
    public static function encrypt(string $plaintext): string
    {
        $key = hash('sha256', Env::required('APP_KEY'), true);
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($ciphertext === false) throw new \RuntimeException('Falha ao criptografar credencial.');
        return base64_encode($iv . $tag . $ciphertext);
    }

    public static function decrypt(string $encoded): string
    {
        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) < 29) throw new \RuntimeException('Credencial criptografada inválida.');
        $key = hash('sha256', Env::required('APP_KEY'), true);
        $plaintext = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        if ($plaintext === false) throw new \RuntimeException('Falha ao descriptografar credencial.');
        return $plaintext;
    }
}
