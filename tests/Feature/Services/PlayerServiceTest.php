<?php

use App\Jobs\ComputePlayerPlusMinus;
use App\Models\Player;
use App\Models\PlayerStat;
use App\Models\Team;
use App\Repositories\PlayerRepository;
use App\Services\PlayerService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

describe('PlayerService', function () {

    beforeEach(function () {
        $this->service = new PlayerService(new PlayerRepository());
        $this->team    = Team::factory()->create();
        Bus::fake();
        Storage::fake('public');
    });

    // ------------------------------------------------------------------
    // createWithStats
    // ------------------------------------------------------------------
    describe('createWithStats', function () {
        it('creates a player record in the database', function () {
            $player = $this->service->createWithStats(
                $this->team,
                ['first_name' => 'LeBron', 'last_name' => 'James', 'jersey_number' => 23, 'is_active' => true],
                [],
            );

            expect($player)->toBeInstanceOf(Player::class);
            $this->assertDatabaseHas('players', ['first_name' => 'LeBron', 'jersey_number' => 23]);
        });

        it('does NOT create a stats row when no stats are provided', function () {
            $player = $this->service->createWithStats(
                $this->team,
                ['first_name' => 'Kyrie', 'last_name' => 'Irving', 'jersey_number' => 11, 'is_active' => true],
                [],
            );

            $this->assertDatabaseMissing('player_stats', ['player_id' => $player->id]);
            Bus::assertNotDispatched(ComputePlayerPlusMinus::class);
        });

        it('creates a stats row when stats are provided and dispatches BPM job', function () {
            $player = $this->service->createWithStats(
                $this->team,
                ['first_name' => 'Anthony', 'last_name' => 'Davis', 'jersey_number' => 3, 'is_active' => true],
                ['pts' => 26.1, 'reb' => 12.5, 'min' => 35.0],
            );

            $this->assertDatabaseHas('player_stats', ['player_id' => $player->id, 'pts' => 26.1]);
            Bus::assertDispatched(ComputePlayerPlusMinus::class);
        });
    });

    // ------------------------------------------------------------------
    // updateWithStats
    // ------------------------------------------------------------------
    describe('updateWithStats', function () {
        it('updates player identity fields', function () {
            $player = Player::factory()->forTeam($this->team)->create(['first_name' => 'Old']);

            $this->service->updateWithStats(
                $player,
                ['first_name' => 'Updated', 'last_name' => $player->last_name, 'jersey_number' => $player->jersey_number],
                [],
            );

            expect($player->fresh()->first_name)->toBe('Updated');
        });

        it('upserts the stats row and dispatches BPM job', function () {
            $player = Player::factory()->forTeam($this->team)->create();
            PlayerStat::factory()->forPlayer($player)->create(['pts' => 10.0]);

            $this->service->updateWithStats($player, [], ['pts' => 25.0]);

            expect($player->stats()->first()->pts)->toBe(25.0);
            Bus::assertDispatched(ComputePlayerPlusMinus::class);
        });
    });

    // ------------------------------------------------------------------
    // delete
    // ------------------------------------------------------------------
    describe('delete', function () {
        it('removes the player from the database', function () {
            $player = Player::factory()->forTeam($this->team)->create();

            $this->service->delete($player);

            $this->assertDatabaseMissing('players', ['id' => $player->id]);
        });

        it('removes associated stats', function () {
            $player = Player::factory()->forTeam($this->team)->create();
            PlayerStat::factory()->forPlayer($player)->create();

            $this->service->delete($player);

            $this->assertDatabaseMissing('player_stats', ['player_id' => $player->id]);
        });

        it('deletes the profile picture from storage', function () {
            $player = Player::factory()->forTeam($this->team)->create([
                'profile_picture_path' => 'profile-pictures/1/photo.jpg',
            ]);
            Storage::disk('public')->put('profile-pictures/1/photo.jpg', 'fake image data');

            $this->service->delete($player);

            Storage::disk('public')->assertMissing('profile-pictures/1/photo.jpg');
        });
    });

    // ------------------------------------------------------------------
    // toggleActive
    // ------------------------------------------------------------------
    describe('toggleActive', function () {
        it('sets is_active to false when player is active', function () {
            $player = Player::factory()->forTeam($this->team)->create(['is_active' => true]);

            $updated = $this->service->toggleActive($player);

            expect($updated->is_active)->toBeFalse();
        });

        it('sets is_active to true when player is inactive', function () {
            $player = Player::factory()->forTeam($this->team)->create(['is_active' => false]);

            $updated = $this->service->toggleActive($player);

            expect($updated->is_active)->toBeTrue();
        });
    });

    // ------------------------------------------------------------------
    // updateProfilePicture
    // ------------------------------------------------------------------
    describe('updateProfilePicture', function () {
        it('stores the new picture and updates the player path', function () {
            $player = Player::factory()->forTeam($this->team)->create(['profile_picture_path' => null]);
            $file   = UploadedFile::fake()->image('avatar.jpg');

            $updated = $this->service->updateProfilePicture($player, $file);

            expect($updated->profile_picture_path)->not->toBeNull();
            Storage::disk('public')->assertExists($updated->profile_picture_path);
        });

        it('deletes the old picture when replacing it', function () {
            $oldPath = "profile-pictures/{$this->team->id}/old.jpg";
            Storage::disk('public')->put($oldPath, 'old data');

            $player = Player::factory()->forTeam($this->team)->create([
                'profile_picture_path' => $oldPath,
            ]);
            $file = UploadedFile::fake()->image('new.jpg');

            $this->service->updateProfilePicture($player, $file);

            Storage::disk('public')->assertMissing($oldPath);
        });
    });
});
