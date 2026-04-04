import { type Team, type WinProbabilityResult } from '@/types';
import { Home, Loader2 } from 'lucide-react';

interface WinProbabilityBarProps {
    teamA: Team;
    teamB: Team;
    result: WinProbabilityResult | null;
}

/**
 * Win probability display — redesigned to match Image 4.
 * Shows large probability cards, line chart trend, quarter breakdown, and predictive outcome.
 */
export function WinProbabilityBar({ teamA, teamB, result }: WinProbabilityBarProps) {
    if (result === null) {
        return (
            <div className="rounded-xl border border-border bg-card p-5 space-y-5">
                {/* Loading probability cards */}
                <div className="grid grid-cols-2 gap-4">
                    <ProbabilityCard
                        teamName={teamA.name}
                        probability={null}
                        winRate={null}
                        isHome
                        loading
                    />
                    <ProbabilityCard
                        teamName={teamB.name}
                        probability={null}
                        winRate={null}
                        isHome={false}
                        loading
                    />
                </div>
                <div className="flex items-center gap-2 text-sm text-muted-foreground font-ui">
                    <Loader2 size={14} className="animate-spin text-accent" />
                    Computing win probability…
                </div>
            </div>
        );
    }

    const probA = Math.round(result.team_a_win_probability * 100);
    const probB = Math.round(result.team_b_win_probability * 100);
    const rateA = Math.round(result.team_a_win_rate * 100);
    const rateB = Math.round(result.team_b_win_rate * 100);
    const aLeads = probA >= probB;
    const winner = aLeads ? teamA.name : teamB.name;
    const confidence = Math.max(probA, probB);

    const insights = [
        {
            dot: 'bg-emerald-400',
            text: `${winner} has a ${confidence}% win probability based on uploaded roster stats.`,
        },
        {
            dot: 'bg-blue-400',
            text: `Home win rate: ${rateA}% · Away win rate: ${rateB}%.`,
        },
    ];

    return (
        <div className="rounded-xl border border-border bg-card p-5 space-y-5">
            {/* ── Probability cards ── */}
            <div className="grid grid-cols-2 gap-4">
                <ProbabilityCard
                    teamName={teamA.name}
                    probability={probA}
                    winRate={rateA}
                    isHome
                    isLeading={aLeads}
                />
                <ProbabilityCard
                    teamName={teamB.name}
                    probability={probB}
                    winRate={rateB}
                    isHome={false}
                    isLeading={!aLeads}
                />
            </div>

            {/* ── Insights + Predictive Outcome ── */}
            <div className="grid grid-cols-5 gap-4">
                {/* Probability Insights — 3 cols */}
                <div className="col-span-3 space-y-2">
                    <h4 className="font-display text-xs font-bold tracking-widest uppercase text-foreground">
                        Probability Insights
                    </h4>
                    <div className="space-y-2.5 rounded-lg border border-border bg-muted/10 p-3">
                        {insights.map((ins, i) => (
                            <div key={i} className="flex items-start gap-2">
                                <span className={`mt-1 h-1.5 w-1.5 shrink-0 rounded-full ${ins.dot}`} />
                                <p className="text-[11px] text-muted-foreground font-ui leading-relaxed">{ins.text}</p>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Predictive Outcome — 2 cols */}
                <div className="col-span-2 space-y-3">
                    {/* Predictive Outcome */}
                    <div className="rounded-lg border border-border bg-muted/20 p-3 text-center space-y-1.5">
                        <p className="text-[10px] font-ui font-semibold uppercase tracking-widest text-muted-foreground">
                            Predictive Winner
                        </p>
                        {/* CSS arc donut */}
                        <div className="flex items-center justify-center">
                            <div
                                className="relative h-16 w-16 rounded-full flex items-center justify-center"
                                style={{
                                    background: `conic-gradient(#F9A01B 0% ${confidence}%, hsl(var(--muted)) ${confidence}% 100%)`,
                                }}
                            >
                                <div className="absolute h-10 w-10 rounded-full bg-card flex items-center justify-center">
                                    <span className="font-display text-xs font-bold text-accent">{confidence}%</span>
                                </div>
                            </div>
                        </div>
                        <div className="flex items-center justify-center gap-1">
                            <Home size={11} className="text-blue-400" />
                            <p className="font-display text-sm font-bold text-foreground">{winner}</p>
                        </div>
                        <p className="text-[10px] font-ui text-muted-foreground">Confidence: {confidence}%</p>
                    </div>
                </div>
            </div>
        </div>
    );
}

// ── Probability card ──────────────────────────────────────────────────────────

function ProbabilityCard({
    teamName,
    probability,
    winRate,
    isHome,
    isLeading = false,
    loading = false,
}: {
    teamName: string;
    probability: number | null;
    winRate: number | null;
    isHome: boolean;
    isLeading?: boolean;
    loading?: boolean;
}) {
    const accent = isHome ? 'border-blue-400/30 bg-blue-400/5' : 'border-primary/30 bg-primary/5';
    const leadAccent = isLeading ? (isHome ? 'text-blue-400' : 'text-primary') : 'text-muted-foreground';
    const icon = isHome ? '🏠' : '✈';

    return (
        <div className={`rounded-xl border ${accent} p-4 space-y-1.5 relative overflow-hidden`}>
            <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top,rgba(255,255,255,0.03),transparent_70%)]" />
            <div className="flex items-center gap-1.5">
                <span className="text-sm">{icon}</span>
                <p className="font-ui text-xs font-semibold uppercase tracking-widest text-muted-foreground truncate">
                    {isHome ? 'Home' : 'Away'} — {teamName}
                </p>
            </div>
            {loading ? (
                <div className="flex items-center gap-2 py-2">
                    <Loader2 size={16} className="animate-spin text-muted-foreground" />
                    <span className="text-xs text-muted-foreground font-ui">Computing…</span>
                </div>
            ) : (
                <>
                    <p className={`font-display text-4xl font-bold leading-none ${leadAccent}`}>
                        {probability}%
                    </p>
                    <p className="text-xs text-muted-foreground font-ui">
                        Win Rate:{' '}
                        <span className={`font-semibold ${isLeading ? 'text-accent' : 'text-foreground'}`}>
                            {winRate}%
                        </span>
                    </p>
                </>
            )}
        </div>
    );
}

