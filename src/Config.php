<?php

namespace Barua;

class Config
{
    private static ?array $data = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        if (self::$data === null) {
            $path = __DIR__ . '/../config/config.php';
            if (!file_exists($path)) {
                throw new \RuntimeException('config/config.php missing — copy config/config.php.example and fill it in.');
            }
            self::$data = require $path;
        }

        $segments = explode('.', $key);
        $value = self::$data;
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }
}
