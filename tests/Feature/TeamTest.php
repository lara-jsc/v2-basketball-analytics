<?php

use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia;

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
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Teams/Show')
                ->where('playersExportUrl', route('teams.players.export', $team)));
    });

    it('exports the visible roster subset as an import-safe csv', function () {
        $team = Team::factory()->create();
        $keep = Player::factory()->for($team)->create([
            'first_name' => 'Alex',
            'last_name' => 'Stone',
            'jersey_number' => 7,
            'role' => 'Guard',
            'height_feet' => 6.1,
            'weight_kg' => 83.5,
            'is_active' => true,
        ]);
        Player::factory()->for($team)->create([
            'first_name' => 'Brian',
            'last_name' => 'Mills',
            'jersey_number' => 12,
            'role' => 'Forward',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('teams.players.export', ['team' => $team, 'search' => 'alex']));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $rows = array_map(
            static fn (string $line): array => str_getcsv($line),
            preg_split('/\r\n|\r|\n/', trim($response->streamedContent())) ?: [],
        );

        expect($rows[0])->toBe(\App\Services\CsvTemplateService::HEADERS);
        expect($rows)->toHaveCount(2);
        expect($rows[1])->toBe([
            $keep->first_name,
            $keep->last_name,
            (string) $keep->jersey_number,
            $keep->role,
            (string) $keep->height_feet,
            (string) $keep->weight_kg,
            '1',
        ]);
    });

    it('allows an exported roster csv to be uploaded through the existing import route', function () {
        $sourceTeam = Team::factory()->create();
        $targetTeam = Team::factory()->create();

        Player::factory()->for($sourceTeam)->create([
            'first_name' => 'Maya',
            'last_name' => 'Lane',
            'jersey_number' => 4,
            'role' => 'Point Guard',
            'height_feet' => 5.9,
            'weight_kg' => 70.2,
            'is_active' => true,
        ]);
        Player::factory()->for($sourceTeam)->create([
            'first_name' => 'Tori',
            'last_name' => 'Banks',
            'jersey_number' => 15,
            'role' => 'Center',
            'height_feet' => 6.4,
            'weight_kg' => 91.3,
            'is_active' => false,
        ]);

        $export = $this->actingAs($this->user)
            ->get(route('teams.players.export', $sourceTeam))
            ->streamedContent();

        $upload = UploadedFile::fake()->createWithContent('roster.csv', $export);

        $this->actingAs($this->user)
            ->post(route('csv.upload'), [
                'team_id' => $targetTeam->id,
                'file' => $upload,
            ])
            ->assertRedirect(route('teams.show', $targetTeam));

        $this->assertDatabaseHas('players', [
            'team_id' => $targetTeam->id,
            'first_name' => 'Maya',
            'last_name' => 'Lane',
            'jersey_number' => 4,
        ]);
        $this->assertDatabaseHas('players', [
            'team_id' => $targetTeam->id,
            'first_name' => 'Tori',
            'last_name' => 'Banks',
            'jersey_number' => 15,
            'is_active' => false,
        ]);
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
        $file = UploadedFile::fake()->image('logo.png');

        $this->actingAs($this->user)
            ->post(route('teams.uploadLogo', $team), ['logo' => $file])
            ->assertRedirect(route('teams.show', $team));

        expect($team->fresh()->logo_path)->not->toBeNull();
    });
});
