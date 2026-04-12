<?php

namespace App\Console\Commands;

use App\Jobs\ComputePlayerPlusMinus;
use App\Models\Player;
use Illuminate\Console\Command;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Throwable;

/**
 * Dispatch ComputePlayerPlusMinus jobs for all matching players.
 *
 * Usage:
 *   php artisan stats:compute-plus-minus            # all players
 *   php artisan stats:compute-plus-minus --team=1    # players in team ID 1
 *   php artisan stats:compute-plus-minus --player=42 # specific player ID 42
 */
class ComputePlusMinus extends Command
{
    protected $signature = 'stats:compute-plus-minus
                            {--team=   : Compute only for players in this team ID}
                            {--player= : Compute only for this specific player ID}';

    protected $description = 'Recompute plus/minus aggregates from player_histories for all players (or a filtered subset)';

    public function handle(): int
    {
        $playerOption = $this->option('player');
        $teamOption   = $this->option('team');

        $query = Player::query();

        if ($playerOption !== null) {
            $query->where('id', (int) $playerOption);
        } elseif ($teamOption !== null) {
            $query->where('team_id', (int) $teamOption);
        }

        $players = $query->get(['id']);

        if ($players->isEmpty()) {
            $this->warn('No players found matching the given options.');
            return self::FAILURE;
        }

        $jobs = $players
            ->map(fn (Player $player) => new ComputePlayerPlusMinus($player->id))
            ->all();

        $command = $this;

        Bus::batch($jobs)
            ->name('compute-plus-minus')
            ->catch(function (Batch $batch, Throwable $e) use ($command) {
                $command->getOutput()->error("A batch job failed: {$e->getMessage()}");
            })
            ->dispatch();

        $this->info("Dispatched plus/minus computation for {$players->count()} player(s).");

        return self::SUCCESS;
    }
}
