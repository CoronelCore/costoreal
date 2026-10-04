<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ImportControlTest extends TestCase
{
    use RefreshDatabase;

    private function trip(User $u, string $name = 'Importación', string $date = '2026-10-03'): Trip
    {
        $t = Trip::create(['user_id' => $u->id, 'nombre' => $name, 'fecha_inicio' => $date, 'moneda_base' => 'CLP', 'moneda_trabajo' => 'USD', 'tipo_cambio' => '900', 'estado' => 'activo']);
        $t->products()->create(['nombre' => 'Producto', 'moneda' => 'CLP', 'cantidad' => 1, 'costo_unitario' => '100', 'subtotal_clp' => '100', 'costo_final_unitario_clp' => '100', 'costo_final_total_clp' => '100']);

        return $t;
    }

    public function test_paginated_totals_cover_entire_authorized_filter(): void
    {
        $u = User::factory()->create();
        for ($i = 0; $i < 25; $i++) {
            $this->trip($u, 'Filtrado '.$i);
        }
        $this->trip($u, 'Otro nombre');
        $this->trip(User::factory()->create(), 'Filtrado ajeno');
        $this->actingAs($u)->get('/trips?q=Filtrado')->assertInertia(fn (Assert $p) => $p->has('trips.data', 20)->where('trips.total', 25)->where('metrics.total', '2500')->where('metrics.count', 25)->where('filters.q', 'Filtrado')->etc());
        $this->get('/trips?q=Filtrado&page=2')->assertInertia(fn (Assert $p) => $p->has('trips.data', 5)->where('metrics.total', '2500')->etc());
    }

    public function test_dates_preserve_calendar_day_and_to_filter_works_alone(): void
    {
        $u = User::factory()->create();
        $this->trip($u, 'Anterior', '2026-10-01');
        $this->trip($u, 'Posterior', '2026-10-04');
        $this->actingAs($u)->get('/trips?to=2026-10-03')->assertSessionHasNoErrors()->assertInertia(fn (Assert $p) => $p->where('trips.total', 1)->where('trips.data.0.fecha_inicio', '2026-10-01')->etc());
    }

    public function test_control_query_count_stays_constant_at_two_hundred_imports(): void
    {
        $u = User::factory()->create();
        $this->trip($u);
        $this->actingAs($u);
        DB::enableQueryLog();
        $this->get('/trips')->assertOk();
        $one = count(DB::getQueryLog());
        DB::disableQueryLog();
        for ($i = 1; $i < 200; $i++) {
            $this->trip($u);
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/trips')->assertOk();
        $large = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThanOrEqual($one + 1, $large);
        fwrite(STDOUT, "\nConsultas control: 1 = $one; 200 = $large\n");
    }
}
