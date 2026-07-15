<?php

namespace Barua;

class Crypto
{
    private const CIPHER = 'aes-256-gcm';

    public static function encrypt(string $plaintext): string
    {
        $key = self::key();
        $iv = random_bytes(openssl_cipher_iv_length(self::CIPHER));
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);

        return base64_encode($iv) . ':' . base64_encode($tag) . ':' . base64_encode($ciphertext);
    }

    public static function decrypt(string $encoded): string
    {
        [$ivB64, $tagB64, $cipherB64] = explode(':', $encoded, 3);
        $iv = base64_decode($ivB64);
        $tag = base64_decode($tagB64);
        $ciphertext = base64_decode($cipherB64);

        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($plaintext === false) {
            throw new \RuntimeException('Failed to decrypt value — wrong app_key or corrupted data.');
        }
        return $plaintext;
    }

    private static function key(): string
    {
        $key = base64_decode(Config::get('app_key'));
        if (strlen($key) !== 32) {
            throw new \RuntimeException('app_key must decode to exactly 32 bytes for AES-256.');
        }
        return $key;
    }
}
