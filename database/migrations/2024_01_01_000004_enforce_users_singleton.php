<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $count = DB::table('users')->count();

        if ($count > 1) {
            throw new RuntimeException(
                "Cannot enforce single-user constraint: {$count} users exist. "
                .'Reduce to at most 1 user before running this migration.'
            );
        }

        DB::statement("
            CREATE TRIGGER enforce_users_singleton
            BEFORE INSERT ON users
            WHEN (SELECT COUNT(*) FROM users) >= 1
            BEGIN
                SELECT RAISE(ABORT, 'users table is limited to at most one row (single-user system)');
            END
        ");
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS enforce_users_singleton');
    }
};
