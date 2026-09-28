<?php

use App\Jobs\ComputePlayerPlusMinus;
use App\Models\PlayerStat;
use App\Services\PythonEngineService;

function fakeBpmEngine(?float $plusMinus): void
{
    app()->instance(PythonEngineService::class, new class($plusMinus) extends PythonEngineService
    {
        public function __construct(private readonly ?float $plusMinus) {}

        public function call(string $command, array $payload): array
        {
            return ['player_id' => $payload['player_id'], 'plus_minus' => $this->plusMinus];
        }
    });
}

it('stores the computed plus-minus', function () {
    fakeBpmEngine(3.04);
    $stat = PlayerStat::factory()->create(['plus_minus' => null]);

    app()->call([new ComputePlayerPlusMinus($stat->id), 'handle']);

    expect($stat->fresh()->plus_minus)->toBe(3.04);
});

it('stores null when the engine reports no plus-minus (no minutes)', function () {
    fakeBpmEngine(null);
    $stat = PlayerStat::factory()->create(['plus_minus' => 12.5]);

    app()->call([new ComputePlayerPlusMinus($stat->id), 'handle']);

    expect($stat->fresh()->plus_minus)->toBeNull();
});
