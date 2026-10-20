<?php

/**
 * Runs before Composer and Laravel on every web request.
 *
 * On a shared hosting without a terminal this is what makes CostoReal installable from the browser:
 * it explains in Spanish what the hosting is missing (PHP version, extensions, write permissions)
 * instead of a raw Composer error, and on the very first visit it writes the private configuration,
 * the SQLite file and the one-time installation code. Plain PHP that also parses on older versions.
 */
final class CostoRealFirstRun
{
    public const MINIMUM_PHP = '8.5.0';

    public const CODE_FILE = 'CODIGO-INSTALACION.txt';

    public const EXTENSIONS = ['ctype', 'dom', 'fileinfo', 'filter', 'gd', 'hash', 'iconv', 'json', 'libxml', 'mbstring',
        'openssl', 'pcre', 'pdo', 'session', 'simplexml', 'tokenizer', 'xml', 'xmlreader', 'xmlwriter', 'zip', 'zlib'];

    public static function run(string $base): void
    {
        $problems = self::problems($base, PHP_VERSION, get_loaded_extensions());
        if (! $problems && ! is_file($base.'/.env')) {
            $problems = self::createEnvironment($base, $_SERVER, extension_loaded('pdo_sqlite'));
        }
        if ($problems) {
            self::render($problems);
            exit;
        }
    }

    /** @return list<array{title: string, fix: string}> */
    public static function problems(string $base, string $phpVersion, array $extensions): array
    {
        $problems = [];
        if (version_compare($phpVersion, self::MINIMUM_PHP, '<')) {
            $problems[] = ['title' => 'Tu hosting usa PHP '.$phpVersion.' y CostoReal necesita PHP 8.5.',
                'fix' => 'En hPanel abre tu sitio → Configuración de PHP → elige PHP 8.5 → Actualizar. Espera uno o dos minutos y recarga esta página.'];
        }
        $loaded = array_map('strtolower', $extensions);
        $missing = array_values(array_diff(self::EXTENSIONS, $loaded));
        if ($missing) {
            $problems[] = ['title' => 'Faltan extensiones de PHP: '.implode(', ', $missing).'.',
                'fix' => 'En hPanel abre Configuración de PHP → pestaña Extensiones de PHP → actívalas → Guardar.'];
        }
        if (! in_array('pdo_sqlite', $loaded, true) && ! in_array('pdo_mysql', $loaded, true)) {
            $problems[] = ['title' => 'No hay cómo guardar los datos: falta la extensión pdo_sqlite (o pdo_mysql).',
                'fix' => 'En hPanel abre Configuración de PHP → pestaña Extensiones de PHP → activa pdo_sqlite → Guardar.'];
        }
        foreach (['storage', 'storage/framework/sessions', 'storage/framework/views', 'storage/framework/cache', 'storage/logs', 'bootstrap/cache', 'database'] as $folder) {
            if (! is_dir($base.'/'.$folder) || ! is_writable($base.'/'.$folder)) {
                $problems[] = ['title' => 'CostoReal no puede escribir en la carpeta costoreal/'.$folder.'.',
                    'fix' => 'En el Administrador de archivos haz clic derecho sobre esa carpeta → Permisos → 755, y aplícalo también a lo que contiene.'];
            }
        }

        return $problems;
    }

