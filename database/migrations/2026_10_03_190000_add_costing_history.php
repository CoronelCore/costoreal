<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $t) {
            $t->unsignedInteger('revision')->default(1);
            $t->string('tipo_cambio', 40)->default('950')->change();
            $t->softDeletes();
            $t->index(['user_id', 'company_id', 'estado']);
        });
        Schema::table('products', function (Blueprint $t) {
            $t->string('costo_final_unitario_clp', 40)->change();
            foreach (['subtotal_clp', 'gastos_prorrateados_clp', 'costo_final_total_clp'] as $field) {
                $t->decimal($field, 24, 0)->change();
            }
            $t->string('weight', 40)->nullable();
            $t->string('volume', 40)->nullable();
            $t->string('rate_mode', 20)->default('estimated');
            $t->string('applied_rate', 40)->nullable();
            $t->string('rate_source')->default('legacy-unverified');
            $t->date('rate_date')->nullable();
        });
        Schema::table('expenses', function (Blueprint $t) {
            $t->decimal('monto_clp', 24, 0)->change();
            $t->string('rate_mode', 20)->default('estimated');
            $t->string('applied_rate', 40)->nullable();
            $t->string('rate_source')->default('legacy-unverified');
            $t->date('rate_date')->nullable();
            $t->string('allocation_method', 20)->default('value');
            $t->unsignedBigInteger('target_product_id')->nullable();
        });
        Schema::create('cost_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('revision');
            $t->string('algorithm');
            $t->string('reason');
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->json('input');
            $t->json('result');
            $t->json('snapshot');
            $t->string('input_hash', 64);
            $t->timestamp('created_at');
            $t->index(['trip_id', 'revision']);
        });
        Schema::create('cost_allocations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cost_run_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('expense_id');
            $t->unsignedBigInteger('product_id');
            $t->string('amount', 40);
            $t->string('method', 20);
        });
        Schema::create('audit_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action');
            $t->unsignedInteger('revision');
            $t->json('before');
            $t->json('after');
            $t->timestamp('created_at');
            $t->index(['trip_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('cost_allocations');
        Schema::dropIfExists('cost_runs');
        Schema::table('expenses', fn (Blueprint $t) => $t->dropColumn(['rate_mode', 'applied_rate', 'rate_source', 'rate_date', 'allocation_method', 'target_product_id']));
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn(['weight', 'volume', 'rate_mode', 'applied_rate', 'rate_source', 'rate_date']));
        Schema::table('trips', function (Blueprint $t) {
            $t->dropIndex(['user_id', 'company_id', 'estado']);
            $t->dropColumn(['revision', 'deleted_at']);
        });
        // Keep wider precision during rollback to avoid truncating financial values.
    }
};
