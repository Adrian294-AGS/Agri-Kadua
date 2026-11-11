<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $hasPassword = Schema::hasColumn('users', 'password');
        $hasPasswordHash = Schema::hasColumn('users', 'password_hash');

        if ($hasPassword && ! $hasPasswordHash) {
            Schema::table('users', function (Blueprint $table) {
                $table->renameColumn('password', 'password_hash');
            });

            return;
        }

        if ($hasPassword && $hasPasswordHash) {
            DB::table('users')
                ->whereNull('password_hash')
                ->update(['password_hash' => DB::raw('password')]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users')
            && Schema::hasColumn('users', 'password_hash')
            && ! Schema::hasColumn('users', 'password')) {
            Schema::table('users', function (Blueprint $table) {
                $table->renameColumn('password_hash', 'password');
            });
        }
    }
};
