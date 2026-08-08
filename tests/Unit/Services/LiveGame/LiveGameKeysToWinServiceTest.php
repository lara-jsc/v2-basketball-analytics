<?php

namespace Tests\Unit\Services\LiveGame;

use App\Models\LiveGame;
use App\Models\LiveGameEvent;
use App\Models\Player;
use App\Models\PlayerStat;
use App\Models\Team;
use App\Services\LiveGame\LiveGameKeysToWinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class LiveGameKeysToWinServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_ranks_top_two_opponent_threats_from_season_stats(): void
    {
        $game = LiveGame::factory()->create(['current_period' => 1]);
        $home = $this->rosterWithStats($game->homeTeam, [
            ['role' => 'PG', 'pts' => 10, 'reb' => 3, 'ast' => 4, 'stl' => 2, 'blk' => 0, 'dr' => 2, 'plus_minus' => 1],
            ['role' => 'SG', 'pts' => 11, 'reb' => 3, 'ast' => 3, 'stl' => 1.5, 'blk' => 0, 'dr' => 2, 'plus_minus' => 0.5],
        ]);
        $opp = $this->rosterWithStats($game->opponentTeam, [
            ['role' => 'SG', 'pts' => 28, 'reb' => 4, 'ast' => 3, 'stl' => 1, 'blk' => 0, 'dr' => 3, 'plus_minus' => 8],
            ['role' => 'C', 'pts' => 12, 'reb' => 12, 'ast' => 1, 'stl' => 0.5, 'blk' => 2, 'dr' => 8, 'plus_minus' => 4],
            ['role' => 'PG', 'pts' => 6, 'reb' => 2, 'ast' => 2, 'stl' => 0.5, 'blk' => 0, 'dr' => 1, 'plus_minus' => -2],
        ]);

        $game->forceFill([
            'active_player_ids' => $home->pluck('id')->take(2)->all(),
            'opponent_active_player_ids' => $opp->pluck('id')->all(),
        ])->save();

        $result = app(LiveGameKeysToWinService::class)->compute($game, collect());

        $keys = $result['home']['keys'];
        $this->assertCount(2, $keys);
        $this->assertSame($opp[0]->id, $keys[0]['opponent_player_id']);
        $this->assertSame($opp[1]->id, $keys[1]['opponent_player_id']);
        $this->assertContains($keys[0]['strength_tag'], ['scorer', 'playmaker', 'boarder', 'rim_protector', 'disruptor']);
        $this->assertNotNull($keys[0]['counter_player_id']);
        $this->assertSame('season', $keys[0]['live_status']);
    }

    public function test_strength_tag_prefers_largest_edge_vs_own_median(): void
    {
        $game = LiveGame::factory()->create();
        $this->rosterWithStats($game->homeTeam, [
            ['role' => 'PG', 'pts' => 10, 'reb' => 3, 'ast' => 4, 'stl' => 1, 'blk' => 0, 'dr' => 2, 'plus_minus' => 0],
        ]);
        $opp = $this->rosterWithStats($game->opponentTeam, [
            ['role' => 'PG', 'pts' => 8, 'reb' => 2, 'ast' => 11, 'stl' => 1, 'blk' => 0, 'dr' => 2, 'plus_minus' => 3],
        ]);
        $game->forceFill([
            'active_player_ids' => [],
            'opponent_active_player_ids' => [$opp[0]->id],
        ])->save();

        $keys = app(LiveGameKeysToWinService::class)->compute($game, collect())['home']['keys'];

        $this->assertSame('playmaker', $keys[0]['strength_tag']);
        $this->assertSame('Pressure the ball; deny easy entries', $keys[0]['defense_key']);
    }

    public function test_counter_prefers_same_role(): void
    {
        $game = LiveGame::factory()->create();
        $home = $this->rosterWithStats($game->homeTeam, [
            ['role' => 'SG', 'pts' => 10, 'reb' => 3, 'ast' => 2, 'stl' => 0.5, 'blk' => 0, 'dr' => 2, 'plus_minus' => 0],
            ['role' => 'C', 'pts' => 8, 'reb' => 8, 'ast' => 1, 'stl' => 3, 'blk' => 2, 'dr' => 6, 'plus_minus' => 1],
        ]);
        $opp = $this->rosterWithStats($game->opponentTeam, [
            ['role' => 'SG', 'pts' => 24, 'reb' => 3, 'ast' => 2, 'stl' => 1, 'blk' => 0, 'dr' => 2, 'plus_minus' => 6],
        ]);
        $game->forceFill([
            'active_player_ids' => $home->pluck('id')->all(),
            'opponent_active_player_ids' => [$opp[0]->id],
        ])->save();

        $keys = app(LiveGameKeysToWinService::class)->compute($game, collect())['home']['keys'];

        $this->assertSame($home[0]->id, $keys[0]['counter_player_id']);
    }

    public function test_prefers_on_court_threat_when_scores_are_close(): void
    {
        $game = LiveGame::factory()->create();
        $this->rosterWithStats($game->homeTeam, [
            ['role' => 'PG', 'pts' => 10, 'reb' => 3, 'ast' => 4, 'stl' => 2, 'blk' => 0, 'dr' => 2, 'plus_minus' => 1],
        ]);
        // Identical season threat inputs → equal scores; on-court breaks the tie.
        $opp = $this->rosterWithStats($game->opponentTeam, [
            ['role' => 'SG', 'pts' => 20, 'reb' => 4, 'ast' => 3, 'stl' => 1, 'blk' => 0, 'dr' => 3, 'plus_minus' => 5.0],
            ['role' => 'SF', 'pts' => 20, 'reb' => 4, 'ast' => 3, 'stl' => 1, 'blk' => 0, 'dr' => 3, 'plus_minus' => 5.0],
        ]);
        $game->forceFill([
            'active_player_ids' => [],
            'opponent_active_player_ids' => [$opp[1]->id],
        ])->save();

        $keys = app(LiveGameKeysToWinService::class)->compute($game, collect())['home']['keys'];

        $this->assertSame($opp[1]->id, $keys[0]['opponent_player_id']);
    }

    public function test_live_status_confirmed_when_threat_has_three_makes_this_period(): void
    {
        $game = LiveGame::factory()->create(['current_period' => 2]);
        $this->rosterWithStats($game->homeTeam, [
            ['role' => 'PG', 'pts' => 10, 'reb' => 3, 'ast' => 4, 'stl' => 2, 'blk' => 0, 'dr' => 2, 'plus_minus' => 1],
        ]);
        $opp = $this->rosterWithStats($game->opponentTeam, [
            ['role' => 'SG', 'pts' => 22, 'reb' => 4, 'ast' => 3, 'stl' => 1, 'blk' => 0, 'dr' => 3, 'plus_minus' => 5],
        ]);
        $game->forceFill([
            'active_player_ids' => [],
            'opponent_active_player_ids' => [$opp[0]->id],
        ])->save();

        $events = collect([
            $this->event($game, 'shot_made', $opp[0], period: 2),
            $this->event($game, 'shot_made', $opp[0], period: 2),
            $this->event($game, 'shot_made', $opp[0], period: 2),
        ]);

        $keys = app(LiveGameKeysToWinService::class)->compute($game, $events)['home']['keys'];

        $this->assertSame('confirmed', $keys[0]['live_status']);
    }

    public function test_live_status_fading_on_miss_streak(): void
    {
        $game = LiveGame::factory()->create(['current_period' => 1]);
        $this->rosterWithStats($game->homeTeam, [
            ['role' => 'PG', 'pts' => 10, 'reb' => 3, 'ast' => 4, 'stl' => 2, 'blk' => 0, 'dr' => 2, 'plus_minus' => 1],
        ]);
        $opp = $this->rosterWithStats($game->opponentTeam, [
            ['role' => 'SG', 'pts' => 22, 'reb' => 4, 'ast' => 3, 'stl' => 1, 'blk' => 0, 'dr' => 3, 'plus_minus' => 5],
        ]);
        $game->forceFill([
            'active_player_ids' => [],
            'opponent_active_player_ids' => [$opp[0]->id],
        ])->save();

        $events = collect([
            $this->event($game, 'shot_missed', $opp[0]),
            $this->event($game, 'shot_missed', $opp[0]),
            $this->event($game, 'shot_missed', $opp[0]),
        ]);

        $keys = app(LiveGameKeysToWinService::class)->compute($game, $events)['home']['keys'];

        $this->assertSame('fading', $keys[0]['live_status']);
    }

    public function test_null_plus_minus_does_not_crash_and_still_ranks(): void
    {
        $game = LiveGame::factory()->create();
        $this->rosterWithStats($game->homeTeam, [
            ['role' => 'PG', 'pts' => 10, 'reb' => 3, 'ast' => 4, 'stl' => 2, 'blk' => 0, 'dr' => 2, 'plus_minus' => null],
        ]);
        $opp = $this->rosterWithStats($game->opponentTeam, [
            ['role' => 'SG', 'pts' => 18, 'reb' => 4, 'ast' => 3, 'stl' => 1, 'blk' => 0, 'dr' => 3, 'plus_minus' => null],
        ]);
        $game->forceFill([
            'active_player_ids' => [],
            'opponent_active_player_ids' => [$opp[0]->id],
        ])->save();

        $keys = app(LiveGameKeysToWinService::class)->compute($game, collect())['home']['keys'];

        $this->assertCount(1, $keys);
        $this->assertSame($opp[0]->id, $keys[0]['opponent_player_id']);
    }

    public function test_empty_when_opponent_has_no_season_stats(): void
    {
        $game = LiveGame::factory()->create();
        Player::factory()->for($game->homeTeam)->create(['is_active' => true]);
        Player::factory()->for($game->opponentTeam)->create(['is_active' => true]);

        $keys = app(LiveGameKeysToWinService::class)->compute($game, collect())['home']['keys'];

        $this->assertSame([], $keys);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return Collection<int, Player>
     */
    private function rosterWithStats(Team $team, array $rows)
    {
        return collect($rows)->map(function (array $row) use ($team): Player {
            $player = Player::factory()->for($team)->create([
                'is_active' => true,
                'role' => $row['role'],
            ]);
            PlayerStat::factory()->forPlayer($player)->create([
                'pts' => $row['pts'],
                'reb' => $row['reb'],
                'ast' => $row['ast'],
                'stl' => $row['stl'],
                'blk' => $row['blk'],
                'dr' => $row['dr'],
                'pf' => $row['pf'] ?? 2,
                'efg_pct' => $row['efg_pct'] ?? 0.5,
                'plus_minus' => $row['plus_minus'],
            ]);

            return $player;
        });
    }

    /** @param array<string, mixed> $payload */
    private function event(LiveGame $game, string $type, Player $player, array $payload = ['points' => 2], int $period = 1): LiveGameEvent
    {
        return LiveGameEvent::query()->create([
            'live_game_id' => $game->id,
            'sequence' => (int) $game->events()->max('sequence') + 1,
            'type' => $type,
            'team_scope' => 'own',
            'player_id' => $player->id,
            'period' => $period,
            'clock_seconds_remaining' => 600,
            'occurred_at' => now(),
            'payload' => $payload,
        ]);
    }
}
