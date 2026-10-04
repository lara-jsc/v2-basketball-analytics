<?php

use App\Enums\JoinRequestStatus;
use App\Models\Team;
use App\Models\TeamJoinRequest;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

function teamWithMainCoach(string $name = 'Hawks'): Team
{
    $team = Team::factory()->create(['name' => $name]);
    $team->forceFill(['main_coach_user_id' => User::factory()->forTeam($team)->create()->id])->save();

    return $team;
}

beforeEach(function () {
    $this->team = teamWithMainCoach();
    $this->applicant = User::factory()->create(['team_id' => null]);
});

it('lets a team-less coach cancel their pending request', function () {
    TeamJoinRequest::query()->create(['team_id' => $this->team->id, 'user_id' => $this->applicant->id, 'status' => JoinRequestStatus::Pending]);

    $this->actingAs($this->applicant)->delete(route('join-request.destroy'))
        ->assertRedirect(route('dashboard'));

    expect($this->applicant->joinRequest()->exists())->toBeFalse();
});

it('does not cancel a request that was already decided', function () {
    TeamJoinRequest::query()->create(['team_id' => $this->team->id, 'user_id' => $this->applicant->id, 'status' => JoinRequestStatus::Approved]);

    $this->actingAs($this->applicant)->delete(route('join-request.destroy'))
        ->assertSessionHasErrors('join_request');

    expect($this->applicant->joinRequest()->exists())->toBeTrue();
});

it('lets a coach without a request ask to join a team', function () {
    $this->actingAs($this->applicant)->post(route('join-request.store'), ['team_id' => $this->team->id])
        ->assertRedirect(route('dashboard'));

    $request = $this->applicant->joinRequest()->firstOrFail();
    expect($request->team_id)->toBe($this->team->id)
        ->and($request->status)->toBe(JoinRequestStatus::Pending);
});

it('lets a declined coach request another team, reusing their request row', function () {
    $other = teamWithMainCoach('Eagles');
    TeamJoinRequest::query()->create([
        'team_id' => $this->team->id,
        'user_id' => $this->applicant->id,
        'status' => JoinRequestStatus::Rejected,
        'decided_by_user_id' => $this->team->main_coach_user_id,
        'decided_at' => now(),
    ]);

    $this->actingAs($this->applicant)->post(route('join-request.store'), ['team_id' => $other->id])
        ->assertRedirect(route('dashboard'));

    $request = $this->applicant->joinRequest()->firstOrFail();
    expect($request->team_id)->toBe($other->id)
        ->and($request->status)->toBe(JoinRequestStatus::Pending)
        ->and($request->decided_by_user_id)->toBeNull()
        ->and($request->decided_at)->toBeNull()
        ->and(TeamJoinRequest::query()->count())->toBe(1);
});

it('refuses a new request while one is still pending', function () {
    TeamJoinRequest::query()->create(['team_id' => $this->team->id, 'user_id' => $this->applicant->id, 'status' => JoinRequestStatus::Pending]);

    $this->actingAs($this->applicant)->post(route('join-request.store'), ['team_id' => teamWithMainCoach('Owls')->id])
        ->assertSessionHasErrors(['team_id' => 'Cancel your current request first.']);
});

it('refuses a request from a coach who already belongs to a team', function () {
    $coach = User::factory()->forTeam(Team::factory()->create())->create();

    $this->actingAs($coach)->post(route('join-request.store'), ['team_id' => $this->team->id])
        ->assertSessionHasErrors(['team_id' => 'You already belong to a team.']);
});

it('refuses a request to a team without a main coach', function () {
    $this->actingAs($this->applicant)->post(route('join-request.store'), ['team_id' => Team::factory()->create()->id])
        ->assertSessionHasErrors(['team_id' => 'This team has no main coach to approve your request yet.']);
});

it('refuses a request from an admin', function () {
    $this->actingAs(User::factory()->admin()->create())->post(route('join-request.store'), ['team_id' => $this->team->id])
        ->assertSessionHasErrors('team_id');
});

it('gives a team-less coach the joinable teams on the dashboard', function () {
    $this->actingAs($this->applicant)->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('joinableTeams', 1)
            ->where('joinableTeams.0.id', $this->team->id));
});

it('does not send joinable teams to a coach who already has a team', function () {
    $coach = User::factory()->forTeam($this->team)->create();

    $this->actingAs($coach)->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('joinableTeams', []));
});
