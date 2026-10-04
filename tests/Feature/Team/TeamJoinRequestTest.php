<?php

use App\Enums\JoinRequestStatus;
use App\Models\Team;
use App\Models\TeamJoinRequest;
use App\Models\User;
use App\Services\TeamJoinRequestService;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->team = Team::factory()->create();
    $this->main = User::factory()->forTeam($this->team)->create();
    $this->team->forceFill(['main_coach_user_id' => $this->main->id])->save();
    $this->applicant = User::factory()->create(['team_id' => null]);
    $this->joinRequest = TeamJoinRequest::query()->create([
        'team_id' => $this->team->id,
        'user_id' => $this->applicant->id,
        'status' => JoinRequestStatus::Pending,
    ]);
});

function approveUrl(Team $team, TeamJoinRequest $joinRequest): string
{
    return route('teams.join-requests.approve', [$team, $joinRequest]);
}

it('lets the main coach approve, attaching the applicant as assistant', function () {
    $this->actingAs($this->main)->post(approveUrl($this->team, $this->joinRequest))
        ->assertRedirect(route('teams.show', $this->team));

    expect($this->applicant->fresh()->team_id)->toBe($this->team->id)
        ->and($this->team->assistantCoaches()->pluck('users.id')->all())->toBe([$this->applicant->id])
        ->and($this->joinRequest->fresh()->status)->toBe(JoinRequestStatus::Approved)
        ->and($this->joinRequest->fresh()->decided_by_user_id)->toBe($this->main->id);
});

it('lets an admin approve', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(approveUrl($this->team, $this->joinRequest))->assertRedirect();

    expect($this->applicant->fresh()->team_id)->toBe($this->team->id);
});

it('forbids other coaches and assistants', function () {
    $assistant = User::factory()->forTeam($this->team)->create();
    $this->team->assistantCoaches()->attach($assistant->id);

    $this->actingAs($assistant)->post(approveUrl($this->team, $this->joinRequest))->assertForbidden();
    $this->actingAs(User::factory()->create())->post(approveUrl($this->team, $this->joinRequest))->assertForbidden();
});

it('rejects without attaching', function () {
    $this->actingAs($this->main)
        ->post(route('teams.join-requests.reject', [$this->team, $this->joinRequest]))->assertRedirect();

    expect($this->joinRequest->fresh()->status)->toBe(JoinRequestStatus::Rejected)
        ->and($this->applicant->fresh()->team_id)->toBeNull()
        ->and($this->team->assistantCoaches()->count())->toBe(0);
});

it('refuses approval when the applicant already belongs to a team', function () {
    $this->applicant->forceFill(['team_id' => Team::factory()->create()->id])->save();

    $this->actingAs($this->main)->post(approveUrl($this->team, $this->joinRequest))
        ->assertSessionHasErrors(['join_request' => 'This coach already belongs to a team.']);

    expect($this->team->assistantCoaches()->count())->toBe(0)
        ->and($this->joinRequest->fresh()->status)->toBe(JoinRequestStatus::Pending);
});

it('refuses to decide an already-decided request', function () {
    $this->joinRequest->forceFill(['status' => JoinRequestStatus::Rejected])->save();

    $this->actingAs($this->main)->post(approveUrl($this->team, $this->joinRequest))
        ->assertSessionHasErrors('join_request');
    expect($this->applicant->fresh()->team_id)->toBeNull();
});

it('404s when the request belongs to another team', function () {
    $other = Team::factory()->create();
    $otherMain = User::factory()->forTeam($other)->create();
    $other->forceFill(['main_coach_user_id' => $otherMain->id])->save();

    $this->actingAs($otherMain)->post(approveUrl($other, $this->joinRequest))->assertNotFound();
});

it('does not list a pending applicant as an assistant coach', function () {
    expect($this->team->assistantCoaches()->count())->toBe(0);
});

it('refuses to approve an applicant who has not verified their email', function () {
    $this->applicant->forceFill(['email_verified_at' => null])->save();

    $this->actingAs($this->main)->post(approveUrl($this->team, $this->joinRequest))
        ->assertSessionHasErrors(['join_request' => "This coach hasn't verified their email yet."]);

    expect($this->applicant->fresh()->team_id)->toBeNull()
        ->and($this->team->assistantCoaches()->count())->toBe(0);
});

it('refuses to approve an applicant who has become an admin', function () {
    $this->applicant->forceFill(['role' => 'admin'])->save();

    $this->actingAs($this->main)->post(approveUrl($this->team, $this->joinRequest))
        ->assertSessionHasErrors('join_request');

    expect($this->applicant->fresh()->team_id)->toBeNull();
});

it('approves when an admin already placed the applicant on this same team', function () {
    $this->applicant->forceFill(['team_id' => $this->team->id])->save();

    $this->actingAs($this->main)->post(approveUrl($this->team, $this->joinRequest))
        ->assertSessionHasNoErrors();

    expect($this->team->assistantCoaches()->pluck('users.id')->all())->toBe([$this->applicant->id])
        ->and($this->joinRequest->fresh()->status)->toBe(JoinRequestStatus::Approved);
});

it('re-reads the request status so a stale model cannot approve a rejected request', function () {
    $stale = TeamJoinRequest::query()->findOrFail($this->joinRequest->id);
    TeamJoinRequest::query()->whereKey($this->joinRequest->id)->update(['status' => JoinRequestStatus::Rejected]);

    expect(fn () => app(TeamJoinRequestService::class)->approve($stale, $this->main))
        ->toThrow(ValidationException::class);

    expect($this->applicant->fresh()->team_id)->toBeNull()
        ->and($this->joinRequest->fresh()->status)->toBe(JoinRequestStatus::Rejected);
});

it('re-reads the request status so a stale model cannot reject an approved request', function () {
    $stale = TeamJoinRequest::query()->findOrFail($this->joinRequest->id);
    TeamJoinRequest::query()->whereKey($this->joinRequest->id)->update(['status' => JoinRequestStatus::Approved]);

    expect(fn () => app(TeamJoinRequestService::class)->reject($stale, $this->main))
        ->toThrow(ValidationException::class);

    expect($this->joinRequest->fresh()->status)->toBe(JoinRequestStatus::Approved);
});
