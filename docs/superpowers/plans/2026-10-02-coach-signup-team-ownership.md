# Coach Self-Signup, Team Ownership & Assistant Join Requests — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** At signup a coach picks **Main coach** (create a new team, or claim an existing team without a main coach) or **Assistant coach** (request to join a team; its main coach or an admin approves). Every coach belongs to at most one team.

**Architecture:** Reuse the existing staffing model: `users.team_id`, `teams.main_coach_user_id`, and the `team_assistant_coaches` pivot. Add one table, `team_join_requests`, so pending assistants never appear in `assistantCoaches()` (live-game code reads that relation). Signup logic moves out of the controller into `RegisterCoachRequest` + `CoachRegistrationService`. Approval goes through `TeamPolicy` + `TeamJoinRequestService`.

**Tech Stack:** Laravel 13, Pest, Inertia 2, React 18 + TypeScript, Tailwind, SQLite (tests) / MySQL (prod).

**Spec:** this file's "Context & decisions" section (brainstormed 2026-10-02).

## Context & decisions (spec)
- Today self-signup collects only name/email/password. The user gets `role=coach` (column default), `team_id=null` and is unverified.
- Admins already create coaches and assign main/assistant slots (`AccountService`, `TeamStaffingService`, `PUT /teams/{team}/staffing`). That stays.
- Decisions the user made:
  1. Signup has a coach-type dropdown: **Main coach** / **Assistant coach**.
  2. Main coach → **create a new team** *or* **claim an existing active team with no main coach**. They become `main_coach_user_id` and `users.team_id` is set.
  3. Assistant coach → pick a team that **has** a main coach. A **pending join request** is created. The team's main coach or any admin approves or rejects it on the team page. On approval: `users.team_id` is set and the user is attached to `team_assistant_coaches`.
  4. **Strict one-team-per-coach.** A coach holding a staffing slot cannot be moved to another team; an admin must unassign them on the team page first. A team never has two main coaches.
- Out of scope (YAGNI): limiting which teams coaches can view or edit; Teams → Create stays owner-less (coaches still create opponent teams for comparison); re-requesting after a rejection (user contacts an admin); notifications or emails for join requests.
- CLAUDE.md lists "per-user team ownership" as out of scope. This feature intentionally changes that (Task 8).

## Global Constraints
- Layering: Controller → FormRequest → Service → Repository → Model (Models have zero logic). No Eloquent queries in new controllers.
- New frontend code is `.tsx`. `resources/js/Components/ui/*` must never be modified. New components go in `Components/features/teams/`.
- Error copy, verbatim:
  - claim taken: `This team already has a main coach.`
  - assistant to team with no main coach: `This team has no main coach to approve your request yet.`
  - strict move: `Remove this coach from {team}'s staff before moving them to another team.`
  - approve when user already on a team: `This coach already belongs to a team.`
- Tablet (768–1024px) is the primary breakpoint.
- **Do not commit** unless the user explicitly asks (user rule). Never add Co-Authored-By trailers. The "Checkpoint" steps below mean: run the suite and stop for review.

## Review Focus
1. **Claim race:** two signups claim the same team at once → exactly one becomes main coach; the other gets `This team already has a main coach.` (test in Task 2: the service re-checks inside the transaction).
2. **Inactive team:** a claim posting an inactive team's id → rejected (test in Task 2).
3. **Stale approval:** an admin gives a pending user a team, then the main coach approves → error, no double membership (test in Task 4).
4. **Approve a decided request twice / a request from another team via a URL swap** → 404 or validation error, never a write (tests in Task 4).
5. **Pending user leaking into live game:** a pending requester must not be in `assistantCoaches` (test in Task 4).

---

## File structure
| File | Responsibility |
|---|---|
| `database/migrations/2026_10_02_000001_create_team_join_requests_table.php` | new table |
| `app/Enums/JoinRequestStatus.php` | pending/approved/rejected |
| `app/Models/TeamJoinRequest.php` | Eloquent, no logic |
| `app/Repositories/TeamJoinRequestRepository.php` | join-request queries/writes |
| `app/Http/Requests/RegisterCoachRequest.php` | signup validation |
| `app/Services/CoachRegistrationService.php` | signup writes (user + team/claim/request) |
| `app/Policies/TeamPolicy.php` | `manageJoinRequests` |
| `app/Services/TeamJoinRequestService.php` | approve/reject |
| `app/Http/Controllers/TeamJoinRequestController.php` | approve/reject endpoints |
| `resources/js/Pages/Auth/Register.tsx` (replaces `.jsx`) | signup UI |
| `resources/js/Components/features/teams/TeamJoinRequestsCard.tsx` | pending list on team page |
| `resources/js/Components/features/dashboard/JoinRequestBanner.tsx` | pending/rejected banner |

Modified: `app/Models/{User,Team}.php`, `app/Repositories/TeamRepository.php`, `app/Http/Controllers/Auth/RegisteredUserController.php`, `app/Http/Controllers/TeamController.php`, `routes/web.php`, `app/Http/Middleware/HandleInertiaRequests.php`, `app/Http/Requests/UpdateAccountRequest.php`, `app/Services/AccountService.php`, `resources/js/types/index.ts`, `resources/js/Pages/{Dashboard,Teams/Show}.tsx`, `resources/js/Components/features/accounts/AccountForm.tsx`, tests listed per task, `CLAUDE.md`, `docs/USER_MANUAL.md`.

