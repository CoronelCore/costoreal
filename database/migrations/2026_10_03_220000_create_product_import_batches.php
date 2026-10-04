<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('idempotency_key');
            $table->string('input_hash', 64);
            $table->unsignedBigInteger('revision_before');
            $table->unsignedBigInteger('revision_after');
            $table->unsignedInteger('imported_count');
            $table->json('product_ids')->nullable();
            $table->timestamp('created_at');
            $table->unique(['trip_id', 'idempotency_key']);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('product_import_batch_id')->nullable()->constrained('product_import_batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropConstrainedForeignId('product_import_batch_id'));
        Schema::dropIfExists('product_import_batches');
    }
};
