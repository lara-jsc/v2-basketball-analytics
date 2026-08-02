<?php

namespace Tests\Feature\LiveGame;

use App\Events\LiveGameStateUpdated;
use App\Models\LiveGame;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class LiveGameControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_verified_user_can_view_live_game_pages(): void
    {
        $home = Team::factory()->create();
        $user = User::factory()->forTeam($home)->create(['email_verified_at' => now()]);
        $game = LiveGame::factory()->create([
            'home_team_id' => $home->id,
            'created_by_user_id' => $user->id,
        ]);
        Player::factory()->for($game->homeTeam)->create(['is_active' => true]);
        Player::factory()->for($game->opponentTeam)->create(['is_active' => true]);

        $this->actingAs($user)->get(route('live-games.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('LiveGames/Index')->has('liveGames'));

        $this->actingAs($user)->get(route('live-games.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('LiveGames/Create')
                ->has('homeTeam.players')
                ->has('opponentTeams')
                ->has('preselectedPlayerIds')
            );

        $this->actingAs($user)->get(route('live-games.show', $game))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('LiveGames/Show')
                ->has('liveGame')
                ->has('snapshot.clock')
                ->has('snapshot.opponent_active_player_ids')
                ->has('snapshot.home_lineup_ready')
                ->has('snapshot.opponent_lineup_ready')
                ->has('snapshot.both_lineups_ready')
                ->has('homePlayers')
                ->has('opponentPlayers')
                ->has('viewerSide')
                ->has('isCreator')
                ->where('isCreator', true)
                ->where('viewerSide', 'home')
            );
    }

    public function test_an_unverified_user_cannot_access_live_game_pages(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('live-games.index'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_a_team_coach_can_create_a_live_game_with_only_their_starting_five(): void
    {
        $this->travelTo('2026-08-02 12:00:00');
        $home = Team::factory()->create();
        $opponent = Team::factory()->create();
        $user = User::factory()->forTeam($home)->create(['email_verified_at' => now()]);
        $players = Player::factory()->count(5)->for($home)->create(['is_active' => true]);
        Player::factory()->count(5)->for($opponent)->create(['is_active' => true]);

        $response = $this->actingAs($user)->post(route('live-games.store'), [
            'home_team_id' => $opponent->id,
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
        $this->assertSame('2026-08-02', $game->fresh()->game_date->toDateString());
        $this->assertSame($players->modelKeys(), $game->starting_player_ids);
        $this->assertSame($players->modelKeys(), $game->active_player_ids);
        $this->assertNull($game->opponent_starting_player_ids);
        $this->assertNull($game->opponent_active_player_ids);
    }

    public function test_a_user_without_a_team_cannot_create_a_live_game(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'team_id' => null]);
        $opponent = Team::factory()->create();
        $players = Player::factory()->count(5)->create(['is_active' => true]);

        $this->actingAs($user)->post(route('live-games.store'), [
            'opponent_team_id' => $opponent->id,
            'period_length_seconds' => 600,
            'starting_player_ids' => $players->modelKeys(),
        ])->assertSessionHasErrors('home_team_id');

        $this->assertDatabaseCount('live_games', 0);
    }

    public function test_create_page_soft_preselects_player_ids_from_query(): void
    {
        $home = Team::factory()->create();
        $opponent = Team::factory()->create();
        $user = User::factory()->forTeam($home)->create(['email_verified_at' => now()]);
        $players = Player::factory()->count(5)->for($home)->create(['is_active' => true]);
        $foreign = Player::factory()->for($opponent)->create(['is_active' => true]);

        $ids = $players->modelKeys();
        $query = implode(',', [...$ids, $foreign->id]);

        $this->actingAs($user)->get(route('live-games.create', [
            'opponent_team_id' => $opponent->id,
            'player_ids' => $query,
        ]))->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('LiveGames/Create')
                ->where('preselectedOpponentTeamId', $opponent->id)
                ->where('preselectedPlayerIds', $ids)
            );
    }

    public function test_opponent_coach_can_submit_their_starting_five(): void
    {
        Event::fake([LiveGameStateUpdated::class]);

        $home = Team::factory()->create();
        $opponent = Team::factory()->create();
        $creator = User::factory()->forTeam($home)->create(['email_verified_at' => now()]);
        $joiner = User::factory()->forTeam($opponent)->create(['email_verified_at' => now()]);
        $homePlayers = Player::factory()->count(5)->for($home)->create(['is_active' => true]);
        $opponentPlayers = Player::factory()->count(5)->for($opponent)->create(['is_active' => true]);

        $game = LiveGame::factory()->create([
            'home_team_id' => $home->id,
            'opponent_team_id' => $opponent->id,
            'created_by_user_id' => $creator->id,
            'starting_player_ids' => $homePlayers->modelKeys(),
            'active_player_ids' => $homePlayers->modelKeys(),
            'opponent_starting_player_ids' => null,
            'opponent_active_player_ids' => null,
        ]);

        $this->actingAs($joiner)->post(route('live-games.lineup', $game), [
            'starting_player_ids' => $opponentPlayers->modelKeys(),
        ])->assertRedirect(route('live-games.show', $game));

        $game->refresh();
        $this->assertSame($opponentPlayers->modelKeys(), $game->opponent_starting_player_ids);
        $this->assertSame($opponentPlayers->modelKeys(), $game->opponent_active_player_ids);
        Event::assertDispatched(LiveGameStateUpdated::class);
    }

    public function test_home_coach_cannot_submit_opponent_lineup_via_wrong_side(): void
    {
        $home = Team::factory()->create();
        $opponent = Team::factory()->create();
        $creator = User::factory()->forTeam($home)->create(['email_verified_at' => now()]);
        $homePlayers = Player::factory()->count(5)->for($home)->create(['is_active' => true]);
        $opponentPlayers = Player::factory()->count(5)->for($opponent)->create(['is_active' => true]);

        $game = LiveGame::factory()->create([
            'home_team_id' => $home->id,
            'opponent_team_id' => $opponent->id,
            'created_by_user_id' => $creator->id,
            'starting_player_ids' => $homePlayers->modelKeys(),
            'active_player_ids' => $homePlayers->modelKeys(),
            'opponent_starting_player_ids' => null,
            'opponent_active_player_ids' => null,
        ]);

        $this->actingAs($creator)->post(route('live-games.lineup', $game), [
            'starting_player_ids' => $opponentPlayers->modelKeys(),
        ])->assertSessionHasErrors('starting_player_ids');

        $this->assertNull($game->fresh()->opponent_starting_player_ids);
    }

    public function test_non_participant_cannot_submit_lineup(): void
    {
        $home = Team::factory()->create();
        $opponent = Team::factory()->create();
        $outsiderTeam = Team::factory()->create();
        $creator = User::factory()->forTeam($home)->create(['email_verified_at' => now()]);
        $outsider = User::factory()->forTeam($outsiderTeam)->create(['email_verified_at' => now()]);
        $players = Player::factory()->count(5)->for($outsiderTeam)->create(['is_active' => true]);

        $game = LiveGame::factory()->create([
            'home_team_id' => $home->id,
            'opponent_team_id' => $opponent->id,
            'created_by_user_id' => $creator->id,
            'opponent_starting_player_ids' => null,
            'opponent_active_player_ids' => null,
        ]);

        $this->actingAs($outsider)->post(route('live-games.lineup', $game), [
            'starting_player_ids' => $players->modelKeys(),
        ])->assertForbidden();
    }

    public function test_start_is_rejected_until_both_lineups_are_ready(): void
    {
        Event::fake([LiveGameStateUpdated::class]);

        $home = Team::factory()->create();
        $opponent = Team::factory()->create();
        $creator = User::factory()->forTeam($home)->create(['email_verified_at' => now()]);
        $joiner = User::factory()->forTeam($opponent)->create(['email_verified_at' => now()]);
        $homePlayers = Player::factory()->count(5)->for($home)->create(['is_active' => true]);
        $opponentPlayers = Player::factory()->count(5)->for($opponent)->create(['is_active' => true]);

        $game = LiveGame::factory()->create([
            'home_team_id' => $home->id,
            'opponent_team_id' => $opponent->id,
            'created_by_user_id' => $creator->id,
            'starting_player_ids' => $homePlayers->modelKeys(),
            'active_player_ids' => $homePlayers->modelKeys(),
            'opponent_starting_player_ids' => null,
            'opponent_active_player_ids' => null,
        ]);

        $this->actingAs($creator)->post(route('live-games.start', $game))
            ->assertSessionHasErrors('game');

        $this->assertSame(LiveGame::STATUS_SETUP, $game->fresh()->status);

        $this->actingAs($joiner)->post(route('live-games.lineup', $game), [
            'starting_player_ids' => $opponentPlayers->modelKeys(),
        ])->assertRedirect(route('live-games.show', $game));

        $this->actingAs($creator)->post(route('live-games.start', $game))
            ->assertRedirect(route('live-games.show', $game));

        $this->assertDatabaseHas('live_games', [
            'id' => $game->id,
            'status' => LiveGame::STATUS_LIVE,
            'clock_running' => true,
        ]);
    }

    public function test_starting_and_finishing_a_game_updates_its_lifecycle(): void
    {
        Event::fake([LiveGameStateUpdated::class]);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $game = LiveGame::factory()->withBothLineups()->create(['created_by_user_id' => $user->id]);

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
}
