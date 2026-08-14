<?php

namespace Tests\Feature\LiveGame;

use App\Events\LiveGameStateUpdated;
use App\Models\LiveGame;
use App\Models\LiveGamePlayerDelegation;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use App\Services\LiveGame\LiveGameDelegationWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LiveGameDelegationTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_game_with_multiple_assistants_writes_delegations(): void
    {
        $home = Team::factory()->create();
        $opponent = Team::factory()->create();

        $mainCoach = User::factory()->forTeam($home)->create(['email_verified_at' => now()]);
        $assistantA = User::factory()->forTeam($home)->create(['email_verified_at' => now()]);
        $assistantB = User::factory()->forTeam($home)->create(['email_verified_at' => now()]);

        $starters = Player::factory()->count(5)->for($home)->create(['is_active' => true]);
        $bench = Player::factory()->count(2)->for($home)->create(['is_active' => true]);

        $response = $this->actingAs($mainCoach)->post(route('live-games.store'), [
            'opponent_team_id' => $opponent->id,
            'period_length_seconds' => 600,
            'starting_player_ids' => $starters->pluck('id')->all(),
            'assistant_assignments' => [
                [
                    'coach_user_id' => $assistantA->id,
                    'player_ids' => [$starters[0]->id, $bench[0]->id],
                ],
                [
                    'coach_user_id' => $assistantB->id,
                    'player_ids' => [$starters[1]->id, $bench[1]->id],
                ],
            ],
        ]);

        $game = LiveGame::query()->firstOrFail();
        $response->assertRedirect(route('live-games.show', $game));

        $this->assertDatabaseHas('live_game_player_delegations', [
            'live_game_id' => $game->id,
            'coach_user_id' => $assistantA->id,
            'player_id' => $starters[0]->id,
        ]);
        $this->assertDatabaseHas('live_game_player_delegations', [
            'live_game_id' => $game->id,
            'coach_user_id' => $assistantB->id,
            'player_id' => $bench[1]->id,
        ]);
    }

    public function test_create_game_rejects_the_same_player_assigned_to_two_assistants(): void
    {
        $home = Team::factory()->create();
        $opponent = Team::factory()->create();

        $mainCoach = User::factory()->forTeam($home)->create(['email_verified_at' => now()]);
        $assistantA = User::factory()->forTeam($home)->create(['email_verified_at' => now()]);
        $assistantB = User::factory()->forTeam($home)->create(['email_verified_at' => now()]);
        $starters = Player::factory()->count(5)->for($home)->create(['is_active' => true]);

        $this->actingAs($mainCoach)
            ->from(route('live-games.create'))
            ->post(route('live-games.store'), [
                'opponent_team_id' => $opponent->id,
                'period_length_seconds' => 600,
                'starting_player_ids' => $starters->pluck('id')->all(),
                'assistant_assignments' => [
                    ['coach_user_id' => $assistantA->id, 'player_ids' => [$starters[0]->id]],
                    ['coach_user_id' => $assistantB->id, 'player_ids' => [$starters[0]->id]],
                ],
            ])
            ->assertSessionHasErrors('assistant_assignments');
    }

    public function test_create_game_without_assistant_writes_no_delegations(): void
    {
        $home = Team::factory()->create();
        $opponent = Team::factory()->create();
        $mainCoach = User::factory()->forTeam($home)->create(['email_verified_at' => now()]);
        $starters = Player::factory()->count(5)->for($home)->create(['is_active' => true]);

        $this->actingAs($mainCoach)->post(route('live-games.store'), [
            'opponent_team_id' => $opponent->id,
            'period_length_seconds' => 600,
            'starting_player_ids' => $starters->pluck('id')->all(),
        ])->assertRedirect();

        $game = LiveGame::query()->firstOrFail();
        $this->assertDatabaseCount('live_game_player_delegations', 0);
        $this->assertSame((int) $mainCoach->id, (int) $game->home_main_coach_user_id);
    }

    public function test_opponent_lineup_submit_with_multiple_assistants_writes_rows(): void
    {
        Event::fake([LiveGameStateUpdated::class]);

        $home = Team::factory()->create();
        $opponent = Team::factory()->create();

        $homeCoach = User::factory()->forTeam($home)->create(['email_verified_at' => now()]);
        $oppMain = User::factory()->forTeam($opponent)->create(['email_verified_at' => now()]);
        $oppAssistantA = User::factory()->forTeam($opponent)->create(['email_verified_at' => now()]);
        $oppAssistantB = User::factory()->forTeam($opponent)->create(['email_verified_at' => now()]);

        $homeStarters = Player::factory()->count(5)->for($home)->create(['is_active' => true]);
        $oppStarters = Player::factory()->count(5)->for($opponent)->create(['is_active' => true]);
        $oppBench = Player::factory()->count(2)->for($opponent)->create(['is_active' => true]);

        $game = LiveGame::factory()->create([
            'home_team_id' => $home->id,
            'opponent_team_id' => $opponent->id,
            'created_by_user_id' => $homeCoach->id,
            'status' => LiveGame::STATUS_SETUP,
            'home_main_coach_user_id' => $homeCoach->id,
            'opponent_main_coach_user_id' => null,
            'starting_player_ids' => $homeStarters->pluck('id')->all(),
            'active_player_ids' => $homeStarters->pluck('id')->all(),
            'opponent_starting_player_ids' => null,
            'opponent_active_player_ids' => null,
        ]);

        $this->actingAs($oppMain)->post(route('live-games.lineup', $game), [
            'starting_player_ids' => $oppStarters->pluck('id')->all(),
            'assistant_assignments' => [
                [
                    'coach_user_id' => $oppAssistantA->id,
                    'player_ids' => [$oppStarters[0]->id, $oppBench[0]->id],
                ],
                [
                    'coach_user_id' => $oppAssistantB->id,
                    'player_ids' => [$oppStarters[1]->id, $oppBench[1]->id],
                ],
            ],
        ])->assertRedirect(route('live-games.show', $game));

        $game->refresh();
        $this->assertSame((int) $oppMain->id, (int) $game->opponent_main_coach_user_id);

        $this->assertDatabaseHas('live_game_player_delegations', [
            'live_game_id' => $game->id,
            'coach_user_id' => $oppAssistantA->id,
            'player_id' => $oppStarters[0]->id,
        ]);
        $this->assertDatabaseHas('live_game_player_delegations', [
            'live_game_id' => $game->id,
            'coach_user_id' => $oppAssistantB->id,
            'player_id' => $oppBench[1]->id,
        ]);
    }

    public function test_wrong_team_assistant_is_rejected_on_create(): void
    {
        $home = Team::factory()->create();
        $opponent = Team::factory()->create();
        $other = Team::factory()->create();

        $mainCoach = User::factory()->forTeam($home)->create(['email_verified_at' => now()]);
        $outsider = User::factory()->forTeam($other)->create(['email_verified_at' => now()]);
        $starters = Player::factory()->count(5)->for($home)->create(['is_active' => true]);

        $this->actingAs($mainCoach)->from(route('live-games.create'))->post(route('live-games.store'), [
            'opponent_team_id' => $opponent->id,
            'period_length_seconds' => 600,
            'starting_player_ids' => $starters->pluck('id')->all(),
            'assistant_assignments' => [
                [
                    'coach_user_id' => $outsider->id,
                    'player_ids' => [$starters[0]->id],
                ],
            ],
        ])->assertSessionHasErrors('assistant_assignments.0.coach_user_id');

        $this->assertDatabaseCount('live_games', 0);
        $this->assertDatabaseCount('live_game_player_delegations', 0);
    }

    public function test_write_replaces_all_existing_assignments_for_one_side(): void
    {
        $home = Team::factory()->create();
        $opponent = Team::factory()->create();

        $mainCoach = User::factory()->forTeam($home)->create(['email_verified_at' => now()]);
        $assistantA = User::factory()->forTeam($home)->create(['email_verified_at' => now()]);
        $assistantB = User::factory()->forTeam($home)->create(['email_verified_at' => now()]);

        $players = Player::factory()->count(4)->for($home)->create(['is_active' => true]);

        $game = LiveGame::factory()->create([
            'home_team_id' => $home->id,
            'opponent_team_id' => $opponent->id,
            'home_main_coach_user_id' => $mainCoach->id,
        ]);

        LiveGamePlayerDelegation::query()->create([
            'live_game_id' => $game->id,
            'coach_user_id' => $assistantA->id,
            'player_id' => $players[0]->id,
        ]);

        app(LiveGameDelegationWriter::class)->writeAssignments($game, $mainCoach, $home->id, [
            ['coach_user_id' => $assistantB->id, 'player_ids' => [$players[1]->id, $players[2]->id]],
        ]);

        $this->assertDatabaseMissing('live_game_player_delegations', [
            'live_game_id' => $game->id,
            'coach_user_id' => $assistantA->id,
            'player_id' => $players[0]->id,
        ]);

        $this->assertDatabaseHas('live_game_player_delegations', [
            'live_game_id' => $game->id,
            'coach_user_id' => $assistantB->id,
            'player_id' => $players[2]->id,
        ]);
    }

    public function test_delegations_store_route_is_removed(): void
    {
        $this->assertFalse(
            Route::has('live-games.delegations.store'),
        );
    }
}
