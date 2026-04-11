<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $apiKey = config('herold.api_key');

        if (blank($apiKey)) {
            throw new \RuntimeException(
                'HEROLD_API_KEY must be set in .env before running migrations.'
            );
        }

        if (DB::table('users')->exists()) {
            return;
        }

        DB::table('users')->insert([
            'name' => 'Herold',
            'email' => 'herold@flitzpeople.com',
            'password' => bcrypt('password'),
            'api_key_hash' => hash('sha256', $apiKey),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('users')->where('email', 'herold@flitzpeople.com')->delete();
    }
};