    /** First visit: private configuration with a fresh key, SQLite when available, and the installation code. */
    public static function createEnvironment(string $base, array $server, bool $sqlite): array
    {
        if (! is_writable($base)) {
            return [['title' => 'CostoReal no puede crear su configuración en la carpeta costoreal.',
                'fix' => 'En el Administrador de archivos haz clic derecho sobre la carpeta costoreal → Permisos → 755.']];
        }
        $https = (! empty($server['HTTPS']) && strtolower((string) $server['HTTPS']) !== 'off')
            || strtolower((string) ($server['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
        $prefix = rtrim(str_replace('\\', '/', dirname((string) ($server['SCRIPT_NAME'] ?? '/'))), '/.');
        $url = ($https ? 'https' : 'http').'://'.($server['HTTP_HOST'] ?? 'localhost').$prefix;
        $database = str_replace('\\', '/', $base).'/database/database.sqlite';
        if ($sqlite && ! is_file($database)) {
            touch($database);
        }
        $env = self::environment('base64:'.base64_encode(random_bytes(32)), $url, $https, $sqlite ? $database : null);
        $temporary = $base.'/.env.'.bin2hex(random_bytes(4));
        if (file_put_contents($temporary, $env, LOCK_EX) === false || ! rename($temporary, $base.'/.env')) {
            @unlink($temporary);

            return [['title' => 'No se pudo guardar la configuración de CostoReal.', 'fix' => 'Revisa los permisos de la carpeta costoreal (755) y recarga.']];
        }
        @chmod($base.'/.env', 0600);
        self::writeCode($base);

        return [];
    }

    public static function environment(string $key, string $url, bool $https, ?string $sqliteFile): string
    {
        $database = $sqliteFile !== null
            ? "DB_CONNECTION=sqlite\nDB_DATABASE=\"{$sqliteFile}\""
            : "DB_CONNECTION=mysql\nDB_HOST=localhost\nDB_PORT=3306\nDB_DATABASE=\nDB_USERNAME=\nDB_PASSWORD=";

        return implode("\n", ['# Configuración privada de CostoReal. No la compartas ni la subas a internet.',
            'APP_NAME=CostoReal', 'APP_ENV=production', "APP_KEY={$key}", 'APP_DEBUG=false', "APP_URL=\"{$url}\"",
            'APP_LOCALE=es', 'APP_FALLBACK_LOCALE=en', 'APP_FAKER_LOCALE=es_CL', 'APP_MAINTENANCE_DRIVER=file',
            'LOG_CHANNEL=stack', 'LOG_STACK=single', 'LOG_LEVEL=warning', 'COSTOREAL_PERFORMANCE_ENABLED=false',
            $database,
            'SESSION_DRIVER=file', 'SESSION_LIFETIME=120', 'SESSION_COOKIE=costoreal_session', 'SESSION_SECURE_COOKIE='.($https ? 'true' : 'false'),
            'CACHE_STORE=file', 'QUEUE_CONNECTION=sync', 'FILESYSTEM_DISK=local', 'MAIL_MAILER=log',
            'MAIL_FROM_ADDRESS="no-responder@'.(parse_url($url, PHP_URL_HOST) ?: 'localhost').'"', 'MAIL_FROM_NAME=CostoReal', '']);
    }

    /** A one-time code readable only from the hosting's file manager, so nobody else can install first. */
    public static function writeCode(string $base): string
    {
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)].($i === 3 ? '-' : '');
        }
        file_put_contents($base.'/'.self::CODE_FILE, "Código para instalar CostoReal: {$code}\n\n"
            ."Escríbelo en la página de instalación de tu sitio. Este archivo se borra solo al terminar.\n", LOCK_EX);

        return $code;
    }

    public static function readCode(string $base): ?string
    {
        $text = @file_get_contents($base.'/'.self::CODE_FILE);

        return $text !== false && preg_match('/[2-9A-HJ-NP-Z]{4}-[2-9A-HJ-NP-Z]{4}/', $text, $match) ? $match[0] : null;
    }

    /** @param list<array{title: string, fix: string}> $problems */
    public static function render(array $problems): void
    {
        if (! headers_sent()) {
            http_response_code(503);
            header('Content-Type: text/html; charset=utf-8');
            header('Cache-Control: no-store');
        }
        $items = '';
        foreach ($problems as $problem) {
            $items .= '<li><strong>'.htmlspecialchars($problem['title'], ENT_QUOTES).'</strong><span>'.htmlspecialchars($problem['fix'], ENT_QUOTES).'</span></li>';
        }
        echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>CostoReal · Falta preparar el hosting</title><style>body{margin:0;background:#f4f6f6;color:#17282f;font:16px/1.5 system-ui,sans-serif}'
            .'main{max-width:640px;margin:48px auto;padding:0 20px}h1{font-size:26px;margin:8px 0 4px}p{color:#4f5f65}.brand{color:#0a5c4f;font-weight:700;letter-spacing:.08em;font-size:12px}'
            .'ul{list-style:none;padding:0;margin:24px 0}li{background:#fff;border:1px solid #d9e2e3;border-radius:10px;padding:16px 18px;margin-bottom:12px}'
            .'li span{display:block;margin-top:6px;color:#4f5f65}</style></head><body><main><div class="brand">COSTOREAL · INSTALACIÓN</div>'
            .'<h1>Falta preparar el hosting</h1><p>Arregla esto en tu panel y recarga la página. No se guardó ningún dato todavía.</p><ul>'
            .$items.'</ul></main></body></html>';
    }
}
