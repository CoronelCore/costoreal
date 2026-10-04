<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_run_views', function (Blueprint $table) {
            $table->foreignId('cost_run_id')->primary()->constrained()->cascadeOnDelete();
            $table->json('summary');
        });
        Schema::create('cost_run_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cost_run_id')->constrained()->cascadeOnDelete();
            // Historical IDs survive deletion of a live product.
            $table->unsignedBigInteger('product_id');
            $table->json('data');
            $table->unique(['cost_run_id', 'product_id']);
        });
        Schema::create('cost_run_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cost_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('expense_id');
            $table->string('name');
            $table->unique(['cost_run_id', 'expense_id']);
        });
        Schema::table('cost_allocations', function (Blueprint $table) {
            $table->json('trace')->nullable();
            $table->index(['cost_run_id', 'product_id', 'expense_id'], 'cost_allocations_product_page');
        });
    }

    public function down(): void
    {
        Schema::table('cost_allocations', function (Blueprint $table) {
            $table->dropIndex('cost_allocations_product_page');
            $table->dropColumn('trace');
        });
        Schema::dropIfExists('cost_run_expenses');
        Schema::dropIfExists('cost_run_lines');
        Schema::dropIfExists('cost_run_views');
    }
};
