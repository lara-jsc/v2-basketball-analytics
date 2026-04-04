import { type Team, type TeamAggregateStats } from '@/types';

interface TeamStatsPanelProps {
    teamA: Team;
    teamB: Team;
    statsA: TeamAggregateStats;
    statsB: TeamAggregateStats;
    plusMinusA: number | null;
    plusMinusB: number | null;
}

interface StatRow {
    label: string;
    keyA: keyof TeamAggregateStats;
    keyB: keyof TeamAggregateStats;
    higherIsBetter: boolean;
}

const STAT_ROWS: StatRow[] = [
    { label: 'Points (avg)',    keyA: 'avg_pts',         keyB: 'avg_pts',         higherIsBetter: true },
    { label: 'Rebounds (avg)',  keyA: 'avg_reb',         keyB: 'avg_reb',         higherIsBetter: true },
    { label: 'Assists (avg)',   keyA: 'avg_ast',         keyB: 'avg_ast',         higherIsBetter: true },
    { label: 'FG% (avg)',       keyA: 'avg_fg_pct',      keyB: 'avg_fg_pct',      higherIsBetter: true },
    { label: 'Blocks (avg)',    keyA: 'avg_blk',         keyB: 'avg_blk',         higherIsBetter: true },
    { label: 'Steals (avg)',    keyA: 'avg_stl',         keyB: 'avg_stl',         higherIsBetter: true },
    { label: 'Turnovers (avg)', keyA: 'avg_to_per_game', keyB: 'avg_to_per_game', higherIsBetter: false },
];

/**
 * Side-by-side aggregate stat comparison — arena-styled.
 * Highlights stronger value in amber per spec.
 */
export function TeamStatsPanel({
    teamA, teamB, statsA, statsB, plusMinusA, plusMinusB,
}: TeamStatsPanelProps) {
    return (
        <div className="overflow-hidden rounded-xl border border-border bg-card">
            {/* Team header row */}
            <div className="grid grid-cols-3 border-b border-border bg-gradient-to-r from-primary/15 via-muted/30 to-primary/15 px-4 py-3">
                <span className="font-display text-sm font-bold tracking-wide text-foreground uppercase truncate">
                    {teamA.name}
                </span>
                <span className="text-center font-ui text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">
                    Stat
                </span>
                <span className="text-right font-display text-sm font-bold tracking-wide text-foreground uppercase truncate">
                    {teamB.name}
                </span>
            </div>

            {/* Plus-Minus row */}
            <PlusMinusRow labelA={plusMinusA} labelB={plusMinusB} />

            {/* Aggregate stat rows */}
            {STAT_ROWS.map((row, i) => {
                const valA = statsA[row.keyA];
                const valB = statsB[row.keyB];
                const aWins = row.higherIsBetter ? valA > valB : valA < valB;
                const bWins = row.higherIsBetter ? valB > valA : valB < valA;

                return (
                    <div
                        key={row.label}
                        className={[
                            'grid grid-cols-3 border-b border-border/50 px-4 py-2.5 text-sm last:border-0 transition-colors hover:bg-muted/5',
                            i % 2 === 1 ? 'bg-primary/[0.02]' : '',
                        ].join(' ')}
                    >
                        <span className={[
                            'font-mono tabular-nums font-semibold',
                            aWins ? 'text-accent' : 'text-foreground/80',
                        ].join(' ')}>
                            {valA.toFixed(1)}
                            {aWins && <span className="ml-1.5 inline-flex h-4 w-4 items-center justify-center rounded bg-accent/15 text-[9px] text-accent font-bold">▲</span>}
                        </span>
                        <span className="text-center font-ui text-xs text-muted-foreground">{row.label}</span>
                        <span className={[
                            'text-right font-mono tabular-nums font-semibold',
                            bWins ? 'text-accent' : 'text-foreground/80',
                        ].join(' ')}>
                            {bWins && <span className="mr-1.5 inline-flex h-4 w-4 items-center justify-center rounded bg-accent/15 text-[9px] text-accent font-bold">▲</span>}
                            {valB.toFixed(1)}
                        </span>
                    </div>
                );
            })}
        </div>
    );
}

// ── Plus-Minus special row ────────────────────────────────────────────────────

function PlusMinusRow({ labelA, labelB }: { labelA: number | null; labelB: number | null }) {
    const aWins = labelA !== null && labelB !== null && labelA > labelB;
    const bWins = labelA !== null && labelB !== null && labelB > labelA;

    function fmtPM(val: number | null): string {
        if (val === null) return '—';
        return val >= 0 ? `+${val.toFixed(1)}` : `${val.toFixed(1)}`;
    }

    return (
        <div className="grid grid-cols-3 border-b border-border bg-accent/5 px-4 py-2.5 text-sm">
            <span className={`font-mono font-bold tabular-nums ${aWins ? 'text-accent drop-shadow-[0_0_4px_rgba(249,160,27,0.5)]' : 'text-foreground/80'}`}>
                {fmtPM(labelA)}
            </span>
            <span className="text-center font-ui text-[10px] font-bold uppercase tracking-widest text-accent/70">
                Team +/-
            </span>
            <span className={`text-right font-mono font-bold tabular-nums ${bWins ? 'text-accent drop-shadow-[0_0_4px_rgba(249,160,27,0.5)]' : 'text-foreground/80'}`}>
                {fmtPM(labelB)}
            </span>
        </div>
    );
}
