<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_scenarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->uuid('idempotency_key');
            $table->unsignedBigInteger('base_revision');
            $table->string('baseline_hash', 64);
            $table->string('input_hash', 64);
            $table->json('input');
            $table->json('result');
            $table->unsignedBigInteger('applied_revision')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('created_at');
            $table->unique(['trip_id', 'idempotency_key']);
            $table->index(['trip_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_scenarios');
    }
};
