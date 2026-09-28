import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { type AccountSummary, type AccountTeamOption, type UserRole } from '@/types';
import { Link, useForm } from '@inertiajs/react';
import { AlertTriangle, Loader2, ShieldCheck, UserRound } from 'lucide-react';
import { type FormEvent, type ReactNode, useId } from 'react';

type StaffingChoice = 'none' | 'main' | 'assistant';

interface AccountFormData {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    role: UserRole;
    team_id: string;
    staffing: StaffingChoice;
}

type AccountFormProps =
    | { mode: 'create'; teams: AccountTeamOption[]; account?: undefined }
    | { mode: 'edit'; teams: AccountTeamOption[]; account: AccountSummary };

const labelClass = 'font-ui text-xs font-semibold uppercase tracking-wide text-muted-foreground';
const selectClass =
    'h-10 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50';

/**
 * Shared create/edit form for admin-managed accounts.
 * Admins are team-less; coaches pick a team and (on create) an optional staffing slot.
 */
export function AccountForm({ mode, teams, account }: AccountFormProps) {
    const isEdit = mode === 'edit';

    const { data, setData, post, put, processing, errors, transform } = useForm<AccountFormData>({
        name: account?.name ?? '',
        email: account?.email ?? '',
        password: '',
        password_confirmation: '',
        role: account?.role ?? 'coach',
        team_id: account?.team_id ? String(account.team_id) : '',
        staffing: 'none',
    });

    const isCoach = data.role === 'coach';
    const selectedTeam = teams.find((team) => String(team.id) === data.team_id) ?? null;
    const mainSlotTaken = selectedTeam?.main_coach !== null && selectedTeam?.main_coach !== undefined;

    const willClearStaffing =
        isEdit &&
        account.staffing !== null &&
        (data.role === 'admin' || data.team_id !== String(account.team_id ?? ''));

    const handleTeamChange = (teamId: string): void => {
        const team = teams.find((option) => String(option.id) === teamId);
        setData((current) => ({
            ...current,
            team_id: teamId,
            staffing: current.staffing === 'main' && team?.main_coach ? 'none' : current.staffing,
        }));
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        if (isEdit) {
            transform(({ staffing: _staffing, ...rest }) => rest);
            put(route('accounts.update', account.id), { preserveScroll: true });

            return;
        }

        post(route('accounts.store'), { preserveScroll: true });
    };

    return (
        <form onSubmit={handleSubmit} className="divide-y divide-border">
            {/* ── Role ── */}
            <FormSection title="Account type">
                <div role="radiogroup" aria-label="Account type" className="grid gap-3 sm:grid-cols-2">
                    <RoleOption
                        value="coach"
                        current={data.role}
                        onSelect={(role) => setData('role', role)}
                        icon={<UserRound size={16} />}
                        title="Coach"
                        description="Belongs to one team. Can be its main or an assistant coach."
                        disabled={processing}
                    />
                    <RoleOption
                        value="admin"
                        current={data.role}
                        onSelect={(role) => setData('role', role)}
                        icon={<ShieldCheck size={16} />}
                        title="Admin"
                        description="Manages accounts and team staffing. Not tied to a team."
                        disabled={processing}
                    />
                </div>
                <FieldError message={errors.role} />
            </FormSection>

            {/* ── Identity ── */}
            <FormSection title="Details">
                <div className="grid gap-4 md:grid-cols-2">
                    <Field id="account-name" label="Full name" error={errors.name}>
                        <Input
                            id="account-name"
                            value={data.name}
                            onChange={(event) => setData('name', event.target.value)}
                            autoComplete="off"
                            maxLength={255}
                            required
                            disabled={processing}
                        />
                    </Field>
                    <Field id="account-email" label="Email" error={errors.email}>
                        <Input
                            id="account-email"
                            type="email"
                            value={data.email}
                            onChange={(event) => setData('email', event.target.value.toLowerCase())}
                            autoComplete="off"
                            required
                            disabled={processing}
                        />
                    </Field>
                </div>
            </FormSection>

            {/* ── Team + staffing (coach only) ── */}
            {isCoach && (
                <FormSection
                    title="Team"
                    hint={isEdit ? 'Staffing slots are managed from the team page.' : undefined}
                >
                    <Field id="account-team" label="Team" error={errors.team_id}>
                        <select
                            id="account-team"
                            value={data.team_id}
                            onChange={(event) => handleTeamChange(event.target.value)}
                            className={selectClass}
                            disabled={processing}
                            required
                        >
                            <option value="" disabled>
                                Select a team
                            </option>
                            {teams.map((team) => (
                                <option key={team.id} value={team.id}>
                                    {team.name}
                                </option>
                            ))}
                        </select>
                    </Field>

                    {!isEdit && (
                        <fieldset className="space-y-2 transition-opacity disabled:opacity-60" disabled={processing || selectedTeam === null}>
                            <legend className={labelClass}>Staffing</legend>
                            <div className="grid gap-2 sm:grid-cols-3">
                                <StaffingOption
                                    value="none"
                                    current={data.staffing}
                                    onSelect={(value) => setData('staffing', value)}
                                    title="Not assigned"
                                    detail="Assign later"
                                />
                                <StaffingOption
                                    value="main"
                                    current={data.staffing}
                                    onSelect={(value) => setData('staffing', value)}
                                    title="Main coach"
                                    detail={
                                        mainSlotTaken
                                            ? `Held by ${selectedTeam?.main_coach?.name}`
                                            : 'One per team'
                                    }
                                    unavailable={mainSlotTaken}
                                />
                                <StaffingOption
                                    value="assistant"
                                    current={data.staffing}
                                    onSelect={(value) => setData('staffing', value)}
                                    title="Assistant coach"
                                    detail="Added to current staff"
                                />
                            </div>
                            {selectedTeam === null && (
                                <p className="text-xs text-muted-foreground">Choose a team to pick a staffing slot.</p>
                            )}
                            <FieldError message={errors.staffing} />
                        </fieldset>
                    )}
                </FormSection>
            )}

            {/* ── Password ── */}
            <FormSection
                title={isEdit ? 'Reset password' : 'Password'}
                hint={
                    isEdit
                        ? 'Leave blank to keep the current password.'
                        : 'Share it with the account holder. They can change it from their profile.'
                }
            >
                <div className="grid gap-4 md:grid-cols-2">
                    <Field id="account-password" label={isEdit ? 'New password' : 'Password'} error={errors.password}>
                        <Input
                            id="account-password"
                            type="password"
                            value={data.password}
                            onChange={(event) => setData('password', event.target.value)}
                            autoComplete="new-password"
                            required={!isEdit}
                            disabled={processing}
                        />
                    </Field>
                    <Field id="account-password-confirmation" label="Confirm password">
                        <Input
                            id="account-password-confirmation"
                            type="password"
                            value={data.password_confirmation}
                            onChange={(event) => setData('password_confirmation', event.target.value)}
                            autoComplete="new-password"
                            required={!isEdit || data.password !== ''}
                            disabled={processing}
                        />
                    </Field>
                </div>
            </FormSection>

            {/* ── Footer ── */}
            <div className="flex flex-col gap-4 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div aria-live="polite" className="min-h-0 sm:flex-1">
                    {willClearStaffing && (
                        <p className="flex items-start gap-2 rounded-lg border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-sm text-foreground">
                            <AlertTriangle size={15} className="mt-0.5 shrink-0 text-amber-600 dark:text-amber-400" />
                            <span>
                                Saving removes {account?.name} as{' '}
                                {account?.staffing === 'main' ? 'main coach' : 'an assistant coach'} of{' '}
                                {account?.team?.name}.
                            </span>
                        </p>
                    )}
                </div>

                <div className="flex items-center gap-3 sm:justify-end">
                    <Link
                        href={route('accounts.index')}
                        className="rounded-lg px-3 py-2 text-sm text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        disabled={processing}
                        className="flex items-center gap-2 rounded-lg bg-primary px-4 py-2 font-ui text-sm font-semibold tracking-wide text-primary-foreground transition-[opacity,box-shadow] duration-200 hover:opacity-90 hover:shadow-[0_0_12px_rgba(152,0,46,0.4)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:opacity-50"
                    >
                        {processing && <Loader2 size={14} className="animate-spin" />}
                        {isEdit ? 'Save changes' : 'Create account'}
                    </button>
                </div>
            </div>
        </form>
    );
}

