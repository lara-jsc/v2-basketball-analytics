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
 * Side-by-side aggregate stat comparison for two teams.
 * Highlights the stronger value in accent color (#F9A01B) per spec.
 */
export function TeamStatsPanel({
  teamA, teamB, statsA, statsB, plusMinusA, plusMinusB,
}: TeamStatsPanelProps) {
  return (
    <div className="overflow-hidden rounded-xl border border-border bg-card">
      {/* Team header row */}
      <div className="grid grid-cols-3 border-b border-border bg-muted/50 px-4 py-3 text-sm font-semibold">
        <span className="text-foreground">{teamA.name}</span>
        <span className="text-center text-xs uppercase tracking-widest text-muted-foreground">Stat</span>
        <span className="text-right text-foreground">{teamB.name}</span>
      </div>

      {/* Plus-Minus row — special: computed from stored values, not aggregate */}
      <PlusMinusRow labelA={plusMinusA} labelB={plusMinusB} />

      {/* Aggregate stat rows */}
      {STAT_ROWS.map((row) => {
        const valA = statsA[row.keyA];
        const valB = statsB[row.keyB];
        const aWins = row.higherIsBetter ? valA > valB : valA < valB;
        const bWins = row.higherIsBetter ? valB > valA : valB < valA;
        return (
          <div key={row.label} className="grid grid-cols-3 border-b border-border px-4 py-2.5 text-sm last:border-0">
            <span className={`font-medium tabular-nums ${aWins ? 'text-accent' : 'text-foreground'}`}>
              {valA.toFixed(1)}
            </span>
            <span className="text-center text-xs text-muted-foreground">{row.label}</span>
            <span className={`text-right font-medium tabular-nums ${bWins ? 'text-accent' : 'text-foreground'}`}>
              {valB.toFixed(1)}
            </span>
          </div>
        );
      })}
    </div>
  );
}

function PlusMinusRow({ labelA, labelB }: { labelA: number | null; labelB: number | null }) {
  const aWins = labelA !== null && labelB !== null && labelA > labelB;
  const bWins = labelA !== null && labelB !== null && labelB > labelA;

  function fmtPM(val: number | null): string {
    if (val === null) return '—';
    return val >= 0 ? `+${val.toFixed(1)}` : `${val.toFixed(1)}`;
  }

  return (
    <div className="grid grid-cols-3 border-b border-border bg-primary/5 px-4 py-2.5 text-sm">
      <span className={`font-semibold tabular-nums ${aWins ? 'text-accent' : 'text-foreground'}`}>
        {fmtPM(labelA)}
      </span>
      <span className="text-center text-xs font-medium text-muted-foreground">Team +/-</span>
      <span className={`text-right font-semibold tabular-nums ${bWins ? 'text-accent' : 'text-foreground'}`}>
        {fmtPM(labelB)}
      </span>
    </div>
  );
}
