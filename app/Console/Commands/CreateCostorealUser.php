<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class CreateCostorealUser extends Command
{
    protected $signature = 'costoreal:create-user {--email=} {--name=} {--generate : Generar una contraseña aleatoria y mostrarla una sola vez}';

    protected $description = 'Crear un usuario personal sin credenciales predeterminadas';

    public function handle(): int
    {
        $email = $this->option('email') ?: $this->ask('Correo electrónico');
        $name = $this->option('name') ?: $this->ask('Nombre');
        $password = $this->option('generate') ? Str::password(24) : $this->secret('Contraseña (mínimo 12 caracteres)');
        $validation = Validator::make(compact('email', 'name', 'password'), ['email' => 'required|email|max:255|unique:users,email', 'name' => 'required|string|max:255', 'password' => ['required', Password::min(12)->mixedCase()->numbers()->symbols()]]);
        if ($validation->fails()) {
            foreach ($validation->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }
        User::create(['email' => $email, 'name' => $name, 'password' => Hash::make($password)]);
        $this->info('Usuario creado: '.$email);
        if ($this->option('generate')) {
            $this->line('Contraseña generada (guárdala ahora): '.$password);
        }

        return self::SUCCESS;
    }
}