function FormSection({ title, hint, children }: { title: string; hint?: string; children: ReactNode }) {
    return (
        <section className="grid gap-4 px-5 py-5 lg:grid-cols-[180px_minmax(0,1fr)] lg:gap-6">
            <div>
                <h2 className="font-ui text-sm font-bold tracking-wide text-foreground">{title}</h2>
                {hint && <p className="mt-1 text-xs text-muted-foreground">{hint}</p>}
            </div>
            <div className="min-w-0 space-y-4">{children}</div>
        </section>
    );
}

function Field({ id, label, error, children }: { id: string; label: string; error?: string; children: ReactNode }) {
    return (
        <div className="space-y-1.5">
            <Label htmlFor={id} className={labelClass}>
                {label}
            </Label>
            {children}
            <FieldError message={error} />
        </div>
    );
}

function FieldError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return <p className="text-xs text-destructive">{message}</p>;
}

interface RoleOptionProps {
    value: UserRole;
    current: UserRole;
    onSelect: (role: UserRole) => void;
    icon: ReactNode;
    title: string;
    description: string;
    disabled: boolean;
}

function RoleOption({ value, current, onSelect, icon, title, description, disabled }: RoleOptionProps) {
    const id = useId();
    const selected = value === current;

    return (
        <label
            htmlFor={id}
            className={`flex cursor-pointer items-start gap-3 rounded-lg border px-4 py-3 transition-colors duration-150 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring has-[:focus-visible]:ring-offset-2 ${
                selected ? 'border-primary bg-primary/[0.06]' : 'border-border hover:bg-muted/40'
            } ${disabled ? 'cursor-not-allowed opacity-60' : ''}`}
        >
            <input
                id={id}
                type="radio"
                name="role"
                value={value}
                aria-labelledby={`${id}-title`}
                aria-describedby={`${id}-desc`}
                checked={selected}
                onChange={() => onSelect(value)}
                disabled={disabled}
                className="sr-only"
            />
            <span
                className={`mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-md border transition-colors duration-150 ${
                    selected ? 'border-primary/40 bg-primary text-primary-foreground' : 'border-border text-muted-foreground'
                }`}
            >
                {icon}
            </span>
            <span className="min-w-0">
                <span id={`${id}-title`} className="block text-sm font-semibold text-foreground">
                    {title}
                </span>
                <span id={`${id}-desc`} className="mt-0.5 block text-xs leading-relaxed text-muted-foreground">
                    {description}
                </span>
            </span>
        </label>
    );
}

