<?php

use App\Jobs\RebuildPlayerStats;
use App\Models\Player;
use App\Models\PlayerStat;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Full HTTP-flow tests for the player statistics pipeline
|--------------------------------------------------------------------------
|
| Routes (all require auth + verified middleware):
|   POST /teams                         → teams.store
|   POST /teams/{team}/players          → players.store
|   POST /players/{player}/histories   → player-histories.store
|
| Stats are computed synchronously in tests because phpunit.xml sets
| QUEUE_CONNECTION=sync. Each POST to /histories automatically dispatches
| RebuildPlayerStats → ComputePlayerPlusMinus in-process.
|
| Plus/minus note: PlayerHistory.plus_minus stores the per-game value
| supplied by the caller. ComputePlayerPlusMinus sums those values and
| writes the result to PlayerStat.plus_minus.
|
*/

// ── Test 1: Create team ───────────────────────────────────────────────────────

it('creates a team via POST /teams and persists it in the database', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('teams.store'), [
        'code' => 'LAL',
        'name' => 'Los Angeles Lakers',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('teams', [
        'code' => 'LAL',
        'name' => 'Los Angeles Lakers',
    ]);
});

// ── Test 2: Create player under team ─────────────────────────────────────────

it('creates a player under a team via POST /teams/{team}/players', function (): void {
    $user = User::factory()->create();
    $team = Team::factory()->create(['code' => 'LAL', 'name' => 'Los Angeles Lakers']);

    $response = $this->actingAs($user)->post(route('players.store', $team), [
        'first_name'    => 'LeBron',
        'last_name'     => 'James',
        'jersey_number' => 23,
        'role'          => 'Small Forward',
        'is_active'     => true,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('players', [
        'first_name' => 'LeBron',
        'last_name'  => 'James',
        'team_id'    => $team->id,
    ]);
});

// ── Test 3: Add game history to player ───────────────────────────────────────

it('adds a game history record via POST /players/{player}/histories', function (): void {
    $user     = User::factory()->create();
    $team     = Team::factory()->create();
    $opponent = Team::factory()->create();
    $player   = Player::factory()->for($team)->create();

    $response = $this->actingAs($user)->post(route('player-histories.store', $player), [
        'game_date'       => '2025-01-15',
        'playing_team_id' => $team->id,
        'opponent_team_id'=> $opponent->id,
        'plus_minus'      => 8,
    ]);

    $response->assertRedirect();

    // SQLite stores dates with a time component; check player + plus_minus, not raw date string.
    $this->assertDatabaseHas('player_histories', [
        'player_id'  => $player->id,
        'plus_minus' => 8,
    ]);
});

// ── Test 4: Core plus/minus computation ──────────────────────────────────────

it('computes player plus/minus as the sum of all per-game plus_minus values', function (): void {
    /*
     * Game 1: plus_minus = +8  (e.g. team scored 58, opponent 50: 58 − 50 = +8)
     * Game 2: plus_minus = −3  (e.g. team scored 45, opponent 48: 45 − 48 = −3)
     * Total:  +8 + (−3) = +5
     */
    $user     = User::factory()->create();
    $team     = Team::factory()->create();
    $opponent = Team::factory()->create();
    $player   = Player::factory()->for($team)->create();

    // Game 1: +8
    $this->actingAs($user)->post(route('player-histories.store', $player), [
        'game_date'        => '2025-01-01',
        'playing_team_id'  => $team->id,
        'opponent_team_id' => $opponent->id,
        'plus_minus'       => 8,
    ]);

    // Game 2: −3
    $this->actingAs($user)->post(route('player-histories.store', $player), [
        'game_date'        => '2025-01-02',
        'playing_team_id'  => $team->id,
        'opponent_team_id' => $opponent->id,
        'plus_minus'       => -3,
    ]);

    // Both jobs ran synchronously (QUEUE_CONNECTION=sync).
    $stats = PlayerStat::where('player_id', $player->id)->first();

    expect($stats)->not->toBeNull();
    expect((int) $stats->plus_minus)->toBe(5);
});

// ── Test 5: Recompute updates plus/minus when a new game is added ─────────────

it('updates plus/minus when a third game is added and stats are recomputed', function (): void {
    /*
     * First two games: +8 + (−3) = +5  (same as Test 4)
     * Third game added: +7
     * Recomputed total: +5 + (+7) = +12
     */
    $user     = User::factory()->create();
    $team     = Team::factory()->create();
    $opponent = Team::factory()->create();
    $player   = Player::factory()->for($team)->create();

    foreach ([['date' => '2025-01-01', 'pm' => 8], ['date' => '2025-01-02', 'pm' => -3]] as $game) {
        $this->actingAs($user)->post(route('player-histories.store', $player), [
            'game_date'        => $game['date'],
            'playing_team_id'  => $team->id,
            'opponent_team_id' => $opponent->id,
            'plus_minus'       => $game['pm'],
        ]);
    }

    $afterTwoGames = PlayerStat::where('player_id', $player->id)->first();
    expect((int) $afterTwoGames->plus_minus)->toBe(5);

    // Add third game: +7 — triggers automatic recompute
    $this->actingAs($user)->post(route('player-histories.store', $player), [
        'game_date'        => '2025-01-03',
        'playing_team_id'  => $team->id,
        'opponent_team_id' => $opponent->id,
        'plus_minus'       => 7,
    ]);

    $afterThreeGames = PlayerStat::where('player_id', $player->id)->first()->fresh();
    expect((int) $afterThreeGames->plus_minus)->toBe(12);
});

// ── Test 6: Edge case — player with zero game records ────────────────────────

it('leaves plus_minus as null for a player with no game history', function (): void {
    /*
     * Design note: ComputePlayerPlusMinus only writes to player_stats when at
     * least one PlayerHistory row has a non-null plus_minus value. With zero
     * history rows, RebuildPlayerStats creates the player_stats row via
     * PlayerStatsAggregator::zeroed(), which sets plus_minus = null, and
     * ComputePlayerPlusMinus returns early without overwriting that null.
     * Result: plus_minus === null, displayed as "—" in the UI per CLAUDE.md.
     */
    $player = Player::factory()->create();

    RebuildPlayerStats::dispatchSync($player->id);

    $stats = PlayerStat::where('player_id', $player->id)->first();

    expect($stats)->not->toBeNull();
    expect($stats->plus_minus)->toBeNull();
});
