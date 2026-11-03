<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The owner sees when each account last used the installation and can suspend one without deleting its data. */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
        });
        // Installations made by terminal never recorded their owner: it was always the first account.
        $first = DB::table('users')->min('id');
        if ($first !== null && ! DB::table('app_settings')->where('key', 'owner_user_id')->exists()) {
            DB::table('app_settings')->insert(['key' => 'owner_user_id', 'value' => (string) $first, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['last_seen_at', 'suspended_at']);
        });
    }
};