---

### Task 1: `team_join_requests` schema, enum, model, relations

**Files:**
- Create: `database/migrations/2026_10_02_000001_create_team_join_requests_table.php`, `app/Enums/JoinRequestStatus.php`, `app/Models/TeamJoinRequest.php`
- Modify: `app/Models/User.php`, `app/Models/Team.php`
- Test: `tests/Feature/Team/TeamJoinRequestSchemaTest.php`

**Interfaces:**
- Produces: `JoinRequestStatus::{Pending,Approved,Rejected}` (string-backed `'pending'|'approved'|'rejected'`); `TeamJoinRequest` with `team()`, `user()`, `decidedBy()`, casts `status => JoinRequestStatus`, `decided_at => datetime`; `User::joinRequest(): HasOne`; `Team::joinRequests(): HasMany`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\JoinRequestStatus;
use App\Models\Team;
use App\Models\TeamJoinRequest;
use App\Models\User;
use Illuminate\Database\QueryException;

it('stores a pending join request linked to team and user', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();

    $request = TeamJoinRequest::query()->create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'status' => JoinRequestStatus::Pending,
    ]);

    expect($request->fresh()->status)->toBe(JoinRequestStatus::Pending)
        ->and($user->joinRequest->id)->toBe($request->id)
        ->and($team->joinRequests()->count())->toBe(1);
});

it('allows only one join request per user', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $attrs = ['team_id' => $team->id, 'user_id' => $user->id, 'status' => JoinRequestStatus::Pending];

    TeamJoinRequest::query()->create($attrs);
    TeamJoinRequest::query()->create($attrs);
})->throws(QueryException::class);
```

- [ ] **Step 2: Run to verify it fails**
Run: `php artisan test tests/Feature/Team/TeamJoinRequestSchemaTest.php`
Expected: FAIL, `Class "App\Enums\JoinRequestStatus" not found`.

- [ ] **Step 3: Implement**

Migration:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_join_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_join_requests');
    }
};
```

`app/Enums/JoinRequestStatus.php`:
```php
<?php

namespace App\Enums;

enum JoinRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
```

`app/Models/TeamJoinRequest.php`:
```php
<?php

namespace App\Models;

use App\Enums\JoinRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamJoinRequest extends Model
{
    protected $fillable = ['team_id', 'user_id', 'status', 'decided_by_user_id', 'decided_at'];

    protected $casts = [
        'status' => JoinRequestStatus::class,
        'decided_at' => 'datetime',
    ];

    /** @return BelongsTo<Team, TeamJoinRequest> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<User, TeamJoinRequest> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, TeamJoinRequest> */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }
}
```

`User.php`: add `use Illuminate\Database\Eloquent\Relations\HasOne;` and
```php
    /** @return HasOne<TeamJoinRequest, User> */
    public function joinRequest(): HasOne
    {
        return $this->hasOne(TeamJoinRequest::class);
    }
```
`Team.php`:
```php
    /** @return HasMany<TeamJoinRequest, Team> */
    public function joinRequests(): HasMany
    {
        return $this->hasMany(TeamJoinRequest::class);
    }
```

- [ ] **Step 4: Run to verify it passes**
Run: `php artisan test tests/Feature/Team/TeamJoinRequestSchemaTest.php` → PASS.

- [ ] **Step 5: Checkpoint.** Run `vendor/bin/pint` and stop for review (no commit unless asked).

---

### Task 2: Signup backend (validation + registration service)

**Files:**
- Create: `app/Http/Requests/RegisterCoachRequest.php`, `app/Services/CoachRegistrationService.php`, `app/Repositories/TeamJoinRequestRepository.php`
- Modify: `app/Http/Controllers/Auth/RegisteredUserController.php`, `app/Repositories/TeamRepository.php`
- Test: `tests/Feature/Auth/CoachRegistrationTest.php` (new). Update `tests/Feature/Auth/RegistrationTest.php::test_new_users_can_register` and `tests/Feature/Accounts/AccountCreateTest.php` ("still registers self-signups as coaches") to post a valid main+create payload.

**Interfaces:**
- Consumes: Task 1 model/enum.
- Produces:
  - POST `/register` fields: `name, email, password, password_confirmation, coach_type ('main'|'assistant'), team_mode ('create'|'claim', required if main), team_code, team_name (required if main+create), team_id (required if main+claim or assistant)`.
  - `CoachRegistrationService::register(array $data): User`
  - `TeamJoinRequestRepository::createPending(int $teamId, int $userId): TeamJoinRequest`
  - `TeamRepository::claimableForSignup(): Collection` (active, `main_coach_user_id` null; columns `id, code, name`)
  - `TeamRepository::joinableForSignup(): Collection` (`main_coach_user_id` not null; `id, code, name`)
  - Inertia props on `Auth/Register`: `claimableTeams`, `joinableTeams` (`{id, code, name}[]`).

- [ ] **Step 1: Write the failing tests** (`tests/Feature/Auth/CoachRegistrationTest.php`)

