<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use App\Repositories\AccountRepository;
use App\Repositories\TeamJoinRequestRepository;
use App\Repositories\TeamRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CoachRegistrationService
{
    public function __construct(
        private readonly AccountRepository $accountRepository,
        private readonly TeamRepository $teamRepository,
        private readonly TeamJoinRequestRepository $joinRequestRepository,
    ) {}

    /**
     * Create a self-signed-up coach. Main coaches get (or claim) a team and
     * its main-coach slot; assistants get a pending join request only.
     *
     * @param  array{name: string, email: string, password: string, coach_type: string, team_mode?: string|null, team_code?: string, team_name?: string, team_id?: int|string|null}  $data
     */
    public function register(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = $this->accountRepository->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => UserRole::Coach,
            ]);

            if ($data['coach_type'] === 'assistant') {
                $this->joinRequestRepository->createPending((int) $data['team_id'], $user->id);

                return $user;
            }

            $team = ($data['team_mode'] ?? null) === 'create'
                ? $this->teamRepository->create(['code' => $data['team_code'], 'name' => $data['team_name']])
                : $this->teamRepository->lockForUpdate((int) $data['team_id']);

            if ($team->main_coach_user_id !== null) {
                throw ValidationException::withMessages(['team_id' => 'This team already has a main coach.']);
            }

            $team->forceFill(['main_coach_user_id' => $user->id])->save();
            $user->forceFill(['team_id' => $team->id])->save();

            return $user;
        });
    }
}
