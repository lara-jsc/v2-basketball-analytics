<?php

use App\Models\Team;
use App\Models\User;

describe('Team HTTP endpoints', function () {

    beforeEach(function () {
        $this->user = User::factory()->create();
    });

    // ------------------------------------------------------------------
    // Auth guard
    // ------------------------------------------------------------------
    it('redirects unauthenticated users away from team routes', function () {
        $this->get(route('teams.index'))->assertRedirect(route('login'));
        $this->get(route('teams.create'))->assertRedirect(route('login'));
        $this->post(route('teams.store'))->assertRedirect(route('login'));
    });

    // ------------------------------------------------------------------
    // Index
    // ------------------------------------------------------------------
    it('shows the team list page', function () {
        Team::factory()->count(3)->create();

        $this->actingAs($this->user)
            ->get(route('teams.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Teams/Index'));
    });

    // ------------------------------------------------------------------
    // Create / Store
    // ------------------------------------------------------------------
    it('shows the create team form', function () {
        $this->actingAs($this->user)
            ->get(route('teams.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Teams/Create'));
    });

    it('creates a team and redirects to the show page', function () {
        $this->actingAs($this->user)
            ->post(route('teams.store'), [
                'name' => 'Lakers',
                'code' => 'LAL',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('teams', ['code' => 'LAL', 'name' => 'Lakers']);
    });

    it('rejects store when required fields are missing', function () {
        $this->actingAs($this->user)
            ->post(route('teams.store'), [])
            ->assertSessionHasErrors(['name', 'code']);
    });

    // ------------------------------------------------------------------
    // Show
    // ------------------------------------------------------------------
    it('shows a single team page', function () {
        $team = Team::factory()->create();

        $this->actingAs($this->user)
            ->get(route('teams.show', $team))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Teams/Show'));
    });

    // ------------------------------------------------------------------
    // Edit / Update
    // ------------------------------------------------------------------
    it('shows the team edit form', function () {
        $team = Team::factory()->create();

        $this->actingAs($this->user)
            ->get(route('teams.edit', $team))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Teams/Edit'));
    });

    it('updates a team and redirects', function () {
        $team = Team::factory()->create(['name' => 'Old Name', 'code' => 'OLD']);

        $this->actingAs($this->user)
            ->put(route('teams.update', $team), [
                'name' => 'New Name',
                'code' => 'NEW',
            ])
            ->assertRedirect(route('teams.show', $team));

        expect($team->fresh()->name)->toBe('New Name');
    });

    // ------------------------------------------------------------------
    // Toggle Active
    // ------------------------------------------------------------------
    it('toggles a team from active to inactive', function () {
        $team = Team::factory()->create(['is_active' => true]);

        $this->actingAs($this->user)
            ->patch(route('teams.toggleActive', $team))
            ->assertRedirect(route('teams.show', $team));

        expect($team->fresh()->is_active)->toBeFalse();
    });

    it('toggles a team from inactive to active', function () {
        $team = Team::factory()->create(['is_active' => false]);

        $this->actingAs($this->user)
            ->patch(route('teams.toggleActive', $team))
            ->assertRedirect();

        expect($team->fresh()->is_active)->toBeTrue();
    });

    // ------------------------------------------------------------------
    // Logo Upload
    // ------------------------------------------------------------------
    it('accepts a logo upload and updates the team', function () {
        $team = Team::factory()->create();
        $file = \Illuminate\Http\UploadedFile::fake()->image('logo.png');

        $this->actingAs($this->user)
            ->post(route('teams.uploadLogo', $team), ['logo' => $file])
            ->assertRedirect(route('teams.show', $team));

        expect($team->fresh()->logo_path)->not->toBeNull();
    });
});