```php
<?php

use App\Enums\JoinRequestStatus;
use App\Enums\UserRole;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

function signup(array $overrides = []): array
{
    return array_merge([
        'name' => 'New Coach',
        'email' => 'new@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'coach_type' => 'main',
        'team_mode' => 'create',
        'team_code' => 'NEW',
        'team_name' => 'New Team',
    ], $overrides);
}

it('passes claimable and joinable teams to the register page', function () {
    $open = Team::factory()->create();
    $owned = Team::factory()->create();
    $owned->forceFill(['main_coach_user_id' => User::factory()->forTeam($owned)->create()->id])->save();
    Team::factory()->inactive()->create();

    $this->get('/register')->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Auth/Register')
        ->has('claimableTeams', 1)
        ->where('claimableTeams.0.id', $open->id)
        ->has('joinableTeams', 1)
        ->where('joinableTeams.0.id', $owned->id));
});

it('creates a team and makes the new coach its main coach', function () {
    $this->post('/register', signup())->assertRedirect(route('dashboard', absolute: false));

    $user = User::query()->where('email', 'new@example.com')->firstOrFail();
    $team = Team::query()->where('code', 'NEW')->firstOrFail();

    expect($user->role)->toBe(UserRole::Coach)
        ->and($user->team_id)->toBe($team->id)
        ->and($team->main_coach_user_id)->toBe($user->id);
    $this->assertAuthenticatedAs($user);
});

it('rejects a duplicate team code', function () {
    Team::factory()->create(['code' => 'NEW']);

    $this->post('/register', signup())->assertSessionHasErrors('team_code');
    expect(User::query()->where('email', 'new@example.com')->exists())->toBeFalse();
});

it('claims an existing team without a main coach', function () {
    $team = Team::factory()->create();

    $this->post('/register', signup(['team_mode' => 'claim', 'team_id' => $team->id]))
        ->assertRedirect(route('dashboard', absolute: false));

    $user = User::query()->where('email', 'new@example.com')->firstOrFail();
    expect($team->fresh()->main_coach_user_id)->toBe($user->id)
        ->and($user->team_id)->toBe($team->id);
});

it('refuses to claim a team that already has a main coach', function () {
    $team = Team::factory()->create();
    $team->forceFill(['main_coach_user_id' => User::factory()->forTeam($team)->create()->id])->save();

    $this->post('/register', signup(['team_mode' => 'claim', 'team_id' => $team->id]))
        ->assertSessionHasErrors(['team_id' => 'This team already has a main coach.']);
});

it('refuses to claim an inactive team', function () {
    $team = Team::factory()->inactive()->create();

    $this->post('/register', signup(['team_mode' => 'claim', 'team_id' => $team->id]))
        ->assertSessionHasErrors('team_id');
});

it('service re-checks the main coach slot inside the transaction', function () {
    $team = Team::factory()->create();
    $rival = User::factory()->forTeam($team)->create();
    // Simulates the race: slot gets taken after validation passed.
    $team->forceFill(['main_coach_user_id' => $rival->id])->save();

    expect(fn () => app(\App\Services\CoachRegistrationService::class)->register(
        signup(['team_mode' => 'claim', 'team_id' => $team->id]),
    ))->toThrow(\Illuminate\Validation\ValidationException::class);

    expect(User::query()->where('email', 'new@example.com')->exists())->toBeFalse()
        ->and($team->fresh()->main_coach_user_id)->toBe($rival->id);
});

it('creates a pending join request for an assistant and leaves them team-less', function () {
    $team = Team::factory()->create();
    $team->forceFill(['main_coach_user_id' => User::factory()->forTeam($team)->create()->id])->save();

    $this->post('/register', signup(['coach_type' => 'assistant', 'team_id' => $team->id]))
        ->assertRedirect(route('dashboard', absolute: false));

    $user = User::query()->where('email', 'new@example.com')->firstOrFail();
    expect($user->team_id)->toBeNull()
        ->and($user->joinRequest->team_id)->toBe($team->id)
        ->and($user->joinRequest->status)->toBe(JoinRequestStatus::Pending)
        ->and($team->assistantCoaches()->count())->toBe(0);
});

it('refuses an assistant request to a team without a main coach', function () {
    $team = Team::factory()->create();

    $this->post('/register', signup(['coach_type' => 'assistant', 'team_id' => $team->id]))
        ->assertSessionHasErrors(['team_id' => 'This team has no main coach to approve your request yet.']);
});

it('requires a coach type', function () {
    $this->post('/register', signup(['coach_type' => null]))->assertSessionHasErrors('coach_type');
});
```

Update the two existing tests' payloads by adding `'coach_type' => 'main', 'team_mode' => 'create', 'team_code' => 'SLF', 'team_name' => 'Self Team'`.

> If `TeamFactory` has no `inactive()` state, use `Team::factory()->create(['is_active' => false])` (the factory has a state returning `['is_active' => false]`; check its name first).

- [ ] **Step 2: Run to verify they fail**
Run: `php artisan test tests/Feature/Auth`
Expected: FAIL. The props are missing and no team gets created.

- [ ] **Step 3: Implement**

`TeamRepository` additions:
```php
    /** @return Collection<int, Team> */
    public function claimableForSignup(): Collection
    {
        return Team::query()
            ->where('is_active', true)
            ->whereNull('main_coach_user_id')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
    }

    /** @return Collection<int, Team> */
    public function joinableForSignup(): Collection
    {
        return Team::query()
            ->whereNotNull('main_coach_user_id')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
    }

    /** Lock the team row for a slot check inside a transaction. */
    public function lockForUpdate(int $id): Team
    {
        return Team::query()->lockForUpdate()->findOrFail($id);
    }
```

`app/Repositories/TeamJoinRequestRepository.php`:
```php
<?php

namespace App\Repositories;

use App\Enums\JoinRequestStatus;
use App\Models\TeamJoinRequest;

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
}
```

