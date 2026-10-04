<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Trip;
use App\Models\User;
use App\Modules\Companies\ReadCompany;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CompanyReadTest extends TestCase
{
    use RefreshDatabase;

    private function company(User $user): Company
    {
        return Company::create(['user_id' => $user->id, 'nombre' => 'Empresa de prueba', 'color' => '#2f615b', 'icono' => 'BuildingOfficeIcon', 'activa' => true]);
    }

    private function trip(User $user, Company $company, string $purchase = '100.25', string $expense = '20.10'): Trip
    {
        $trip = Trip::create(['user_id' => $user->id, 'company_id' => $company->id, 'nombre' => 'Importación de prueba', 'moneda_base' => 'CLP', 'moneda_trabajo' => 'USD', 'tipo_cambio' => '900', 'estado' => 'activo']);
        $trip->products()->create(['nombre' => 'Producto', 'moneda' => 'CLP', 'cantidad' => 1, 'costo_unitario' => $purchase, 'subtotal_clp' => $purchase, 'costo_final_total_clp' => $purchase, 'costo_final_unitario_clp' => $purchase]);
        $trip->expenses()->create(['concepto' => 'Flete', 'moneda' => 'CLP', 'monto' => $expense, 'monto_clp' => $expense]);

        return $trip;
    }

    public function test_company_metrics_reconcile_authorized_rows_and_exclude_legacy_foreign_links_and_trash(): void
    {
        $user = User::factory()->create();
        $company = $this->company($user);
        $a = $this->trip($user, $company);
        $b = $this->trip($user, $company, '200.25', '30.10');
        $this->trip(User::factory()->create(), $company, '9000')->update(['nombre' => 'Nombre privado ajeno']);
        $this->trip($user, $company, '8000')->delete();
        $before = DB::table('products')->get()->toJson();
        $page = $this->actingAs($user)->get("/companies/{$company->id}")->assertOk()->viewData('page')['props'];
        $this->assertTrue(BigDecimal::of($page['metrics']['total'])->isEqualTo('350.70'));
        $this->assertSame('175.35', $page['metrics']['average']);
        $this->assertSame(2, $page['metrics']['count']);
        $this->assertSame(2, $page['trips']['total']);
        $this->assertSame($company->id, $page['company']['userCompanies'][0]['id']);
        $edit = $this->get("/companies/{$company->id}/edit")->assertOk()->viewData('page')['props'];
        $this->assertSame($company->id, $edit['company']['userCompanies'][0]['id']);
        $this->assertSame($company->id, $edit['companyRecord']['id']);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], array_column($page['trips']['data'], 'id'));
        $this->assertArrayNotHasKey('user_id', $page['companyRecord']);
        $this->assertArrayNotHasKey('user_id', $page['trips']['data'][0]);
        $this->assertSame($before, DB::table('products')->get()->toJson());
        $this->actingAs(User::factory()->create())->get("/companies/{$company->id}")->assertForbidden();
    }

    public function test_summary_covers_full_set_when_paged_and_explicit_company_is_independent_of_selector(): void
    {
        $user = User::factory()->create();
        $company = $this->company($user);
        $other = $this->company($user);
        for ($i = 0; $i < 21; $i++) {
            $this->trip($user, $company, '100', '20');
        }
        $page = $this->actingAs($user)->withSession(['active_company_id' => $other->id])->get("/companies/{$company->id}?page=2")->assertOk()->viewData('page')['props'];
        $this->assertSame('2520', $page['metrics']['total']);
        $this->assertSame(21, $page['metrics']['count']);
        $this->assertCount(1, $page['trips']['data']);
        $this->assertSame(2, $page['trips']['current_page']);
        $index = $this->get('/companies')->assertOk()->viewData('page')['props']['companies'];
        $own = collect($index)->firstWhere('id', $company->id);
        $this->assertSame(21, $own['trips_count']);
        $this->assertFalse($own['can_delete']);
    }

    public function test_empty_company_and_bounded_read_queries_at_1_and_200_importations(): void
    {
        $user = User::factory()->create();
        $company = $this->company($user);
        $this->actingAs($user);
        $empty = $this->get("/companies/{$company->id}")->assertOk()->viewData('page')['props'];
        $this->assertSame('0', $empty['metrics']['total']);
        $this->assertSame('0', $empty['metrics']['average']);
        $this->assertTrue($empty['companyRecord']['can_delete']);
        $counts = [];
        for ($i = 1; $i <= 200; $i++) {
            $this->trip($user, $company, '100', '20');
            if (in_array($i, [1, 200])) {
                DB::enableQueryLog();
                DB::flushQueryLog();
                $result = app(ReadCompany::class)->show($user, $company);
                $counts[] = count(DB::getQueryLog());
                DB::disableQueryLog();
                $this->assertSame((string) ($i * 120), $result['metrics']['total']);
            }
        }
        $this->assertSame($counts[0], $counts[1]);
        $this->assertLessThanOrEqual(4, $counts[1]);
    }
}
