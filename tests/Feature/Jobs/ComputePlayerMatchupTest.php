<?php

use App\Jobs\ComputePlayerMatchup;
use App\Models\Player;
use App\Models\PlayerStat;
use App\Services\PlayerMatchupService;
use App\Services\PythonEngineService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

/**
 * Stand in for the Python subprocess and record what the job sends. The edge
 * arithmetic itself is covered by tests/python/test_win_probability.py.
 */
function fakeMatchupEngine(): object
{
    $fake = new class extends PythonEngineService
    {
        /** @var list<array<string, mixed>> */
        public array $payloads = [];

        public function call(string $command, array $payload): array
        {
            $this->payloads[] = $payload;

            return [
                'player_a_edge_score' => 0.5,
                'player_b_edge_score' => 0.5,
                'stronger_stats_a' => [],
                'stronger_stats_b' => [],
            ];
        }
    };

    app()->instance(PythonEngineService::class, $fake);

    return $fake;
}

it('sends the advanced stats the engine compares', function () {
    $engine = fakeMatchupEngine();
    $a = Player::factory()->create();
    $b = Player::factory()->create();
    PlayerStat::factory()->for($a)->create(['eff' => 17.1, 'efg_pct' => 0.5, 'ts_pct' => 0.55]);
    PlayerStat::factory()->for($b)->create(['eff' => 16.0, 'efg_pct' => 0.51, 'ts_pct' => 0.53]);

    app()->call([new ComputePlayerMatchup($a->id, $b->id), 'handle']);

    expect($engine->payloads[0]['player_a'])->toMatchArray(['eff' => 17.1, 'efg_pct' => 0.5, 'ts_pct' => 0.55])
        ->and($engine->payloads[0]['player_b'])->toMatchArray(['eff' => 16.0, 'efg_pct' => 0.51, 'ts_pct' => 0.53]);
});

it('sends zeros when a player has no stats', function () {
    $engine = fakeMatchupEngine();
    $a = Player::factory()->create();
    $b = Player::factory()->create();

    app()->call([new ComputePlayerMatchup($a->id, $b->id), 'handle']);

    expect($engine->payloads[0]['player_a'])->toMatchArray(['eff' => 0, 'efg_pct' => 0, 'ts_pct' => 0]);
});

it('ignores matchup results cached before advanced stats were sent', function () {
    Bus::fake();
    Cache::put('matchup.1.2', ['player_a_edge_score' => 0.7692], now()->addHour());

    expect(app(PlayerMatchupService::class)->getOrDispatch(1, 2))->toBeNull();
    Bus::assertDispatched(ComputePlayerMatchup::class);
});
