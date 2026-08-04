<?php

namespace Tests\Unit\Services\LiveGame;

use App\Models\LiveGame;
use App\Models\LiveGamePlayerDelegation;
use App\Models\Player;
use App\Models\User;
use App\Services\LiveGame\LiveGameClockService;
use App\Services\LiveGame\LiveGameEventRecorder;
use App\Services\LiveGame\LiveGameEventRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LiveGameEventRecorderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
    }

    public function test_it_rejects_recording_against_a_setup_game(): void
    {
        [$game, $player, $user] = $this->gameWithStarter();

        $this->assertValidationException(
            fn (): array => $this->record($game, $user, $this->ownEvent($player)),
            'game',
        );

        $this->assertDatabaseCount('live_game_events', 0);
    }

    public function test_it_rejects_non_correction_events_against_a_finished_game(): void
    {
        [$game, $player, $user] = $this->gameWithStarter(LiveGame::STATUS_FINISHED);

        $this->assertValidationException(
            fn (): array => $this->record($game, $user, $this->ownEvent($player)),
            'game',
        );

        $this->assertDatabaseCount('live_game_events', 0);
    }

    public function test_it_rejects_a_substitution_when_the_outgoing_player_is_inactive(): void
    {
        [$game, $starter, $user] = $this->gameWithStarter(LiveGame::STATUS_LIVE);
        $bench = Player::factory()->for($game->homeTeam)->create();

        $this->assertValidationException(
            fn (): array => $this->record($game, $user, $this->substitution($bench, $starter)),
            'payload.player_out_id',
        );

        $this->assertDatabaseCount('live_game_events', 0);
    }

    public function test_it_rejects_a_substitution_when_the_incoming_player_is_already_active(): void
    {
        [$game, $starter, $user] = $this->gameWithStarter(LiveGame::STATUS_LIVE);
        $otherStarter = Player::factory()->for($game->homeTeam)->create();
        $bench = Player::factory()->for($game->homeTeam)->create();
        $game->update(['starting_player_ids' => [$starter->id, $otherStarter->id], 'active_player_ids' => [$starter->id, $otherStarter->id]]);

        $this->record($game, $user, $this->substitution($starter, $bench));

        $this->assertValidationException(
            fn (): array => $this->record($game, $user, $this->substitution($otherStarter, $bench)),
            'payload.player_in_id',
        );

        $this->assertDatabaseCount('live_game_events', 1);
    }

    public function test_it_records_running_clock_substitutions_with_the_effective_clock_and_correct_stint_duration(): void
    {
        $this->travelTo('2026-08-02 12:00:00');
        [$game, $starter, $user] = $this->gameWithStarter(LiveGame::STATUS_LIVE);
        $bench = Player::factory()->for($game->homeTeam)->create();
        $this->runClock($game);

        // Substitutions happen during a stoppage, so the clock is stopped after 90 seconds
        // of play and the substitution is stamped with the elapsed clock.
        $this->travel(90)->seconds();
        $this->stopClock($game);
        $this->record($game, $user, $this->substitution($starter, $bench));

        $this->assertDatabaseHas('live_game_events', [
            'live_game_id' => $game->id,
            'type' => 'substitution',
            'clock_seconds_remaining' => 510,
        ]);
        $this->assertDatabaseHas('live_game_lineup_stints', [
            'live_game_id' => $game->id,
            'player_id' => $starter->id,
            'duration_seconds' => 90,
        ]);
    }

    public function test_it_rejects_an_own_player_event_for_a_benched_player_until_a_substitution_activates_them(): void
    {
        [$game, $starter, $user] = $this->gameWithStarter(LiveGame::STATUS_LIVE);
        $bench = Player::factory()->for($game->homeTeam)->create();
        $this->runClock($game);

        $this->assertValidationException(
            fn (): array => $this->record($game, $user, $this->ownEvent($bench)),
            'player_id',
        );

        $this->stopClock($game);
        $this->record($game, $user, $this->substitution($starter, $bench));
        $this->runClock($game);
        $this->record($game, $user, $this->ownEvent($bench));

        $this->assertDatabaseHas('live_game_events', [
            'live_game_id' => $game->id,
            'type' => 'shot_made',
            'player_id' => $bench->id,
        ]);
    }

    public function test_it_rejects_recording_own_events_for_players_delegated_to_an_assistant(): void
    {
        [$game, $starterA, $mainCoach] = $this->gameWithStarter(LiveGame::STATUS_LIVE);
        $starterB = Player::factory()->for($game->homeTeam)->create();
        $assistant = User::factory()->forTeam($game->homeTeam)->create();

        $game->forceFill([
            'starting_player_ids' => [$starterA->id, $starterB->id],
            'active_player_ids' => [$starterA->id, $starterB->id],
            'home_main_coach_user_id' => $mainCoach->id,
        ])->save();
        $this->runClock($game);

        LiveGamePlayerDelegation::query()->create([
            'live_game_id' => $game->id,
            'coach_user_id' => $assistant->id,
            'player_id' => $starterA->id,
        ]);

        // Main coach cannot record for delegated-away players.
        $this->assertValidationException(
            fn (): array => $this->record($game, $mainCoach, $this->ownEvent($starterA)),
            'player_id',
        );

        // Assistant can record only for their delegated players.
        $this->record($game, $assistant, $this->ownEvent($starterA));
        $this->assertDatabaseHas('live_game_events', [
            'live_game_id' => $game->id,
            'type' => 'shot_made',
            'player_id' => $starterA->id,
            'recorded_by_user_id' => $assistant->id,
        ]);

        $this->assertValidationException(
            fn (): array => $this->record($game, $assistant, $this->ownEvent($starterB)),
            'player_id',
        );
    }

    public function test_it_rejects_substitutions_when_involved_players_are_not_delegated_to_the_recording_coach(): void
    {
        [$game, $starterA, $mainCoach] = $this->gameWithStarter(LiveGame::STATUS_LIVE);
        $assistant = User::factory()->forTeam($game->homeTeam)->create();

        $benchDelegated = Player::factory()->for($game->homeTeam)->create();
        $benchNotDelegated = Player::factory()->for($game->homeTeam)->create();

        $game->forceFill([
            'starting_player_ids' => [$starterA->id],
            'active_player_ids' => [$starterA->id],
            'home_main_coach_user_id' => $mainCoach->id,
        ])->save();

        LiveGamePlayerDelegation::query()->insert([
            [
                'live_game_id' => $game->id,
                'coach_user_id' => $assistant->id,
                'player_id' => $starterA->id,
            ],
            [
                'live_game_id' => $game->id,
                'coach_user_id' => $assistant->id,
                'player_id' => $benchDelegated->id,
            ],
        ]);

        // Allowed: both out and in are delegated to the assistant.
        $this->record($game, $assistant, $this->substitution($starterA, $benchDelegated));
        $this->assertDatabaseHas('live_game_events', [
            'live_game_id' => $game->id,
            'type' => 'substitution',
            'recorded_by_user_id' => $assistant->id,
        ]);

        // Rejected: incoming player is not delegated to this coach.
        $this->assertValidationException(
            fn (): array => $this->record($game, $assistant, $this->substitution($starterA, $benchNotDelegated)),
            'payload.player_in_id',
        );
    }

    public function test_it_rejects_a_field_goal_while_the_clock_is_stopped(): void
    {
        [$game, $player, $user] = $this->gameWithStarter(LiveGame::STATUS_LIVE);

        $this->assertValidationException(
            fn (): array => $this->record($game, $user, $this->ownEvent($player)),
            'game',
        );

        $this->assertDatabaseCount('live_game_events', 0);
    }

    public function test_it_rejects_a_free_throw_while_the_clock_is_running(): void
    {
        [$game, $player, $user] = $this->gameWithStarter(LiveGame::STATUS_LIVE);
        $this->runClock($game);

        $this->assertValidationException(
            fn (): array => $this->record($game, $user, [
                'type' => 'free_throw_made',
                'team_scope' => 'own',
                'player_id' => $player->id,
            ]),
            'game',
        );

        $this->assertDatabaseCount('live_game_events', 0);
    }

    public function test_it_records_a_free_throw_while_the_clock_is_stopped(): void
    {
        [$game, $player, $user] = $this->gameWithStarter(LiveGame::STATUS_LIVE);

        $this->record($game, $user, [
            'type' => 'free_throw_made',
            'team_scope' => 'own',
            'player_id' => $player->id,
        ]);

        $this->assertDatabaseCount('live_game_events', 1);
    }

    public function test_recording_a_foul_stops_a_running_clock(): void
    {
        $this->travelTo('2026-08-02 12:00:00');
        [$game, $player, $user] = $this->gameWithStarter(LiveGame::STATUS_LIVE);
        $this->runClock($game);

        $this->travel(90)->seconds();
        $snapshot = $this->record($game, $user, [
            'type' => 'foul',
            'team_scope' => 'own',
            'player_id' => $player->id,
            'payload' => ['kind' => 'personal'],
        ]);

        $this->assertFalse($snapshot['clock']['running']);
        $this->assertSame(510, $snapshot['clock']['seconds_remaining']);
        $this->assertDatabaseHas('live_games', [
            'id' => $game->id,
            'clock_running' => false,
            'clock_started_at' => null,
        ]);
    }

    public function test_it_rejects_every_event_but_a_correction_once_the_period_has_expired(): void
    {
        [$game, $player, $user] = $this->gameWithStarter(LiveGame::STATUS_LIVE);
        $game->forceFill(['clock_seconds_remaining' => 0, 'clock_running' => false])->save();

        $this->assertValidationException(
            fn (): array => $this->record($game, $user, [
                'type' => 'free_throw_made',
                'team_scope' => 'own',
                'player_id' => $player->id,
            ]),
            'game',
        );

        $this->assertDatabaseCount('live_game_events', 0);
    }

    public function test_it_allows_a_correction_once_the_period_has_expired(): void
    {
        [$game, $player, $user] = $this->gameWithStarter(LiveGame::STATUS_LIVE);
        $this->runClock($game);
        $this->record($game, $user, $this->ownEvent($player));
        $recorded = $game->events()->firstOrFail();

        $game->forceFill(['clock_seconds_remaining' => 0, 'clock_running' => false])->save();

        $this->record($game, $user, [
            'type' => 'correction',
            'team_scope' => 'game',
            'voids_event_id' => $recorded->id,
        ]);

        $this->assertDatabaseCount('live_game_events', 2);
    }

    public function test_it_rejects_a_personal_foul_for_a_disqualified_player(): void
    {
        [$game, $player, $user] = $this->gameWithStarter(LiveGame::STATUS_LIVE);

        for ($i = 0; $i < LiveGameEventRules::MAX_PERSONAL_FOULS; $i++) {
            $this->record($game, $user, [
                'type' => 'foul',
                'team_scope' => 'own',
                'player_id' => $player->id,
                'payload' => ['kind' => 'personal'],
            ]);
        }

        $this->assertDatabaseHas('live_game_player_stats', [
            'live_game_id' => $game->id,
            'player_id' => $player->id,
            'personal_fouls' => LiveGameEventRules::MAX_PERSONAL_FOULS,
        ]);

        $this->assertValidationException(
            fn (): array => $this->record($game, $user, [
                'type' => 'foul',
                'team_scope' => 'own',
                'player_id' => $player->id,
                'payload' => ['kind' => 'personal'],
            ]),
            'player_id',
        );

        $this->assertSame(
            LiveGameEventRules::MAX_PERSONAL_FOULS,
            $game->events()->where('type', 'foul')->count(),
        );
    }

    public function test_a_technical_foul_is_not_capped_by_the_personal_foul_limit(): void
    {
        [$game, $player, $user] = $this->gameWithStarter(LiveGame::STATUS_LIVE);

        for ($i = 0; $i < LiveGameEventRules::MAX_PERSONAL_FOULS; $i++) {
            $this->record($game, $user, [
                'type' => 'foul',
                'team_scope' => 'own',
                'player_id' => $player->id,
                'payload' => ['kind' => 'personal'],
            ]);
        }

        $this->record($game, $user, [
            'type' => 'foul',
            'team_scope' => 'own',
            'player_id' => $player->id,
            'payload' => ['kind' => 'technical'],
        ]);

        $this->assertDatabaseHas('live_game_player_stats', [
            'live_game_id' => $game->id,
            'player_id' => $player->id,
            'technical_fouls' => 1,
        ]);
    }

    public function test_it_rejects_a_second_correction_against_an_already_voided_event(): void
    {
        [$game, $player, $user] = $this->gameWithStarter(LiveGame::STATUS_LIVE);
        $this->runClock($game);
        $this->record($game, $user, $this->ownEvent($player));
        $recorded = $game->events()->firstOrFail();

        $this->record($game, $user, [
            'type' => 'correction',
            'team_scope' => 'game',
            'voids_event_id' => $recorded->id,
        ]);

        $this->assertValidationException(
            fn (): array => $this->record($game, $user, [
                'type' => 'correction',
                'team_scope' => 'game',
                'voids_event_id' => $recorded->id,
            ]),
            'voids_event_id',
        );

        $this->assertSame(1, $game->events()->where('type', 'correction')->count());
    }

    /** Put the game on a running clock so live-ball events are recordable. */
    private function runClock(LiveGame $game, int $elapsedSeconds = 0): void
    {
        $game->forceFill([
            'clock_running' => true,
            'clock_started_at' => now()->subSeconds($elapsedSeconds),
        ])->save();
    }

    /** Stop the clock so dead-ball events (free throws, timeouts, substitutions) are recordable. */
    private function stopClock(LiveGame $game): void
    {
        app(LiveGameClockService::class)->stopFor($game);
    }

    /** @return array{LiveGame, Player, User} */
    private function gameWithStarter(string $status = LiveGame::STATUS_SETUP): array
    {
        $game = LiveGame::factory()->create(['status' => $status]);
        $player = Player::factory()->for($game->homeTeam)->create();
        $user = User::factory()->forTeam($game->homeTeam)->create();
        $game->update([
            'starting_player_ids' => [$player->id],
            'active_player_ids' => [$player->id],
        ]);

        return [$game, $player, $user];
    }

    /** @return array<string, mixed> */
    private function ownEvent(Player $player): array
    {
        return [
            'type' => 'shot_made',
            'team_scope' => 'own',
            'player_id' => $player->id,
            'payload' => ['points' => 2],
        ];
    }

    /** @return array<string, mixed> */
    private function substitution(Player $playerOut, Player $playerIn): array
    {
        return [
            'type' => 'substitution',
            'team_scope' => 'game',
            'payload' => [
                'player_out_id' => $playerOut->id,
                'player_in_id' => $playerIn->id,
            ],
        ];
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function record(LiveGame $game, User $user, array $input): array
    {
        return app(LiveGameEventRecorder::class)->record($game, $user, $input);
    }

    /** @param callable(): array<string, mixed> $callback */
    private function assertValidationException(callable $callback, string $field): void
    {
        try {
            $callback();
            $this->fail('Expected event recording to fail validation.');
        } catch (ValidationException $exception) {
            $this->assertTrue($exception->errors()[$field] !== []);
        }
    }
}