`app/Http/Requests/RegisterCoachRequest.php`:
```php
<?php

namespace App\Http\Requests;

use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterCoachRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $isMain = $this->input('coach_type') === 'main';

        $this->merge([
            'team_mode' => $isMain ? $this->input('team_mode') : null,
            'team_id' => $this->input('team_id') ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'confirmed', Password::defaults()],
            'coach_type' => ['required', Rule::in(['main', 'assistant'])],
            'team_mode' => ['nullable', 'required_if:coach_type,main', Rule::in(['create', 'claim'])],
            'team_code' => ['exclude_unless:team_mode,create', 'required', 'string', 'max:10', 'unique:teams,code'],
            'team_name' => ['exclude_unless:team_mode,create', 'required', 'string', 'max:100'],
            'team_id' => [
                Rule::excludeIf(fn () => $this->input('team_mode') === 'create'),
                'required', 'integer', Rule::exists('teams', 'id'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'coach_type.required' => 'Choose whether you are a main or assistant coach.',
            'team_id.required' => 'Choose a team.',
            'team_code.unique' => 'A team with this code already exists.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || $this->input('team_mode') === 'create') {
                return;
            }

            $team = Team::query()->find($this->input('team_id'));

            if ($this->input('coach_type') === 'main') {
                if (! $team->is_active) {
                    $validator->errors()->add('team_id', 'This team is inactive.');
                } elseif ($team->main_coach_user_id !== null) {
                    $validator->errors()->add('team_id', 'This team already has a main coach.');
                }

                return;
            }

            if ($team->main_coach_user_id === null) {
                $validator->errors()->add('team_id', 'This team has no main coach to approve your request yet.');
            }
        });
    }
}
```
(`withValidator` reading `Team` mirrors the existing `StoreAccountRequest` pattern.)

`app/Services/CoachRegistrationService.php`:
```php
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
     * @param  array{name: string, email: string, password: string, coach_type: string, team_mode?: string|null, team_code?: string, team_name?: string, team_id?: int|null}  $data
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
```
(`AccountRepository::create` uses forceFill; `password` is hashed by the model cast, matching `AccountService::create`.)

`RegisteredUserController`:
```php
    public function __construct(
        private readonly TeamRepository $teamRepository,
        private readonly CoachRegistrationService $coachRegistrationService,
    ) {}

    public function create(): Response
    {
        return Inertia::render('Auth/Register', [
            'claimableTeams' => $this->teamRepository->claimableForSignup(),
            'joinableTeams' => $this->teamRepository->joinableForSignup(),
        ]);
    }

    public function store(RegisterCoachRequest $request): RedirectResponse
    {
        $user = $this->coachRegistrationService->register($request->validated());

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
```
Remove the now-unused imports (`Hash`, `Rules`, `Request`, `User`, `ValidationException`).

- [ ] **Step 4: Run to verify they pass**
Run: `php artisan test tests/Feature/Auth tests/Feature/Accounts` → PASS.

- [ ] **Step 5: Checkpoint.** `vendor/bin/pint`, then stop for review.

---

### Task 3: Signup UI (`Register.jsx` → `Register.tsx`)

**Files:**
- Delete: `resources/js/Pages/Auth/Register.jsx`
- Create: `resources/js/Pages/Auth/Register.tsx`

**Interfaces:**
- Consumes: props `claimableTeams`, `joinableTeams` (`{id:number; code:string; name:string}[]`); POST fields from Task 2.

- [ ] **Step 1: Implement** (keep the Breeze components the page already uses: `InputLabel`, `TextInput`, `InputError`, `PrimaryButton`, `GuestLayout`. The select style is copied from the `AccountForm.tsx` native `<select>` class string.)

