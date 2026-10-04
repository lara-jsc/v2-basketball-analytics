<?php

use App\Enums\JoinRequestStatus;
use App\Models\Team;
use App\Models\TeamJoinRequest;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

function pendingRequestFor(Team $team, JoinRequestStatus $status = JoinRequestStatus::Pending): void
{
    TeamJoinRequest::query()->create([
        'team_id' => $team->id,
        'user_id' => User::factory()->create(['team_id' => null])->id,
        'status' => $status,
    ]);
}

beforeEach(function () {
    $this->hawks = Team::factory()->create(['name' => 'Hawks']);
    $this->main = User::factory()->forTeam($this->hawks)->create();
    $this->hawks->forceFill(['main_coach_user_id' => $this->main->id])->save();

    $this->owls = Team::factory()->create(['name' => 'Owls']);
    $this->owls->forceFill(['main_coach_user_id' => User::factory()->forTeam($this->owls)->create()->id])->save();

    pendingRequestFor($this->hawks);
    pendingRequestFor($this->hawks);
    pendingRequestFor($this->hawks, JoinRequestStatus::Rejected);
    pendingRequestFor($this->owls);
});

it('shares only their own team queue with a main coach', function () {
    $this->actingAs($this->main)->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('joinRequestQueue', 1)
            ->where('joinRequestQueue.0.team_id', $this->hawks->id)
            ->where('joinRequestQueue.0.team_name', 'Hawks')
            ->where('joinRequestQueue.0.count', 2));
});

it('shares every team queue with an admin', function () {
    $this->actingAs(User::factory()->admin()->create())->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('joinRequestQueue', 2));
});

it('shares an empty queue with other coaches', function () {
    $this->actingAs(User::factory()->forTeam($this->hawks)->create())->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('joinRequestQueue', []));
});

it('shares when the request was sent', function () {
    $applicant = User::factory()->create(['team_id' => null]);
    TeamJoinRequest::query()->create(['team_id' => $this->hawks->id, 'user_id' => $applicant->id, 'status' => JoinRequestStatus::Pending]);

    $this->actingAs($applicant)->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('auth.user.join_request.requested_at'));
});
