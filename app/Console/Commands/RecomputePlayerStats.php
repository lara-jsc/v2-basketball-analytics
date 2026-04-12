<?php

namespace App\Console\Commands;

use App\Services\PlayerStatsComputationService;
use Illuminate\Console\Command;

/**
 * Synchronously recompute EFF, eFG%, TS%, and +/- for all players (or one).
 *
 * Usage:
 *   php artisan stats:recompute                   # all players
 *   php artisan stats:recompute --player=42       # single player ID
 */
class RecomputePlayerStats extends Command
{
    protected $signature   = 'stats:recompute {--player= : Player ID (optional)}';
    protected $description = 'Recompute EFF, eFG%, TS%, and +/- for all players (or a single player)';

    public function handle(PlayerStatsComputationService $service): int
    {
        if ($playerId = $this->option('player')) {
            $this->info("Recomputing stats for player {$playerId}...");

            $stat = $service->computeForPlayer((int) $playerId);

            $this->table(
                ['EFF', 'eFG%', 'TS%', '+/-'],
                [[
                    $stat->efficiency_formatted,
                    $stat->efg_percent_formatted,
                    $stat->ts_percent_formatted,
                    $stat->formatted_plus_minus,
                ]]
            );
        } else {
            $this->info('Recomputing stats for all players...');
            $service->computeAll();
            $this->info('Done.');
        }

        return self::SUCCESS;
    }
}
