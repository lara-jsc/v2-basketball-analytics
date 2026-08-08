<?php

use App\Models\Team;
use App\Repositories\TeamRepository;
use App\Services\TeamService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

describe('TeamService', function () {

    beforeEach(function () {
        $this->service = new TeamService(new TeamRepository);
        Storage::fake('public');
    });

    // ------------------------------------------------------------------
    // update
    // ------------------------------------------------------------------
    describe('update', function () {
        it('persists new name and code', function () {
            $team = Team::factory()->create(['name' => 'Old Name', 'code' => 'OLD']);

            $updated = $this->service->update($team, ['name' => 'New Name', 'code' => 'NEW']);

            expect($updated->name)->toBe('New Name');
            expect($updated->code)->toBe('NEW');
        });

        it('returns a fresh model with updated attributes', function () {
            $team = Team::factory()->create();

            $result = $this->service->update($team, ['name' => 'Fresh']);

            expect($result)->toBeInstanceOf(Team::class);
            expect($result->name)->toBe('Fresh');
        });
    });

    // ------------------------------------------------------------------
    // toggleActive
    // ------------------------------------------------------------------
    describe('toggleActive', function () {
        it('deactivates an active team', function () {
            $team = Team::factory()->create(['is_active' => true]);

            $updated = $this->service->toggleActive($team);

            expect($updated->is_active)->toBeFalse();
        });

        it('activates an inactive team', function () {
            $team = Team::factory()->create(['is_active' => false]);

            $updated = $this->service->toggleActive($team);

            expect($updated->is_active)->toBeTrue();
        });
    });

    // ------------------------------------------------------------------
    // updateLogo
    // ------------------------------------------------------------------
    describe('updateLogo', function () {
        it('stores the file and updates the team logo_path', function () {
            $team = Team::factory()->create(['logo_path' => null]);
            $file = UploadedFile::fake()->image('logo.png');

            $updated = $this->service->updateLogo($team, $file);

            expect($updated->logo_path)->not->toBeNull();
            Storage::disk('public')->assertExists($updated->logo_path);
        });

        it('removes the old logo file when replacing it', function () {
            $oldPath = 'team-logos/1/old.png';
            Storage::disk('public')->put($oldPath, 'old logo data');

            $team = Team::factory()->create(['logo_path' => $oldPath]);
            $file = UploadedFile::fake()->image('new.png');

            $this->service->updateLogo($team, $file);

            Storage::disk('public')->assertMissing($oldPath);
        });

        it('stores the logo under team-logos/{team_id}/ path', function () {
            $team = Team::factory()->create();
            $file = UploadedFile::fake()->image('logo.png');

            $updated = $this->service->updateLogo($team, $file);

            expect($updated->logo_path)->toStartWith("team-logos/{$team->id}/");
        });
    });
});
