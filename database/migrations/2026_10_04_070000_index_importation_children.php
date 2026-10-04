<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = ['products', 'expenses', 'documents', 'pricing_batches'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            // SQLite foreign keys do not create a child lookup index automatically.
            // Preserve equivalent indexes already supplied by another database engine.
            if (! Schema::hasIndex($table, ['trip_id'])) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index('trip_id', $table.'_importation_lookup'));
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            $name = $table.'_importation_lookup';
            if (Schema::hasIndex($table, $name)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($name));
            }
        }
    }
};
