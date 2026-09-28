# Admin Account Management — Design & Plan

## Context
The only way to get an account that can be a Main or Assistant Coach is to seed it. Coaches are team relationships (`teams.main_coach_user_id`, pivot `team_assistant_coaches`). `UpdateTeamStaffingRequest` only accepts **verified** users whose `users.team_id` matches the team, and nothing in the UI sets `team_id`. There is also no Admin role: nothing is gated, and "Demo Admin" is just a seeded name. The goal is to add a real Admin role and let admins create and edit accounts (Admin, or Coach + team + optional staffing slot) from inside the app. Auth, the existing staffing rules, and live-game policies stay as they are.

## Decisions (from grilling)
| Topic | Decision |
|---|---|
| Role storage | `users.role` string, default `'coach'`; `App\Enums\UserRole { Admin='admin', Coach='coach' }` |
| Create form | Admin (team-less) **or** Coach + team + optional `staffing` = none / main / assistant |
| Self-signup `/register` | **Kept**; new users get the `coach` role and no team |
| Admin-only | Account screens + `PUT /teams/{team}/staffing` |
| Password | Admin sets the initial password; `email_verified_at = now()` |
| Scope | List + create + edit (name, email, role, team, optional new password). No delete |
| Bootstrap | Migration defaults `coach`; `DemoUserSeeder` makes `test@email.com` admin; `php artisan user:make-admin {email}` |
| Admin + team | Admins have no team (`team_id` forced to null, form hides team fields) |
| Edit a staffed coach (team change or promote to admin) | Clear their old staffing slot in the same transaction; the form warns first |
| Create as main coach when the slot is taken | **Block**: "This team already has a main coach." |
| Lockout | Block any edit that would leave zero admins |

## Backend
Layering: Controller → FormRequest → Service → Repository → Model.

1. **Migration** `add_role_to_users_table`: `string('role', 20)->default('coach')->index()`.
2. **Enum** `app/Enums/UserRole.php` (next to `ShotZone.php`).
3. **User model** (`app/Models/User.php`): add `role` to `#[Fillable]`, cast `'role' => UserRole::class`, add `isAdmin(): bool`. Only the cast and the one-line accessor go here, so the model stays free of logic.
4. **Gate** in `AppServiceProvider::boot`: `Gate::define('admin', fn (User $u) => $u->isAdmin())`.
5. **Routes** (`routes/web.php`, inside the existing `auth,verified` group):
   ```php
   Route::middleware('can:admin')->prefix('accounts')->name('accounts.')->group(function () {
       Route::get('/', [AccountController::class, 'index'])->name('index');
       Route::get('/create', [AccountController::class, 'create'])->name('create');
       Route::post('/', [AccountController::class, 'store'])->name('store');
       Route::get('/{user}/edit', [AccountController::class, 'edit'])->name('edit');
       Route::put('/{user}', [AccountController::class, 'update'])->name('update');
   });
   ```
   Add `->can('admin')` to the existing `teams.staffing.update` route.
6. **`AccountController`**: renders and redirects only. The index and edit forms get their data from the service (users paginated with team and staffing info, plus the team list with `main_coach` names for the form).
7. **FormRequests** `StoreAccountRequest` / `UpdateAccountRequest`:
   - Fields: `name`, `email` (lowercase, unique, ignoring self on update), `password` (store: required+confirmed `Password::defaults()`; update: nullable), `role` (`Rule::enum(UserRole::class)`), `team_id` (required_if role=coach, exists:teams), `staffing` (`in:none,main,assistant`, coach only).
   - `prepareForValidation`: role=admin forces `team_id=null` and `staffing='none'`.
   - `after()` checks: store with `staffing=main` and the team already has `main_coach_user_id` → error. Update that would leave no admins (`role` changes away from admin and `User::where role=admin count === 1` and the target is that admin) → error on `role`.
