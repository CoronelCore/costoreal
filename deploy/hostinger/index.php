<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Keep the application outside public_html. This file lives in public_html.
$basePath = dirname(__DIR__).'/costoreal';

if (file_exists($maintenance = $basePath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $basePath.'/vendor/autoload.php';
$app = require $basePath.'/bootstrap/app.php';
$app->usePublicPath(__DIR__);
$app->handleRequest(Request::capture());
