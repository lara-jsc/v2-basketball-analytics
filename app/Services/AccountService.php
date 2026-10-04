<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Team;
use App\Models\User;
use App\Repositories\AccountRepository;
use Illuminate\Support\Facades\DB;

class AccountService
{
    public function __construct(
        private readonly AccountRepository $accountRepository,
        private readonly TeamStaffingService $teamStaffingService,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listForIndex(): array
    {
        return $this->accountRepository->allWithStaffing()
            ->map(fn (User $user): array => $this->summary($user))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function summaryFor(User $user): array
    {
        return $this->summary($this->accountRepository->loadStaffing($user));
    }

    /**
     * @return list<array{id: int, name: string, main_coach: array{id: int, name: string}|null}>
     */
    public function teamOptions(): array
    {
        return $this->accountRepository->teamsForSelect()
            ->map(fn (Team $team): array => [
                'id' => $team->id,
                'name' => $team->name,
                'main_coach' => $team->mainCoach === null ? null : [
                    'id' => $team->mainCoach->id,
                    'name' => $team->mainCoach->name,
                ],
            ])
            ->values()
            ->all();
    }

    /**
     * Create a verified account. Coaches may be placed straight into a
     * staffing slot; that goes through TeamStaffingService so staffing
     * keeps a single write path.
     *
     * @param  array{name: string, email: string, password: string, role: string, team_id: int|null, staffing: string}  $data
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = $this->accountRepository->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => UserRole::from($data['role']),
                'team_id' => $data['team_id'],
                'email_verified_at' => now(),
            ]);

            if ($user->team_id !== null && in_array($data['staffing'], ['main', 'assistant'], true)) {
                $this->assignSlot($user, $data['staffing']);
            }

            return $user;
        });
    }

    /**
     * Update an account. Promoting a coach to admin frees every staffing
     * slot they held. Moving a staffed coach to another team is refused by
     * UpdateAccountRequest (one team per coach), so it never reaches here.
     *
     * @param  array{name: string, email: string, password?: string|null, role: string, team_id: int|null}  $data
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $role = UserRole::from($data['role']);
            $teamId = $role === UserRole::Admin ? null : $data['team_id'];

            if ($role === UserRole::Admin) {
                $this->accountRepository->clearStaffing($user);
            }

            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'role' => $role,
                'team_id' => $teamId,
            ]);

            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }

            $user->save();

            return $user;
        });
    }

    public function promoteToAdmin(User $user): User
    {
        return $this->update($user, [
            'name' => $user->name,
            'email' => $user->email,
            'role' => UserRole::Admin->value,
            'team_id' => null,
        ]);
    }

    private function assignSlot(User $user, string $slot): void
    {
        $team = $user->team()->firstOrFail();
        $assistantIds = $team->assistantCoaches()->pluck('users.id')->map(fn ($id) => (int) $id)->all();

        if ($slot === 'main') {
            $this->teamStaffingService->update($team, $user->id, $assistantIds);

            return;
        }

        $this->teamStaffingService->update(
            $team,
            $team->main_coach_user_id,
            [...$assistantIds, $user->id],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(User $user): array
    {
        $staffing = null;

        if ($user->mainCoachedTeams->isNotEmpty()) {
            $staffing = 'main';
        } elseif ($user->assistantCoachedTeams->isNotEmpty()) {
            $staffing = 'assistant';
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'team_id' => $user->team_id,
            'team' => $user->team === null ? null : [
                'id' => $user->team->id,
                'name' => $user->team->name,
            ],
            'staffing' => $staffing,
            'email_verified' => $user->email_verified_at !== null,
        ];
    }
}
