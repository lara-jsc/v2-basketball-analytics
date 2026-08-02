<?php

namespace Tests\Feature\LiveGame;

use App\Events\LiveGameStateUpdated;
use App\Models\LiveGame;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class LiveGameClockControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_verified_user_can_start_a_live_game_clock(): void
    {
        Event::fake([LiveGameStateUpdated::class]);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $game = LiveGame::factory()->create();

        $this->actingAs($user)
            ->postJson("/live-games/{$game->id}/clock", ['action' => 'start'])
            ->assertOk()
            ->assertJsonPath('clock.running', true);

        $this->assertDatabaseHas('live_games', [
            'id' => $game->id,
            'status' => LiveGame::STATUS_LIVE,
            'clock_running' => true,
        ]);
    }

    public function test_a_finished_game_rejects_clock_actions(): void
    {
        Event::fake([LiveGameStateUpdated::class]);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $game = LiveGame::factory()->create([
            'status' => LiveGame::STATUS_FINISHED,
            'clock_seconds_remaining' => 120,
        ]);

        $this->actingAs($user)
            ->postJson("/live-games/{$game->id}/clock", ['action' => 'start'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('game');

        $this->assertDatabaseHas('live_games', [
            'id' => $game->id,
            'status' => LiveGame::STATUS_FINISHED,
            'clock_seconds_remaining' => 120,
            'clock_running' => false,
        ]);
    }
}
