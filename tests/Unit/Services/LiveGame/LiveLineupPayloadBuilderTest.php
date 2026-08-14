<?php

namespace Tests\Unit\Services\LiveGame;

use App\Models\LiveGame;
use App\Models\LiveGamePlayerStat;
use App\Models\Player;
use App\Models\PlayerStat;
use App\Services\LiveGame\LiveLineupPayloadBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveLineupPayloadBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_scales_tonights_box_score_to_per_thirty_six_minutes(): void
    {
        [$game, $player] = $this->gameWithPlayer();
        // 18 minutes played, so every counting stat doubles on the way to a per-36 rate.
        $this->liveStat($game, $player, [
            'minutes_seconds' => 1080,
            'points' => 12,
            'rebounds' => 4,
            'assists' => 3,
            'blocks' => 1,
            'steals' => 2,
            'turnovers' => 3,
            'field_goals_made' => 5,
            'field_goals_attempted' => 10,
        ]);

        $row = $this->tonight($game, [$player])[0];

        $this->assertSame(24.0, $row['pts']);
        $this->assertSame(8.0, $row['reb']);
        $this->assertSame(6.0, $row['ast']);
        $this->assertSame(2.0, $row['blk']);
        $this->assertSame(4.0, $row['stl']);
        $this->assertSame(6.0, $row['to_per_game']);
        $this->assertSame(18.0, $row['min'], 'min reports actual minutes played, not the 36 the rates are scaled to');
    }

    public function test_field_goal_percentage_is_a_rate_and_is_not_scaled(): void
    {
        [$game, $player] = $this->gameWithPlayer();
        $this->liveStat($game, $player, [
            'minutes_seconds' => 1080,
            'field_goals_made' => 5,
            'field_goals_attempted' => 10,
        ]);

        $row = $this->tonight($game, [$player])[0];

        $this->assertSame(0.5, $row['fg_pct']);
    }

    public function test_a_player_who_has_not_attempted_a_shot_gets_a_zero_percentage(): void
    {
        [$game, $player] = $this->gameWithPlayer();
        $this->liveStat($game, $player, [
            'minutes_seconds' => 600,
            'field_goals_made' => 0,
            'field_goals_attempted' => 0,
        ]);

        $row = $this->tonight($game, [$player])[0];

        $this->assertSame(0.0, $row['fg_pct']);
    }

    public function test_a_player_below_the_minimum_sample_is_omitted_entirely(): void
    {
        [$game, $player] = $this->gameWithPlayer();
        $this->liveStat($game, $player, [
            'minutes_seconds' => LiveLineupPayloadBuilder::MIN_LIVE_SECONDS - 1,
            'points' => 4,
        ]);

        $this->assertSame([], $this->tonight($game, [$player]));
    }

    public function test_a_player_exactly_at_the_minimum_sample_is_included(): void
    {
        [$game, $player] = $this->gameWithPlayer();
        $this->liveStat($game, $player, [
            'minutes_seconds' => LiveLineupPayloadBuilder::MIN_LIVE_SECONDS,
            'points' => 4,
        ]);

        $rows = $this->tonight($game, [$player]);

        $this->assertCount(1, $rows);
        $this->assertSame(36.0, $rows[0]['pts'], '4 points in 4 minutes projects to 36 per 36');
    }

    public function test_a_player_with_no_minutes_is_omitted_without_dividing_by_zero(): void
    {
        [$game, $player] = $this->gameWithPlayer();
        $this->liveStat($game, $player, ['minutes_seconds' => 0, 'points' => 0]);

        $this->assertSame([], $this->tonight($game, [$player]));
    }

    public function test_a_player_with_no_live_stat_row_is_omitted(): void
    {
        [$game, $player] = $this->gameWithPlayer();

        $this->assertSame([], $this->tonight($game, [$player]));
    }

    public function test_it_ignores_live_stats_belonging_to_another_game(): void
    {
        [$game, $player] = $this->gameWithPlayer();
        $otherGame = LiveGame::factory()->create();
        $this->liveStat($otherGame, $player, ['minutes_seconds' => 1080, 'points' => 30]);

        $this->assertSame([], $this->tonight($game, [$player]));
    }

    public function test_the_tonight_payload_uses_the_same_keys_as_the_season_payload(): void
    {
        [$game, $player] = $this->gameWithPlayer();
        PlayerStat::factory()->for($player)->create();
        $this->liveStat($game, $player, ['minutes_seconds' => 1080, 'points' => 10]);

        $players = $this->reload([$player]);
        $builder = app(LiveLineupPayloadBuilder::class);

        $seasonKeys = array_keys($builder->season($players)[0]);
        $tonightKeys = array_keys($builder->tonight($game, $players)[0]);

        $this->assertSame($seasonKeys, $tonightKeys);
    }

    public function test_the_season_payload_reads_the_players_stored_stat_row(): void
    {
        [$game, $player] = $this->gameWithPlayer();
        PlayerStat::factory()->for($player)->create(['pts' => 21.5, 'fg_pct' => 0.48]);

        $row = app(LiveLineupPayloadBuilder::class)->season($this->reload([$player]))[0];

        $this->assertSame($player->id, $row['player_id']);
        $this->assertSame(21.5, (float) $row['pts']);
        $this->assertSame(0.48, (float) $row['fg_pct']);
    }

    public function test_it_carries_the_player_name_through_both_payloads(): void
    {
        [$game, $player] = $this->gameWithPlayer(playerAttributes: [
            'first_name' => 'Marc',
            'last_name' => 'Reyes',
        ]);
        PlayerStat::factory()->for($player)->create();
        $this->liveStat($game, $player, ['minutes_seconds' => 1080]);

        $players = $this->reload([$player]);
        $builder = app(LiveLineupPayloadBuilder::class);

        $this->assertSame('Marc Reyes', $builder->season($players)[0]['name']);
        $this->assertSame('Marc Reyes', $builder->tonight($game, $players)[0]['name']);
    }

    /**
     * @param  array<string, mixed>  $playerAttributes
     * @return array{LiveGame, Player}
     */
    private function gameWithPlayer(array $playerAttributes = []): array
    {
        $game = LiveGame::factory()->create();
        $player = Player::factory()->for($game->homeTeam)->create($playerAttributes);

        return [$game, $player];
    }

    /** @param array<string, mixed> $attributes */
    private function liveStat(LiveGame $game, Player $player, array $attributes = []): LiveGamePlayerStat
    {
        return LiveGamePlayerStat::query()->create([
            'live_game_id' => $game->id,
            'player_id' => $player->id,
            ...$attributes,
        ]);
    }

    /**
     * @param  list<Player>  $players
     * @return list<array<string, mixed>>
     */
    private function tonight(LiveGame $game, array $players): array
    {
        return app(LiveLineupPayloadBuilder::class)->tonight($game, $this->reload($players));
    }

    /**
     * The season payload reads `$player->stats->first()`, so the collection has to arrive
     * with that relation loaded the same way ComparisonRepository loads it.
     *
     * @param  list<Player>  $players
     * @return Collection<int, Player>
     */
    private function reload(array $players): Collection
    {
        return Player::query()
            ->whereIn('id', array_map(fn (Player $player): int => $player->id, $players))
            ->with(['stats' => fn ($query) => $query->latest()->limit(1)])
            ->orderBy('jersey_number')
            ->get();
    }
}
