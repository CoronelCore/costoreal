<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_cost_baselines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('revision');
            $table->string('fingerprint', 64);
            $table->json('snapshot');
            $table->timestamp('created_at');
        });
        Schema::create('cost_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('base_revision');
            $table->string('fingerprint', 64);
            $table->json('comparison');
            $table->json('snapshot');
            $table->unsignedBigInteger('applied_revision')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('created_at');
            $table->unique(['trip_id', 'fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_reconciliations');
        Schema::dropIfExists('legacy_cost_baselines');
    }
};
