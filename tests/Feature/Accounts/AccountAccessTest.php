<?php

use App\Enums\UserRole;
use App\Models\Team;
use App\Models\User;

it('defaults new users to the coach role', function () {
    $user = User::factory()->create();

    expect($user->fresh()->role)->toBe(UserRole::Coach)
        ->and($user->isAdmin())->toBeFalse();
});

it('forbids coaches from every account route', function () {
    $coach = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($coach)->get(route('accounts.index'))->assertForbidden();
    $this->actingAs($coach)->get(route('accounts.create'))->assertForbidden();
    $this->actingAs($coach)->post(route('accounts.store'), [])->assertForbidden();
    $this->actingAs($coach)->get(route('accounts.edit', $target))->assertForbidden();
    $this->actingAs($coach)->put(route('accounts.update', $target), [])->assertForbidden();
});

it('lets admins open the account screens', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create();

    $this->actingAs($admin)->get(route('accounts.index'))->assertOk();
    $this->actingAs($admin)->get(route('accounts.create'))->assertOk();
    $this->actingAs($admin)->get(route('accounts.edit', $target))->assertOk();
});

it('forbids coaches from updating team staffing', function () {
    $team = Team::factory()->create();
    $coach = User::factory()->forTeam($team)->create();

    $this->actingAs($coach)
        ->put(route('teams.staffing.update', $team), [
            'main_coach_user_id' => $coach->id,
            'assistant_coach_user_ids' => [],
        ])
        ->assertForbidden();
});

it('shares the role and admin flag with the frontend', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('auth.user.role', 'admin')
            ->where('auth.user.is_admin', true));
});
