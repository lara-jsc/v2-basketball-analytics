<?php

use App\Events\LiveGameStateUpdated;
use App\Models\LiveGame;
use App\Models\LiveGamePlayerDelegation;
use App\Models\LiveGamePlayerStat;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Event;

/**
 * Setup: head coach controls all home players; opponent roster is all undelegated.
 *
 * @return array{game: LiveGame, headCoach: User, opponentPlayer: Player, homePlayer: Player}
 */
function liveGameWithOpponentRoster(): array
{
    Event::fake([LiveGameStateUpdated::class]);

    $game = LiveGame::factory()->create([
        'status' => LiveGame::STATUS_LIVE,
        'clock_running' => true,
        'clock_started_at' => now(),
    ]);

    $headCoach = User::factory()->forTeam($game->homeTeam)->create(['email_verified_at' => now()]);
    $game->forceFill(['home_main_coach_user_id' => $headCoach->id])->save();

    $homePlayer = Player::factory()->for($game->homeTeam)->create(['is_active' => true]);
    $opponentPlayer = Player::factory()->for($game->opponentTeam)->create(['is_active' => true]);

    $game->forceFill([
        'starting_player_ids' => [$homePlayer->id],
        'active_player_ids' => [$homePlayer->id],
        'opponent_starting_player_ids' => [$opponentPlayer->id],
        'opponent_active_player_ids' => [$opponentPlayer->id],
    ])->save();

    return compact('game', 'headCoach', 'opponentPlayer', 'homePlayer');
}

/**
 * Setup: head coach + assistant with opponent player delegated to assistant.
 *
 * @return array{game: LiveGame, headCoach: User, assistant: User, opponentPlayer: Player, homePlayer: Player}
 */
function liveGameWithOpponentDelegation(): array
{
    Event::fake([LiveGameStateUpdated::class]);

    $game = LiveGame::factory()->create([
        'status' => LiveGame::STATUS_LIVE,
        'clock_running' => true,
        'clock_started_at' => now(),
    ]);

    $headCoach = User::factory()->forTeam($game->homeTeam)->create(['email_verified_at' => now()]);
    $assistant = User::factory()->forTeam($game->homeTeam)->create(['email_verified_at' => now()]);
    $game->forceFill(['home_main_coach_user_id' => $headCoach->id])->save();

    $homePlayer = Player::factory()->for($game->homeTeam)->create(['is_active' => true]);
    $opponentPlayer = Player::factory()->for($game->opponentTeam)->create(['is_active' => true]);

    $game->forceFill([
        'starting_player_ids' => [$homePlayer->id],
        'active_player_ids' => [$homePlayer->id],
        'opponent_starting_player_ids' => [$opponentPlayer->id],
        'opponent_active_player_ids' => [$opponentPlayer->id],
    ])->save();

    LiveGamePlayerDelegation::query()->create([
        'live_game_id' => $game->id,
        'coach_user_id' => $assistant->id,
        'player_id' => $opponentPlayer->id,
    ]);

    return compact('game', 'headCoach', 'assistant', 'opponentPlayer', 'homePlayer');
}

it('lets the head coach record an opponent shot for an undelegated opponent player', function () {
    ['game' => $game, 'headCoach' => $head, 'opponentPlayer' => $player] = liveGameWithOpponentRoster();

    $this->actingAs($head)
        ->postJson(route('live-games.events.store', $game), [
            'type' => 'shot_made',
            'team_scope' => 'own',
            'player_id' => $player->id,
            'payload' => ['points' => 2],
        ])
        ->assertOk();

    expect($game->fresh()->opponent_score)->toBe(2);
});

it('lets a delegated assistant record an opponent shot', function () {
    ['game' => $game, 'assistant' => $assistant, 'opponentPlayer' => $player] = liveGameWithOpponentDelegation();

    $this->actingAs($assistant)
        ->postJson(route('live-games.events.store', $game), [
            'type' => 'shot_made',
            'team_scope' => 'own',
            'player_id' => $player->id,
            'payload' => ['points' => 2],
        ])
        ->assertOk();

    expect($game->fresh()->opponent_score)->toBe(2)
        ->and($game->fresh()->home_score)->toBe(0);
});

