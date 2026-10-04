<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_drafts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision')->default(1);
            $table->string('step', 20)->default('contexto');
            $table->json('payload');
            $table->uuid('last_write_key');
            $table->string('last_write_hash', 64);
            $table->string('launch_hash', 64)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'completed_at', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_drafts');
    }
};
