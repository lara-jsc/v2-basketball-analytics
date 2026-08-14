<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $password = (string) config('demo.password');

        User::updateOrCreate(
            ['email' => 'test@email.com'],
            [
                'name' => 'Demo Admin',
                'password' => $password,
                'email_verified_at' => now(),
                'team_id' => null,
            ],
        );

        $warriors = Team::query()->where('code', 'GSW')->first();
        $lakers = Team::query()->where('code', 'LAK')->first();

        if ($warriors !== null) {
            User::updateOrCreate(
                ['email' => 'warriors@email.com'],
                [
                    'name' => 'Warriors Coach',
                    'password' => $password,
                    'email_verified_at' => now(),
                    'team_id' => $warriors->id,
                ],
            );
        }

        if ($lakers !== null) {
            User::updateOrCreate(
                ['email' => 'lakers@email.com'],
                [
                    'name' => 'Lakers Coach',
                    'password' => $password,
                    'email_verified_at' => now(),
                    'team_id' => $lakers->id,
                ],
            );
        }
    }
}