```tsx
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';

interface TeamOption {
    id: number;
    code: string;
    name: string;
}

interface RegisterProps {
    claimableTeams: TeamOption[];
    joinableTeams: TeamOption[];
}

type CoachType = 'main' | 'assistant';
type TeamMode = 'create' | 'claim';

const selectClass =
    'mt-1 block h-10 w-full rounded-md border border-gray-300 bg-white px-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-50';

export default function Register({ claimableTeams, joinableTeams }: RegisterProps) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        coach_type: 'main' as CoachType,
        team_mode: 'create' as TeamMode,
        team_code: '',
        team_name: '',
        team_id: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    const teamChoices = data.coach_type === 'assistant' ? joinableTeams : claimableTeams;
    const showTeamSelect = data.coach_type === 'assistant' || data.team_mode === 'claim';

    return (
        <GuestLayout>
            <Head title="Register" />

            <form onSubmit={submit} className="space-y-4">
                {/* name / email / password / confirm — same markup as the old Register.jsx */}

                <div>
                    <InputLabel htmlFor="coach_type" value="I am a" />
                    <select
                        id="coach_type"
                        className={selectClass}
                        value={data.coach_type}
                        onChange={(e) => setData((d) => ({ ...d, coach_type: e.target.value as CoachType, team_id: '' }))}
                    >
                        <option value="main">Main coach</option>
                        <option value="assistant">Assistant coach</option>
                    </select>
                    <InputError message={errors.coach_type} className="mt-2" />
                </div>

                {data.coach_type === 'main' && (
                    <fieldset className="space-y-2">
                        <legend className="text-sm font-medium text-gray-700">Your team</legend>
                        <div className="flex gap-4 text-sm">
                            {(['create', 'claim'] as const).map((mode) => (
                                <label key={mode} className="flex items-center gap-2">
                                    <input
                                        type="radio"
                                        name="team_mode"
                                        value={mode}
                                        checked={data.team_mode === mode}
                                        onChange={() => setData((d) => ({ ...d, team_mode: mode, team_id: '' }))}
                                    />
                                    {mode === 'create' ? 'Create a new team' : 'Claim an existing team'}
                                </label>
                            ))}
                        </div>
                        <InputError message={errors.team_mode} className="mt-2" />
                    </fieldset>
                )}

                {data.coach_type === 'main' && data.team_mode === 'create' && (
                    <div className="grid grid-cols-3 gap-3">
                        <div>
                            <InputLabel htmlFor="team_code" value="Code" />
                            <TextInput id="team_code" value={data.team_code} maxLength={10} className="mt-1 block w-full"
                                onChange={(e) => setData('team_code', e.target.value.toUpperCase())} required />
                            <InputError message={errors.team_code} className="mt-2" />
                        </div>
                        <div className="col-span-2">
                            <InputLabel htmlFor="team_name" value="Team name" />
                            <TextInput id="team_name" value={data.team_name} maxLength={100} className="mt-1 block w-full"
                                onChange={(e) => setData('team_name', e.target.value)} required />
                            <InputError message={errors.team_name} className="mt-2" />
                        </div>
                    </div>
                )}

                {showTeamSelect && (
                    <div>
                        <InputLabel htmlFor="team_id" value="Team" />
                        <select id="team_id" className={selectClass} value={data.team_id}
                            onChange={(e) => setData('team_id', e.target.value)} disabled={teamChoices.length === 0} required>
                            <option value="" disabled>
                                {teamChoices.length === 0 ? 'No teams available' : 'Select a team'}
                            </option>
                            {teamChoices.map((team) => (
                                <option key={team.id} value={team.id}>{team.code} · {team.name}</option>
                            ))}
                        </select>
                        {data.coach_type === 'assistant' && (
                            <p className="mt-1 text-xs text-gray-500">
                                Your request goes to the team's main coach. You'll get access once it's approved.
                            </p>
                        )}
                        <InputError message={errors.team_id} className="mt-2" />
                    </div>
                )}

                {/* "Already registered?" link + Register button — same as old file */}
            </form>
        </GuestLayout>
    );
}
```
The implementer copies the four existing input blocks and the footer from the old `Register.jsx` verbatim where the comments say so, adding types as needed.

- [ ] **Step 2: Verify**
Run: `npm run typecheck && php artisan test tests/Feature/Auth` → both pass. Then run `composer dev`, open `/register` at 768px width and check all three paths submit.

- [ ] **Step 3: Checkpoint.** Stop for review.

---

### Task 4: Approve / reject join requests (backend)

**Files:**
- Create: `app/Policies/TeamPolicy.php`, `app/Services/TeamJoinRequestService.php`, `app/Http/Controllers/TeamJoinRequestController.php`
- Modify: `app/Repositories/TeamJoinRequestRepository.php`, `routes/web.php`
- Test: `tests/Feature/Team/TeamJoinRequestTest.php`

**Interfaces:**
- Consumes: Task 1 model; `TeamStaffingService::update(Team, ?int, list<int>)`.
- Produces:
  - `TeamPolicy::manageJoinRequests(User $user, Team $team): bool`
  - Routes `teams.join-requests.approve` / `teams.join-requests.reject`: `POST /teams/{team}/join-requests/{joinRequest}/approve|reject`, scoped bindings (`->scopeBindings()`)
  - `TeamJoinRequestService::approve(TeamJoinRequest $r, User $decider): void`, `reject(...)`
  - `TeamJoinRequestRepository::pendingForTeam(int $teamId): Collection` (with `user:id,name,email`, oldest first), `markDecided(TeamJoinRequest $r, JoinRequestStatus $s, int $deciderId): void`

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Enums\JoinRequestStatus;
use App\Models\Team;
use App\Models\TeamJoinRequest;
use App\Models\User;

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

function approveUrl($team, $req): string
{
    return route('teams.join-requests.approve', [$team, $req]);
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
    $other->forceFill(['main_coach_user_id' => User::factory()->forTeam($other)->create()->id])->save();

    $this->actingAs($other->mainCoach)->post(approveUrl($other, $this->joinRequest))->assertNotFound();
});

it('does not list a pending applicant as an assistant coach', function () {
    expect($this->team->assistantCoaches()->count())->toBe(0);
});
```

- [ ] **Step 2: Run to verify they fail**
Run: `php artisan test tests/Feature/Team/TeamJoinRequestTest.php` → FAIL (route not defined).

- [ ] **Step 3: Implement**

`app/Policies/TeamPolicy.php` (Laravel auto-discovers `App\Policies\TeamPolicy` for `App\Models\Team`):
```php
<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    /** Admins, or the team's own main coach, decide assistant join requests. */
    public function manageJoinRequests(User $user, Team $team): bool
    {
        return $user->isAdmin() || $team->main_coach_user_id === $user->id;
    }
}
```

Repository additions:
```php
    /** @return Collection<int, TeamJoinRequest> */
    public function pendingForTeam(int $teamId): Collection
    {
        return TeamJoinRequest::query()
            ->with('user:id,name,email')
            ->where('team_id', $teamId)
            ->where('status', JoinRequestStatus::Pending)
            ->oldest()
            ->get();
    }

    public function markDecided(TeamJoinRequest $joinRequest, JoinRequestStatus $status, int $deciderId): void
    {
        $joinRequest->forceFill([
            'status' => $status,
            'decided_by_user_id' => $deciderId,
            'decided_at' => now(),
        ])->save();
    }
