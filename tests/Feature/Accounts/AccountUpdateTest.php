<?php

use App\Enums\UserRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

function updatePayload(User $user, array $overrides = []): array
{
    return array_merge([
        'name' => $user->name,
        'email' => $user->email,
        'password' => null,
        'password_confirmation' => null,
        'role' => $user->role->value,
        'team_id' => $user->team_id,
    ], $overrides);
}

it('renames an account and keeps its password when none is given', function () {
    $team = Team::factory()->create();
    $coach = User::factory()->forTeam($team)->create();
    $originalHash = $coach->password;

    $this->actingAs($this->admin)
        ->put(route('accounts.update', $coach), updatePayload($coach, ['name' => 'Renamed']))
        ->assertRedirect(route('accounts.index'));

    $coach->refresh();

    expect($coach->name)->toBe('Renamed')
        ->and($coach->password)->toBe($originalHash);
});

it('changes the password when one is given', function () {
    $team = Team::factory()->create();
    $coach = User::factory()->forTeam($team)->create();

    $this->actingAs($this->admin)
        ->put(route('accounts.update', $coach), updatePayload($coach, [
            'password' => 'Brand-new-pass-1',
            'password_confirmation' => 'Brand-new-pass-1',
        ]))
        ->assertRedirect(route('accounts.index'));

    expect(Hash::check('Brand-new-pass-1', $coach->fresh()->password))->toBeTrue();
});

it('clears the old staffing slots when a coach changes team', function () {
    $oldTeam = Team::factory()->create();
    $newTeam = Team::factory()->create();
    $coach = User::factory()->forTeam($oldTeam)->create();
    $oldTeam->forceFill(['main_coach_user_id' => $coach->id])->save();
    $oldTeam->assistantCoaches()->attach($coach->id);

    $this->actingAs($this->admin)
        ->put(route('accounts.update', $coach), updatePayload($coach, ['team_id' => $newTeam->id]))
        ->assertRedirect(route('accounts.index'));

    expect($coach->fresh()->team_id)->toBe($newTeam->id)
        ->and($oldTeam->fresh()->main_coach_user_id)->toBeNull()
        ->and($oldTeam->assistantCoaches()->count())->toBe(0);
});

it('keeps staffing when the team does not change', function () {
    $team = Team::factory()->create();
    $coach = User::factory()->forTeam($team)->create();
    $team->forceFill(['main_coach_user_id' => $coach->id])->save();

    $this->actingAs($this->admin)
        ->put(route('accounts.update', $coach), updatePayload($coach, ['name' => 'Still Main']))
        ->assertRedirect(route('accounts.index'));

    expect($team->fresh()->main_coach_user_id)->toBe($coach->id);
});

it('promoting a coach to admin clears team and staffing', function () {
    $team = Team::factory()->create();
    $coach = User::factory()->forTeam($team)->create();
    $team->assistantCoaches()->attach($coach->id);

    $this->actingAs($this->admin)
        ->put(route('accounts.update', $coach), updatePayload($coach, ['role' => 'admin']))
        ->assertRedirect(route('accounts.index'));

    $coach->refresh();

    expect($coach->role)->toBe(UserRole::Admin)
        ->and($coach->team_id)->toBeNull()
        ->and($team->assistantCoaches()->count())->toBe(0);
});

it('blocks demoting the last admin', function () {
    $team = Team::factory()->create();

    $this->actingAs($this->admin)
        ->put(route('accounts.update', $this->admin), updatePayload($this->admin, [
            'role' => 'coach',
            'team_id' => $team->id,
        ]))
        ->assertSessionHasErrors('role');

    expect($this->admin->fresh()->role)->toBe(UserRole::Admin);
});

it('allows demoting an admin when another admin exists', function () {
    $team = Team::factory()->create();
    $other = User::factory()->admin()->create();

    $this->actingAs($this->admin)
        ->put(route('accounts.update', $other), updatePayload($other, [
            'role' => 'coach',
            'team_id' => $team->id,
        ]))
        ->assertRedirect(route('accounts.index'));

    expect($other->fresh()->role)->toBe(UserRole::Coach)
        ->and($other->fresh()->team_id)->toBe($team->id);
});

it('allows keeping your own email on update', function () {
    $this->actingAs($this->admin)
        ->put(route('accounts.update', $this->admin), updatePayload($this->admin, ['name' => 'Boss']))
        ->assertSessionHasNoErrors();
});
