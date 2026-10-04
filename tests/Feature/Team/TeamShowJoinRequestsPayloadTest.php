<?php

use App\Enums\JoinRequestStatus;
use App\Models\Team;
use App\Models\TeamJoinRequest;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

it('shows pending join requests to the main coach only', function () {
    $team = Team::factory()->create();
    $main = User::factory()->forTeam($team)->create();
    $team->forceFill(['main_coach_user_id' => $main->id])->save();
    TeamJoinRequest::query()->create([
        'team_id' => $team->id,
        'user_id' => User::factory()->create(['team_id' => null])->id,
        'status' => JoinRequestStatus::Pending,
    ]);

    $this->actingAs($main)->get(route('teams.show', $team))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('canManageJoinRequests', true)
            ->has('pendingJoinRequests', 1));

    $this->actingAs(User::factory()->create())->get(route('teams.show', $team))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('canManageJoinRequests', false)
            ->has('pendingJoinRequests', 0));
});
