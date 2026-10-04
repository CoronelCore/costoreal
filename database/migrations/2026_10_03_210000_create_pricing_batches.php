<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('revision');
            $table->json('policy');
            $table->json('result');
            $table->timestamp('created_at');
            $table->unique(['trip_id', 'revision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_batches');
    }
};
