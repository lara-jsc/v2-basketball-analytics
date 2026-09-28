<?php

use App\Jobs\RebuildPlayerStats;
use App\Models\Player;
use App\Models\PlayerHistory;
use App\Models\Team;
use App\Services\LineupService;
use App\Services\WinProbabilityService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Cache::flush();
    Queue::fake(); // keep ComputePlayerPlusMinus (Python) from running
});

function cacheTeamResults(int $teamId, int $opponentId): void
{
    app(WinProbabilityService::class)->store($teamId, $opponentId, ['team_a_win_probability' => 0.6]);
    app(LineupService::class)->store($teamId, $opponentId, ['recommended_lineup' => [], 'confidence' => 0.0]);
}

it('clears cached win probability and lineup for the player team', function () {
    $team = Team::factory()->create();
    $opponent = Team::factory()->create();
    $player = Player::factory()->for($team)->create();
    PlayerHistory::factory()->for($player)->create([
        'playing_team_id' => $team->id,
        'opponent_team_id' => $opponent->id,
    ]);
    cacheTeamResults($team->id, $opponent->id);

    app()->call([new RebuildPlayerStats($player->id), 'handle']);

    expect(app(WinProbabilityService::class)->getOrDispatch($team->id, $opponent->id))->toBeNull()
        ->and(app(LineupService::class)->getOrDispatch($team->id, $opponent->id))->toBeNull();
});

it('invalidates even when the player has no histories left', function () {
    $team = Team::factory()->create();
    $opponent = Team::factory()->create();
    $player = Player::factory()->for($team)->create();
    cacheTeamResults($team->id, $opponent->id);

    app()->call([new RebuildPlayerStats($player->id), 'handle']);

    expect(app(WinProbabilityService::class)->getOrDispatch($team->id, $opponent->id))->toBeNull();
});

it('leaves unrelated teams cached', function () {
    $team = Team::factory()->create();
    [$otherA, $otherB] = Team::factory()->count(2)->create();
    $player = Player::factory()->for($team)->create();
    cacheTeamResults($otherA->id, $otherB->id);

    app()->call([new RebuildPlayerStats($player->id), 'handle']);

    expect(app(WinProbabilityService::class)->getOrDispatch($otherA->id, $otherB->id))->not->toBeNull();
});
