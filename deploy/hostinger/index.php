<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// This file lives in public_html (or in a subdomain folder inside it). The private costoreal folder sits
// outside public_html: next to it, or a few levels up for a subdomain. A copy inside public_html is ignored,
// because anything there can be downloaded from the internet.
$basePath = null;
for ($dir = dirname(__DIR__), $level = 0; $level < 4 && $basePath === null; $dir = dirname($dir), $level++) {
    $candidate = $dir.'/costoreal';
    if (is_file($candidate.'/bootstrap/app.php') && ! preg_match('#[/\\\\]public_html([/\\\\]|$)#', $candidate)) {
        $basePath = $candidate;
    }
}
if ($basePath === null) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        .'<title>CostoReal · Falta la carpeta costoreal</title><body style="font:16px/1.5 system-ui,sans-serif;max-width:640px;margin:48px auto;padding:0 20px;color:#17282f">'
        .'<h1>Falta la carpeta costoreal</h1><p>Esta página es la entrada de CostoReal, pero no encuentro la carpeta privada <strong>costoreal</strong>.</p>'
        .'<p>Debe quedar <strong>al lado de public_html</strong>, no dentro: así nadie puede descargar tu configuración ni tus datos. '
        .'En el Administrador de archivos de hPanel, mueve la carpeta costoreal una carpeta más arriba y recarga esta página.</p></body></html>';
    exit;
}

require_once $basePath.'/bootstrap/first-run.php';
CostoRealFirstRun::run($basePath);

if (file_exists($maintenance = $basePath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $basePath.'/vendor/autoload.php';
$app = require $basePath.'/bootstrap/app.php';
$app->usePublicPath(__DIR__);
$app->handleRequest(Request::capture());
