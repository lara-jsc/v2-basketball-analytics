<?php

namespace App\Services;

use App\Enums\JoinRequestStatus;
use App\Models\TeamJoinRequest;
use App\Models\User;
use App\Repositories\TeamJoinRequestRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeamJoinRequestService
{
    public function __construct(
        private readonly TeamJoinRequestRepository $joinRequestRepository,
        private readonly TeamStaffingService $teamStaffingService,
    ) {}

    /**
     * Put the applicant on the team as an assistant coach. A coach belongs
     * to one team only, so an applicant already on another team is refused.
     */
    public function approve(TeamJoinRequest $joinRequest, User $decider): void
    {
        DB::transaction(function () use ($joinRequest, $decider): void {
            $joinRequest = $this->lockPending($joinRequest);

            $applicant = $joinRequest->user()->lockForUpdate()->firstOrFail();

            if ($applicant->email_verified_at === null) {
                throw ValidationException::withMessages(['join_request' => "This coach hasn't verified their email yet."]);
            }

            if ($applicant->isAdmin()) {
                throw ValidationException::withMessages(['join_request' => 'This account is an admin and cannot join a team.']);
            }

            if ($applicant->team_id !== null && $applicant->team_id !== $joinRequest->team_id) {
                throw ValidationException::withMessages(['join_request' => 'This coach already belongs to a team.']);
            }

            $team = $joinRequest->team()->firstOrFail();
            $applicant->forceFill(['team_id' => $team->id])->save();

            $assistantIds = $team->assistantCoaches()->pluck('users.id')->map(fn ($id) => (int) $id)->all();
            $this->teamStaffingService->update(
                $team,
                $team->main_coach_user_id,
                array_values(array_unique([...$assistantIds, $applicant->id])),
            );

            $this->joinRequestRepository->markDecided($joinRequest, JoinRequestStatus::Approved, $decider->id);
        });
    }

    public function reject(TeamJoinRequest $joinRequest, User $decider): void
    {
        DB::transaction(function () use ($joinRequest, $decider): void {
            $joinRequest = $this->lockPending($joinRequest);

            $this->joinRequestRepository->markDecided($joinRequest, JoinRequestStatus::Rejected, $decider->id);
        });
    }

    /**
     * A team-less coach asks to join a team (first request, or again after
     * a decline or cancel). Eligibility is checked by StoreJoinRequestRequest.
     */
    public function submit(User $user, int $teamId): TeamJoinRequest
    {
        return $this->joinRequestRepository->submitForUser($user->id, $teamId);
    }

    /**
     * Withdraw the user's own request while it is still pending.
     */
    public function cancel(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $joinRequest = $user->joinRequest()->first();

            if ($joinRequest === null) {
                return;
            }

            $this->joinRequestRepository->delete($this->lockPending($joinRequest));
        });
    }

    /**
     * Re-read the request under a row lock so a concurrent approve/reject
     * cannot both pass the pending check.
     */
    private function lockPending(TeamJoinRequest $joinRequest): TeamJoinRequest
    {
        $fresh = $this->joinRequestRepository->lockForUpdate($joinRequest->id);

        if ($fresh->status !== JoinRequestStatus::Pending) {
            throw ValidationException::withMessages(['join_request' => 'This request was already decided.']);
        }

        return $fresh;
    }
}
