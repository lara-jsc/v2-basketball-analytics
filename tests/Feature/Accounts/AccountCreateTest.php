<?php

use App\Enums\UserRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

function accountPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'New Person',
        'email' => 'new@example.com',
        'password' => 'Secret-pass-123',
        'password_confirmation' => 'Secret-pass-123',
        'role' => 'coach',
        'team_id' => null,
        'staffing' => 'none',
    ], $overrides);
}

it('creates a verified team-less admin', function () {
    $team = Team::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('accounts.store'), accountPayload([
            'role' => 'admin',
            'team_id' => $team->id,
            'staffing' => 'main',
        ]))
        ->assertRedirect(route('accounts.index'));

    $user = User::query()->where('email', 'new@example.com')->firstOrFail();

    expect($user->role)->toBe(UserRole::Admin)
        ->and($user->team_id)->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('Secret-pass-123', $user->password))->toBeTrue()
        ->and($team->fresh()->main_coach_user_id)->toBeNull();
});

it('confirms the new account with a flash message', function () {
    $this->actingAs($this->admin)
        ->followingRedirects()
        ->post(route('accounts.store'), accountPayload(['role' => 'admin']))
        ->assertInertia(fn ($page) => $page
            ->component('Accounts/Index')
            ->where('flash.success', 'Account for New Person created.'));
});

it('creates a coach on a team without a staffing slot', function () {
    $team = Team::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('accounts.store'), accountPayload(['team_id' => $team->id]))
        ->assertRedirect(route('accounts.index'));

    $user = User::query()->where('email', 'new@example.com')->firstOrFail();

    expect($user->role)->toBe(UserRole::Coach)
        ->and($user->team_id)->toBe($team->id)
        ->and($team->fresh()->main_coach_user_id)->toBeNull()
        ->and($team->assistantCoaches()->count())->toBe(0);
});

it('creates a coach as the team main coach', function () {
    $team = Team::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('accounts.store'), accountPayload(['team_id' => $team->id, 'staffing' => 'main']))
        ->assertRedirect(route('accounts.index'));

    $user = User::query()->where('email', 'new@example.com')->firstOrFail();

    expect($team->fresh()->main_coach_user_id)->toBe($user->id);
});

it('adds a coach as an assistant while keeping existing staff', function () {
    $team = Team::factory()->create();
    $main = User::factory()->forTeam($team)->create();
    $existingAssistant = User::factory()->forTeam($team)->create();
    $team->forceFill(['main_coach_user_id' => $main->id])->save();
    $team->assistantCoaches()->attach($existingAssistant->id);

    $this->actingAs($this->admin)
        ->post(route('accounts.store'), accountPayload(['team_id' => $team->id, 'staffing' => 'assistant']))
        ->assertRedirect(route('accounts.index'));

    $user = User::query()->where('email', 'new@example.com')->firstOrFail();
    $team->refresh();

    expect($team->main_coach_user_id)->toBe($main->id)
        ->and($team->assistantCoaches()->pluck('users.id')->sort()->values()->all())
        ->toBe(collect([$existingAssistant->id, $user->id])->sort()->values()->all());
});

it('blocks creating a main coach when the slot is taken', function () {
    $team = Team::factory()->create();
    $main = User::factory()->forTeam($team)->create();
    $team->forceFill(['main_coach_user_id' => $main->id])->save();

    $this->actingAs($this->admin)
        ->post(route('accounts.store'), accountPayload(['team_id' => $team->id, 'staffing' => 'main']))
        ->assertSessionHasErrors('staffing');

    expect(User::query()->where('email', 'new@example.com')->exists())->toBeFalse();
});

it('requires a team for coaches', function () {
    $this->actingAs($this->admin)
        ->post(route('accounts.store'), accountPayload())
        ->assertSessionHasErrors('team_id');
});

it('rejects a duplicate email', function () {
    User::factory()->create(['email' => 'new@example.com']);

    $this->actingAs($this->admin)
        ->post(route('accounts.store'), accountPayload(['role' => 'admin']))
        ->assertSessionHasErrors('email');
});

it('still registers self-signups as coaches', function () {
    $this->post('/register', [
        'name' => 'Self Signup',
        'email' => 'self@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    expect(User::query()->where('email', 'self@example.com')->firstOrFail()->role)
        ->toBe(UserRole::Coach);
});
