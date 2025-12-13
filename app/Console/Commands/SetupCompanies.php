<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Console\Command;

class SetupCompanies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'companies:setup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Configuración inicial del sistema multi-empresa. Crea empresa por defecto y asigna importaciones existentes.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🏢 Iniciando configuración del sistema multi-empresa...');
        $this->newLine();

        $users = User::all();
        $totalUsers = $users->count();
        $companiesCreated = 0;
        $tripsAssigned = 0;

        foreach ($users as $user) {
            $this->info("Procesando usuario: {$user->name} ({$user->email})");

            // Verificar si el usuario ya tiene empresas
            if ($user->companies()->count() > 0) {
                $this->line("  ✓ Usuario ya tiene " . $user->companies()->count() . " empresa(s)");
            } else {
                // Crear empresa por defecto
                $company = Company::create([
                    'user_id' => $user->id,
                    'nombre' => 'Mi Empresa',
                    'rut' => null,
                    'color' => '#3B82F6',
                    'icono' => 'building',
                    'descripcion' => 'Empresa creada automáticamente por el sistema',
                    'activa' => true,
                ]);

                $companiesCreated++;
                $this->line("  ✓ Empresa por defecto creada: Mi Empresa");
            }

            // Asignar importaciones sin empresa
            $tripsWithoutCompany = $user->trips()->whereNull('company_id')->get();

            if ($tripsWithoutCompany->count() > 0) {
                // Obtener la primera empresa del usuario (o la que acabamos de crear)
                $defaultCompany = $user->companies()->first();

                foreach ($tripsWithoutCompany as $trip) {
                    $trip->update(['company_id' => $defaultCompany->id]);
                    $tripsAssigned++;
                }

                $this->line("  ✓ {$tripsWithoutCompany->count()} importación(es) asignada(s) a {$defaultCompany->nombre}");
            } else {
                $this->line("  · No hay importaciones sin asignar");
            }

            $this->newLine();
        }

        // Resumen
        $this->newLine();
        $this->info('✅ Configuración completada!');
        $this->newLine();
        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Usuarios procesados', $totalUsers],
                ['Empresas creadas', $companiesCreated],
                ['Importaciones asignadas', $tripsAssigned],
            ]
        );

        $this->newLine();
        $this->line('Ahora puedes:');
        $this->line('  1. Acceder a la aplicación');
        $this->line('  2. Ir a "Gestionar empresas"');
        $this->line('  3. Renombrar "Mi Empresa" y crear tus empresas reales');
        $this->line('  4. Reasignar las importaciones a las empresas correspondientes');

        return Command::SUCCESS;
    }
}
