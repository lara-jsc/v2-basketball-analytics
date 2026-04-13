<?php

namespace App\Services;

use App\Jobs\ComputePlayerPlusMinus;
use App\Models\Player;
use App\Models\Team;
use App\Repositories\PlayerRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PlayerService
{
    public function __construct(
        private readonly PlayerRepository $playerRepository,
    ) {}

    /**
     * Create a player with an initial stats row and dispatch BPM computation.
     *
     * @param  array<string, mixed>  $playerData
     * @param  array<string, mixed>  $statsData
     */
    public function createWithStats(Team $team, array $playerData, array $statsData): Player
    {
        return DB::transaction(function () use ($team, $playerData, $statsData) {
            $player = $this->playerRepository->create([
                ...$playerData,
                'team_id' => $team->id,
            ]);

            if ($this->hasAnyStatValue($statsData)) {
                $stat = $this->playerRepository->createStat([
                    ...$statsData,
                    'player_id' => $player->id,
                ]);

                ComputePlayerPlusMinus::dispatch($stat->id);
            }

            return $player;
        });
    }

    /**
     * Update player identity fields and upsert their stats row.
     *
     * @param  array<string, mixed>  $playerData
     * @param  array<string, mixed>  $statsData
     */
    public function updateWithStats(Player $player, array $playerData, array $statsData): Player
    {
        return DB::transaction(function () use ($player, $playerData, $statsData) {
            $this->playerRepository->update($player, $playerData);

            $stat = $this->playerRepository->upsertStat($player, $statsData);

            ComputePlayerPlusMinus::dispatch($stat->id);

            return $player->fresh();
        });
    }

    /**
     * Hard-delete a player and their stats. Removes profile picture from storage.
     */
    public function delete(Player $player): void
    {
        if ($player->profile_picture_path) {
            Storage::disk('public')->delete($player->profile_picture_path);
        }

        $player->stats()->delete();
        $player->delete();
    }

    /**
     * Toggle is_active and return the updated player.
     */
    public function toggleActive(Player $player): Player
    {
        $this->playerRepository->update($player, ['is_active' => ! $player->is_active]);

        return $player->fresh();
    }

    /**
     * Store a new profile picture, remove the old one, persist the path.
     */
    public function updateProfilePicture(Player $player, UploadedFile $file): Player
    {
        if ($player->profile_picture_path) {
            Storage::disk('public')->delete($player->profile_picture_path);
        }

        $path = $file->store("profile-pictures/{$player->id}", 'public');

        $this->playerRepository->update($player, ['profile_picture_path' => $path]);

        return $player->fresh();
    }

    /**
     * Returns true if at least one stat value is non-null, so we only create
     * a stats row when the user actually entered stats.
     *
     * @param  array<string, mixed>  $statsData
     */
    private function hasAnyStatValue(array $statsData): bool
    {
        foreach ($statsData as $value) {
            if ($value !== null && $value !== '') {
                return true;
            }
        }

        return false;
    }
}