```
(import `Illuminate\Database\Eloquent\Collection`.)

`app/Services/TeamJoinRequestService.php`:
```php
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

    /** Put the applicant on the team as an assistant coach. One team per coach. */
    public function approve(TeamJoinRequest $joinRequest, User $decider): void
    {
        DB::transaction(function () use ($joinRequest, $decider): void {
            $this->ensurePending($joinRequest);

            $applicant = $joinRequest->user()->lockForUpdate()->firstOrFail();

            if ($applicant->team_id !== null) {
                throw ValidationException::withMessages(['join_request' => 'This coach already belongs to a team.']);
            }

            $team = $joinRequest->team()->firstOrFail();
            $applicant->forceFill(['team_id' => $team->id])->save();

            $assistantIds = $team->assistantCoaches()->pluck('users.id')->map(fn ($id) => (int) $id)->all();
            $this->teamStaffingService->update($team, $team->main_coach_user_id, [...$assistantIds, $applicant->id]);

            $this->joinRequestRepository->markDecided($joinRequest, JoinRequestStatus::Approved, $decider->id);
        });
    }

    public function reject(TeamJoinRequest $joinRequest, User $decider): void
    {
        $this->ensurePending($joinRequest);
        $this->joinRequestRepository->markDecided($joinRequest, JoinRequestStatus::Rejected, $decider->id);
    }

    private function ensurePending(TeamJoinRequest $joinRequest): void
    {
        if ($joinRequest->status !== JoinRequestStatus::Pending) {
            throw ValidationException::withMessages(['join_request' => 'This request was already decided.']);
        }
    }
}
```

`app/Http/Controllers/TeamJoinRequestController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\TeamJoinRequest;
use App\Services\TeamJoinRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TeamJoinRequestController extends Controller
{
    public function __construct(private readonly TeamJoinRequestService $service) {}

    public function approve(Request $request, Team $team, TeamJoinRequest $joinRequest): RedirectResponse
    {
        $this->service->approve($joinRequest, $request->user());

        return redirect()->route('teams.show', $team)
            ->with('success', "{$joinRequest->user->name} joined as an assistant coach.");
    }

    public function reject(Request $request, Team $team, TeamJoinRequest $joinRequest): RedirectResponse
    {
        $this->service->reject($joinRequest, $request->user());

        return redirect()->route('teams.show', $team)->with('success', 'Join request declined.');
    }
}
```

`routes/web.php`, next to the staffing route:
```php
    Route::scopeBindings()->group(function () {
        Route::post('/teams/{team}/join-requests/{joinRequest}/approve', [TeamJoinRequestController::class, 'approve'])
            ->can('manageJoinRequests', 'team')
            ->name('teams.join-requests.approve');
        Route::post('/teams/{team}/join-requests/{joinRequest}/reject', [TeamJoinRequestController::class, 'reject'])
            ->can('manageJoinRequests', 'team')
            ->name('teams.join-requests.reject');
    });
```
Scoped binding resolves `{joinRequest}` via `Team::joinRequests()`, so a request from another team returns 404.

- [ ] **Step 4: Run to verify they pass**
Run: `php artisan test tests/Feature/Team` → PASS.

- [ ] **Step 5: Checkpoint.** `vendor/bin/pint`, then stop for review.

---

### Task 5: Join requests on the team page

**Files:**
- Modify: `app/Http/Controllers/TeamController.php` (`show`), `resources/js/types/index.ts`, `resources/js/Pages/Teams/Show.tsx`
- Create: `resources/js/Components/features/teams/TeamJoinRequestsCard.tsx`
- Test: append to `tests/Feature/Team/TeamShowStaffingPayloadTest.php` (or a new Pest file `tests/Feature/Team/TeamShowJoinRequestsPayloadTest.php`)

**Interfaces:**
- Consumes: `TeamJoinRequestRepository::pendingForTeam`, `TeamPolicy::manageJoinRequests`, routes from Task 4.
- Produces: Inertia props `canManageJoinRequests: boolean` and `pendingJoinRequests: TeamJoinRequestSummary[]` (empty when the viewer can't manage). TS type:
  ```ts
  export interface TeamJoinRequestSummary {
    id: number;
    created_at: string;
    user: { id: number; name: string; email: string };
  }
  ```

- [ ] **Step 1: Write the failing test**

```php
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
```

- [ ] **Step 2: Run to verify it fails** → FAIL, prop missing.

- [ ] **Step 3: Implement**

In `TeamController::show` inject `TeamJoinRequestRepository` in the constructor and add:
```php
        $canManageJoinRequests = request()->user()->can('manageJoinRequests', $team);
        // ...
            'canManageJoinRequests' => $canManageJoinRequests,
            'pendingJoinRequests' => fn () => $canManageJoinRequests
                ? $this->teamJoinRequestRepository->pendingForTeam($team->id)
                : [],
```

`TeamJoinRequestsCard.tsx` reuses the card shell markup of `TeamCoachStaffingCard` (same `rounded-xl border border-border bg-card` header block, eyebrow "Team Management", title "Join Requests"). Body:
```tsx
import { type TeamJoinRequestSummary } from '@/types';
import { router } from '@inertiajs/react';
import { Check, X } from 'lucide-react';

