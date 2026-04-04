import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { type PageProps, type Team } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { GitCompare, Swords } from 'lucide-react';

interface ComparisonIndexProps extends PageProps {
    teams: Team[];
}

/**
 * Team selector page — choose two active teams to compare.
 */
export default function ComparisonIndex({ teams }: ComparisonIndexProps) {
    const { data, setData, post, processing, errors } = useForm({
        team_a: '',
        team_b: '',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(route('comparison.select'));
    }

    const activeTeams = teams.filter((t) => t.is_active);
    const teamAName = activeTeams.find((t) => String(t.id) === data.team_a)?.name;
    const teamBName = activeTeams.find((t) => String(t.id) === data.team_b)?.name;

    return (
        <AuthenticatedLayout>
            <Head title="Compare Teams" />

            <div className="flex flex-col gap-5">
                {/* Page heading */}
                <div>
                    <h1 className="font-display text-2xl font-bold tracking-wide text-foreground uppercase">
                        Team Comparison
                    </h1>
                    <p className="mt-0.5 text-sm text-muted-foreground font-ui">
                        Select two teams to generate win probability, win rate, and lineup recommendations.
                    </p>
                </div>

                {/* Selector panel */}
                <div className="rounded-xl border border-border bg-card overflow-hidden max-w-xl">
                    {/* Arena header band */}
                    <div className="relative flex items-center gap-3 border-b border-border bg-gradient-to-r from-primary/25 via-primary/10 to-transparent px-5 py-3 overflow-hidden">
                        <div className="pointer-events-none absolute inset-0 bg-[repeating-linear-gradient(90deg,transparent,transparent_48px,rgba(249,160,27,0.03)_48px,rgba(249,160,27,0.03)_49px)]" />
                        <Swords size={16} className="text-accent relative" />
                        <div className="relative">
                            <h3 className="font-display text-sm font-bold tracking-widest text-foreground uppercase">
                                Select Matchup
                            </h3>
                            <p className="text-[11px] text-muted-foreground font-ui">
                                Choose a home team and an opponent
                            </p>
                        </div>
                    </div>

                    <div className="px-5 py-5">
                        {activeTeams.length < 2 ? (
                            <div className="rounded-lg border border-border bg-muted/20 px-4 py-4 text-sm text-muted-foreground font-ui">
                                You need at least two active teams to run a comparison. Go to{' '}
                                <a href={route('teams.index')} className="text-accent hover:underline font-semibold">
                                    Teams &amp; Players
                                </a>{' '}
                                to create teams and upload rosters.
                            </div>
                        ) : (
                            <form onSubmit={submit} className="space-y-4">
                                {/* VS preview — shown when both selected */}
                                {teamAName && teamBName && (
                                    <div className="flex items-center justify-center gap-3 rounded-lg border border-accent/20 bg-accent/5 px-4 py-2.5">
                                        <span className="font-display text-sm font-bold text-foreground truncate max-w-[120px]">
                                            {teamAName}
                                        </span>
                                        <span className="shrink-0 rounded border border-primary/40 bg-primary/10 px-2 py-0.5 font-display text-xs font-bold text-primary tracking-widest uppercase">
                                            VS
                                        </span>
                                        <span className="font-display text-sm font-bold text-foreground truncate max-w-[120px]">
                                            {teamBName}
                                        </span>
                                    </div>
                                )}

                                <TeamSelect
                                    label="Home Team"
                                    id="team_a"
                                    value={data.team_a}
                                    onChange={(v) => setData('team_a', v)}
                                    teams={activeTeams}
                                    exclude={data.team_b}
                                    error={errors.team_a}
                                />

                                <TeamSelect
                                    label="Opponent"
                                    id="team_b"
                                    value={data.team_b}
                                    onChange={(v) => setData('team_b', v)}
                                    teams={activeTeams}
                                    exclude={data.team_a}
                                    error={errors.team_b}
                                />

                                <button
                                    type="submit"
                                    disabled={!data.team_a || !data.team_b || processing}
                                    className="flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-ui font-semibold tracking-wide text-primary-foreground transition-all hover:opacity-90 hover:shadow-[0_0_16px_rgba(152,0,46,0.4)] disabled:opacity-40 disabled:cursor-not-allowed"
                                >
                                    <GitCompare size={15} />
                                    Compare Teams
                                </button>
                            </form>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

// ── Internal helpers ─────────────────────────────────────────────────────────

function TeamSelect({
    label,
    id,
    value,
    onChange,
    teams,
    exclude,
    error,
}: {
    label: string;
    id: string;
    value: string;
    onChange: (v: string) => void;
    teams: Team[];
    exclude: string;
    error?: string;
}) {
    return (
        <div className="space-y-1.5">
            <label htmlFor={id} className="text-xs font-ui font-semibold uppercase tracking-widest text-muted-foreground">
                {label}
            </label>
            <select
                id={id}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className="w-full rounded-lg border border-border bg-background px-3 py-2.5 text-sm text-foreground font-ui transition-colors focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent/40"
            >
                <option value="">Select a team…</option>
                {teams
                    .filter((t) => String(t.id) !== exclude)
                    .map((t) => (
                        <option key={t.id} value={String(t.id)}>
                            {t.name} ({t.code})
                        </option>
                    ))}
            </select>
            {error && <p className="text-xs text-destructive font-ui">{error}</p>}
        </div>
    );
}
