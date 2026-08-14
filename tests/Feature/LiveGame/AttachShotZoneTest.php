<?php

use App\Events\LiveGameStateUpdated;
use App\Models\LiveGame;
use App\Models\LiveGameEvent;
use App\Models\Player;
use App\Models\User;
use Illuminate\Support\Facades\Event;

/**
 * Create a live game with a recorded shot and return the game, recording coach, the event, and a second coach.
 *
 * @return array{game: LiveGame, coach: User, event: LiveGameEvent, otherCoach: User}
 */
function liveGameWithRecordedShot(int $points = 2): array
{
    Event::fake([LiveGameStateUpdated::class]);

    $game = LiveGame::factory()->create([
        'status' => LiveGame::STATUS_LIVE,
        'clock_running' => true,
        'clock_started_at' => now(),
    ]);
    $coach = User::factory()->forTeam($game->homeTeam)->create(['email_verified_at' => now()]);
    $game->forceFill(['home_main_coach_user_id' => $coach->id])->save();

    $player = Player::factory()->for($game->homeTeam)->create(['is_active' => true]);
    $game->forceFill([
        'starting_player_ids' => [$player->id],
        'active_player_ids' => [$player->id],
    ])->save();

    test()->actingAs($coach)->postJson(route('live-games.events.store', $game), [
        'type' => 'shot_made',
        'team_scope' => 'own',
        'player_id' => $player->id,
        'payload' => ['points' => $points],
    ])->assertOk();

    $event = LiveGameEvent::query()
        ->where('live_game_id', $game->id)
        ->where('type', 'shot_made')
        ->firstOrFail();

    $otherCoach = User::factory()->forTeam($game->homeTeam)->create(['email_verified_at' => now()]);

    return compact('game', 'coach', 'event', 'otherCoach');
}

/**
 * @return array{game: LiveGame, coach: User, event: LiveGameEvent}
 */
function liveGameWithRecordedTurnover(): array
{
    Event::fake([LiveGameStateUpdated::class]);

    $game = LiveGame::factory()->create([
        'status' => LiveGame::STATUS_LIVE,
        'clock_running' => true,
        'clock_started_at' => now(),
    ]);
    $coach = User::factory()->forTeam($game->homeTeam)->create(['email_verified_at' => now()]);
    $game->forceFill(['home_main_coach_user_id' => $coach->id])->save();

    $player = Player::factory()->for($game->homeTeam)->create(['is_active' => true]);
    $game->forceFill([
        'starting_player_ids' => [$player->id],
        'active_player_ids' => [$player->id],
    ])->save();

    test()->actingAs($coach)->postJson(route('live-games.events.store', $game), [
        'type' => 'turnover',
        'team_scope' => 'own',
        'player_id' => $player->id,
    ])->assertOk();

    $event = LiveGameEvent::query()
        ->where('live_game_id', $game->id)
        ->where('type', 'turnover')
        ->firstOrFail();

    return compact('game', 'coach', 'event');
}

/**
 * @return array{game: LiveGame, coach: User, event: LiveGameEvent}
 */
function liveGameWithVoidedShot(int $points = 2): array
{
    $data = liveGameWithRecordedShot(points: $points);
    ['game' => $game, 'coach' => $coach, 'event' => $event] = $data;

    test()->actingAs($coach)->postJson(route('live-games.events.store', $game), [
        'type' => 'correction',
        'team_scope' => 'game',
        'voids_event_id' => $event->id,
    ])->assertOk();

    return compact('game', 'coach', 'event');
}

it('attaches a zone to a shot event', function () {
    ['game' => $game, 'coach' => $coach, 'event' => $event] = liveGameWithRecordedShot(points: 3);

    $this->actingAs($coach)
        ->patchJson(route('live-games.events.zone', [$game, $event]), ['zone' => 'corner_3_left'])
        ->assertOk();

    expect(LiveGameEvent::find($event->id)->payload['zone'])->toBe('corner_3_left');
});

it('refuses to overwrite a zone that is already set', function () {
    ['game' => $game, 'coach' => $coach, 'event' => $event] = liveGameWithRecordedShot(points: 3);

    $this->actingAs($coach)->patchJson(route('live-games.events.zone', [$game, $event]), ['zone' => 'corner_3_left'])->assertOk();

    $this->actingAs($coach)
        ->patchJson(route('live-games.events.zone', [$game, $event]), ['zone' => 'above_break_3'])
        ->assertStatus(422);

    expect(LiveGameEvent::find($event->id)->payload['zone'])->toBe('corner_3_left');
});

it('refuses a zone whose value contradicts the shot', function () {
    ['game' => $game, 'coach' => $coach, 'event' => $event] = liveGameWithRecordedShot(points: 2);

    $this->actingAs($coach)
        ->patchJson(route('live-games.events.zone', [$game, $event]), ['zone' => 'corner_3_left'])
        ->assertStatus(422);
});

it('refuses a zone on a non-shot event', function () {
    ['game' => $game, 'coach' => $coach, 'event' => $event] = liveGameWithRecordedTurnover();

    $this->actingAs($coach)
        ->patchJson(route('live-games.events.zone', [$game, $event]), ['zone' => 'paint'])
        ->assertStatus(422);
});

it('refuses a zone on a voided event', function () {
    ['game' => $game, 'coach' => $coach, 'event' => $event] = liveGameWithVoidedShot(points: 2);

    $this->actingAs($coach)
        ->patchJson(route('live-games.events.zone', [$game, $event]), ['zone' => 'paint'])
        ->assertStatus(422);
});

it('refuses a zone from a coach who did not record the shot', function () {
    ['game' => $game, 'event' => $event, 'otherCoach' => $other] = liveGameWithRecordedShot(points: 2);

    $this->actingAs($other)
        ->patchJson(route('live-games.events.zone', [$game, $event]), ['zone' => 'paint'])
        ->assertStatus(403);
});
