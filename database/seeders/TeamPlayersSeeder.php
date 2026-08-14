<?php

namespace Database\Seeders;

use App\Jobs\RebuildPlayerStats;
use App\Models\Player;
use App\Models\PlayerHistory;
use App\Models\Team;
use Illuminate\Database\Seeder;

class TeamPlayersSeeder extends Seeder
{
    /**
     * Seed two teams, each with 10 players.
     * Each player gets 10 per-game PlayerHistory records.
     * PlayerStat is derived by running RebuildPlayerStats synchronously — not seeded directly.
     */
    public function run(): void
    {
        $teams = [
            ['code' => 'LAK', 'name' => 'Los Angeles Lakers'],
            ['code' => 'GSW', 'name' => 'Golden State Warriors'],
        ];

        $rostersByTeam = [
            'LAK' => [
                ['first_name' => 'LeBron',    'last_name' => 'James',          'jersey_number' => 23, 'role' => 'Small Forward'],
                ['first_name' => 'Anthony',   'last_name' => 'Davis',          'jersey_number' => 3,  'role' => 'Power Forward'],
                ['first_name' => 'Austin',    'last_name' => 'Reaves',         'jersey_number' => 15, 'role' => 'Shooting Guard'],
                ['first_name' => 'D\'Angelo', 'last_name' => 'Russell',        'jersey_number' => 1,  'role' => 'Point Guard'],
                ['first_name' => 'Rui',       'last_name' => 'Hachimura',      'jersey_number' => 28, 'role' => 'Power Forward'],
                ['first_name' => 'Taurean',   'last_name' => 'Prince',         'jersey_number' => 12, 'role' => 'Small Forward'],
                ['first_name' => 'Christian', 'last_name' => 'Wood',           'jersey_number' => 35, 'role' => 'Center'],
                ['first_name' => 'Spencer',   'last_name' => 'Dinwiddie',      'jersey_number' => 26, 'role' => 'Point Guard'],
                ['first_name' => 'Gabe',      'last_name' => 'Vincent',        'jersey_number' => 7,  'role' => 'Shooting Guard'],
                ['first_name' => 'Jaxson',    'last_name' => 'Hayes',          'jersey_number' => 11, 'role' => 'Center'],
            ],
            'GSW' => [
                ['first_name' => 'Stephen',   'last_name' => 'Curry',          'jersey_number' => 30, 'role' => 'Point Guard'],
                ['first_name' => 'Klay',      'last_name' => 'Thompson',       'jersey_number' => 11, 'role' => 'Shooting Guard'],
                ['first_name' => 'Draymond',  'last_name' => 'Green',          'jersey_number' => 23, 'role' => 'Power Forward'],
                ['first_name' => 'Andrew',    'last_name' => 'Wiggins',        'jersey_number' => 22, 'role' => 'Small Forward'],
                ['first_name' => 'Kevon',     'last_name' => 'Looney',         'jersey_number' => 5,  'role' => 'Center'],
                ['first_name' => 'Chris',     'last_name' => 'Paul',           'jersey_number' => 3,  'role' => 'Point Guard'],
                ['first_name' => 'Jonathan',  'last_name' => 'Kuminga',        'jersey_number' => 0,  'role' => 'Small Forward'],
                ['first_name' => 'Moses',     'last_name' => 'Moody',          'jersey_number' => 4,  'role' => 'Shooting Guard'],
                ['first_name' => 'Gary',      'last_name' => 'Payton II',      'jersey_number' => 8,  'role' => 'Shooting Guard'],
                ['first_name' => 'Trayce',    'last_name' => 'Jackson-Davis',  'jersey_number' => 32, 'role' => 'Power Forward'],
            ],
        ];

        $createdTeams = [];

        foreach ($teams as $teamData) {
            $createdTeams[$teamData['code']] = Team::create([
                'code' => $teamData['code'],
                'name' => $teamData['name'],
                'logo_path' => null,
                'is_active' => true,
            ]);
        }

        $lakTeam = $createdTeams['LAK'];
        $gswTeam = $createdTeams['GSW'];

        foreach ($createdTeams as $code => $team) {
            $opponent = $code === 'LAK' ? $gswTeam : $lakTeam;

            foreach ($rostersByTeam[$code] as $playerData) {
                $player = Player::factory()
                    ->forTeam($team)
                    ->create([
                        'first_name' => $playerData['first_name'],
                        'last_name' => $playerData['last_name'],
                        'jersey_number' => $playerData['jersey_number'],
                        'role' => $playerData['role'],
                    ]);

                // 10 per-game history records — spaced 14 days apart to avoid the
                // unique constraint on (player_id, game_date, opponent_team_id)
                $gameDates = collect(range(1, 10))
                    ->map(fn (int $i) => now()->subDays($i * 14)->format('Y-m-d'))
                    ->all();

                PlayerHistory::factory()
                    ->forPlayer($player)
                    ->forTeams($team, $opponent)
                    ->count(10)
                    ->sequence(fn ($seq) => ['game_date' => $gameDates[$seq->index]])
                    ->create();

                // Compute and upsert player_stats from the history records above
                RebuildPlayerStats::dispatchSync($player->id);
            }
        }
    }
}
