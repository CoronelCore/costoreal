<?php

/**
 * Builds the upload-ready ZIP for hostings without a terminal.
 *
 *   npm ci && npm run build
 *   php deploy/package.php v1.2.0 [dist]
 *
 * The ZIP holds two folders meant to be extracted next to each other: costoreal/ (private: code, Composer
 * dependencies already installed, storage) and public_html/ (the entry point, .htaccess and the compiled
 * design). Only files tracked by git are packed, so local configuration, databases and documents never
 * leave this machine. Composer runs inside the staging copy; set COSTOREAL_COMPOSER to override the command.
 */
$root = dirname(__DIR__);
$version = preg_replace('/[^0-9A-Za-z._-]/', '', $argv[1] ?? 'dev') ?: 'dev';
$dist = rtrim($argv[2] ?? $root.'/dist', '/\\');
$composer = getenv('COSTOREAL_COMPOSER') ?: 'composer';

function fail(string $message): never
{
    fwrite(STDERR, $message.PHP_EOL);
    exit(1);
}

function copyFile(string $from, string $to): void
{
    if (! is_dir(dirname($to)) && ! mkdir(dirname($to), 0775, true)) {
        fail("No se pudo crear la carpeta de {$to}");
    }
    if (! copy($from, $to)) {
        fail("No se pudo copiar {$from}");
    }
}

function removeTree(string $path): void
{
    if (! is_dir($path)) {
        return;
    }
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}

if (! is_file($root.'/public/build/manifest.json')) {
    fail('Falta public/build: ejecuta "npm ci" y "npm run build" antes de empaquetar.');
}
exec('git -C '.escapeshellarg($root).' ls-files -z', $output, $status);
if ($status !== 0) {
    fail('No se pudo leer la lista de archivos del repositorio (git ls-files).');
}
$tracked = array_filter(explode("\0", implode("\n", $output)));

$staging = $dist.'/staging-'.$version;
removeTree($staging);
$private = $staging.'/costoreal';
$public = $staging.'/public_html';
$privatePrefixes = ['app/', 'bootstrap/', 'config/', 'database/', 'lang/', 'resources/views/', 'routes/', 'storage/'];
$privateFiles = ['artisan', 'composer.json', 'composer.lock', '.env.example', 'LICENSE'];
foreach ($tracked as $file) {
    $file = str_replace('\\', '/', $file);
    if (in_array($file, $privateFiles, true) || array_filter($privatePrefixes, fn ($prefix) => str_starts_with($file, $prefix))) {
        copyFile($root.'/'.$file, $private.'/'.$file);
    } elseif (str_starts_with($file, 'public/') && $file !== 'public/index.php') {
        copyFile($root.'/'.$file, $public.'/'.substr($file, 7));
    }
}
$build = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/public/build', FilesystemIterator::SKIP_DOTS));
foreach ($build as $item) {
    copyFile($item->getPathname(), $public.'/build/'.str_replace('\\', '/', substr($item->getPathname(), strlen($root.'/public/build/'))));
}
copyFile($root.'/deploy/hostinger/index.php', $public.'/index.php');
// Second line of defence if the private folder ever ends up inside public_html.
file_put_contents($private.'/.htaccess', "Require all denied\nDeny from all\n");
file_put_contents($staging.'/LEEME-INSTALACION.txt', implode("\r\n", [
    'CostoReal '.$version.' · instalación sin terminal',
    '',
    '1. En hPanel, elige PHP 8.5 para tu sitio (Configuración de PHP).',
    '2. En el Administrador de archivos, sube este ZIP a la carpeta que contiene public_html y descomprímelo ahí.',
    '   Quedan dos carpetas: costoreal (privada) y public_html (lo público). costoreal no va dentro de public_html.',
    '3. Abre tu sitio en el navegador: la página de instalación te pide el código del archivo',
    '   costoreal/CODIGO-INSTALACION.txt, dónde guardar los datos y tu cuenta.',
    '',
    'Guía completa con imágenes: https://github.com/CoronelCore/costoreal#instalar-sin-terminal',
    '',
]));

$command = $composer.' install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress --working-dir='.escapeshellarg($private);
passthru($command, $status);
if ($status !== 0) {
    fail('Composer no pudo instalar las dependencias de producción.');
}

$zipPath = $dist.'/costoreal-'.$version.'.zip';
@unlink($zipPath);
$zip = new ZipArchive;
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fail("No se pudo crear {$zipPath}");
}
$items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($staging, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
foreach ($items as $item) {
    $name = str_replace('\\', '/', substr($item->getPathname(), strlen($staging) + 1));
    $item->isDir() ? $zip->addEmptyDir($name) : $zip->addFile($item->getPathname(), $name);
}
$count = $zip->numFiles;
$zip->close();
removeTree($staging);
printf("Listo: %s (%d archivos, %.1f MB)\n", $zipPath, $count, filesize($zipPath) / 1048576);
