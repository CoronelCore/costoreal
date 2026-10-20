<?php

namespace Tests\Unit;

use App\Support\EnvFile;
use CostoRealFirstRun;
use PHPUnit\Framework\TestCase;

class FirstRunTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2).'/bootstrap/first-run.php';
        $this->base = sys_get_temp_dir().'/costoreal-first-run-'.bin2hex(random_bytes(4));
        foreach (['storage/framework/sessions', 'storage/framework/views', 'storage/framework/cache', 'storage/logs', 'bootstrap/cache', 'database'] as $folder) {
            mkdir($this->base.'/'.$folder, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->base, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->base);
    }

    public function test_an_old_php_and_missing_extensions_are_explained_with_the_hpanel_fix(): void
    {
        $problems = CostoRealFirstRun::problems($this->base, '8.3.12', ['Core', 'pdo', 'mbstring']);
        $this->assertSame('Tu hosting usa PHP 8.3.12 y CostoReal necesita PHP 8.5.', $problems[0]['title']);
        $this->assertStringContainsString('Configuración de PHP → elige PHP 8.5', $problems[0]['fix']);
        $this->assertStringContainsString('gd', $problems[1]['title']);
        $this->assertStringContainsString('pdo_sqlite', $problems[2]['title']);
        $this->assertSame([], CostoRealFirstRun::problems($this->base, '8.5.1', [...CostoRealFirstRun::EXTENSIONS, 'pdo_sqlite']));
    }

    public function test_the_first_visit_writes_a_private_production_configuration_and_a_code(): void
    {
        $server = ['HTTPS' => 'on', 'HTTP_HOST' => 'costoreal.example.cl', 'SCRIPT_NAME' => '/index.php'];
        $this->assertSame([], CostoRealFirstRun::createEnvironment($this->base, $server, true));
        $env = file_get_contents($this->base.'/.env');
        $this->assertMatchesRegularExpression('/^APP_KEY=base64:[A-Za-z0-9+\/=]{44}$/m', $env);
        foreach (['APP_ENV=production', 'APP_DEBUG=false', 'APP_URL="https://costoreal.example.cl"', 'DB_CONNECTION=sqlite',
            'SESSION_SECURE_COOKIE=true', 'SESSION_DRIVER=file', 'APP_LOCALE=es'] as $line) {
            $this->assertStringContainsString($line, $env);
        }
        $this->assertFileExists($this->base.'/database/database.sqlite');
        $this->assertMatchesRegularExpression('/^[2-9A-HJ-NP-Z]{4}-[2-9A-HJ-NP-Z]{4}$/', CostoRealFirstRun::readCode($this->base));
    }

    public function test_without_sqlite_the_configuration_waits_for_mysql_details(): void
    {
        CostoRealFirstRun::createEnvironment($this->base, ['HTTP_HOST' => 'ejemplo.cl', 'SCRIPT_NAME' => '/sub/index.php'], false);
        $env = file_get_contents($this->base.'/.env');
        $this->assertStringContainsString('DB_CONNECTION=mysql', $env);
        $this->assertStringContainsString('APP_URL="http://ejemplo.cl/sub"', $env);
        $this->assertStringContainsString('SESSION_SECURE_COOKIE=false', $env);
        $this->assertFileDoesNotExist($this->base.'/database/database.sqlite');
    }

    public function test_env_values_are_replaced_or_added_and_quoted_safely(): void
    {
        file_put_contents($this->base.'/.env', "APP_NAME=CostoReal\nDB_CONNECTION=sqlite\n");
        EnvFile::set($this->base.'/.env', ['DB_CONNECTION' => 'mysql', 'DB_PASSWORD' => 'cl@ve "dura" $1\\x']);
        $this->assertSame("APP_NAME=CostoReal\nDB_CONNECTION=mysql\nDB_PASSWORD=\"cl@ve \\\"dura\\\" \\\$1\\\\x\"\n", file_get_contents($this->base.'/.env'));
    }
}
