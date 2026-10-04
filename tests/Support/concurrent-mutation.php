<?php

use App\Models\Trip;
use App\Modules\Importations\Actions\MutateTrip;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
[$script, $database, $tripId, $userId, $name, $marker] = $argv;
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $database, 'database.connections.sqlite.busy_timeout' => 1000]);
DB::purge('sqlite');
Auth::loginUsingId($userId);
try {
    app(MutateTrip::class)->execute(Trip::findOrFail($tripId), function ($current) use ($name, $marker) {
        if ($name === 'Worker A') {
            file_put_contents($marker, 'claimed');
            usleep(250000);
        }
        $current->update(['nombre' => $name]);
    }, 'concurrent_test', expectedRevision: 1);
    echo 'applied';
} catch (ValidationException $e) {
    echo isset($e->errors()['conflict']) ? 'conflict' : 'unexpected-validation';
}
