<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@herold.local'],
            [
                'name' => 'Herold Admin',
                'password' => bcrypt('password'),
                'api_key_hash' => hash('sha256', config('herold.api_key') ?? 'default-dev-key'),
            ]
        );
    }
}