interface Props {
    teamId: number;
    requests: TeamJoinRequestSummary[];
}

export function TeamJoinRequestsCard({ teamId, requests }: Props) {
    const decide = (id: number, action: 'approve' | 'reject') =>
        router.post(route(`teams.join-requests.${action}`, [teamId, id]), {}, { preserveScroll: true });

    return (
        <div className="rounded-xl border border-border bg-card">
            <div className="border-b border-border px-5 py-4">
                <p className="text-[10px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">Team Management</p>
                <h2 className="mt-1 font-display text-base font-bold tracking-wide text-foreground">Join Requests</h2>
                <p className="mt-1 text-sm text-muted-foreground">Assistant coaches waiting for your approval.</p>
            </div>
            {requests.length === 0 ? (
                <p className="px-5 py-4 text-sm text-muted-foreground">No pending requests.</p>
            ) : (
                <ul className="divide-y divide-border">
                    {requests.map((r) => (
                        <li key={r.id} className="flex items-center justify-between gap-4 px-5 py-3">
                            <div className="min-w-0">
                                <p className="truncate text-sm font-semibold text-foreground">{r.user.name}</p>
                                <p className="truncate text-xs text-muted-foreground">
                                    {r.user.email} · requested {new Date(r.created_at).toLocaleDateString()}
                                </p>
                            </div>
                            <div className="flex shrink-0 gap-2">
                                <button onClick={() => decide(r.id, 'reject')}
                                    className="flex items-center gap-1 rounded-lg border border-border px-3 py-1.5 text-xs font-ui font-semibold text-muted-foreground hover:bg-muted hover:text-foreground">
                                    <X size={12} /> Decline
                                </button>
                                <button onClick={() => decide(r.id, 'approve')}
                                    className="flex items-center gap-1 rounded-lg bg-primary px-3 py-1.5 text-xs font-ui font-semibold text-primary-foreground hover:opacity-90">
                                    <Check size={12} /> Approve
                                </button>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
```
In `Teams/Show.tsx`, add the props to `TeamsShowProps` and render below `<TeamCoachStaffingCard …/>`:
```tsx
{canManageJoinRequests && <TeamJoinRequestsCard teamId={team.id} requests={pendingJoinRequests} />}
```
Errors under the `join_request` key show through the existing flash/error display. If the page doesn't render `errors`, add `const { errors } = usePage().props` and render `errors.join_request` inside the card.

- [ ] **Step 4: Verify** — `php artisan test tests/Feature/Team && npm run typecheck` → PASS.
- [ ] **Step 5: Checkpoint.**

---

### Task 6: Pending/rejected banner for assistant applicants

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`, `resources/js/types/index.ts`, `resources/js/Pages/Dashboard.tsx`
- Create: `resources/js/Components/features/dashboard/JoinRequestBanner.tsx`
- Test: `tests/Feature/Auth/JoinRequestSharedPropTest.php`

**Interfaces:**
- Produces: `auth.user.join_request: { status: 'pending' | 'approved' | 'rejected'; team_name: string } | null`

- [ ] **Step 1: Failing test**
```php
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
```
- [ ] **Step 2: Run** → FAIL.
- [ ] **Step 3: Implement.** In `share()`, change `loadMissing('team:id,name')` to `loadMissing(['team:id,name', 'joinRequest.team:id,name'])` and add:
```php
                    'join_request' => $user->joinRequest === null ? null : [
                        'status' => $user->joinRequest->status->value,
                        'team_name' => $user->joinRequest->team->name,
                    ],
```
Add `join_request: { status: 'pending' | 'approved' | 'rejected'; team_name: string } | null;` to the `auth.user` type.

`JoinRequestBanner.tsx`:
```tsx
import { type PageProps } from '@/types';
import { usePage } from '@inertiajs/react';
import { Clock, XCircle } from 'lucide-react';

export function JoinRequestBanner() {
    const request = usePage<PageProps>().props.auth.user?.join_request;

    if (!request || request.status === 'approved') return null;

    const pending = request.status === 'pending';

    return (
        <div className="flex items-center gap-3 rounded-xl border border-border bg-card px-5 py-3 text-sm">
            {pending ? <Clock size={16} className="text-accent" /> : <XCircle size={16} className="text-destructive" />}
            <span className="text-foreground">
                {pending
                    ? `Waiting for ${request.team_name}'s main coach to approve your request.`
                    : `Your request to join ${request.team_name} was declined. Contact an admin.`}
            </span>
        </div>
    );
}
```
Render `<JoinRequestBanner />` in `Dashboard.tsx` directly under the page-heading block.
- [ ] **Step 4: Verify** — tests + `npm run typecheck` → PASS.
- [ ] **Step 5: Checkpoint.**

---

### Task 7: Strict one-team-per-coach in admin account edit

**Files:**
- Modify: `app/Http/Requests/UpdateAccountRequest.php`, `app/Services/AccountService.php`, `resources/js/Components/features/accounts/AccountForm.tsx`
- Test: `tests/Feature/Accounts/AccountUpdateTest.php`

**Interfaces:** unchanged routes. New validation error on `team_id`.

- [ ] **Step 1: Update tests.** Replace `it('clears the old staffing slots when a coach changes team', …)` with:
```php
it('blocks moving a staffed coach to another team', function () {
    $oldTeam = Team::factory()->create(['name' => 'Old']);
    $newTeam = Team::factory()->create();
    $coach = User::factory()->forTeam($oldTeam)->create();
    $oldTeam->forceFill(['main_coach_user_id' => $coach->id])->save();

    $this->actingAs($this->admin)
        ->put(route('accounts.update', $coach), updatePayload($coach, ['team_id' => $newTeam->id]))
        ->assertSessionHasErrors(['team_id' => "Remove this coach from Old's staff before moving them to another team."]);

    expect($coach->fresh()->team_id)->toBe($oldTeam->id)
        ->and($oldTeam->fresh()->main_coach_user_id)->toBe($coach->id);
});

it('blocks moving an assistant coach to another team', function () {
    $oldTeam = Team::factory()->create();
    $coach = User::factory()->forTeam($oldTeam)->create();
    $oldTeam->assistantCoaches()->attach($coach->id);

    $this->actingAs($this->admin)
        ->put(route('accounts.update', $coach), updatePayload($coach, ['team_id' => Team::factory()->create()->id]))
        ->assertSessionHasErrors('team_id');
});

it('moves an unstaffed coach freely', function () {
    $newTeam = Team::factory()->create();
    $coach = User::factory()->forTeam(Team::factory()->create())->create();

    $this->actingAs($this->admin)
        ->put(route('accounts.update', $coach), updatePayload($coach, ['team_id' => $newTeam->id]))
        ->assertRedirect(route('accounts.index'));

    expect($coach->fresh()->team_id)->toBe($newTeam->id);
});
```
Keep `promoting a coach to admin clears team and staffing` unchanged. Promotion isn't "attaching to another team".

- [ ] **Step 2: Run** `php artisan test tests/Feature/Accounts/AccountUpdateTest.php` → the two "blocks" tests FAIL.

- [ ] **Step 3: Implement.** In `UpdateAccountRequest::withValidator` after-callback, append:
```php
            $movingTeams = $this->input('role') === UserRole::Coach->value
                && $account->team_id !== null
                && (int) $this->input('team_id') !== $account->team_id;

            if ($movingTeams) {
                $staffedTeam = Team::query()
                    ->where('id', $account->team_id)
                    ->where(fn ($q) => $q->where('main_coach_user_id', $account->id)
                        ->orWhereHas('assistantCoaches', fn ($a) => $a->where('users.id', $account->id)))
                    ->first();

                if ($staffedTeam !== null) {
                    $validator->errors()->add('team_id', "Remove this coach from {$staffedTeam->name}'s staff before moving them to another team.");
                }
            }
```
(add `use App\Models\Team;`). In `AccountService::update`, narrow the clear branch to promotion only and update the docblock:
```php
            if ($role === UserRole::Admin) {
                $this->accountRepository->clearStaffing($user);
            }
```
In `AccountForm.tsx`, the existing warning (around line 236–245) tells the admin the staffing will be cleared on a team change. Reword it for coach→coach team changes: "This coach is the {main coach|an assistant coach} of {team}. Remove them from that team's staff first." Keep the existing text for promotion to admin.

- [ ] **Step 4: Verify** — `php artisan test tests/Feature/Accounts && npm run typecheck` → PASS.
- [ ] **Step 5: Checkpoint.**

---

### Task 8: Docs + full verification

**Files:** `CLAUDE.md`, `docs/USER_MANUAL.md`

- [ ] **Step 1:** In `CLAUDE.md` Gotchas, replace "Real-time possession tracking and per-user team ownership are explicitly out of scope" with: "Real-time possession tracking is out of scope. Team ownership is limited to staffing: one team per coach (`users.team_id`); self-signup main coaches create or claim a team; assistants join via `team_join_requests` approved by the main coach or an admin."
- [ ] **Step 2:** In `docs/USER_MANUAL.md`, add a "Signing up as a coach" section: the three paths, the approval flow, and "To move a coach, an admin first removes them from their team's staff."
- [ ] **Step 3: Full verification**
```bash
php artisan test
npm run typecheck
npm run build
vendor/bin/pint --test
```
All must pass. Then run `composer dev` and check by hand at 768px:
  (a) sign up as main + create
  (b) sign up as main + claim
  (c) sign up as assistant → banner shows pending → log in as that team's main coach → approve on the team page → applicant now appears under Assistant Coaches
  (d) admin edit of a staffed coach to another team shows the error
- [ ] **Step 4: Checkpoint.** Report the results. Commit/PR only if the user asks.

---

## Self-review notes
- Spec coverage: signup dropdown (T2/T3), create/claim (T2), assistant request + approval (T2/T4/T5), pending state (T6), strict rule (T4 approve guard, T7 account edit; staffing sheet already restricts candidates to `users.team_id = team`), admin still creates/assigns coaches (unchanged `AccountService::create` + `StoreAccountRequest` main-slot guard), docs (T8).
- Names used consistently: `coach_type`, `team_mode`, `team_code`, `team_name`, `team_id`; `JoinRequestStatus`; `TeamJoinRequestRepository::{createPending,pendingForTeam,markDecided}`; `TeamRepository::{claimableForSignup,joinableForSignup,lockForUpdate}`; route names `teams.join-requests.{approve,reject}`; props `claimableTeams`, `joinableTeams`, `canManageJoinRequests`, `pendingJoinRequests`, `auth.user.join_request`.
