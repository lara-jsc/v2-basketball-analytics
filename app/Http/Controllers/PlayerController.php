<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePlayerRequest;
use App\Http\Requests\UpdatePlayerRequest;
use App\Http\Requests\UploadProfilePictureRequest;
use App\Models\Player;
use App\Models\Team;
use App\Services\PlayerService;
use Illuminate\Http\RedirectResponse;

class PlayerController extends Controller
{
    /** Stat fields sent in the form that belong to player_stats, not players. */
    private const STAT_FIELDS = [
        'pc', 'sd', 'three_p_pct', 'three_pt', 'ast', 'ast_to', 'blk', 'dd2', 'dq',
        'dr', 'eject', 'fg', 'fg_pct', 'flag', 'ft', 'ft_pct', 'gp', 'gs', 'min',
        'offensive_rebounds', 'pf', 'pts', 'reb', 'sc_eff', 'sh_eff', 'stl', 'stl_to',
        'td3', 'tech', 'to_per_game',
    ];

    /** Player identity fields that belong to the players table. */
    private const PLAYER_FIELDS = [
        'first_name', 'last_name', 'jersey_number', 'role',
        'height_feet', 'weight_kg', 'is_active',
    ];

    public function __construct(
        private readonly PlayerService $playerService,
    ) {}

    /**
     * Create a new player under the given team.
     */
    public function store(StorePlayerRequest $request, Team $team): RedirectResponse
    {
        $validated = $request->validated();

        $this->playerService->createWithStats(
            $team,
            $this->extractPlayerData($validated),
            $this->extractStatsData($validated),
        );

        return redirect()
            ->route('teams.show', $team->id)
            ->with('success', 'Player added successfully.');
    }

    /**
     * Update an existing player and their stat row.
     */
    public function update(UpdatePlayerRequest $request, Player $player): RedirectResponse
    {
        $validated = $request->validated();

        $this->playerService->updateWithStats(
            $player,
            $this->extractPlayerData($validated),
            $this->extractStatsData($validated),
        );

        return redirect()
            ->route('teams.show', $player->team_id)
            ->with('success', 'Player updated successfully.');
    }

    /**
     * Hard-delete a player after confirmation (handled on the frontend).
     */
    public function destroy(Player $player): RedirectResponse
    {
        $teamId = $player->team_id;

        $this->playerService->delete($player);

        return redirect()
            ->route('teams.show', $teamId)
            ->with('success', 'Player removed.');
    }

    /**
     * Toggle player is_active status.
     */
    public function toggleActive(Player $player): RedirectResponse
    {
        $this->playerService->toggleActive($player);

        return redirect()
            ->route('teams.show', $player->team_id)
            ->with('success', $player->is_active ? 'Player deactivated.' : 'Player activated.');
    }

    /**
     * Upload / replace the player's profile picture.
     */
    public function uploadPicture(UploadProfilePictureRequest $request, Player $player): RedirectResponse
    {
        $this->playerService->updateProfilePicture($player, $request->file('picture'));

        return redirect()
            ->route('teams.show', $player->team_id)
            ->with('success', 'Profile picture updated.');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function extractPlayerData(array $validated): array
    {
        return array_filter(
            $validated,
            fn (string $key) => in_array($key, self::PLAYER_FIELDS, true),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function extractStatsData(array $validated): array
    {
        return array_filter(
            $validated,
            fn (string $key) => in_array($key, self::STAT_FIELDS, true),
            ARRAY_FILTER_USE_KEY,
        );
    }
}
