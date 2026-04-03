import { type PlayerMatchupResult, type PlayerStat, type PlayerWithStats } from '@/types';
import { Loader2 } from 'lucide-react';

interface PlayerMatchupTableProps {
  playerA: PlayerWithStats;
  playerB: PlayerWithStats;
  matchup: PlayerMatchupResult | null;
  isPending: boolean;
}

interface StatRow {
  label: string;
  getValue: (s: PlayerStat) => string;
  dbKey: string;
  higherIsBetter: boolean;
}

const STAT_ROWS: StatRow[] = [
  { label: '+/-',      getValue: (s) => fmtPM(s.plus_minus),         dbKey: 'plus_minus',         higherIsBetter: true },
  { label: 'PTS',      getValue: (s) => fmt(s.pts),                   dbKey: 'pts',                higherIsBetter: true },
  { label: 'REB',      getValue: (s) => fmt(s.reb),                   dbKey: 'reb',                higherIsBetter: true },
  { label: 'AST',      getValue: (s) => fmt(s.ast),                   dbKey: 'ast',                higherIsBetter: true },
  { label: 'BLK',      getValue: (s) => fmt(s.blk),                   dbKey: 'blk',                higherIsBetter: true },
  { label: 'STL',      getValue: (s) => fmt(s.stl),                   dbKey: 'stl',                higherIsBetter: true },
  { label: 'TO',       getValue: (s) => fmt(s.to_per_game),           dbKey: 'to_per_game',        higherIsBetter: false },
  { label: 'MIN',      getValue: (s) => fmt(s.min),                   dbKey: 'min',                higherIsBetter: true },
  { label: 'FG%',      getValue: (s) => fmt(s.fg_pct),                dbKey: 'fg_pct',             higherIsBetter: true },
  { label: '3P%',      getValue: (s) => fmt(s.three_p_pct),           dbKey: 'three_p_pct',        higherIsBetter: true },
  { label: 'FT%',      getValue: (s) => fmt(s.ft_pct),                dbKey: 'ft_pct',             higherIsBetter: true },
  { label: 'DR',       getValue: (s) => fmt(s.dr),                    dbKey: 'dr',                 higherIsBetter: true },
  { label: 'OR',       getValue: (s) => fmt(s.offensive_rebounds),    dbKey: 'offensive_rebounds', higherIsBetter: true },
  { label: 'AST/TO',   getValue: (s) => fmt(s.ast_to),                dbKey: 'ast_to',             higherIsBetter: true },
  { label: 'STL/TO',   getValue: (s) => fmt(s.stl_to),                dbKey: 'stl_to',             higherIsBetter: true },
  { label: 'SC-EFF',   getValue: (s) => fmt(s.sc_eff),                dbKey: 'sc_eff',             higherIsBetter: true },
  { label: 'SH-EFF',   getValue: (s) => fmt(s.sh_eff),                dbKey: 'sh_eff',             higherIsBetter: true },
  { label: 'PF',       getValue: (s) => fmt(s.pf),                    dbKey: 'pf',                 higherIsBetter: false },
  { label: 'GP',       getValue: (s) => (s.gp ?? '—').toString(),     dbKey: 'gp',                 higherIsBetter: true },
];

/**
 * Side-by-side player stat comparison table.
 * Highlights the stronger value per row with accent color (#F9A01B) per spec.
 * Spec: plus_minus is the first dedicated comparison row.
 */
export function PlayerMatchupTable({ playerA, playerB, matchup, isPending }: PlayerMatchupTableProps) {
  const statA = playerA.stats[0] ?? null;
  const statB = playerB.stats[0] ?? null;

  const strongerA = matchup?.stronger_stats_a ?? [];
  const strongerB = matchup?.stronger_stats_b ?? [];

  return (
    <div className="space-y-4">
      {/* Edge score summary */}
      {isPending ? (
        <div className="flex items-center gap-2 rounded-xl border border-border bg-card px-5 py-3 text-sm text-muted-foreground">
          <Loader2 size={13} className="animate-spin" />
          Computing matchup prediction…
        </div>
      ) : matchup ? (
        <div className="rounded-xl border border-border bg-card px-5 py-4">
          <p className="text-xs uppercase tracking-widest text-muted-foreground mb-3">
            Matchup Edge Scores
          </p>
          <div className="flex items-center gap-4">
            <EdgeBar
              label={`${playerA.first_name} ${playerA.last_name}`}
              score={matchup.player_a_edge_score}
              isHigher={matchup.player_a_edge_score >= matchup.player_b_edge_score}
            />
            <span className="text-muted-foreground text-xs shrink-0">vs</span>
            <EdgeBar
              label={`${playerB.first_name} ${playerB.last_name}`}
              score={matchup.player_b_edge_score}
              isHigher={matchup.player_b_edge_score >= matchup.player_a_edge_score}
              reversed
            />
          </div>
        </div>
      ) : null}

      {/* Stat comparison table */}
      <div className="overflow-hidden rounded-xl border border-border bg-card">
        <div className="grid grid-cols-3 border-b border-border bg-muted/50 px-4 py-2.5 text-xs font-semibold">
          <span>{playerA.first_name} {playerA.last_name}</span>
          <span className="text-center text-muted-foreground uppercase tracking-widest">Stat</span>
          <span className="text-right">{playerB.first_name} {playerB.last_name}</span>
        </div>

        {STAT_ROWS.map((row) => {
          const aStr = statA ? row.getValue(statA) : '—';
          const bStr = statB ? row.getValue(statB) : '—';
          const aHighlighted = strongerA.includes(row.dbKey);
          const bHighlighted = strongerB.includes(row.dbKey);

          return (
            <div
              key={row.label}
              className="grid grid-cols-3 border-b border-border px-4 py-2 text-sm last:border-0"
            >
              <span className={`font-medium tabular-nums ${aHighlighted ? 'text-accent' : 'text-foreground'}`}>
                {aStr}
              </span>
              <span className="text-center text-xs text-muted-foreground">{row.label}</span>
              <span className={`text-right font-medium tabular-nums ${bHighlighted ? 'text-accent' : 'text-foreground'}`}>
                {bStr}
              </span>
            </div>
          );
        })}
      </div>
    </div>
  );
}

// ── Internal helpers ─────────────────────────────────────────────────────────

function EdgeBar({ label, score, isHigher, reversed = false }: {
  label: string;
  score: number;
  isHigher: boolean;
  reversed?: boolean;
}) {
  const pct = Math.round(score * 100);
  return (
    <div className={`flex flex-1 flex-col gap-1 ${reversed ? 'items-end' : 'items-start'}`}>
      <span className="text-xs font-medium text-foreground truncate max-w-[120px]">{label}</span>
      <div className={`flex w-full items-center gap-2 ${reversed ? 'flex-row-reverse' : ''}`}>
        <div className="relative h-2 flex-1 overflow-hidden rounded-full bg-muted">
          <div
            className={`absolute inset-y-0 ${reversed ? 'right-0' : 'left-0'} rounded-full transition-all ${isHigher ? 'bg-accent' : 'bg-primary/40'}`}
            style={{ width: `${pct}%` }}
          />
        </div>
        <span className={`text-xs font-semibold tabular-nums shrink-0 ${isHigher ? 'text-accent' : 'text-muted-foreground'}`}>
          {pct}%
        </span>
      </div>
    </div>
  );
}

function fmt(value: number | null | undefined): string {
  if (value === null || value === undefined) return '—';
  return value.toString();
}

function fmtPM(value: number | null): string {
  if (value === null) return '—';
  return value >= 0 ? `+${value.toFixed(1)}` : `${value.toFixed(1)}`;
}
