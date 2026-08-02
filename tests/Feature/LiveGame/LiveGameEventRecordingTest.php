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

        $game = LiveGame::factory()->create(['status' => LiveGame::STATUS_LIVE]);
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

        $game = LiveGame::factory()->create(['status' => LiveGame::STATUS_LIVE]);
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

    public function test_a_coach_cannot_record_events_for_the_other_team(): void
    {
        Event::fake([LiveGameStateUpdated::class]);

        $game = LiveGame::factory()->create(['status' => LiveGame::STATUS_LIVE]);
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

        $game = LiveGame::factory()->create(['status' => LiveGame::STATUS_LIVE]);
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
