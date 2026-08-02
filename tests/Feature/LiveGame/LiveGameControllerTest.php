<?php

namespace Tests\Feature\LiveGame;

use App\Events\LiveGameStateUpdated;
use App\Jobs\RebuildPlayerStats;
use App\Models\LiveGame;
use App\Models\LiveGameEvent;
use App\Models\LiveGamePlayerStat;
use App\Models\Player;
use App\Models\PlayerHistory;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LiveGameControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_verified_user_can_view_live_game_pages(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $game = LiveGame::factory()->create();
        Player::factory()->for($game->homeTeam)->create(['is_active' => true]);

        $this->actingAs($user)->get(route('live-games.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('LiveGames/Index')->has('liveGames'));

        $this->actingAs($user)->get(route('live-games.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('LiveGames/Create')->has('teams.0.players'));

        $this->actingAs($user)->get(route('live-games.show', $game))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('LiveGames/Show')
                ->has('liveGame')
                ->has('snapshot.clock')
                ->has('players')
            );
    }

    public function test_a_verified_user_can_create_a_live_game_with_a_starting_five(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $home = Team::factory()->create();
        $opponent = Team::factory()->create();
        $players = Player::factory()->count(5)->for($home)->create(['is_active' => true]);

        $response = $this->actingAs($user)->post(route('live-games.store'), [
            'home_team_id' => $home->id,
            'opponent_team_id' => $opponent->id,
            'period_length_seconds' => 480,
            'starting_player_ids' => $players->modelKeys(),
        ]);

        $game = LiveGame::query()->firstOrFail();

        $response->assertRedirect(route('live-games.show', $game));
        $this->assertDatabaseHas('live_games', [
            'id' => $game->id,
            'home_team_id' => $home->id,
            'opponent_team_id' => $opponent->id,
            'created_by_user_id' => $user->id,
            'period_length_seconds' => 480,
            'clock_seconds_remaining' => 480,
        ]);
        $this->assertSame($players->modelKeys(), $game->starting_player_ids);
        $this->assertSame($players->modelKeys(), $game->active_player_ids);
    }

    public function test_starting_and_finishing_a_game_updates_its_lifecycle(): void
    {
        Event::fake([LiveGameStateUpdated::class]);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $game = LiveGame::factory()->create();

        $this->actingAs($user)->post(route('live-games.start', $game))
            ->assertRedirect(route('live-games.show', $game));

        $this->assertDatabaseHas('live_games', [
            'id' => $game->id,
            'status' => LiveGame::STATUS_LIVE,
            'clock_running' => true,
        ]);

        $this->actingAs($user)->post(route('live-games.finish', $game))
            ->assertRedirect(route('live-games.index'));

        $this->assertDatabaseHas('live_games', [
            'id' => $game->id,
            'status' => LiveGame::STATUS_FINISHED,
            'clock_running' => false,
        ]);
        $this->assertNotNull($game->fresh()->finished_at);
    }

    public function test_finishing_a_game_finalizes_participating_player_stats_and_queues_rebuilds(): void
    {
        Event::fake([LiveGameStateUpdated::class]);
        Queue::fake();
        $this->travelTo('2026-08-02 12:00:00');

        $user = User::factory()->create(['email_verified_at' => now()]);
        $game = LiveGame::factory()->create([
            'status' => LiveGame::STATUS_LIVE,
            'game_date' => '2026-08-02',
            'clock_running' => true,
            'clock_started_at' => now()->subSeconds(90),
        ]);
        $player = Player::factory()->for($game->homeTeam)->create();
        $game->update([
            'starting_player_ids' => [$player->id],
            'active_player_ids' => [$player->id],
        ]);
        LiveGameEvent::factory()->create([
            'live_game_id' => $game->id,
            'sequence' => 1,
            'type' => 'shot_made',
            'team_scope' => 'own',
            'player_id' => $player->id,
            'payload' => ['points' => 3],
        ]);

        $this->actingAs($user)->post(route('live-games.finish', $game))
            ->assertRedirect(route('live-games.index'));

        $this->assertDatabaseHas('player_histories', [
            'player_id' => $player->id,
            'playing_team_id' => $game->home_team_id,
            'opponent_team_id' => $game->opponent_team_id,
            'minutes_played' => 1.50,
            'points' => 3,
            'field_goals_made' => 1,
            'field_goals_attempted' => 1,
            'three_pointers_made' => 1,
            'three_pointers_attempted' => 1,
            'is_started' => true,
            'notes' => "Finalized from live game #{$game->id}",
        ]);
        Queue::assertPushed(RebuildPlayerStats::class, fn (RebuildPlayerStats $job): bool => $job->playerId === $player->id);

        $history = PlayerHistory::query()->where('player_id', $player->id)->sole();
        $this->assertSame('2026-08-02', $history->game_date->toDateString());
        $this->assertSame(1, LiveGamePlayerStat::query()->where('live_game_id', $game->id)->count());
    }
}
