<?php

namespace Tests\Feature\LiveGame;

use App\Models\LiveGame;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveGameDelegationTest extends TestCase
{
    use RefreshDatabase;

    public function test_main_coach_can_store_delegations_while_setup(): void
    {
        $home = Team::factory()->create();
        $opponent = Team::factory()->create();

        $mainCoach = User::factory()->forTeam($home)->create(['email_verified_at' => now()]);
        $assistant = User::factory()->forTeam($home)->create(['email_verified_at' => now()]);

        $game = LiveGame::factory()->create([
            'home_team_id' => $home->id,
            'opponent_team_id' => $opponent->id,
            'created_by_user_id' => $mainCoach->id,
            'status' => LiveGame::STATUS_SETUP,
            'home_main_coach_user_id' => $mainCoach->id,
            'opponent_main_coach_user_id' => null,
        ]);

        $p1 = Player::factory()->for($home)->create(['is_active' => true]);
        $p2 = Player::factory()->for($home)->create(['is_active' => true]);

        $this->actingAs($mainCoach)->postJson(route('live-games.delegations.store', $game), [
            'assistant_coach_user_id' => $assistant->id,
            'player_ids' => [$p1->id, $p2->id],
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('live_game_player_delegations', [
            'live_game_id' => $game->id,
            'coach_user_id' => $assistant->id,
            'player_id' => $p1->id,
        ]);
        $this->assertDatabaseHas('live_game_player_delegations', [
            'live_game_id' => $game->id,
            'coach_user_id' => $assistant->id,
            'player_id' => $p2->id,
        ]);
    }

    public function test_non_main_coach_cannot_store_delegations(): void
    {
        $home = Team::factory()->create();
        $opponent = Team::factory()->create();

        $mainCoach = User::factory()->forTeam($home)->create(['email_verified_at' => now()]);
        $assistant = User::factory()->forTeam($home)->create(['email_verified_at' => now()]);

        $game = LiveGame::factory()->create([
            'home_team_id' => $home->id,
            'opponent_team_id' => $opponent->id,
            'created_by_user_id' => $mainCoach->id,
            'status' => LiveGame::STATUS_SETUP,
            'home_main_coach_user_id' => $mainCoach->id,
            'opponent_main_coach_user_id' => null,
        ]);

        $player = Player::factory()->for($home)->create(['is_active' => true]);

        $this->actingAs($assistant)->postJson(route('live-games.delegations.store', $game), [
            'assistant_coach_user_id' => $assistant->id,
            'player_ids' => [$player->id],
        ])->assertForbidden();
    }
}

