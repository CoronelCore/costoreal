<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->onDelete('cascade');
            $table->string('nombre');
            $table->string('moneda', 3)->default('CLP');
            $table->integer('cantidad')->default(1);
            $table->decimal('costo_unitario', 12, 2);
            $table->decimal('subtotal_clp', 12, 2);
            $table->decimal('gastos_prorrateados_clp', 12, 2)->default(0);
            $table->decimal('costo_final_unitario_clp', 12, 2);
            $table->decimal('costo_final_total_clp', 12, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
