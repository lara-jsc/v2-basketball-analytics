<?php

use App\Enums\UserRole;
use App\Models\Team;
use App\Models\User;

it('promotes a user to admin and clears their staffing', function () {
    $team = Team::factory()->create();
    $coach = User::factory()->forTeam($team)->create(['email' => 'coach@example.com']);
    $team->forceFill(['main_coach_user_id' => $coach->id])->save();

    $this->artisan('user:make-admin', ['email' => 'coach@example.com'])->assertSuccessful();

    $coach->refresh();

    expect($coach->role)->toBe(UserRole::Admin)
        ->and($coach->team_id)->toBeNull()
        ->and($team->fresh()->main_coach_user_id)->toBeNull();
});

it('fails for an unknown email', function () {
    $this->artisan('user:make-admin', ['email' => 'nobody@example.com'])->assertFailed();
});
