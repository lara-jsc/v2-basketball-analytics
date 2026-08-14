<?php

use App\Jobs\ShotZoneProfileImportJob;
use App\Models\CsvImport;
use App\Models\Player;
use App\Models\PlayerHistory;
use App\Models\PlayerShotZoneProfile;
use App\Models\PlayerStat;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Build a minimal CSV string with the shot zone profile headers.
 *
 * @param  list<list<mixed>>  $rows
 * @param  list<string>|null  $headers  Override for bad-header tests
 */
function buildProfileCsv(array $rows = [], ?array $headers = null): string
{
    $hdrs = $headers ?? ShotZoneProfileImportJob::HEADERS;
    $lines = [implode(',', $hdrs)];

    foreach ($rows as $row) {
        $lines[] = implode(',', $row);
    }

    return implode("\n", $lines);
}

/**
 * Upload a profile CSV via the import route and return the HTTP response.
 */
function uploadProfileCsv(array $rows = [], ?array $headers = null): TestResponse
{
    $team = Team::factory()->create();
    $user = User::factory()->forTeam($team)->create(['email_verified_at' => now()]);

    $csv = buildProfileCsv($rows, $headers);
    $file = UploadedFile::fake()->createWithContent('profile.csv', $csv);

    return test()->actingAs($user)->post(route('shot-zone-profiles.import', $team), ['file' => $file]);
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('upserts a season shot profile for a team player', function () {
    Storage::fake('local');
    $team = Team::factory()->create();
    $user = User::factory()->forTeam($team)->create(['email_verified_at' => now()]);
    $player = Player::factory()->forTeam($team)->create(['is_active' => true]);

    $csv = buildProfileCsv([
        [$player->id, 30, 55, 12, 30, 5, 13, 6, 15, 18, 50],
    ]);

    $file = UploadedFile::fake()->createWithContent('profile.csv', $csv);

    test()->actingAs($user)->post(route('shot-zone-profiles.import', $team), ['file' => $file])
        ->assertRedirect();

    $profile = PlayerShotZoneProfile::where('player_id', $player->id)->first();
    expect($profile)->not->toBeNull()
        ->and($profile->paint_made)->toBe(30)
        ->and($profile->paint_attempted)->toBe(55)
        ->and($profile->above_break_3_made)->toBe(18)
        ->and($profile->above_break_3_attempted)->toBe(50);
});

it('rejects a row when zone made exceeds attempted', function () {
    Storage::fake('local');
    $team = Team::factory()->create();
    $user = User::factory()->forTeam($team)->create(['email_verified_at' => now()]);
    $player = Player::factory()->forTeam($team)->create(['is_active' => true]);

    // paint_made (60) > paint_attempted (55)
    $csv = buildProfileCsv([
        [$player->id, 60, 55, 12, 30, 5, 13, 6, 15, 18, 50],
    ]);

    $file = UploadedFile::fake()->createWithContent('profile.csv', $csv);

    test()->actingAs($user)->post(route('shot-zone-profiles.import', $team), ['file' => $file])
        ->assertRedirect();

    $import = CsvImport::latest()->first();
    expect($import)->not->toBeNull()
        ->and($import->error_log)->toContain('cannot exceed attempted');

    expect(PlayerShotZoneProfile::where('player_id', $player->id)->exists())->toBeFalse();
});

it('rejects a row when 2PT zone totals disagree with player histories', function () {
    Storage::fake('local');
    $team = Team::factory()->create();
    $user = User::factory()->forTeam($team)->create(['email_verified_at' => now()]);
    $player = Player::factory()->forTeam($team)->create(['is_active' => true]);
    $opponent = Team::factory()->create();

    // History: FGA=20, 3PA=8 → 2PT attempts = 12; 3PT attempts = 8
    PlayerHistory::factory()->for($player)->create([
        'playing_team_id' => $team->id,
        'opponent_team_id' => $opponent->id,
        'field_goals_made' => 8,
        'field_goals_attempted' => 20,
        'three_pointers_made' => 3,
        'three_pointers_attempted' => 8,
    ]);

    // Profile claims paint=10 + mid=10 = 20 2PT attempts, but history says 12
    // → mismatch on 2PT split → must be rejected
    $csv = buildProfileCsv([
        [$player->id, 5, 10, 4, 10, 2, 4, 2, 4, 3, 8],
    ]);

    $file = UploadedFile::fake()->createWithContent('profile.csv', $csv);

    test()->actingAs($user)->post(route('shot-zone-profiles.import', $team), ['file' => $file])
        ->assertRedirect();

    $import = CsvImport::latest()->first();
    expect($import->error_log)->toContain('does not match box-score history');

    expect(PlayerShotZoneProfile::where('player_id', $player->id)->exists())->toBeFalse();
});

it('accepts a profile when the player has no history yet', function () {
    Storage::fake('local');
    $team = Team::factory()->create();
    $user = User::factory()->forTeam($team)->create(['email_verified_at' => now()]);
    $player = Player::factory()->forTeam($team)->create(['is_active' => true]);

    $csv = buildProfileCsv([
        [$player->id, 10, 20, 5, 15, 3, 8, 3, 8, 5, 18],
    ]);

    $file = UploadedFile::fake()->createWithContent('profile.csv', $csv);

    test()->actingAs($user)->post(route('shot-zone-profiles.import', $team), ['file' => $file])
        ->assertRedirect();

    expect(PlayerShotZoneProfile::where('player_id', $player->id)->exists())->toBeTrue();
});

it('shows matchup heat from profile alone with no live games', function () {
    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();
    $user = User::factory()->forTeam($teamA)->create(['email_verified_at' => now()]);

    $playerA = Player::factory()->forTeam($teamA)->create(['is_active' => true]);
    $playerB = Player::factory()->forTeam($teamB)->create(['is_active' => true]);

    PlayerStat::factory()->for($playerA)->create();
    PlayerStat::factory()->for($playerB)->create();

    // Seed profiles directly — no live events
    PlayerShotZoneProfile::factory()->forPlayer($playerA)->create([
        'paint_made' => 30,
        'paint_attempted' => 55,
    ]);

    test()->actingAs($user)
        ->get(route('comparison.show', ['teamA' => $teamA->id, 'teamB' => $teamB->id])
            ."?player_a={$playerA->id}&player_b={$playerB->id}")
        ->assertInertia(fn ($page) => $page
            ->has('playerAShotZones.zones', 5)
            ->where('playerAShotZones.zones.paint.made', 30)
            ->where('playerAShotZones.zones.paint.attempted', 55)
            ->where('playerAShotZones.zones.paint.has_enough_data', true));
});

it('replaces a prior profile on re-import', function () {
    Storage::fake('local');
    $team = Team::factory()->create();
    $user = User::factory()->forTeam($team)->create(['email_verified_at' => now()]);
    $player = Player::factory()->forTeam($team)->create(['is_active' => true]);

    // First import
    $csv = buildProfileCsv([[$player->id, 10, 20, 5, 15, 3, 8, 3, 8, 5, 18]]);
    test()->actingAs($user)->post(route('shot-zone-profiles.import', $team), [
        'file' => UploadedFile::fake()->createWithContent('profile.csv', $csv),
    ]);

    expect(PlayerShotZoneProfile::where('player_id', $player->id)->count())->toBe(1);

    // Second import with different numbers
    $csv2 = buildProfileCsv([[$player->id, 20, 40, 8, 20, 4, 10, 4, 10, 8, 22]]);
    test()->actingAs($user)->post(route('shot-zone-profiles.import', $team), [
        'file' => UploadedFile::fake()->createWithContent('profile.csv', $csv2),
    ]);

    expect(PlayerShotZoneProfile::where('player_id', $player->id)->count())->toBe(1);

    $profile = PlayerShotZoneProfile::where('player_id', $player->id)->first();
    expect($profile->paint_made)->toBe(20)->and($profile->paint_attempted)->toBe(40);
});

it('rejects a player not belonging to the team', function () {
    Storage::fake('local');
    $team = Team::factory()->create();
    $otherTeam = Team::factory()->create();
    $user = User::factory()->forTeam($team)->create(['email_verified_at' => now()]);
    $foreignPlayer = Player::factory()->forTeam($otherTeam)->create(['is_active' => true]);

    $csv = buildProfileCsv([[$foreignPlayer->id, 10, 20, 5, 15, 3, 8, 3, 8, 5, 18]]);

    test()->actingAs($user)->post(route('shot-zone-profiles.import', $team), [
        'file' => UploadedFile::fake()->createWithContent('profile.csv', $csv),
    ]);

    expect(PlayerShotZoneProfile::where('player_id', $foreignPlayer->id)->exists())->toBeFalse();
});
