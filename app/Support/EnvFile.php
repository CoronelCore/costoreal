<?php

namespace App\Support;

use RuntimeException;

/** Sets keys in the private .env file, keeping the other lines, written atomically. */
final class EnvFile
{
    public static function set(string $path, array $values): void
    {
        $lines = is_file($path) ? preg_split('/\R/', (string) file_get_contents($path)) : [];
        foreach ($values as $key => $value) {
            $line = $key.'='.self::quote((string) $value);
            $found = false;
            foreach ($lines as $index => $existing) {
                if (preg_match('/^\s*'.preg_quote($key, '/').'\s*=/', $existing)) {
                    $lines[$index] = $line;
                    $found = true;
                }
            }
            if (! $found) {
                array_splice($lines, max(0, count($lines) - (end($lines) === '' ? 1 : 0)), 0, [$line]);
            }
        }
        $temporary = $path.'.'.bin2hex(random_bytes(4));
        if (file_put_contents($temporary, implode("\n", $lines), LOCK_EX) === false || ! rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('No se pudo guardar la configuración privada.');
        }
        @chmod($path, 0600);
    }

    /** Plain values stay bare; anything else goes in double quotes with quotes and backslashes escaped. */
    public static function quote(string $value): string
    {
        return preg_match('/^[A-Za-z0-9_.:\/@+-]*$/', $value) ? $value : '"'.addcslashes($value, '"\\$').'"';
    }
}
