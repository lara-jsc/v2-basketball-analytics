<?php

use App\Enums\JoinRequestStatus;
use App\Enums\UserRole;
use App\Models\Team;
use App\Models\User;
use App\Services\CoachRegistrationService;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

function signup(array $overrides = []): array
{
    return array_merge([
        'name' => 'New Coach',
        'email' => 'new@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'coach_type' => 'main',
        'team_mode' => 'create',
        'team_code' => 'NEW',
        'team_name' => 'New Team',
    ], $overrides);
}

it('passes claimable and joinable teams to the register page', function () {
    $open = Team::factory()->create();
    $owned = Team::factory()->create();
    $owned->forceFill(['main_coach_user_id' => User::factory()->forTeam($owned)->create()->id])->save();
    Team::factory()->inactive()->create();

    $this->get('/register')->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Auth/Register')
        ->has('claimableTeams', 1)
        ->where('claimableTeams.0.id', $open->id)
        ->has('joinableTeams', 1)
        ->where('joinableTeams.0.id', $owned->id));
});

it('creates a team and makes the new coach its main coach', function () {
    $this->post('/register', signup())->assertRedirect(route('dashboard', absolute: false));

    $user = User::query()->where('email', 'new@example.com')->firstOrFail();
    $team = Team::query()->where('code', 'NEW')->firstOrFail();

    expect($user->role)->toBe(UserRole::Coach)
        ->and($user->team_id)->toBe($team->id)
        ->and($team->main_coach_user_id)->toBe($user->id);
    $this->assertAuthenticatedAs($user);
});

it('rejects a duplicate team code', function () {
    Team::factory()->create(['code' => 'NEW']);

    $this->post('/register', signup())->assertSessionHasErrors('team_code');
    expect(User::query()->where('email', 'new@example.com')->exists())->toBeFalse();
});

it('claims an existing team without a main coach', function () {
    $team = Team::factory()->create();

    $this->post('/register', signup(['team_mode' => 'claim', 'team_id' => $team->id]))
        ->assertRedirect(route('dashboard', absolute: false));

    $user = User::query()->where('email', 'new@example.com')->firstOrFail();
    expect($team->fresh()->main_coach_user_id)->toBe($user->id)
        ->and($user->team_id)->toBe($team->id);
});

it('refuses to claim a team that already has a main coach', function () {
    $team = Team::factory()->create();
    $team->forceFill(['main_coach_user_id' => User::factory()->forTeam($team)->create()->id])->save();

    $this->post('/register', signup(['team_mode' => 'claim', 'team_id' => $team->id]))
        ->assertSessionHasErrors(['team_id' => 'This team already has a main coach.']);
});

it('refuses to claim an inactive team', function () {
    $team = Team::factory()->inactive()->create();

    $this->post('/register', signup(['team_mode' => 'claim', 'team_id' => $team->id]))
        ->assertSessionHasErrors('team_id');
});

it('service re-checks the main coach slot inside the transaction', function () {
    $team = Team::factory()->create();
    $rival = User::factory()->forTeam($team)->create();
    // Simulates the race: the slot is taken after validation passed.
    $team->forceFill(['main_coach_user_id' => $rival->id])->save();

    expect(fn () => app(CoachRegistrationService::class)->register(
        signup(['team_mode' => 'claim', 'team_id' => $team->id]),
    ))->toThrow(ValidationException::class);

    expect(User::query()->where('email', 'new@example.com')->exists())->toBeFalse()
        ->and($team->fresh()->main_coach_user_id)->toBe($rival->id);
});

it('creates a pending join request for an assistant and leaves them team-less', function () {
    $team = Team::factory()->create();
    $team->forceFill(['main_coach_user_id' => User::factory()->forTeam($team)->create()->id])->save();

    $this->post('/register', signup(['coach_type' => 'assistant', 'team_id' => $team->id]))
        ->assertRedirect(route('dashboard', absolute: false));

    $user = User::query()->where('email', 'new@example.com')->firstOrFail();
    expect($user->team_id)->toBeNull()
        ->and($user->joinRequest->team_id)->toBe($team->id)
        ->and($user->joinRequest->status)->toBe(JoinRequestStatus::Pending)
        ->and($team->assistantCoaches()->count())->toBe(0);
});

it('refuses an assistant request to a team without a main coach', function () {
    $team = Team::factory()->create();

    $this->post('/register', signup(['coach_type' => 'assistant', 'team_id' => $team->id]))
        ->assertSessionHasErrors(['team_id' => 'This team has no main coach to approve your request yet.']);
});

it('requires a coach type', function () {
    $this->post('/register', signup(['coach_type' => null]))->assertSessionHasErrors('coach_type');
});
