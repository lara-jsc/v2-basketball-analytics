<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class AssistantCoachesSeeder extends Seeder
{
    public function run(): void
    {
        $teamToCoachBase = [
            'GSW' => 'warriors',
            'LAK' => 'lakers',
        ];

        foreach ($teamToCoachBase as $teamCode => $coachBase) {
            $team = Team::query()->where('code', $teamCode)->first();

            if ($team === null) {
                continue;
            }

            for ($i = 2; $i <= 5; $i++) {
                $email = "{$coachBase}{$i}@email.com";

                User::updateOrCreate(
                    ['email' => $email],
                    [
                        'name' => ucfirst($coachBase).' Coach '.$i,
                        'password' => 'password123',
                        'email_verified_at' => now(),
                        'team_id' => $team->id,
                    ],
                );
            }
        }
    }
}
