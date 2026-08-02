<?php

namespace Tests\Feature\LiveGame;

use App\Models\LiveGame;
use App\Models\LiveGameEvent;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class LiveGameSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_game_tables_include_the_required_columns(): void
    {
        $this->assertTableHasColumns('live_games', [
            'id', 'home_team_id', 'opponent_team_id', 'created_by_user_id', 'status', 'game_date',
            'period_length_seconds', 'current_period', 'clock_seconds_remaining', 'clock_running',
            'clock_started_at', 'home_score', 'opponent_score',             'starting_player_ids', 'active_player_ids', 'opponent_starting_player_ids', 'opponent_active_player_ids',
            'started_at', 'finished_at', 'created_at', 'updated_at',
        ]);

        $this->assertTableHasColumns('live_game_events', [
            'id', 'live_game_id', 'sequence', 'type', 'team_scope', 'player_id', 'period',
            'clock_seconds_remaining', 'occurred_at', 'payload', 'voids_event_id', 'recorded_by_user_id',
            'created_at', 'updated_at',
        ]);

        $this->assertTableHasColumns('live_game_player_stats', [
            'id', 'live_game_id', 'player_id', 'is_starter', 'is_active', 'minutes_seconds', 'plus_minus',
            'points', 'field_goals_made', 'field_goals_attempted', 'three_pointers_made',
            'three_pointers_attempted', 'free_throws_made', 'free_throws_attempted', 'offensive_rebounds',
            'defensive_rebounds', 'rebounds', 'assists', 'steals', 'blocks', 'turnovers', 'personal_fouls',
            'flagrant_fouls', 'technical_fouls', 'created_at', 'updated_at',
        ]);

        $this->assertTableHasColumns('live_game_lineup_stints', [
            'id', 'live_game_id', 'player_id', 'start_period', 'start_clock_seconds_remaining', 'end_period',
            'end_clock_seconds_remaining', 'started_at', 'ended_at', 'start_score_for',
            'start_score_against', 'end_score_for', 'end_score_against', 'duration_seconds', 'plus_minus',
            'created_at', 'updated_at',
        ]);

        $this->assertTableHasColumns('live_game_alerts', [
            'id', 'live_game_id', 'player_id', 'type', 'severity', 'period', 'clock_seconds_remaining',
            'message', 'context', 'triggered_at', 'resolved_at', 'created_at', 'updated_at',
        ]);
    }

    public function test_event_sequence_is_unique_within_a_live_game(): void
    {
        $game = $this->createLiveGame();

        LiveGameEvent::query()->create($this->eventAttributes($game));

        $this->expectException(QueryException::class);

        LiveGameEvent::query()->create($this->eventAttributes($game));
    }

    public function test_live_game_player_stats_are_unique_per_game_and_player(): void
    {
        $game = $this->createLiveGame();
        $player = Player::factory()->for($game->homeTeam)->create();

        DB::table('live_game_player_stats')->insert([
            'live_game_id' => $game->id,
            'player_id' => $player->id,
        ]);

        $this->expectException(QueryException::class);

        DB::table('live_game_player_stats')->insert([
            'live_game_id' => $game->id,
            'player_id' => $player->id,
        ]);
    }

    public function test_live_game_foreign_keys_apply_the_required_delete_behavior(): void
    {
        $creator = User::factory()->create();
        $game = $this->createLiveGame($creator);
        $player = Player::factory()->for($game->homeTeam)->create();

        $event = LiveGameEvent::query()->create($this->eventAttributes($game, $player, $creator));
        $voidingEvent = LiveGameEvent::query()->create($this->eventAttributes($game, $player, $creator, [
            'sequence' => 2,
            'voids_event_id' => $event->id,
        ]));

        $player->delete();
        $creator->delete();
        $event->delete();

        $voidingEvent->refresh();
        $this->assertNull($voidingEvent->player_id);
        $this->assertNull($voidingEvent->recorded_by_user_id);
        $this->assertNull($voidingEvent->voids_event_id);

        $game->delete();

        $this->assertDatabaseMissing('live_game_events', ['id' => $voidingEvent->id]);
    }

    public function test_required_indexes_are_declared(): void
    {
        $this->assertIndexExists('live_games', ['status']);
        $this->assertIndexExists('live_games', ['home_team_id']);
        $this->assertIndexExists('live_games', ['opponent_team_id']);
        $this->assertIndexExists('live_games', ['created_by_user_id']);
        $this->assertIndexExists('live_game_events', ['live_game_id']);
        $this->assertIndexExists('live_game_events', ['type']);
        $this->assertIndexExists('live_game_events', ['player_id']);
        $this->assertIndexExists('live_game_events', ['voids_event_id']);
        $this->assertIndexExists('live_game_player_stats', ['live_game_id']);
        $this->assertIndexExists('live_game_player_stats', ['player_id']);
        $this->assertIndexExists('live_game_lineup_stints', ['live_game_id']);
        $this->assertIndexExists('live_game_lineup_stints', ['player_id']);
        $this->assertIndexExists('live_game_lineup_stints', ['live_game_id', 'ended_at']);
        $this->assertIndexExists('live_game_alerts', ['live_game_id']);
        $this->assertIndexExists('live_game_alerts', ['type']);
        $this->assertIndexExists('live_game_alerts', ['player_id']);
        $this->assertIndexExists('live_game_alerts', ['resolved_at']);
    }

    public function test_required_foreign_keys_are_declared(): void
    {
        $this->assertForeignKeyExists('live_games', 'home_team_id', 'teams');
        $this->assertForeignKeyExists('live_games', 'opponent_team_id', 'teams');
        $this->assertForeignKeyExists('live_games', 'created_by_user_id', 'users');
        $this->assertForeignKeyExists('live_game_events', 'live_game_id', 'live_games');
        $this->assertForeignKeyExists('live_game_events', 'player_id', 'players');
        $this->assertForeignKeyExists('live_game_events', 'voids_event_id', 'live_game_events');
        $this->assertForeignKeyExists('live_game_events', 'recorded_by_user_id', 'users');
        $this->assertForeignKeyExists('live_game_player_stats', 'live_game_id', 'live_games');
        $this->assertForeignKeyExists('live_game_player_stats', 'player_id', 'players');
        $this->assertForeignKeyExists('live_game_lineup_stints', 'live_game_id', 'live_games');
        $this->assertForeignKeyExists('live_game_lineup_stints', 'player_id', 'players');
        $this->assertForeignKeyExists('live_game_alerts', 'live_game_id', 'live_games');
        $this->assertForeignKeyExists('live_game_alerts', 'player_id', 'players');
    }

    public function test_live_game_status_must_be_a_valid_lifecycle_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid live game status [cancelled]. Allowed statuses: setup, live, finished.');

        LiveGame::factory()->create(['status' => 'cancelled']);
    }

    public function test_live_game_can_be_created_in_each_valid_lifecycle_status(): void
    {
        foreach (LiveGame::STATUSES as $status) {
            $game = LiveGame::factory()->create(['status' => $status]);

            $this->assertSame($status, $game->status);
            $this->assertDatabaseHas('live_games', ['id' => $game->id, 'status' => $status]);
        }
    }

    /** @param list<string> $columns */
    private function assertTableHasColumns(string $table, array $columns): void
    {
        $existingColumns = Schema::getColumnListing($table);

        foreach ($columns as $column) {
            $this->assertContains($column, $existingColumns, "Expected {$table}.{$column} to exist.");
        }
    }

    /** @param list<string> $columns */
    private function assertIndexExists(string $table, array $columns): void
    {
        $indexExists = collect(Schema::getIndexes($table))
            ->contains(fn (array $index): bool => $index['columns'] === $columns);

        $this->assertTrue($indexExists, "Expected an index on {$table} (".implode(', ', $columns).').');
    }

    private function assertForeignKeyExists(string $table, string $column, string $referencedTable): void
    {
        $foreignKeyExists = collect(Schema::getForeignKeys($table))
            ->contains(fn (array $foreignKey): bool => $foreignKey['columns'] === [$column]
                && $foreignKey['foreign_table'] === $referencedTable);

        $this->assertTrue($foreignKeyExists, "Expected {$table}.{$column} to reference {$referencedTable}.");
    }

    private function createLiveGame(?User $creator = null): LiveGame
    {
        return LiveGame::query()->create([
            'home_team_id' => Team::factory()->create()->id,
            'opponent_team_id' => Team::factory()->create()->id,
            'created_by_user_id' => $creator?->id,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function eventAttributes(LiveGame $game, ?Player $player = null, ?User $user = null, array $overrides = []): array
    {
        return array_merge([
            'live_game_id' => $game->id,
            'sequence' => 1,
            'type' => 'two_pt_made',
            'team_scope' => 'own',
            'player_id' => $player?->id,
            'period' => 1,
            'clock_seconds_remaining' => 600,
            'occurred_at' => now(),
            'recorded_by_user_id' => $user?->id,
        ], $overrides);
    }
}
