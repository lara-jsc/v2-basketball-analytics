import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { type PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import {
    BarChart2,
    ChevronRight,
    Swords,
    TrendingUp,
    Trophy,
    Upload,
    Users2,
    Zap,
} from 'lucide-react';

export default function Dashboard({ auth }: PageProps) {
    return (
        <AuthenticatedLayout>
            <Head title="Dashboard" />

            <div className="flex flex-col gap-5 h-full">
                {/* ── Page heading ──────────────────────────────────────── */}
                <div>
                    <h1 className="font-display text-2xl font-bold tracking-wide text-foreground">
                        Dashboard
                    </h1>
                    <p className="mt-0.5 text-sm text-muted-foreground">
                        Welcome back, <span className="text-foreground font-medium">{auth.user.name}</span>
                    </p>
                </div>

                {/* ── Summary cards ─────────────────────────────────────── */}
                <div className="grid grid-cols-3 gap-4">
                    <SummaryCard
                        label="Team Win Rate"
                        icon={<Trophy size={16} />}
                        empty
                        emptyText="No data yet"
                    />
                    <SummaryCard
                        label="Live Plus-Minus"
                        icon={<Zap size={16} />}
                        empty
                        emptyText="No data yet"
                        accentBorder
                    />
                    <SummaryCard
                        label="Active Teams"
                        icon={<Users2 size={16} />}
                        empty
                        emptyText="No teams added"
                    />
                </div>

                {/* ── Main grid ─────────────────────────────────────────── */}
                <div className="grid grid-cols-5 gap-4 flex-1 min-h-0">
                    {/* Player Impact Analysis — 3 cols */}
                    <div className="col-span-3 flex flex-col rounded-xl border border-border bg-card p-4 gap-3">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <BarChart2 size={15} className="text-accent" />
                                <h2 className="font-display text-sm font-semibold tracking-wide text-foreground uppercase">
                                    Player Impact Analysis
                                </h2>
                            </div>
                            <span className="text-[10px] font-ui font-medium uppercase tracking-widest text-muted-foreground">
                                Plus-Minus
                            </span>
                        </div>

                        {/* Empty state bars */}
                        <div className="flex flex-1 items-end gap-2 pt-2 min-h-0">
                            {PLACEHOLDER_BARS.map((h, i) => (
                                <div key={i} className="flex flex-1 flex-col items-center gap-1.5">
                                    <div
                                        className="w-full rounded-t-sm bg-muted/40"
                                        style={{ height: `${h}%`, maxHeight: '120px', minHeight: '8px' }}
                                    />
                                    <div className="h-2 w-6 rounded bg-muted/30" />
                                </div>
                            ))}
                        </div>

                        <EmptyOverlay
                            icon={<Upload size={18} />}
                            message="Upload a roster to see player impact"
                            cta="Go to Teams & Players"
                            href={route('teams.index')}
                        />
                    </div>

                    {/* Top Players Leaderboard — 2 cols */}
                    <div className="col-span-2 flex flex-col rounded-xl border border-border bg-card p-4 gap-3">
                        <div className="flex items-center gap-2">
                            <TrendingUp size={15} className="text-accent" />
                            <h2 className="font-display text-sm font-semibold tracking-wide text-foreground uppercase">
                                Top Players Plus-Minus
                            </h2>
                        </div>

                        <div className="flex flex-col gap-2 flex-1">
                            {PLACEHOLDER_PLAYERS.map((p, i) => (
                                <PlaceholderLeaderRow key={i} width={p} rank={i + 1} />
                            ))}
                        </div>

                        <EmptyOverlay
                            icon={<Upload size={18} />}
                            message="Upload a roster to see rankings"
                            cta="Go to Teams & Players"
                            href={route('teams.index')}
                        />
                    </div>
                </div>

                {/* ── Recommended Lineup & AI Insights ──────────────────── */}
                <div className="flex rounded-xl border border-border bg-card overflow-hidden">
                    {/* Lineup slots */}
                    <div className="flex flex-1 gap-3 p-4">
                        <div className="flex items-center gap-2 mr-2">
                            <Swords size={15} className="text-accent shrink-0" />
                            <h2 className="font-display text-sm font-semibold tracking-wide text-foreground uppercase whitespace-nowrap">
                                Recommended Lineup
                            </h2>
                        </div>
                        <div className="flex flex-1 gap-2">
                            {[1, 2, 3, 4, 5].map((n) => (
                                <div
                                    key={n}
                                    className="flex-1 rounded-lg border border-dashed border-border bg-muted/20 flex flex-col items-center justify-center py-3 gap-1"
                                >
                                    <div className="h-7 w-7 rounded-full bg-muted/40" />
                                    <div className="h-2 w-10 rounded bg-muted/30" />
                                    <div className="h-1.5 w-6 rounded bg-muted/20" />
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Net value + CTA */}
                    <div className="flex flex-col items-center justify-center gap-2 border-l border-border bg-muted/10 px-6">
                        <span className="font-display text-3xl font-bold text-muted/40">—</span>
                        <span className="text-[10px] font-ui font-medium uppercase tracking-widest text-muted-foreground text-center whitespace-nowrap">
                            Net Plus-Minus
                        </span>
                        <Link
                            href={route('comparison.index')}
                            className="mt-1 flex items-center gap-1.5 rounded-lg bg-primary px-4 py-1.5 text-xs font-ui font-semibold tracking-wide text-primary-foreground transition-opacity hover:opacity-90 whitespace-nowrap"
                        >
                            Run Comparison
                            <ChevronRight size={12} />
                        </Link>
                    </div>
                </div>

                {/* ── Quick-access feature cards ────────────────────────── */}
                <div className="grid grid-cols-3 gap-4">
                    <QuickCard
                        href={route('teams.index')}
                        icon={<Users2 size={18} />}
                        title="Teams & Players"
                        description="Create teams and upload rosters via CSV."
                    />
                    <QuickCard
                        href={route('comparison.index')}
                        icon={<Swords size={18} />}
                        title="Team Comparison"
                        description="Win probability, win rate, and lineup recommendations."
                    />
                    <QuickCard
                        href={route('comparison.index')}
                        icon={<BarChart2 size={18} />}
                        title="Player Matchup"
                        description="Head-to-head player comparison with edge prediction."
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

// ── Internal components ───────────────────────────────────────────────────────

function SummaryCard({
    label,
    icon,
    empty,
    emptyText,
    accentBorder,
}: {
    label: string;
    icon: React.ReactNode;
    empty?: boolean;
    emptyText?: string;
    accentBorder?: boolean;
}) {
    return (
        <div
            className={[
                'relative flex flex-col gap-3 rounded-xl border bg-card p-4 transition-shadow hover:shadow-[0_0_16px_0_rgba(249,160,27,0.08)]',
                accentBorder ? 'border-accent/30' : 'border-border',
            ].join(' ')}
        >
            <div className="flex items-center justify-between">
                <span className="text-[10px] font-ui font-semibold uppercase tracking-widest text-muted-foreground">
                    {label}
                </span>
                <span className="text-muted-foreground/60">{icon}</span>
            </div>
            {empty ? (
                <div className="flex flex-col gap-1">
                    <div className="font-display text-3xl font-bold text-muted/40">—</div>
                    <span className="text-xs text-muted-foreground">{emptyText}</span>
                </div>
            ) : null}
        </div>
    );
}

function PlaceholderLeaderRow({ width, rank }: { width: number; rank: number }) {
    return (
        <div className="flex items-center gap-2">
            <span className="w-4 shrink-0 text-right text-[10px] font-ui font-semibold text-muted-foreground/40">
                {rank}
            </span>
            <div className="h-2 w-16 rounded bg-muted/30 shrink-0" />
            <div className="flex-1 h-2 rounded-full bg-muted/20 overflow-hidden">
                <div
                    className="h-full rounded-full bg-accent/20"
                    style={{ width: `${width}%` }}
                />
            </div>
            <div className="h-2 w-6 rounded bg-muted/30 shrink-0" />
        </div>
    );
}

function EmptyOverlay({
    icon,
    message,
    cta,
    href,
}: {
    icon: React.ReactNode;
    message: string;
    cta: string;
    href: string;
}) {
    return (
        <div className="flex flex-col items-center justify-center gap-2 py-2">
            <span className="text-muted-foreground/40">{icon}</span>
            <p className="text-xs text-muted-foreground text-center">{message}</p>
            <Link
                href={href}
                className="flex items-center gap-1 text-xs font-ui font-semibold text-accent hover:underline"
            >
                {cta} <ChevronRight size={11} />
            </Link>
        </div>
    );
}

function QuickCard({
    href,
    icon,
    title,
    description,
}: {
    href: string;
    icon: React.ReactNode;
    title: string;
    description: string;
}) {
    return (
        <Link
            href={href}
            className="group flex flex-col gap-3 rounded-xl border border-border bg-card p-4 transition-all hover:border-primary/40 hover:shadow-[0_0_16px_0_rgba(152,0,46,0.08)]"
        >
            <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary transition-colors group-hover:bg-primary/20">
                {icon}
            </div>
            <div>
                <h3 className="font-display text-sm font-semibold tracking-wide text-foreground">
                    {title}
                </h3>
                <p className="mt-0.5 text-xs text-muted-foreground">{description}</p>
            </div>
            <div className="flex items-center gap-1 text-[11px] font-ui font-semibold text-primary opacity-0 transition-opacity group-hover:opacity-100">
                Open <ChevronRight size={11} />
            </div>
        </Link>
    );
}

// ── Placeholder data ──────────────────────────────────────────────────────────

/** Bar heights (%) for the impact chart placeholder */
const PLACEHOLDER_BARS = [55, 80, 35, 65, 90, 45, 70, 40, 60, 75];

/** Leaderboard bar fill widths (%) for placeholder rows */
const PLACEHOLDER_PLAYERS = [92, 78, 65, 54, 42];
