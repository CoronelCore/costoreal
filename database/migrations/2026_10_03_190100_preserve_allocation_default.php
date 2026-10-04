<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', fn (Blueprint $t) => $t->decimal('gastos_prorrateados_clp', 24, 0)->default(0)->change());
    }

    public function down(): void
    { /* This default is also required by the original schema. */
    }
};
