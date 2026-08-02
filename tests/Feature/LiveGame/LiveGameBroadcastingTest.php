<?php

namespace Tests\Feature\LiveGame;

use App\Models\LiveGame;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveGameBroadcastingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_authorize_a_live_game_channel(): void
    {
        $this->postJson('/broadcasting/auth', [
            'channel_name' => 'private-live-game.1',
            'socket_id' => '123.456',
        ])->assertUnauthorized();
    }

    public function test_authenticated_users_can_authorize_an_existing_live_game_channel(): void
    {
        $user = User::factory()->create();
        $liveGame = LiveGame::factory()->create();

        $this->actingAs($user)
            ->postJson('/broadcasting/auth', [
                'channel_name' => "private-live-game.{$liveGame->id}",
                'socket_id' => '123.456',
            ])
            ->assertOk()
            ->assertJsonStructure(['auth']);
    }

    public function test_authenticated_users_cannot_authorize_a_missing_live_game_channel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/broadcasting/auth', [
                'channel_name' => 'private-live-game.999999',
                'socket_id' => '123.456',
            ])
            ->assertForbidden();
    }
}
