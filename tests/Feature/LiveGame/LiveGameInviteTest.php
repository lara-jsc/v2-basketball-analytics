<?php

namespace Tests\Feature\LiveGame;

use App\Models\LiveGame;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use App\Notifications\LiveGameInviteNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LiveGameInviteTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_live_game_notifies_both_teams_except_creator_with_role_aware_kinds(): void
    {
        Notification::fake();

        $home = Team::factory()->create(['name' => 'Home Squad']);
        $opponent = Team::factory()->create(['name' => 'Away Squad']);

        $creator = User::factory()->forTeam($home)->create(['email_verified_at' => now()]);
        $homeAssistant = User::factory()->forTeam($home)->create(['email_verified_at' => now()]);
        $homeOther = User::factory()->forTeam($home)->create(['email_verified_at' => now()]);
        $oppCoach = User::factory()->forTeam($opponent)->create(['email_verified_at' => now()]);

        $starters = Player::factory()->count(5)->for($home)->create(['is_active' => true]);

        $this->actingAs($creator)->post(route('live-games.store'), [
            'opponent_team_id' => $opponent->id,
            'period_length_seconds' => 600,
            'starting_player_ids' => $starters->pluck('id')->all(),
            'assistant_coach_user_id' => $homeAssistant->id,
            'delegated_player_ids' => [$starters[0]->id],
        ])->assertRedirect();

        Notification::assertNotSentTo($creator, LiveGameInviteNotification::class);

        Notification::assertSentTo($oppCoach, LiveGameInviteNotification::class, function (LiveGameInviteNotification $notification): bool {
            return $notification->kind === LiveGameInviteNotification::KIND_OPPONENT_SETUP;
        });

        Notification::assertSentTo($homeAssistant, LiveGameInviteNotification::class, function (LiveGameInviteNotification $notification): bool {
            return $notification->kind === LiveGameInviteNotification::KIND_HOME_ASSIGNED;
        });

        Notification::assertSentTo($homeOther, LiveGameInviteNotification::class, function (LiveGameInviteNotification $notification): bool {
            return $notification->kind === LiveGameInviteNotification::KIND_HOME_TEAM;
        });
    }

    public function test_invite_is_shared_on_inertia_and_dismiss_clears_it(): void
    {
        $home = Team::factory()->create();
        $opponent = Team::factory()->create();
        $creator = User::factory()->forTeam($home)->create(['email_verified_at' => now()]);
        $oppCoach = User::factory()->forTeam($opponent)->create(['email_verified_at' => now()]);
        $starters = Player::factory()->count(5)->for($home)->create(['is_active' => true]);

        $this->actingAs($creator)->post(route('live-games.store'), [
            'opponent_team_id' => $opponent->id,
            'period_length_seconds' => 600,
            'starting_player_ids' => $starters->pluck('id')->all(),
        ])->assertRedirect();

        $game = LiveGame::query()->firstOrFail();

        $this->actingAs($oppCoach)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->has('liveGameInvite')
                ->where('liveGameInvite.live_game_id', $game->id)
                ->where('liveGameInvite.kind', LiveGameInviteNotification::KIND_OPPONENT_SETUP));

        $notificationId = $oppCoach->unreadNotifications()->firstOrFail()->id;

        $this->actingAs($oppCoach)
            ->post(route('live-game-invites.dismiss', ['notification' => $notificationId]))
            ->assertRedirect();

        $this->actingAs($oppCoach)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('liveGameInvite', null));
    }

    public function test_opening_live_game_show_marks_invite_read(): void
    {
        $home = Team::factory()->create();
        $opponent = Team::factory()->create();
        $creator = User::factory()->forTeam($home)->create(['email_verified_at' => now()]);
        $oppCoach = User::factory()->forTeam($opponent)->create(['email_verified_at' => now()]);
        $starters = Player::factory()->count(5)->for($home)->create(['is_active' => true]);
        Player::factory()->count(5)->for($opponent)->create(['is_active' => true]);

        $this->actingAs($creator)->post(route('live-games.store'), [
            'opponent_team_id' => $opponent->id,
            'period_length_seconds' => 600,
            'starting_player_ids' => $starters->pluck('id')->all(),
        ])->assertRedirect();

        $game = LiveGame::query()->firstOrFail();
        $this->assertSame(1, $oppCoach->fresh()->unreadNotifications()->count());

        $this->actingAs($oppCoach)
            ->get(route('live-games.show', $game))
            ->assertOk();

        $this->assertSame(0, $oppCoach->fresh()->unreadNotifications()->count());
    }
}
