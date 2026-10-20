<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Demo\CreateDemoData;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class CreateDemo extends Command
{
    protected $signature = 'costoreal:demo {email : Correo de la única cuenta de una instalación vacía}';

    protected $description = 'Cargar una importación y un viaje de compras ficticios en una instalación vacía, sin crear contraseñas';

    public function handle(CreateDemoData $demo): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('Crea primero tu cuenta con costoreal:create-user. La demo no crea credenciales.');

            return self::FAILURE;
        }
        try {
            $created = $demo->execute($user);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        }
        $this->info('Demostración ficticia creada: 1 empresa y 2 compras.');
        $this->line('Viaje de compras a Brasil: compra 1.386.000 CLP · gastos 677.000 CLP · costo 2.063.000 CLP. Abre /trips/'.$created['trip']->id);
        $this->line('Importación de accesorios: compra 1.485.000 CLP · gastos 385.000 CLP · costo 1.870.000 CLP. Abre /trips/'.$created['importation']->id);
        $this->line('Ambas quedan en curso: puedes revisarlas y cerrarlas desde la app.');

        return self::SUCCESS;
    }
}
