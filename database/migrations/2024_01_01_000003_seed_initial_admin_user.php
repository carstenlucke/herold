<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->insertOrIgnore([
            'name' => 'Herold',
            'email' => 'herold@flitzpeople.com',
            'password' => bcrypt('password'),
            'api_key_hash' => hash('sha256', config('herold.api_key') ?? 'default-dev-key'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('users')->where('email', 'herold@flitzpeople.com')->delete();
    }
};
