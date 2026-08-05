<?php

namespace Tests\Feature\LiveGame;

use App\Events\LiveGameStateUpdated;
use App\Jobs\RecommendLiveLineup;
use App\Models\LiveGame;
use App\Models\LiveGamePlayerDelegation;
use App\Models\LiveGamePlayerStat;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use App\Services\LiveGame\LiveGameEventRules;
use App\Services\LiveGame\LiveLineupEligibilityFilter;
use App\Services\PythonEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LiveLineupSuggestionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([LiveGameStateUpdated::class]);
    }

    public function test_a_user_on_an_unrelated_team_cannot_request_a_suggestion(): void
    {
        [$game] = $this->liveGame();
        $outsider = User::factory()->forTeam(Team::factory()->create())->create(['email_verified_at' => now()]);

        $this->actingAs($outsider)
            ->postJson("/live-games/{$game->id}/suggested-lineup")
            ->assertForbidden();
    }

    public function test_a_suggestion_cannot_be_requested_before_the_game_is_live(): void
    {
        [$game, $coach] = $this->liveGame(['status' => LiveGame::STATUS_SETUP]);

        $this->actingAs($coach)
            ->postJson("/live-games/{$game->id}/suggested-lineup")
            ->assertStatus(422)
            ->assertJsonValidationErrors('game');
    }

    public function test_the_first_request_dispatches_the_job_and_reports_pending(): void
    {
        Queue::fake();
        [$game, $coach] = $this->liveGame();

        $this->actingAs($coach)
            ->postJson("/live-games/{$game->id}/suggested-lineup")
            ->assertOk()
            ->assertJson(['pending' => true, 'suggestion' => null]);

        Queue::assertPushed(
            RecommendLiveLineup::class,
            fn (RecommendLiveLineup $job): bool => $job->liveGameId === $game->id
                && $job->teamId === (int) $game->home_team_id,
        );
    }

    public function test_a_completed_job_makes_both_columns_available_on_the_next_request(): void
    {
        $this->fakeEngine();
        [$game, $coach] = $this->liveGame();

        foreach ($game->activePlayerIdsForSide(LiveGame::SIDE_HOME) as $playerId) {
            $this->liveStat($game, Player::query()->findOrFail($playerId), [
                'minutes_seconds' => 1080,
                'points' => 10,
            ]);
        }

        // The sync queue runs the job during this first call, but the response was already
        // decided against an empty cache — so this is the pending poll.
        $this->actingAs($coach)->postJson("/live-games/{$game->id}/suggested-lineup")->assertOk();

        $response = $this->actingAs($coach)
            ->postJson("/live-games/{$game->id}/suggested-lineup")
            ->assertOk();

        $response->assertJson(['pending' => false]);
        $response->assertJsonPath('suggestion.season.confidence', 0.9);
        $response->assertJsonPath('suggestion.tonight.confidence', 0.8);
        $this->assertCount(5, $response->json('suggestion.season.recommended_lineup'));
        $this->assertCount(5, $response->json('suggestion.tonight.recommended_lineup'));
    }

    /**
     * Design decision, not a gap: tonight's data has no opinion until players have logged
     * real minutes, and the interface must not invent one. Early in Q1 the season column
     * stands alone.
     */
    public function test_the_tonight_column_is_empty_until_someone_has_a_usable_live_sample(): void
    {
        $this->fakeEngine();
        [$game, $coach] = $this->liveGame();

        $this->actingAs($coach)->postJson("/live-games/{$game->id}/suggested-lineup")->assertOk();

        $response = $this->actingAs($coach)
            ->postJson("/live-games/{$game->id}/suggested-lineup")
            ->assertOk();

        $this->assertCount(5, $response->json('suggestion.season.recommended_lineup'));
        $this->assertSame([], $response->json('suggestion.tonight.recommended_lineup'));
    }

    public function test_the_two_columns_are_ranked_from_different_samples(): void
    {
        $engine = $this->fakeEngine();
        [$game, $coach] = $this->liveGame();
        $starter = Player::query()->findOrFail($game->starting_player_ids[0]);
        $this->liveStat($game, $starter, ['minutes_seconds' => 1080, 'points' => 18]);

        $this->actingAs($coach)->postJson("/live-games/{$game->id}/suggested-lineup")->assertOk();

        $this->assertSame(['lineup', 'lineup'], array_column($engine->calls, 'command'));

        $season = collect($engine->calls[0]['payload']['home_team_players'])->firstWhere('player_id', $starter->id);
        $tonight = collect($engine->calls[1]['payload']['home_team_players'])->firstWhere('player_id', $starter->id);

        $this->assertNotNull($tonight, 'a starter with 18 minutes belongs in the tonight payload');
        $this->assertSame(36.0, $tonight['pts'], '18 points in 18 minutes is 36 per 36');
        $this->assertNotSame($season['pts'], $tonight['pts']);
    }

    public function test_the_response_reports_why_a_disqualified_player_was_excluded(): void
    {
        Queue::fake();
        [$game, $coach] = $this->liveGame();
        $fouledOut = Player::query()->findOrFail($game->starting_player_ids[0]);
        $this->liveStat($game, $fouledOut, [
            'personal_fouls' => LiveGameEventRules::MAX_PERSONAL_FOULS,
        ]);

        $this->actingAs($coach)
            ->postJson("/live-games/{$game->id}/suggested-lineup")
            ->assertOk()
            ->assertJsonPath(
                "reasons.{$fouledOut->id}",
                LiveLineupEligibilityFilter::REASON_DISQUALIFIED,
            );
    }

    public function test_a_player_assigned_to_an_assistant_is_reported_as_locked(): void
    {
        Queue::fake();
        [$game, $coach] = $this->liveGame();
        $delegated = Player::query()->findOrFail($game->starting_player_ids[0]);
        $assistant = User::factory()->forTeam($game->homeTeam)->create(['email_verified_at' => now()]);

        LiveGamePlayerDelegation::query()->create([
            'live_game_id' => $game->id,
            'coach_user_id' => $assistant->id,
            'player_id' => $delegated->id,
        ]);

        $this->actingAs($coach)
            ->postJson("/live-games/{$game->id}/suggested-lineup")
            ->assertOk()
            ->assertJsonPath('locked_player_ids.0', $delegated->id)
            ->assertJsonPath(
                "reasons.{$delegated->id}",
                LiveLineupEligibilityFilter::REASON_ASSIGNED_TO_ASSISTANT,
            );
    }

    public function test_applying_a_lineup_records_one_substitution_per_change(): void
    {
        [$game, $coach] = $this->liveGame();
        $bench = Player::factory()->count(2)->for($game->homeTeam)->create(['is_active' => true]);
        $active = $game->activePlayerIdsForSide(LiveGame::SIDE_HOME);

        $chosen = array_merge(array_slice($active, 0, 3), $bench->modelKeys());

        $this->actingAs($coach)
            ->postJson("/live-games/{$game->id}/suggested-lineup/apply", ['player_ids' => $chosen])
            ->assertOk()
            ->assertJsonCount(2, 'applied');

        $this->assertSame(2, $game->events()->where('type', 'substitution')->count());
        $this->assertEqualsCanonicalizing(
            $chosen,
            $game->fresh()->activePlayerIdsForSide(LiveGame::SIDE_HOME),
        );
    }

    public function test_applying_the_lineup_that_is_already_on_the_floor_records_nothing(): void
    {
        [$game, $coach] = $this->liveGame();
        $active = $game->activePlayerIdsForSide(LiveGame::SIDE_HOME);

        $this->actingAs($coach)
            ->postJson("/live-games/{$game->id}/suggested-lineup/apply", ['player_ids' => $active])
            ->assertOk()
            ->assertJsonCount(0, 'applied');

        $this->assertSame(0, $game->events()->where('type', 'substitution')->count());
    }

    public function test_a_lineup_cannot_be_applied_while_the_clock_is_running(): void
    {
        [$game, $coach] = $this->liveGame(['clock_running' => true, 'clock_started_at' => now()]);
        $bench = Player::factory()->for($game->homeTeam)->create(['is_active' => true]);
        $active = $game->activePlayerIdsForSide(LiveGame::SIDE_HOME);
        $chosen = array_merge(array_slice($active, 0, 4), [$bench->id]);

        $this->actingAs($coach)
            ->postJson("/live-games/{$game->id}/suggested-lineup/apply", ['player_ids' => $chosen])
            ->assertStatus(422)
            ->assertJsonValidationErrors('game');

        $this->assertSame(0, $game->events()->where('type', 'substitution')->count());
    }

    public function test_a_lineup_touching_a_delegated_player_cannot_be_applied_by_the_main_coach(): void
    {
        [$game, $coach] = $this->liveGame();
        $bench = Player::factory()->for($game->homeTeam)->create(['is_active' => true]);
        $assistant = User::factory()->forTeam($game->homeTeam)->create(['email_verified_at' => now()]);
        $active = $game->activePlayerIdsForSide(LiveGame::SIDE_HOME);

        // The assistant owns the player the main coach would have to sub out.
        LiveGamePlayerDelegation::query()->create([
            'live_game_id' => $game->id,
            'coach_user_id' => $assistant->id,
            'player_id' => $active[4],
        ]);

        $chosen = array_merge(array_slice($active, 0, 4), [$bench->id]);

        $this->actingAs($coach)
            ->postJson("/live-games/{$game->id}/suggested-lineup/apply", ['player_ids' => $chosen])
            ->assertStatus(422)
            ->assertJsonValidationErrors('player_ids');

        $this->assertSame(0, $game->events()->where('type', 'substitution')->count());
    }

    public function test_applying_a_lineup_requires_exactly_five_players(): void
    {
        [$game, $coach] = $this->liveGame();
        $active = $game->activePlayerIdsForSide(LiveGame::SIDE_HOME);

        $this->actingAs($coach)
            ->postJson("/live-games/{$game->id}/suggested-lineup/apply", [
                'player_ids' => array_slice($active, 0, 4),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('player_ids');
    }

    public function test_a_lineup_cannot_pull_in_a_player_from_another_team(): void
    {
        [$game, $coach] = $this->liveGame();
        $stranger = Player::factory()->for(Team::factory())->create(['is_active' => true]);
        $active = $game->activePlayerIdsForSide(LiveGame::SIDE_HOME);
        $chosen = array_merge(array_slice($active, 0, 4), [$stranger->id]);

        $this->actingAs($coach)
            ->postJson("/live-games/{$game->id}/suggested-lineup/apply", ['player_ids' => $chosen])
            ->assertStatus(422)
            ->assertJsonValidationErrors('player_ids');

        $this->assertSame(0, $game->events()->where('type', 'substitution')->count());
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{LiveGame, User}
     */
    private function liveGame(array $attributes = []): array
    {
        $game = LiveGame::factory()->withBothLineups()->create([
            'status' => LiveGame::STATUS_LIVE,
            'clock_running' => false,
            'clock_started_at' => null,
            ...$attributes,
        ]);

        $coach = User::factory()->forTeam($game->homeTeam)->create(['email_verified_at' => now()]);
        $game->forceFill([
            'created_by_user_id' => $coach->id,
            'home_main_coach_user_id' => $coach->id,
        ])->save();

        return [$game->fresh(), $coach];
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
     * Stand in for the Python subprocess: the job's contract with the engine is what
     * matters here, not the ranker's arithmetic, which pytest already covers.
     */
    private function fakeEngine(): object
    {
        $fake = new class extends PythonEngineService
        {
            /** @var list<array{command: string, payload: array<string, mixed>}> */
            public array $calls = [];

            public function call(string $command, array $payload): array
            {
                $this->calls[] = ['command' => $command, 'payload' => $payload];

                $players = $payload['home_team_players'] ?? [];

                return [
                    'recommended_lineup' => array_map(
                        static fn (array $player): array => [
                            'player_id' => $player['player_id'],
                            'name' => $player['name'],
                            'plus_minus_score' => 10.0,
                        ],
                        array_slice($players, 0, 5),
                    ),
                    // Distinct per call so a test can tell the two columns apart.
                    'confidence' => count($this->calls) === 1 ? 0.9 : 0.8,
                ];
            }
        };

        $this->app->instance(PythonEngineService::class, $fake);

        return $fake;
    }
}
