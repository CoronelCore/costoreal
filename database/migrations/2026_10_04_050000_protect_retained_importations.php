<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->change(true);
    }

    public function down(): void
    {
        $this->change(false);
    }

    private function change(bool $protect): void
    {
        $alter = fn () => Schema::table('trips', function (Blueprint $table) use ($protect) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['company_id']);
            $user = $table->foreign('user_id')->references('id')->on('users');
            $company = $table->foreign('company_id')->references('id')->on('companies');
            if ($protect) {
                $user->restrictOnDelete();
                $company->nullOnDelete();
            } else {
                $user->cascadeOnDelete();
                $company->cascadeOnDelete();
            }
        });
        if (DB::getDriverName() !== 'sqlite') {
            $alter();

            return;
        }
        // SQLite table rebuild must be atomic with FK checks disabled BEFORE starting its transaction.
        if (DB::transactionLevel() !== 0) {
            throw new RuntimeException('La migración de retención requiere ejecutarse fuera de una transacción existente.');
        }
        Schema::disableForeignKeyConstraints();
        try {
            DB::transaction($alter);
        } finally {
            Schema::enableForeignKeyConstraints();
        }
        if (DB::select('PRAGMA foreign_key_check')) {
            throw new RuntimeException('La migración de retención detectó relaciones inconsistentes.');
        }
    }
};
