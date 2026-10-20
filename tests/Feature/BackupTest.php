<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Installation;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Storage;
use PDO;
use Tests\TestCase;
use ZipArchive;

class BackupTest extends TestCase
{
    // SQLite cannot copy itself (VACUUM INTO) inside the transaction RefreshDatabase opens.
    use DatabaseMigrations;

    public function test_the_owner_downloads_the_database_documents_and_restore_instructions(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('documents/comprobante.pdf', '%PDF-1.4 comprobante de prueba');
        $owner = User::factory()->create(['email' => 'duena@example.test']);
        User::factory()->create();

        $response = $this->actingAs($owner)->get('/profile/respaldo')->assertOk()->assertDownload();
        $this->assertMatchesRegularExpression('/costoreal-respaldo-\d{4}-\d{2}-\d{2}-\d{6}\.zip/', $response->headers->get('content-disposition'));
        $zipPath = $response->getFile()->getPathname();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath));
        $this->assertSame('%PDF-1.4 comprobante de prueba', $zip->getFromName('documents/comprobante.pdf'));
        $this->assertStringContainsString('Sube base-de-datos.sqlite a costoreal/database', $zip->getFromName('LEEME-RESPALDO.txt'));
        $database = tempnam(sys_get_temp_dir(), 'respaldo');
        file_put_contents($database, $zip->getFromName('base-de-datos.sqlite'));
        $zip->close();
        unlink($zipPath);
        $copy = new PDO('sqlite:'.$database);
        $this->assertSame(['duena@example.test'], $copy->query('select email from users order by id limit 1')->fetchAll(PDO::FETCH_COLUMN));
        $copy = null;
        unlink($database);
        $this->assertNotNull(Installation::setting('last_backup_at'));
    }

    public function test_only_the_owner_can_download_a_backup(): void
    {
        User::factory()->create();
        $this->actingAs(User::factory()->create())->get('/profile/respaldo')->assertForbidden();
        $this->assertNull(Installation::setting('last_backup_at'));
    }
}
