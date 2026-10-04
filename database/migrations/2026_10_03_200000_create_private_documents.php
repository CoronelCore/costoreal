<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $t->foreignId('expense_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('storage_path')->unique();
            $t->string('filename');
            $t->string('mime', 100);
            $t->unsignedBigInteger('size');
            $t->string('sha256', 64);
            $t->string('kind', 30);
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::table('expenses', fn (Blueprint $t) => $t->foreignId('document_id')->nullable()->constrained()->nullOnDelete());
        Schema::table('products', fn (Blueprint $t) => $t->foreignId('document_id')->nullable()->constrained()->nullOnDelete());
    }

    public function down(): void
    {
        Schema::table('expenses', fn (Blueprint $t) => $t->dropConstrainedForeignId('document_id'));
        Schema::table('products', fn (Blueprint $t) => $t->dropConstrainedForeignId('document_id'));
        Schema::dropIfExists('documents');
    }
};
