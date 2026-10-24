<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Installs seeded before the seed migration was hardened carry bcrypt('password')
     * in the unused users.password column. Replace it with the hash of a random,
     * discarded value so no known credential remains in the database.
     */
    public function up(): void
    {
        DB::table('users')->update([
            'password' => bcrypt(Str::random(64)),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Intentional no-op: the previous value must not be restored.
    }
};
