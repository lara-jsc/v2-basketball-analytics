<?php

use App\Jobs\ComputeWinProbability;
use App\Services\WinProbabilityService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

describe('WinProbabilityService', function () {

    beforeEach(function () {
        $this->service = new WinProbabilityService();
        Cache::flush();
    });

    describe('getOrDispatch', function () {
        it('returns null and dispatches a job when no cached result exists', function () {
            Bus::fake();

            $result = $this->service->getOrDispatch(1, 2);

            expect($result)->toBeNull();
            Bus::assertDispatched(ComputeWinProbability::class);
        });

        it('returns the cached result without dispatching a job', function () {
            Bus::fake();

            $this->service->store(1, 2, [
                'team_a_win_probability' => 0.62,
                'team_b_win_probability' => 0.38,
                'team_a_win_rate'        => 0.67,
                'team_b_win_rate'        => 0.54,
            ]);

            $result = $this->service->getOrDispatch(1, 2);

            expect($result)->toMatchArray([
                'team_a_win_probability' => 0.62,
                'team_b_win_probability' => 0.38,
            ]);
            Bus::assertNotDispatched(ComputeWinProbability::class);
        });

        it('treats A vs B and B vs A as the same matchup (symmetric key)', function () {
            Bus::fake();

            $this->service->store(1, 2, ['team_a_win_probability' => 0.60]);

            // Swap order — should still find the cache
            $result = $this->service->getOrDispatch(2, 1);

            expect($result)->not->toBeNull();
            Bus::assertNotDispatched(ComputeWinProbability::class);
        });
    });

    describe('store', function () {
        it('persists the result so subsequent gets return it', function () {
            $data = ['team_a_win_probability' => 0.55, 'team_b_win_probability' => 0.45];

            $this->service->store(3, 4, $data);

            expect($this->service->getOrDispatch(3, 4))->toMatchArray($data);
        });
    });

    describe('invalidateForTeam', function () {
        it('increments the team cache version, causing the old cached key to be a miss', function () {
            Bus::fake();

            $data = ['team_a_win_probability' => 0.50];
            $this->service->store(5, 6, $data);

            // Verify it's cached
            expect($this->service->getOrDispatch(5, 6))->not->toBeNull();

            // Invalidate one side of the matchup
            $this->service->invalidateForTeam(5);

            // The old cache key is now orphaned — lookup is a miss
            $result = $this->service->getOrDispatch(5, 6);
            expect($result)->toBeNull();
        });
    });
});
