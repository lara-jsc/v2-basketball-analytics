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
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-bold tracking-wide text-foreground uppercase">
                            Dashboard
                        </h1>
                        <p className="mt-0.5 text-sm text-muted-foreground font-ui">
                            Welcome back,{' '}
                            <span className="text-accent font-semibold">{auth.user.name}</span>
                        </p>
                    </div>
                    {/* Live indicator */}
                    <div className="flex items-center gap-2 rounded-full border border-accent/30 bg-accent/10 px-3 py-1.5">
                        <span className="h-2 w-2 rounded-full bg-accent animate-pulse" />
                        <span className="font-ui text-xs font-semibold tracking-widest text-accent uppercase">
                            Pre-Game Mode
                        </span>
                    </div>
                </div>

                {/* ── Summary cards ─────────────────────────────────────── */}
                <div className="grid grid-cols-3 gap-4">
                    <SummaryCard
                        label="Team Win Rate"
                        icon={<Trophy size={16} />}
                        emptyText="No data yet"
                    />
                    <SummaryCard
                        label="Live Plus-Minus"
                        icon={<Zap size={16} />}
                        emptyText="No data yet"
                        accent
                    />
                    <SummaryCard
                        label="Active Teams"
                        icon={<Users2 size={16} />}
                        emptyText="No teams added"
                    />
                </div>

                {/* ── Main grid ─────────────────────────────────────────── */}
                <div className="grid grid-cols-5 gap-4 flex-1 min-h-0">
                    {/* Player Impact Analysis — 3 cols */}
                    <div className="col-span-3 flex flex-col rounded-xl border border-border bg-card p-4 gap-3 relative overflow-hidden transition-shadow hover:shadow-[0_0_20px_rgba(249,160,27,0.07)]">
                        {/* Subtle court atmosphere */}
                        <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_bottom,rgba(249,160,27,0.04)_0%,transparent_70%)]" />

                        <div className="flex items-center justify-between relative">
                            <div className="flex items-center gap-2">
                                <BarChart2 size={15} className="text-accent" />
                                <h2 className="font-display text-sm font-semibold tracking-widest text-foreground uppercase">
                                    Player Impact Analysis
                                </h2>
                            </div>
                            <span className="text-[10px] font-ui font-semibold uppercase tracking-widest text-accent/70 border border-accent/20 bg-accent/5 rounded px-2 py-0.5">
                                Plus-Minus
                            </span>
                        </div>

                        {/* Bar chart — amber bars */}
                        <div className="flex flex-1 items-end gap-2 pt-2 min-h-0 relative">
                            {PLACEHOLDER_BARS.map((h, i) => (
                                <div key={i} className="flex flex-1 flex-col items-center gap-1.5">
                                    <div
                                        className="w-full rounded-t-sm"
                                        style={{
                                            height: `${h}%`,
                                            maxHeight: '120px',
                                            minHeight: '8px',
                                            background: `linear-gradient(to top, rgba(249,160,27,0.5), rgba(249,160,27,0.15))`,
                                            borderTop: '1px solid rgba(249,160,27,0.4)',
                                        }}
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
                    <div className="col-span-2 flex flex-col rounded-xl border border-border bg-card p-4 gap-3 relative overflow-hidden transition-shadow hover:shadow-[0_0_20px_rgba(249,160,27,0.07)]">
                        <div className="flex items-center gap-2">
                            <TrendingUp size={15} className="text-accent" />
                            <h2 className="font-display text-sm font-semibold tracking-widest text-foreground uppercase">
                                Top Players Plus-Minus
                            </h2>
                        </div>

                        <div className="flex flex-col gap-2.5 flex-1">
                            {PLACEHOLDER_PLAYERS.map((p, i) => (
                                <PlaceholderLeaderRow key={i} width={p} rank={i + 1} value={PLACEHOLDER_VALUES[i]} />
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

                {/* ── Recommended Lineup strip ───────────────────────────── */}
                <div className="flex rounded-xl border border-border bg-card overflow-hidden relative">
                    {/* Court atmosphere gradient */}
                    <div className="pointer-events-none absolute inset-0 bg-gradient-to-r from-primary/10 via-transparent to-accent/5" />

                    {/* Lineup slots */}
                    <div className="flex flex-1 gap-3 p-4 relative">
                        <div className="flex items-center gap-2 mr-2 shrink-0">
                            <Swords size={15} className="text-accent shrink-0" />
                            <h2 className="font-display text-sm font-semibold tracking-widest text-foreground uppercase whitespace-nowrap">
                                Recommended Lineup
                            </h2>
                        </div>
                        <div className="flex flex-1 gap-2">
                            {[1, 2, 3, 4, 5].map((n) => (
                                <div
                                    key={n}
                                    className="flex-1 rounded-lg border border-dashed border-border/60 bg-muted/10 flex flex-col items-center justify-center py-3 gap-1.5 transition-colors hover:border-accent/20 hover:bg-accent/5"
                                >
                                    {/* Avatar placeholder */}
                                    <div className="h-8 w-8 rounded-full bg-muted/40 border border-border flex items-center justify-center">
                                        <PlayerSilhouette />
                                    </div>
                                    <div className="h-2 w-10 rounded bg-muted/30" />
                                    <div className="h-1.5 w-6 rounded bg-muted/20" />
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Net value + CTA */}
                    <div className="flex flex-col items-center justify-center gap-2 border-l border-border bg-muted/5 px-6 relative">
                        <span className="font-display text-3xl font-bold text-muted/40">—</span>
                        <span className="text-[10px] font-ui font-semibold uppercase tracking-widest text-muted-foreground text-center whitespace-nowrap">
                            Net Plus-Minus
                        </span>
                        <Link
                            href={route('comparison.index')}
                            className="mt-1 flex items-center gap-1.5 rounded-lg bg-primary px-4 py-1.5 text-xs font-ui font-semibold tracking-wide text-primary-foreground transition-all hover:opacity-90 hover:shadow-[0_0_12px_rgba(152,0,46,0.4)] whitespace-nowrap"
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
    emptyText,
    accent = false,
}: {
    label: string;
    icon: React.ReactNode;
    emptyText?: string;
    accent?: boolean;
}) {
    return (
        <div
            className={[
                'relative flex flex-col gap-3 rounded-xl border bg-card p-4 transition-all hover:shadow-[0_0_16px_rgba(249,160,27,0.08)]',
                accent ? 'border-accent/40 shadow-[0_0_12px_rgba(249,160,27,0.06)]' : 'border-border',
            ].join(' ')}
        >
            {accent && (
                <div className="pointer-events-none absolute inset-0 rounded-xl bg-[radial-gradient(ellipse_at_top_right,rgba(249,160,27,0.06),transparent_70%)]" />
            )}
            <div className="flex items-center justify-between">
                <span className="text-[10px] font-ui font-semibold uppercase tracking-widest text-muted-foreground">
                    {label}
                </span>
                <span className={accent ? 'text-accent/70' : 'text-muted-foreground/60'}>{icon}</span>
            </div>
            <div className="flex flex-col gap-1">
                <div className={`font-display text-3xl font-bold ${accent ? 'text-accent/40' : 'text-muted/40'}`}>—</div>
                <span className="text-xs text-muted-foreground font-ui">{emptyText}</span>
            </div>
        </div>
    );
}

function PlaceholderLeaderRow({ width, rank, value }: { width: number; rank: number; value: string }) {
    return (
        <div className="flex items-center gap-2">
            <span className="w-4 shrink-0 text-right text-[10px] font-ui font-bold text-muted-foreground/50">
                {rank}
            </span>
            <div className="h-2 w-16 rounded bg-muted/30 shrink-0" />
            <div className="flex-1 h-2.5 rounded-full bg-muted/15 overflow-hidden">
                <div
                    className="h-full rounded-full"
                    style={{
                        width: `${width}%`,
                        background: 'linear-gradient(to right, rgba(249,160,27,0.5), rgba(249,160,27,0.2))',
                    }}
                />
            </div>
            <span className="font-display text-xs font-bold text-accent/50 shrink-0 w-8 text-right">
                {value}
            </span>
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
        <div className="flex flex-col items-center justify-center gap-2 py-1">
            <span className="text-muted-foreground/30">{icon}</span>
            <p className="text-xs text-muted-foreground text-center font-ui">{message}</p>
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
            className="group flex flex-col gap-3 rounded-xl border border-border bg-card p-4 transition-all hover:border-accent/30 hover:shadow-[0_0_16px_rgba(249,160,27,0.08)]"
        >
            <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary transition-colors group-hover:bg-primary/20">
                {icon}
            </div>
            <div>
                <h3 className="font-display text-sm font-semibold tracking-wide text-foreground uppercase">
                    {title}
                </h3>
                <p className="mt-0.5 text-xs text-muted-foreground font-ui">{description}</p>
            </div>
            <div className="flex items-center gap-1 text-[11px] font-ui font-semibold text-accent opacity-0 transition-opacity group-hover:opacity-100">
                Open <ChevronRight size={11} />
            </div>
        </Link>
    );
}

function PlayerSilhouette() {
    return (
        <svg viewBox="0 0 24 24" fill="none" className="h-4 w-4 text-muted-foreground/30" stroke="currentColor" strokeWidth={1.5}>
            <circle cx="12" cy="7" r="3" />
            <path d="M5 21v-2a7 7 0 0 1 14 0v2" strokeLinecap="round" />
        </svg>
    );
}

// ── Placeholder data ──────────────────────────────────────────────────────────

const PLACEHOLDER_BARS = [55, 80, 35, 65, 90, 45, 70, 40, 60, 75];
const PLACEHOLDER_PLAYERS = [92, 78, 65, 54, 42];
const PLACEHOLDER_VALUES = ['+14', '+11', '+9', '+8', '+6'];
