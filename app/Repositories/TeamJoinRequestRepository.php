<?php

namespace App\Repositories;

use App\Enums\JoinRequestStatus;
use App\Models\TeamJoinRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class TeamJoinRequestRepository
{
    public function createPending(int $teamId, int $userId): TeamJoinRequest
    {
        return TeamJoinRequest::query()->create([
            'team_id' => $teamId,
            'user_id' => $userId,
            'status' => JoinRequestStatus::Pending,
        ]);
    }

    /**
     * Point the user's single request row at a team as a fresh pending
     * request (a declined coach re-requests through the same row).
     */
    public function submitForUser(int $userId, int $teamId): TeamJoinRequest
    {
        return TeamJoinRequest::query()->updateOrCreate(
            ['user_id' => $userId],
            [
                'team_id' => $teamId,
                'status' => JoinRequestStatus::Pending,
                'decided_by_user_id' => null,
                'decided_at' => null,
            ],
        );
    }

    public function delete(TeamJoinRequest $joinRequest): void
    {
        $joinRequest->delete();
    }

    /**
     * Pending request counts per team the user can decide on: every team
     * for an admin, otherwise the teams they are main coach of.
     *
     * @return list<array{team_id: int, team_name: string, count: int}>
     */
    public function queueFor(User $user): array
    {
        return TeamJoinRequest::query()
            ->join('teams', 'teams.id', '=', 'team_join_requests.team_id')
            ->where('team_join_requests.status', JoinRequestStatus::Pending)
            ->when(! $user->isAdmin(), fn ($query) => $query->where('teams.main_coach_user_id', $user->id))
            ->groupBy('team_join_requests.team_id', 'teams.name')
            ->orderBy('teams.name')
            ->selectRaw('team_join_requests.team_id as team_id, teams.name as team_name, count(*) as aggregate')
            ->get()
            ->map(fn ($row): array => [
                'team_id' => (int) $row->team_id,
                'team_name' => (string) $row->team_name,
                'count' => (int) $row->aggregate,
            ])
            ->all();
    }

    /**
     * Pending requests for a team with the applicant, oldest first.
     *
     * @return Collection<int, TeamJoinRequest>
     */
    public function pendingForTeam(int $teamId): Collection
    {
        return TeamJoinRequest::query()
            ->with('user:id,name,email')
            ->where('team_id', $teamId)
            ->where('status', JoinRequestStatus::Pending)
            ->oldest()
            ->get();
    }

    public function lockForUpdate(int $id): TeamJoinRequest
    {
        return TeamJoinRequest::query()->lockForUpdate()->findOrFail($id);
    }

    public function markDecided(TeamJoinRequest $joinRequest, JoinRequestStatus $status, int $deciderId): void
    {
        $joinRequest->forceFill([
            'status' => $status,
            'decided_by_user_id' => $deciderId,
            'decided_at' => now(),
        ])->save();
    }
}
