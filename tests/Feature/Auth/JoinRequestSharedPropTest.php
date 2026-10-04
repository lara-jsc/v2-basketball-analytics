<?php

use App\Enums\JoinRequestStatus;
use App\Models\Team;
use App\Models\TeamJoinRequest;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

it('shares the pending join request with the frontend', function () {
    $team = Team::factory()->create(['name' => 'Falcons']);
    $user = User::factory()->create(['team_id' => null]);
    TeamJoinRequest::query()->create(['team_id' => $team->id, 'user_id' => $user->id, 'status' => JoinRequestStatus::Pending]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('auth.user.join_request.status', 'pending')
            ->where('auth.user.join_request.team_name', 'Falcons'));
});

it('shares null when there is no join request', function () {
    $this->actingAs(User::factory()->create())->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.user.join_request', null));
});

it('does not share a stale pending request once the user already has a team', function () {
    $team = Team::factory()->create();
    $user = User::factory()->forTeam($team)->create();
    TeamJoinRequest::query()->create(['team_id' => $team->id, 'user_id' => $user->id, 'status' => JoinRequestStatus::Pending]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.user.join_request', null));
});
