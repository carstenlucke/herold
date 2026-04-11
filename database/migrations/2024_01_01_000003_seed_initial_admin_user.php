<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests() || DB::table('users')->exists()) {
            return;
        }

        $apiKey = config('herold.api_key');

        if (blank($apiKey)) {
            throw new RuntimeException(
                'HEROLD_API_KEY must be set in .env before running migrations.'
            );
        }

        $adminEmail = config('herold.admin_email', 'herold@flitzpeople.com');

        DB::table('users')->insert([
            'name' => 'Herold',
            'email' => $adminEmail,
            'password' => bcrypt('password'),
            'api_key_hash' => hash('sha256', $apiKey),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Delete by the hardcoded name (stable) rather than the mutable email config.
        DB::table('users')->where('name', 'Herold')->delete();
    }
};
