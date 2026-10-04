<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_document_moves', function (Blueprint $table) {
            $table->id();
            $table->string('source_table', 20);
            $table->unsignedBigInteger('source_id');
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->restrictOnDelete();
            $table->string('source_path');
            $table->string('storage_path');
            $table->string('sha256', 64);
            $table->unsignedBigInteger('size');
            $table->string('status', 30)->default('copied');
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['source_table', 'source_id']);
            $table->index(['status', 'id']);
        });
        Schema::create('legacy_document_quarantine', function (Blueprint $table) {
            $table->id();
            $table->string('source_path')->unique();
            $table->string('storage_path')->unique();
            $table->string('sha256', 64);
            $table->unsignedBigInteger('size');
            $table->string('mime', 100);
            $table->string('status', 30)->default('copied');
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_document_quarantine');
        Schema::dropIfExists('legacy_document_moves');
    }
};
