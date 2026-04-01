<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'test@email.com'],
            [
                'name' => 'Demo Admin',
                'password' => 'password123',
                'email_verified_at' => now(),
            ],
        );
    }
}