8. **`AccountRepository`** (`app/Repositories/`): `paginateWithTeams()`, `adminCount()`, `teamsForSelect()`.
9. **`AccountService`** (`app/Services/`), all writes in `DB::transaction`:
   - `create(array $data): User`. Creates the user with `email_verified_at=now()`. If coach with staffing main/assistant, it applies the slot by reusing **`TeamStaffingService::update`** (`app/Services/TeamStaffingService.php`) with the team's current main coach and assistant ids plus the new user, so there is one write path for staffing.
   - `update(User $user, array $data): User`. If team changed or promoted to admin: clear `teams.main_coach_user_id` where it equals the user, and detach the user from `team_assistant_coaches`. Then save, and hash the password only if one was provided (the `hashed` cast handles hashing).
   - On update, staffing for the new team is not assigned from this form; that stays in the staffing sheet. On create, the optional slot is the convenience.
10. **Staffing gate**: `UpdateTeamStaffingRequest::authorize()` stays `true`; the route's `can:admin` enforces access.
11. **Inertia share** (`app/Http/Middleware/HandleInertiaRequests.php`): add `'role' => $user->role->value` and `'is_admin' => $user->isAdmin()` to `auth.user`.
12. **Artisan** `app/Console/Commands/MakeAdminCommand.php`, signature `user:make-admin {email}`. Sets the role to admin, sets `team_id` to null, and clears staffing through `AccountService`.
13. **Seeder** `DemoUserSeeder`: `test@email.com` gets `'role' => UserRole::Admin`. `UserFactory` gets default `'role' => UserRole::Coach` and an `admin()` state.

## Frontend (tablet-first, `.tsx`, no edits to `Components/ui/`)
- `resources/js/Pages/Accounts/Index.tsx`: a table with name, email, role badge, team, staffing (Main / Assistant / —), an Edit action, and a "Create account" button.
- `resources/js/Pages/Accounts/Create.tsx` and `Edit.tsx`: both use `Components/features/accounts/AccountForm.tsx` with a role radio (Admin / Coach). Choosing Coach shows the team select and staffing radio (create only). The Main option shows "Current: {name}" or is disabled when taken. On edit, a warning callout appears when the change clears an existing staffing slot.
- `AuthenticatedLayout.tsx`: an "Accounts" `NavItem` (icon `UserCog`), rendered only when `auth.user.is_admin`.
- `TeamCoachStaffingCard.tsx`: hide the edit trigger when not `is_admin`, so non-admins see the card read-only.
- Update the `auth.user` TS type (in `resources/js/types`) with `role` and `is_admin`.

## Tests (Pest/PHPUnit Feature, TDD)
- `tests/Feature/Accounts/AccountAccessTest.php`: a coach gets 403 on every `accounts.*` route; an admin gets 200.
- `AccountCreateTest`: create admin (team null, verified); create coach with no staffing, as main, and as assistant; main slot taken → error; duplicate email → error.
- `AccountUpdateTest`: rename; change password; team change clears old main/assistant slot; promote to admin clears staffing and nulls the team; demoting the last admin → error; demote allowed when another admin exists.
- `MakeAdminCommandTest`.
- Registration test: a new user's role is `coach`.
- **Existing staffing tests** (`tests/Feature/Team/TeamCoachStaffingUpdateTest.php` etc.): make the `actingUserAndTeam()` actor `->admin()`, and add a test that a coach gets 403. Check `tests/Browser/team-staffing.spec.ts` logs in as the admin demo user.

## Verification
1. `php artisan test` (all green, including the updated staffing tests), `vendor/bin/pint`, `npm run typecheck`, `npm run build`.
2. `php artisan migrate:fresh --seed`, then `composer dev`:
   - Log in as `test@email.com`: Accounts nav shows. Create a coach as GSW assistant, log out, and log in as that coach. They see no Accounts nav, get 403 on `/accounts`, and the staffing card is read-only.
   - Try to create a second GSW main coach: blocked.
   - Edit the new coach's team to LAK: the GSW assistant slot is cleared.
   - Try to demote the only admin: blocked.
   - `/register` still works and creates a coach.
