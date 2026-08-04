<?php

namespace Tests\Feature\LiveGame;

use App\Events\LiveGameStateUpdated;
use App\Models\LiveGame;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class LiveGameEventRecordingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_an_event_updates_the_projection_returns_a_snapshot_and_broadcasts_it(): void
    {
        Event::fake([LiveGameStateUpdated::class]);

        $game = LiveGame::factory()->create([
            'status' => LiveGame::STATUS_LIVE,
            'clock_running' => true,
            'clock_started_at' => now(),
        ]);
        $user = User::factory()->forTeam($game->homeTeam)->create(['email_verified_at' => now()]);
        $player = Player::factory()->for($game->homeTeam)->create();
        $opponentPlayer = Player::factory()->for($game->opponentTeam)->create();
        $game->update([
            'starting_player_ids' => [$player->id],
            'active_player_ids' => [$player->id],
            'opponent_starting_player_ids' => [$opponentPlayer->id],
            'opponent_active_player_ids' => [$opponentPlayer->id],
        ]);

        $response = $this->actingAs($user)->postJson("/live-games/{$game->id}/events", [
            'type' => 'shot_made',
            'team_scope' => 'own',
            'player_id' => $player->id,
            'payload' => ['points' => 3],
        ]);

        $response->assertOk()
            ->assertJsonPath('score.home', 3)
            ->assertJsonPath('score.opponent', 0)
            ->assertJsonPath('active_player_ids.0', $player->id)
            ->assertJsonPath('stats.0.player_id', $player->id)
            ->assertJsonPath('stats.0.points', 3)
            ->assertJsonPath('events.0.sequence', 1)
            ->assertJsonStructure(['liveGame', 'score', 'clock', 'active_player_ids', 'opponent_active_player_ids', 'stats', 'events', 'alerts']);

        $this->assertDatabaseHas('live_game_events', [
            'live_game_id' => $game->id,
            'sequence' => 1,
            'type' => 'shot_made',
            'team_scope' => 'own',
            'player_id' => $player->id,
            'recorded_by_user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('live_games', ['id' => $game->id, 'home_score' => 3]);

        Event::assertDispatched(LiveGameStateUpdated::class, function (LiveGameStateUpdated $event) use ($game): bool {
            return $event->liveGameId === $game->id
                && $event->snapshot['score'] === ['home' => 3, 'opponent' => 0];
        });
    }

    public function test_it_assigns_the_next_sequence_for_each_game(): void
    {
        Event::fake([LiveGameStateUpdated::class]);

        $game = LiveGame::factory()->create([
            'status' => LiveGame::STATUS_LIVE,
            'clock_running' => true,
            'clock_started_at' => now(),
        ]);
        $user = User::factory()->forTeam($game->homeTeam)->create(['email_verified_at' => now()]);
        $player = Player::factory()->for($game->homeTeam)->create();
        $game->update([
            'starting_player_ids' => [$player->id],
            'active_player_ids' => [$player->id],
        ]);

        $this->actingAs($user)->postJson("/live-games/{$game->id}/events", [
            'type' => 'assist',
            'team_scope' => 'own',
            'player_id' => $player->id,
        ])->assertOk();

        $this->actingAs($user)->postJson("/live-games/{$game->id}/events", [
            'type' => 'turnover',
            'team_scope' => 'own',
            'player_id' => $player->id,
        ])->assertOk();

        $this->assertDatabaseHas('live_game_events', ['live_game_id' => $game->id, 'sequence' => 1, 'type' => 'assist']);
        $this->assertDatabaseHas('live_game_events', ['live_game_id' => $game->id, 'sequence' => 2, 'type' => 'turnover']);
    }

    public function test_a_timeout_is_attributed_to_the_recording_coachs_team(): void
    {
        Event::fake([LiveGameStateUpdated::class]);

        $game = LiveGame::factory()->withBothLineups()->create(['status' => LiveGame::STATUS_LIVE]);
        $opponentCoach = User::factory()->forTeam($game->opponentTeam)->create(['email_verified_at' => now()]);
        $game->forceFill(['opponent_main_coach_user_id' => $opponentCoach->id])->save();

        $this->actingAs($opponentCoach)->postJson("/live-games/{$game->id}/events", [
            'type' => 'timeout',
            'team_scope' => 'own',
        ])->assertOk();

        $timeout = $game->events()->where('type', 'timeout')->firstOrFail();
        $this->assertSame('own', $timeout->team_scope);
        $this->assertSame((int) $game->opponent_team_id, (int) $timeout->payload['team_id']);
        $this->assertSame(0, $game->fresh()->home_score);
    }

    public function test_a_user_on_neither_team_cannot_call_a_timeout(): void
    {
        Event::fake([LiveGameStateUpdated::class]);

        $game = LiveGame::factory()->withBothLineups()->create(['status' => LiveGame::STATUS_LIVE]);
        // The creator is a participant by definition, but has no team side to attribute to.
        $creator = User::query()->findOrFail($game->created_by_user_id);

        $this->actingAs($creator)
            ->postJson("/live-games/{$game->id}/events", ['type' => 'timeout', 'team_scope' => 'own'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('game');
    }

    public function test_an_offensive_rebound_and_a_technical_foul_reach_their_own_columns(): void
    {
        Event::fake([LiveGameStateUpdated::class]);

        $game = LiveGame::factory()->withBothLineups()->create([
            'status' => LiveGame::STATUS_LIVE,
            'clock_running' => true,
            'clock_started_at' => now(),
        ]);
        $coach = User::factory()->forTeam($game->homeTeam)->create(['email_verified_at' => now()]);
        $player = Player::query()->findOrFail($game->starting_player_ids[0]);

        $this->actingAs($coach)->postJson("/live-games/{$game->id}/events", [
            'type' => 'rebound',
            'team_scope' => 'own',
            'player_id' => $player->id,
            'payload' => ['kind' => 'offensive'],
        ])->assertOk();

        $this->actingAs($coach)->postJson("/live-games/{$game->id}/events", [
            'type' => 'foul',
            'team_scope' => 'own',
            'player_id' => $player->id,
            'payload' => ['kind' => 'technical'],
        ])->assertOk();

        $this->assertDatabaseHas('live_game_player_stats', [
            'live_game_id' => $game->id,
            'player_id' => $player->id,
            'offensive_rebounds' => 1,
            'defensive_rebounds' => 0,
            'technical_fouls' => 1,
            'personal_fouls' => 0,
        ]);
    }

    public function test_a_coach_cannot_record_events_for_the_other_team(): void
    {
        Event::fake([LiveGameStateUpdated::class]);

        $game = LiveGame::factory()->create([
            'status' => LiveGame::STATUS_LIVE,
            'clock_running' => true,
            'clock_started_at' => now(),
        ]);
        $homeCoach = User::factory()->forTeam($game->homeTeam)->create(['email_verified_at' => now()]);
        $homePlayer = Player::factory()->for($game->homeTeam)->create();
        $opponentPlayer = Player::factory()->for($game->opponentTeam)->create();
        $game->update([
            'starting_player_ids' => [$homePlayer->id],
            'active_player_ids' => [$homePlayer->id],
            'opponent_starting_player_ids' => [$opponentPlayer->id],
            'opponent_active_player_ids' => [$opponentPlayer->id],
        ]);

        $this->actingAs($homeCoach)->postJson("/live-games/{$game->id}/events", [
            'type' => 'shot_made',
            'team_scope' => 'own',
            'player_id' => $opponentPlayer->id,
            'payload' => ['points' => 2],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('player_id');
    }

    public function test_an_opponent_coach_can_score_for_their_roster(): void
    {
        Event::fake([LiveGameStateUpdated::class]);

        $game = LiveGame::factory()->create([
            'status' => LiveGame::STATUS_LIVE,
            'clock_running' => true,
            'clock_started_at' => now(),
        ]);
        $opponentCoach = User::factory()->forTeam($game->opponentTeam)->create(['email_verified_at' => now()]);
        $homePlayer = Player::factory()->for($game->homeTeam)->create();
        $opponentPlayer = Player::factory()->for($game->opponentTeam)->create();
        $game->update([
            'starting_player_ids' => [$homePlayer->id],
            'active_player_ids' => [$homePlayer->id],
            'opponent_starting_player_ids' => [$opponentPlayer->id],
            'opponent_active_player_ids' => [$opponentPlayer->id],
        ]);

        $this->actingAs($opponentCoach)->postJson("/live-games/{$game->id}/events", [
            'type' => 'shot_made',
            'team_scope' => 'own',
            'player_id' => $opponentPlayer->id,
            'payload' => ['points' => 3],
        ])->assertOk()
            ->assertJsonPath('score.home', 0)
            ->assertJsonPath('score.opponent', 3);
    }

    public function test_it_rejects_an_invalid_event_payload(): void
    {
        $game = LiveGame::factory()->create();
        $user = User::factory()->forTeam($game->homeTeam)->create(['email_verified_at' => now()]);

        $this->actingAs($user)->postJson("/live-games/{$game->id}/events", [
            'type' => 'shot_made',
            'team_scope' => 'own',
            'payload' => ['points' => 1],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['player_id', 'payload.points']);
    }

    public function test_it_returns_validation_errors_when_recording_against_a_setup_game(): void
    {
        Event::fake([LiveGameStateUpdated::class]);

        $game = LiveGame::factory()->create();
        $user = User::factory()->forTeam($game->homeTeam)->create(['email_verified_at' => now()]);
        $player = Player::factory()->for($game->homeTeam)->create();
        $game->update([
            'starting_player_ids' => [$player->id],
            'active_player_ids' => [$player->id],
        ]);

        $this->actingAs($user)->postJson("/live-games/{$game->id}/events", [
            'type' => 'shot_made',
            'team_scope' => 'own',
            'player_id' => $player->id,
            'payload' => ['points' => 2],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('game');

        $this->assertDatabaseCount('live_game_events', 0);
    }
}
