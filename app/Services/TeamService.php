<?php

namespace App\Services;

use App\Models\Team;
use App\Repositories\TeamRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class TeamService
{
    public function __construct(
        private readonly TeamRepository $teamRepository,
    ) {}

    /**
     * Update team details (name, code, is_active).
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Team $team, array $data): Team
    {
        $this->teamRepository->update($team, $data);

        return $team->fresh();
    }

    /**
     * Toggle team is_active status.
     */
    public function toggleActive(Team $team): Team
    {
        $this->teamRepository->update($team, ['is_active' => ! $team->is_active]);

        return $team->fresh();
    }

    /**
     * Store a new team logo, remove the old one, persist the path.
     */
    public function updateLogo(Team $team, UploadedFile $file): Team
    {
        if ($team->logo_path) {
            Storage::disk('public')->delete($team->logo_path);
        }

        $path = $file->store("team-logos/{$team->id}", 'public');

        $this->teamRepository->update($team, ['logo_path' => $path]);

        return $team->fresh();
    }
}
