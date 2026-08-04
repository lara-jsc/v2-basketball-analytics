<?php

namespace Tests\Feature\LiveGame;

use App\Events\LiveGameStateUpdated;
use App\Models\LiveGame;
use App\Models\LiveGameEvent;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class LiveGameAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([LiveGameStateUpdated::class]);
    }

    public function test_a_user_on_an_unrelated_team_cannot_void_an_event(): void
    {
        [$game, $event] = $this->liveGameWithOneEvent();

        $this->actingAs($this->outsider())
            ->postJson("/live-games/{$game->id}/correction", ['voids_event_id' => $event->id])
            ->assertForbidden();

        $this->assertSame(0, $game->events()->where('type', 'correction')->count());
    }

    public function test_a_user_on_an_unrelated_team_cannot_record_an_event_or_touch_the_clock(): void
    {
        [$game] = $this->liveGameWithOneEvent();
        $outsider = $this->outsider();

        $this->actingAs($outsider)
            ->postJson("/live-games/{$game->id}/events", ['type' => 'timeout', 'team_scope' => 'own'])
            ->assertForbidden();

        $this->actingAs($outsider)
            ->postJson("/live-games/{$game->id}/clock", ['action' => 'stop'])
            ->assertForbidden();
    }

    public function test_a_user_on_an_unrelated_team_cannot_finish_start_or_submit_a_lineup(): void
    {
        [$game] = $this->liveGameWithOneEvent();
        $outsider = $this->outsider();

        $this->actingAs($outsider)->post("/live-games/{$game->id}/finish")->assertForbidden();
        $this->actingAs($outsider)->post("/live-games/{$game->id}/start")->assertForbidden();
        $this->actingAs($outsider)
            ->post("/live-games/{$game->id}/lineup", ['starting_player_ids' => [1, 2, 3, 4, 5]])
            ->assertForbidden();
    }

    public function test_a_user_on_an_unrelated_team_may_still_view_the_game(): void
    {
        [$game] = $this->liveGameWithOneEvent();

        $this->actingAs($this->outsider())
            ->get("/live-games/{$game->id}")
            ->assertOk();
    }

    public function test_a_participating_coach_is_still_allowed_through(): void
    {
        [$game] = $this->liveGameWithOneEvent();
        $homeCoach = User::factory()->forTeam($game->homeTeam)->create(['email_verified_at' => now()]);

        // A timeout needs a stopped clock, so stop it as the creator first.
        $creator = User::query()->findOrFail($game->created_by_user_id);
        $this->actingAs($creator)
            ->postJson("/live-games/{$game->id}/clock", ['action' => 'stop'])
            ->assertOk();

        $this->actingAs($homeCoach)
            ->postJson("/live-games/{$game->id}/events", ['type' => 'timeout', 'team_scope' => 'own'])
            ->assertOk();
    }

    /** @return array{0: LiveGame, 1: LiveGameEvent} */
    private function liveGameWithOneEvent(): array
    {
        $game = LiveGame::factory()->withBothLineups()->create([
            'status' => LiveGame::STATUS_LIVE,
            'clock_running' => true,
            'clock_started_at' => now(),
        ]);
        $creator = User::factory()->forTeam($game->homeTeam)->create(['email_verified_at' => now()]);
        $game->forceFill([
            'created_by_user_id' => $creator->id,
            'home_main_coach_user_id' => $creator->id,
        ])->save();

        $player = Player::query()->findOrFail($game->fresh()->starting_player_ids[0]);

        $this->actingAs($creator)->postJson("/live-games/{$game->id}/events", [
            'type' => 'shot_made',
            'team_scope' => 'own',
            'player_id' => $player->id,
            'payload' => ['points' => 2],
        ])->assertOk();

        return [$game->fresh(), $game->events()->firstOrFail()];
    }

    private function outsider(): User
    {
        return User::factory()->forTeam(Team::factory()->create())->create(['email_verified_at' => now()]);
    }
}
