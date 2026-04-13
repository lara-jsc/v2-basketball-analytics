<?php

use App\Jobs\RecommendLineup;
use App\Services\LineupService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

describe('LineupService', function () {

    beforeEach(function () {
        $this->service = new LineupService();
        Cache::flush();
    });

    describe('getOrDispatch', function () {
        it('returns null and dispatches a job when no cached result exists', function () {
            Bus::fake();

            $result = $this->service->getOrDispatch(homeTeamId: 1, opponentTeamId: 2);

            expect($result)->toBeNull();
            Bus::assertDispatched(RecommendLineup::class);
        });

        it('returns the cached result without dispatching a job', function () {
            Bus::fake();

            $lineup = ['recommended_lineup' => [], 'confidence' => 0.78];
            $this->service->store(1, 2, $lineup);

            $result = $this->service->getOrDispatch(1, 2);

            expect($result)->toMatchArray($lineup);
            Bus::assertNotDispatched(RecommendLineup::class);
        });

        it('is NOT symmetric — home vs opponent differs from opponent vs home', function () {
            Bus::fake();

            $lineup = ['recommended_lineup' => [['player_id' => 1]], 'confidence' => 0.80];
            $this->service->store(homeTeamId: 1, opponentTeamId: 2, result: $lineup);

            // Swapped: opponent as home — different cache key, should be a miss
            $result = $this->service->getOrDispatch(homeTeamId: 2, opponentTeamId: 1);
            expect($result)->toBeNull();

            // Original order still hits
            $original = $this->service->getOrDispatch(homeTeamId: 1, opponentTeamId: 2);
            expect($original)->not->toBeNull();
        });
    });

    describe('store', function () {
        it('persists the lineup for later retrieval', function () {
            $data = ['recommended_lineup' => [['player_id' => 5]], 'confidence' => 0.90];

            $this->service->store(3, 4, $data);

            expect($this->service->getOrDispatch(3, 4))->toMatchArray($data);
        });
    });
});
