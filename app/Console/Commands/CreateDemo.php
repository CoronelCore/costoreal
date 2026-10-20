<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Demo\CreateDemoData;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class CreateDemo extends Command
{
    protected $signature = 'costoreal:demo {email : Correo de la única cuenta de una instalación vacía}';

    protected $description = 'Cargar una importación ficticia en una instalación vacía, sin crear contraseñas';

    public function handle(CreateDemoData $demo): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('Crea primero tu cuenta con costoreal:create-user. La demo no crea credenciales.');

            return self::FAILURE;
        }
        try {
            $trip = $demo->execute($user);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        }
        $this->info('Demostración ficticia creada: 1 empresa, 3 productos y 6 gastos.');
        $this->line('Compra: 1.485.000 CLP · gastos: 385.000 CLP · costo: 1.870.000 CLP.');
        $this->line('Abre /trips/'.$trip->id.' con tu cuenta. Puedes revisar y cerrar la importación.');

        return self::SUCCESS;
    }
}