it('refuses an opponent shot without a delegation', function () {
    ['game' => $game, 'assistant' => $assistant, 'opponentPlayer' => $player] = liveGameWithOpponentDelegation();
    LiveGamePlayerDelegation::query()->where('player_id', $player->id)->delete();

    $this->actingAs($assistant)
        ->postJson(route('live-games.events.store', $game), [
            'type' => 'shot_made',
            'team_scope' => 'own',
            'player_id' => $player->id,
            'payload' => ['points' => 2],
        ])
        ->assertStatus(422);
});

it('still refuses a foul on an opponent player even with a delegation', function () {
    ['game' => $game, 'assistant' => $assistant, 'opponentPlayer' => $player] = liveGameWithOpponentDelegation();

    $this->actingAs($assistant)
        ->postJson(route('live-games.events.store', $game), [
            'type' => 'foul',
            'team_scope' => 'own',
            'player_id' => $player->id,
            'payload' => ['kind' => 'personal'],
        ])
        ->assertStatus(422);
});

it('assigns an opponent player to an assistant via the lineup submission', function () {
    Event::fake([LiveGameStateUpdated::class]);

    $home = Team::factory()->create();
    $opponent = Team::factory()->create();

    $head = User::factory()->forTeam($home)->create(['email_verified_at' => now()]);
    $assistant = User::factory()->forTeam($home)->create(['email_verified_at' => now()]);

    $homeStarters = Player::factory()->count(5)->for($home)->create(['is_active' => true]);
    $opponentPlayer = Player::factory()->for($opponent)->create(['is_active' => true]);

    $game = LiveGame::factory()->create([
        'home_team_id' => $home->id,
        'opponent_team_id' => $opponent->id,
        'created_by_user_id' => $head->id,
        'status' => LiveGame::STATUS_SETUP,
        'home_main_coach_user_id' => $head->id,
    ]);

    // Submit lineup including an opponent player in the delegation list.
    $this->actingAs($head)
        ->post(route('live-games.lineup', $game), [
            'starting_player_ids' => $homeStarters->pluck('id')->all(),
            'assistant_coach_user_id' => $assistant->id,
            'delegated_player_ids' => [$opponentPlayer->id],
        ])
        ->assertRedirect();

    expect(LiveGamePlayerDelegation::query()
        ->where('live_game_id', $game->id)
        ->where('player_id', $opponentPlayer->id)
        ->value('coach_user_id'))->toBe($assistant->id);
});

it('refuses to delegate a player from a team not in this game', function () {
    Event::fake([LiveGameStateUpdated::class]);

    $home = Team::factory()->create();
    $opponent = Team::factory()->create();
    $stranger = Team::factory()->create();

    $head = User::factory()->forTeam($home)->create(['email_verified_at' => now()]);
    $assistant = User::factory()->forTeam($home)->create(['email_verified_at' => now()]);

    $homeStarters = Player::factory()->count(5)->for($home)->create(['is_active' => true]);
    $strangerPlayer = Player::factory()->for($stranger)->create(['is_active' => true]);

    $game = LiveGame::factory()->create([
        'home_team_id' => $home->id,
        'opponent_team_id' => $opponent->id,
        'created_by_user_id' => $head->id,
        'status' => LiveGame::STATUS_SETUP,
        'home_main_coach_user_id' => $head->id,
    ]);

    $this->actingAs($head)
        ->post(route('live-games.lineup', $game), [
            'starting_player_ids' => $homeStarters->pluck('id')->all(),
            'assistant_coach_user_id' => $assistant->id,
            'delegated_player_ids' => [$strangerPlayer->id],
        ])
        ->assertSessionHasErrors('delegated_player_ids');
});

it('credits plus-minus to the correct side for a delegated opponent shot', function () {
    ['game' => $game, 'assistant' => $assistant, 'opponentPlayer' => $player, 'homePlayer' => $homePlayer]
        = liveGameWithOpponentDelegation();

    $this->actingAs($assistant)->postJson(route('live-games.events.store', $game), [
        'type' => 'shot_made',
        'team_scope' => 'own',
        'player_id' => $player->id,
        'payload' => ['points' => 3],
    ])->assertOk();

    $homeStat = LiveGamePlayerStat::query()
        ->where('live_game_id', $game->id)
        ->where('player_id', $homePlayer->id)
        ->first();

    $opponentStat = LiveGamePlayerStat::query()
        ->where('live_game_id', $game->id)
        ->where('player_id', $player->id)
        ->first();

    expect($opponentStat?->points)->toBe(3)
        ->and($game->fresh()->opponent_score)->toBe(3)
        ->and($game->fresh()->home_score)->toBe(0);
});
