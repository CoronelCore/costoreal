<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_categories', function (Blueprint $table) {
            // Null marks a legacy/base category. Shared records are read-only.
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->unique(['user_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'nombre']);
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
