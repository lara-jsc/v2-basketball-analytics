<?php

use App\Jobs\ComputePlayerPlusMinus;
use App\Models\Player;
use App\Models\PlayerStat;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

describe('Player HTTP endpoints', function () {

    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->team = Team::factory()->create();
        Bus::fake(); // prevent real job dispatch
    });

    // ------------------------------------------------------------------
    // Auth guard
    // ------------------------------------------------------------------
    it('redirects unauthenticated users away from player routes', function () {
        $team   = Team::factory()->create();
        $player = Player::factory()->forTeam($team)->create();

        $this->post(route('players.store', $team))->assertRedirect(route('login'));
        $this->put(route('players.update', $player))->assertRedirect(route('login'));
        $this->delete(route('players.destroy', $player))->assertRedirect(route('login'));
    });

    // ------------------------------------------------------------------
    // Store
    // ------------------------------------------------------------------
    it('creates a player under a team and redirects back', function () {
        $this->actingAs($this->user)
            ->post(route('players.store', $this->team), [
                'first_name'    => 'LeBron',
                'last_name'     => 'James',
                'jersey_number' => 23,
                'role'          => 'Small Forward',
                'height_feet'   => 6.9,
                'weight_kg'     => 113.0,
                'is_active'     => true,
            ])
            ->assertRedirect(route('teams.show', $this->team));

        $this->assertDatabaseHas('players', [
            'first_name'    => 'LeBron',
            'last_name'     => 'James',
            'jersey_number' => 23,
            'team_id'       => $this->team->id,
        ]);
    });

    it('rejects store when required fields are missing', function () {
        $this->actingAs($this->user)
            ->post(route('players.store', $this->team), [])
            ->assertSessionHasErrors(['first_name', 'last_name', 'jersey_number']);
    });

    it('dispatches ComputePlayerPlusMinus when stats are provided', function () {
        $this->actingAs($this->user)
            ->post(route('players.store', $this->team), [
                'first_name'    => 'Anthony',
                'last_name'     => 'Davis',
                'jersey_number' => 3,
                'role'          => 'Center',
                'is_active'     => true,
                'pts'           => 26.1,
                'min'           => 35.0,
            ]);

        Bus::assertDispatched(ComputePlayerPlusMinus::class);
    });

    // ------------------------------------------------------------------
    // Update
    // ------------------------------------------------------------------
    it('updates a player and redirects to team show', function () {
        $player = Player::factory()->forTeam($this->team)->create(['first_name' => 'Old']);

        $this->actingAs($this->user)
            ->put(route('players.update', $player), [
                'first_name'    => 'Updated',
                'last_name'     => $player->last_name,
                'jersey_number' => $player->jersey_number,
                'is_active'     => true,
            ])
            ->assertRedirect(route('teams.show', $this->team));

        expect($player->fresh()->first_name)->toBe('Updated');
    });

    // ------------------------------------------------------------------
    // Destroy
    // ------------------------------------------------------------------
    it('deletes a player and redirects to team show', function () {
        $player = Player::factory()->forTeam($this->team)->create();

        $this->actingAs($this->user)
            ->delete(route('players.destroy', $player))
            ->assertRedirect(route('teams.show', $this->team));

        $this->assertDatabaseMissing('players', ['id' => $player->id]);
    });

    it('also deletes the player stats on destroy', function () {
        $player = Player::factory()->forTeam($this->team)->create();
        PlayerStat::factory()->forPlayer($player)->create();

        $this->actingAs($this->user)
            ->delete(route('players.destroy', $player));

        $this->assertDatabaseMissing('player_stats', ['player_id' => $player->id]);
    });

    // ------------------------------------------------------------------
    // Toggle Active
    // ------------------------------------------------------------------
    it('deactivates an active player', function () {
        $player = Player::factory()->forTeam($this->team)->create(['is_active' => true]);

        $this->actingAs($this->user)
            ->patch(route('players.toggleActive', $player))
            ->assertRedirect();

        expect($player->fresh()->is_active)->toBeFalse();
    });

    it('activates an inactive player', function () {
        $player = Player::factory()->forTeam($this->team)->create(['is_active' => false]);

        $this->actingAs($this->user)
            ->patch(route('players.toggleActive', $player))
            ->assertRedirect();

        expect($player->fresh()->is_active)->toBeTrue();
    });

    // ------------------------------------------------------------------
    // Profile Picture Upload
    // ------------------------------------------------------------------
    it('accepts a profile picture upload and updates the player', function () {
        Storage::fake('public');
        $player = Player::factory()->forTeam($this->team)->create();
        $file   = \Illuminate\Http\UploadedFile::fake()->image('photo.jpg');

        $this->actingAs($this->user)
            ->post(route('players.uploadPicture', $player), ['picture' => $file])
            ->assertRedirect(route('teams.show', $this->team));

        expect($player->fresh()->profile_picture_path)->not->toBeNull();
    });
});
