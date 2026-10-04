import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowRight, Hash, Lock, Mail, Shield, User, UserPlus, Users } from 'lucide-react';
import { type FormEvent, type ReactNode } from 'react';

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

const fieldShell =
    'group flex h-12 items-center rounded-2xl border border-white/10 bg-[#0d1428]/90 px-4 shadow-[inset_0_1px_0_rgba(255,255,255,0.03)] transition focus-within:border-[#ff8c42]/60 focus-within:shadow-[0_0_0_1px_rgba(255,140,66,0.2),0_0_24px_rgba(255,140,66,0.08)]';
const fieldInput =
    'h-full w-full border-0 bg-transparent pl-3 text-sm text-white placeholder:text-slate-500 focus:ring-0';
const fieldIcon = 'h-5 w-5 shrink-0 text-slate-400 transition group-focus-within:text-[#ff9d57]';

/**
 * Self-signup for coaches. Main coaches create or claim a team and become its
 * main coach; assistants send a join request the team's main coach approves.
 */
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
    const showCreateFields = data.coach_type === 'main' && data.team_mode === 'create';

    return (
        <>
            <Head title="HoopSense+ Create Account" />

            <div className="relative flex min-h-screen w-full flex-col overflow-hidden bg-[#020611] text-white">
                <div className="absolute inset-0">
                    <img
                        src="/images/login-assets/login-bg.png"
                        alt=""
                        className="absolute inset-0 h-full w-full object-cover object-top"
                        style={{ filter: 'brightness(0.22) saturate(0.7)' }}
                    />
                </div>

                <div className="relative z-10 flex min-h-screen w-full flex-1 flex-col px-4 py-6 sm:px-6 lg:px-8">
                    <div className="flex flex-1 items-center justify-center">
                        <div className="mx-auto flex w-full max-w-[560px] flex-col items-center text-center">
                            <div className="mb-6 flex items-center gap-4">
                                <img
                                    src="/images/dashboard-assets/basketball-logo.png"
                                    alt="HoopSense+ Logo"
                                    className="h-14 w-14 drop-shadow-[0_0_12px_rgba(255,133,50,0.5)]"
                                />
                                <h1 className="text-4xl font-black tracking-tight text-white">
                                    HoopSense<span className="text-[#ff8c42]">+</span>
                                </h1>
                            </div>

                            <div className="relative w-full overflow-hidden rounded-[24px] border border-[#ff8d45]/35 bg-[linear-gradient(180deg,rgba(10,18,38,0.82),rgba(3,8,22,0.92))] px-6 py-7 text-left shadow-[0_0_0_1px_rgba(255,140,66,0.1),0_0_28px_rgba(255,119,37,0.14),0_30px_80px_rgba(1,5,18,0.65)] backdrop-blur-xl sm:px-8">
                                <div className="pointer-events-none absolute inset-0 rounded-[24px] ring-1 ring-inset ring-white/6" />

                                <div className="mb-6 border-b border-white/10 pb-5">
                                    <h2 className="text-2xl font-bold tracking-tight text-white">Create your coach account</h2>
                                    <p className="mt-1 text-sm text-slate-300/80">
                                        Run your own team, or join one as an assistant.
                                    </p>
                                </div>

                                <form onSubmit={submit} className="relative space-y-5">
                                    <Field id="name" label="Name" error={errors.name}>
                                        <div className={fieldShell}>
                                            <User className={fieldIcon} />
                                            <input
                                                id="name"
                                                name="name"
                                                value={data.name}
                                                autoComplete="name"
                                                autoFocus
                                                required
                                                onChange={(e) => setData('name', e.target.value)}
                                                className={fieldInput}
                                            />
                                        </div>
                                    </Field>

                                    <Field id="email" label="Email" error={errors.email}>
                                        <div className={fieldShell}>
                                            <Mail className={fieldIcon} />
                                            <input
                                                id="email"
                                                type="email"
                                                name="email"
                                                value={data.email}
                                                autoComplete="username"
                                                required
                                                onChange={(e) => setData('email', e.target.value)}
                                                className={fieldInput}
                                            />
                                        </div>
                                    </Field>

                                    <div className="grid gap-5 sm:grid-cols-2">
                                        <Field id="password" label="Password" error={errors.password}>
                                            <div className={fieldShell}>
                                                <Lock className={fieldIcon} />
                                                <input
                                                    id="password"
                                                    type="password"
                                                    name="password"
                                                    value={data.password}
                                                    autoComplete="new-password"
                                                    required
                                                    onChange={(e) => setData('password', e.target.value)}
                                                    className={fieldInput}
                                                />
                                            </div>
                                        </Field>

                                        <Field
                                            id="password_confirmation"
                                            label="Confirm password"
                                            error={errors.password_confirmation}
                                        >
                                            <div className={fieldShell}>
                                                <Lock className={fieldIcon} />
                                                <input
                                                    id="password_confirmation"
                                                    type="password"
                                                    name="password_confirmation"
                                                    value={data.password_confirmation}
                                                    autoComplete="new-password"
                                                    required
                                                    onChange={(e) => setData('password_confirmation', e.target.value)}
                                                    className={fieldInput}
                                                />
                                            </div>
                                        </Field>
                                    </div>

                                    <fieldset>
                                        <legend className="text-sm font-semibold text-slate-100">I am a</legend>
                                        <div className="mt-2 grid grid-cols-2 gap-3">
                                            <ChoiceCard
                                                selected={data.coach_type === 'main'}
                                                icon={<Shield className="h-4 w-4" />}
                                                title="Main coach"
                                                description="Runs a team"
                                                onSelect={() =>
                                                    setData((current) => ({ ...current, coach_type: 'main', team_id: '' }))
                                                }
                                            />
                                            <ChoiceCard
                                                selected={data.coach_type === 'assistant'}
                                                icon={<Users className="h-4 w-4" />}
                                                title="Assistant coach"
                                                description="Joins a team"
                                                onSelect={() =>
                                                    setData((current) => ({ ...current, coach_type: 'assistant', team_id: '' }))
                                                }
                                            />
                                        </div>
                                        <FieldError message={errors.coach_type} />
                                    </fieldset>

                                    {data.coach_type === 'main' && (
                                        <fieldset>
                                            <legend className="text-sm font-semibold text-slate-100">Your team</legend>
                                            <div className="mt-2 inline-flex rounded-2xl border border-white/10 bg-[#0d1428]/90 p-1">
                                                {(['create', 'claim'] as const).map((mode) => (
                                                    <button
                                                        key={mode}
                                                        type="button"
                                                        aria-pressed={data.team_mode === mode}
                                                        onClick={() =>
                                                            setData((current) => ({ ...current, team_mode: mode, team_id: '' }))
                                                        }
                                                        className={`rounded-xl px-4 py-2 text-sm font-semibold transition ${
                                                            data.team_mode === mode
                                                                ? 'bg-[#ff8c42]/15 text-[#ffb27a]'
                                                                : 'text-slate-400 hover:text-slate-200'
                                                        }`}
                                                    >
                                                        {mode === 'create' ? 'Create new team' : 'Claim existing team'}
                                                    </button>
                                                ))}
                                            </div>
                                            <FieldError message={errors.team_mode} />
                                        </fieldset>
                                    )}

                                    {showCreateFields && (
                                        <div className="grid grid-cols-[minmax(0,1fr)_minmax(0,2fr)] gap-3">
                                            <Field id="team_code" label="Team code" error={errors.team_code}>
                                                <div className={fieldShell}>
                                                    <Hash className={fieldIcon} />
                                                    <input
                                                        id="team_code"
                                                        name="team_code"
                                                        value={data.team_code}
                                                        maxLength={10}
                                                        required
                                                        placeholder="e.g. FAL"
                                                        onChange={(e) => setData('team_code', e.target.value.toUpperCase())}
                                                        className={fieldInput}
                                                    />
                                                </div>
                                            </Field>

                                            <Field id="team_name" label="Team name" error={errors.team_name}>
                                                <div className={fieldShell}>
                                                    <input
                                                        id="team_name"
                                                        name="team_name"
                                                        value={data.team_name}
                                                        maxLength={100}
                                                        required
                                                        placeholder="e.g. Falcons"
                                                        onChange={(e) => setData('team_name', e.target.value)}
                                                        className="h-full w-full border-0 bg-transparent text-sm text-white placeholder:text-slate-500 focus:ring-0"
                                                    />
                                                </div>
                                            </Field>
                                        </div>
                                    )}

                                    {showTeamSelect && (
                                        <Field id="team_id" label="Team" error={errors.team_id}>
                                            <div className={fieldShell}>
                                                <Users className={fieldIcon} />
                                                <select
                                                    id="team_id"
                                                    name="team_id"
                                                    value={data.team_id}
                                                    required
                                                    disabled={teamChoices.length === 0}
                                                    onChange={(e) => setData('team_id', e.target.value)}
                                                    className="h-full w-full border-0 bg-transparent pl-3 text-sm text-white focus:ring-0 disabled:cursor-not-allowed disabled:text-slate-500 [&>option]:bg-[#0d1428]"
                                                >
                                                    <option value="" disabled>
                                                        {teamChoices.length === 0 ? 'No teams available' : 'Select a team'}
                                                    </option>
                                                    {teamChoices.map((team) => (
                                                        <option key={team.id} value={team.id}>
                                                            {team.code} · {team.name}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>
                                            <p className="mt-2 text-xs text-slate-400">
                                                {data.coach_type === 'assistant'
                                                    ? "Your request goes to the team's main coach. You'll get access once it's approved."
                                                    : 'Only teams without a main coach are listed.'}
                                            </p>
                                        </Field>
                                    )}

                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="group inline-flex h-12 w-full items-center justify-center gap-2 rounded-2xl border border-[#ff9b56]/70 bg-[linear-gradient(135deg,#ff6a00,#ff8c42)] text-base font-bold text-white shadow-[0_0_0_1px_rgba(255,147,77,0.3),0_12px_30px_rgba(255,111,21,0.28),inset_0_1px_0_rgba(255,255,255,0.25)] transition duration-300 hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-70"
                                    >
                                        <UserPlus className="h-5 w-5" />
                                        <span>
                                            {processing
                                                ? 'Creating account...'
                                                : data.coach_type === 'assistant'
                                                  ? 'Create account & request to join'
                                                  : 'Create account'}
                                        </span>
                                        <ArrowRight className="h-5 w-5 transition group-hover:translate-x-0.5" />
                                    </button>
                                </form>

                                <p className="relative mt-6 text-center text-sm text-slate-300/80">
                                    Already have an account?{' '}
                                    <Link
                                        href={route('login')}
                                        className="font-semibold text-[#77a9ff] transition hover:text-[#9bc0ff]"
                                    >
                                        Sign in
                                    </Link>
                                </p>
                            </div>
                        </div>
                    </div>

                    <footer className="relative z-10 flex justify-center pb-2 pt-6 text-center text-sm text-slate-400/80">
                        <p>&copy; 2026 HoopSense+. All rights reserved.</p>
                    </footer>
                </div>
            </div>
        </>
    );
}

function Field({ id, label, error, children }: { id: string; label: string; error?: string; children: ReactNode }) {
    return (
        <div className="space-y-2">
            <label htmlFor={id} className="block text-sm font-semibold text-slate-100">
                {label}
            </label>
            {children}
            <FieldError message={error} />
        </div>
    );
}

function FieldError({ message }: { message?: string }) {
    if (!message) return null;

    return <p className="mt-2 text-sm text-[#ffb48f]">{message}</p>;
}

function ChoiceCard({
    selected,
    icon,
    title,
    description,
    onSelect,
}: {
    selected: boolean;
    icon: ReactNode;
    title: string;
    description: string;
    onSelect: () => void;
}) {
    return (
        <button
            type="button"
            aria-pressed={selected}
            onClick={onSelect}
            className={`flex items-start gap-3 rounded-2xl border px-4 py-3 text-left transition ${
                selected
                    ? 'border-[#ff8c42]/60 bg-[#ff8c42]/10 shadow-[0_0_0_1px_rgba(255,140,66,0.2)]'
                    : 'border-white/10 bg-[#0d1428]/90 hover:border-white/20'
            }`}
        >
            <span className={`mt-0.5 ${selected ? 'text-[#ff9d57]' : 'text-slate-400'}`}>{icon}</span>
            <span>
                <span className="block text-sm font-semibold text-white">{title}</span>
                <span className="block text-xs text-slate-400">{description}</span>
            </span>
        </button>
    );
}