interface StaffingOptionProps {
    value: StaffingChoice;
    current: StaffingChoice;
    onSelect: (value: StaffingChoice) => void;
    title: string;
    detail: string;
    unavailable?: boolean;
}

function StaffingOption({ value, current, onSelect, title, detail, unavailable = false }: StaffingOptionProps) {
    const id = useId();
    const selected = value === current;

    return (
        <label
            htmlFor={id}
            aria-disabled={unavailable}
            className={`flex items-start gap-2.5 rounded-md border px-3 py-2.5 transition-colors duration-150 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring has-[:focus-visible]:ring-offset-2 ${
                unavailable
                    ? 'cursor-not-allowed border-dashed border-border bg-muted/30'
                    : selected
                      ? 'cursor-pointer border-primary bg-primary/[0.06]'
                      : 'cursor-pointer border-border hover:bg-muted/40'
            }`}
        >
            <input
                id={id}
                type="radio"
                name="staffing"
                value={value}
                aria-labelledby={`${id}-title`}
                aria-describedby={`${id}-desc`}
                checked={selected}
                onChange={() => onSelect(value)}
                disabled={unavailable}
                className="mt-0.5 h-4 w-4 shrink-0 accent-primary"
            />
            <span className="min-w-0">
                <span
                    id={`${id}-title`}
                    className={`block text-sm font-semibold ${unavailable ? 'text-muted-foreground' : 'text-foreground'}`}
                >
                    {title}
                </span>
                <span id={`${id}-desc`} className="block truncate text-xs text-muted-foreground">
                    {detail}
                </span>
            </span>
        </label>
    );
}
