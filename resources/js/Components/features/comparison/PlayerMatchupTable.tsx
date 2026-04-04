import { type PlayerMatchupResult, type PlayerStat, type PlayerWithStats } from '@/types';
import { Loader2 } from 'lucide-react';
import {
    Radar,
    RadarChart,
    PolarGrid,
    PolarAngleAxis,
    ResponsiveContainer,
    Tooltip,
} from 'recharts';

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
    { label: '+/-',    getValue: (s) => fmtPM(s.plus_minus),         dbKey: 'plus_minus',         higherIsBetter: true },
    { label: 'PTS',    getValue: (s) => fmt(s.pts),                   dbKey: 'pts',                higherIsBetter: true },
    { label: 'REB',    getValue: (s) => fmt(s.reb),                   dbKey: 'reb',                higherIsBetter: true },
    { label: 'AST',    getValue: (s) => fmt(s.ast),                   dbKey: 'ast',                higherIsBetter: true },
    { label: 'BLK',    getValue: (s) => fmt(s.blk),                   dbKey: 'blk',                higherIsBetter: true },
    { label: 'STL',    getValue: (s) => fmt(s.stl),                   dbKey: 'stl',                higherIsBetter: true },
    { label: 'TO',     getValue: (s) => fmt(s.to_per_game),           dbKey: 'to_per_game',        higherIsBetter: false },
    { label: 'MIN',    getValue: (s) => fmt(s.min),                   dbKey: 'min',                higherIsBetter: true },
    { label: 'FG%',    getValue: (s) => fmt(s.fg_pct),                dbKey: 'fg_pct',             higherIsBetter: true },
    { label: '3P%',    getValue: (s) => fmt(s.three_p_pct),           dbKey: 'three_p_pct',        higherIsBetter: true },
    { label: 'FT%',    getValue: (s) => fmt(s.ft_pct),                dbKey: 'ft_pct',             higherIsBetter: true },
    { label: 'DR',     getValue: (s) => fmt(s.dr),                    dbKey: 'dr',                 higherIsBetter: true },
    { label: 'OR',     getValue: (s) => fmt(s.offensive_rebounds),    dbKey: 'offensive_rebounds', higherIsBetter: true },
    { label: 'AST/TO', getValue: (s) => fmt(s.ast_to),                dbKey: 'ast_to',             higherIsBetter: true },
    { label: 'STL/TO', getValue: (s) => fmt(s.stl_to),                dbKey: 'stl_to',             higherIsBetter: true },
    { label: 'SC-EFF', getValue: (s) => fmt(s.sc_eff),                dbKey: 'sc_eff',             higherIsBetter: true },
    { label: 'SH-EFF', getValue: (s) => fmt(s.sh_eff),                dbKey: 'sh_eff',             higherIsBetter: true },
    { label: 'PF',     getValue: (s) => fmt(s.pf),                    dbKey: 'pf',                 higherIsBetter: false },
    { label: 'GP',     getValue: (s) => (s.gp ?? '—').toString(),     dbKey: 'gp',                 higherIsBetter: true },
];

/** Radar axes — 6 key stats normalized 0-100 */
const RADAR_KEYS: Array<{ label: string; statKey: keyof PlayerStat; max: number }> = [
    { label: 'SH-EFF', statKey: 'sh_eff',   max: 100 },
    { label: 'AST',    statKey: 'ast',       max: 15  },
    { label: 'DR',     statKey: 'dr',        max: 15  },
    { label: 'DD2',    statKey: 'dd2',       max: 82  },
    { label: 'SC-EFF', statKey: 'sc_eff',    max: 100 },
    { label: 'PTS',    statKey: 'pts',       max: 40  },
];

function normalize(value: number | null | undefined, max: number): number {
    if (value === null || value === undefined) return 0;
    return Math.min(Math.round((value / max) * 100), 100);
}

/**
 * Player matchup comparison — redesigned to match Image 5.
 * Shows player header cards, radar chart, and stat rows with amber highlights.
 */
export function PlayerMatchupTable({ playerA, playerB, matchup, isPending }: PlayerMatchupTableProps) {
    const statA = playerA.stats[0] ?? null;
    const statB = playerB.stats[0] ?? null;

    const strongerA = matchup?.stronger_stats_a ?? [];
    const strongerB = matchup?.stronger_stats_b ?? [];

    const picA = playerA.profile_picture_path ? `/storage/${playerA.profile_picture_path}` : null;
    const picB = playerB.profile_picture_path ? `/storage/${playerB.profile_picture_path}` : null;

    // Build radar data
    const radarData = RADAR_KEYS.map(({ label, statKey, max }) => ({
        stat: label,
        A: normalize(statA?.[statKey] as number | null, max),
        B: normalize(statB?.[statKey] as number | null, max),
    }));

    return (
        <div className="space-y-4">
            {/* ── Player header cards + Radar chart ── */}
            <div className="grid grid-cols-5 gap-3 items-center">
                {/* Player A header card */}
                <div className="col-span-2">
                    <PlayerHeaderCard
                        player={playerA}
                        stat={statA}
                        picUrl={picA}
                        matchup={matchup}
                        side="a"
                    />
                </div>

                {/* Radar chart — center */}
                <div className="col-span-1 flex flex-col items-center gap-1">
                    <div className="h-40 w-full">
                        <ResponsiveContainer width="100%" height="100%">
                            <RadarChart data={radarData} margin={{ top: 4, right: 16, bottom: 4, left: 16 }}>
                                <PolarGrid stroke="rgba(255,255,255,0.08)" />
                                <PolarAngleAxis
                                    dataKey="stat"
                                    tick={{ fontSize: 9, fill: '#F9A01B', fontFamily: 'Rajdhani', fontWeight: 600 }}
                                />
                                <Radar
                                    name={`${playerA.first_name} ${playerA.last_name}`}
                                    dataKey="A"
                                    stroke="#98002E"
                                    fill="#98002E"
                                    fillOpacity={0.3}
                                    strokeWidth={1.5}
                                />
                                <Radar
                                    name={`${playerB.first_name} ${playerB.last_name}`}
                                    dataKey="B"
                                    stroke="#60a5fa"
                                    fill="#60a5fa"
                                    fillOpacity={0.25}
                                    strokeWidth={1.5}
                                />
                                <Tooltip
                                    contentStyle={{
                                        background: 'hsl(var(--popover))',
                                        border: '1px solid hsl(var(--border))',
                                        borderRadius: '8px',
                                        fontSize: '11px',
                                        fontFamily: 'Rajdhani',
                                    }}
                                    formatter={(value, name) => [`${value}`, String(name)]}
                                />
                            </RadarChart>
                        </ResponsiveContainer>
                    </div>
                    {/* Legend */}
                    <div className="flex flex-col items-center gap-1 text-[10px] font-ui">
                        <span className="flex items-center gap-1 text-primary">
                            <span className="h-2 w-2 rounded-full bg-primary inline-block" />
                            {playerA.first_name} {playerA.last_name}
                        </span>
                        <span className="flex items-center gap-1 text-blue-400">
                            <span className="h-2 w-2 rounded-full bg-blue-400 inline-block" />
                            {playerB.first_name} {playerB.last_name}
                        </span>
                    </div>
                </div>

                {/* Player B header card */}
                <div className="col-span-2">
                    <PlayerHeaderCard
                        player={playerB}
                        stat={statB}
                        picUrl={picB}
                        matchup={matchup}
                        side="b"
                    />
                </div>
            </div>

            {/* ── Edge score (pending / computed) ── */}
            {isPending ? (
                <div className="flex items-center gap-2 rounded-xl border border-border bg-card px-5 py-3 text-sm text-muted-foreground font-ui">
                    <Loader2 size={13} className="animate-spin text-accent" />
                    Computing matchup prediction…
                </div>
            ) : matchup ? (
                <div className="rounded-xl border border-border bg-card px-5 py-4">
                    <p className="text-[10px] font-ui font-semibold uppercase tracking-widest text-muted-foreground mb-3">
                        Matchup Edge Scores
                    </p>
                    <div className="flex items-center gap-4">
                        <EdgeBar
                            label={`${playerA.first_name} ${playerA.last_name}`}
                            score={matchup.player_a_edge_score}
                            isHigher={matchup.player_a_edge_score >= matchup.player_b_edge_score}
                        />
                        <span className="text-muted-foreground text-xs shrink-0 font-ui font-bold">VS</span>
                        <EdgeBar
                            label={`${playerB.first_name} ${playerB.last_name}`}
                            score={matchup.player_b_edge_score}
                            isHigher={matchup.player_b_edge_score >= matchup.player_a_edge_score}
                            reversed
                        />
                    </div>
                </div>
            ) : null}

            {/* ── Stat comparison rows ── */}
            <div className="overflow-hidden rounded-xl border border-border bg-card">
                <div className="grid grid-cols-3 border-b border-border bg-gradient-to-r from-primary/15 via-muted/30 to-primary/15 px-4 py-2.5">
                    <span className="font-display text-xs font-bold tracking-wide text-foreground uppercase">
                        {playerA.first_name} {playerA.last_name}
                    </span>
                    <span className="text-center font-ui text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">
                        Stat
                    </span>
                    <span className="text-right font-display text-xs font-bold tracking-wide text-foreground uppercase">
                        {playerB.first_name} {playerB.last_name}
                    </span>
                </div>

                {STAT_ROWS.map((row, i) => {
                    const aStr = statA ? row.getValue(statA) : '—';
                    const bStr = statB ? row.getValue(statB) : '—';
                    const aHighlighted = strongerA.includes(row.dbKey);
                    const bHighlighted = strongerB.includes(row.dbKey);

                    return (
                        <div
                            key={row.label}
                            className={[
                                'grid grid-cols-3 border-b border-border/50 px-4 py-2 text-sm last:border-0 hover:bg-muted/5 transition-colors',
                                i % 2 === 1 ? 'bg-primary/[0.02]' : '',
                            ].join(' ')}
                        >
                            <span className={[
                                'font-mono tabular-nums font-semibold',
                                aHighlighted ? 'text-accent' : 'text-foreground/80',
                            ].join(' ')}>
                                {aStr}
                                {aHighlighted && (
                                    <span className="ml-1.5 inline-flex items-center justify-center rounded bg-accent/15 px-1 py-0.5 text-[9px] text-accent font-bold">
                                        ▲
                                    </span>
                                )}
                            </span>
                            <span className="text-center font-ui text-xs text-muted-foreground">{row.label}</span>
                            <span className={[
                                'text-right font-mono tabular-nums font-semibold',
                                bHighlighted ? 'text-accent' : 'text-foreground/80',
                            ].join(' ')}>
                                {bHighlighted && (
                                    <span className="mr-1.5 inline-flex items-center justify-center rounded bg-accent/15 px-1 py-0.5 text-[9px] text-accent font-bold">
                                        ▲
                                    </span>
                                )}
                                {bStr}
                            </span>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

// ── Player header card ────────────────────────────────────────────────────────

function PlayerHeaderCard({
    player,
    stat,
    picUrl,
    matchup,
    side,
}: {
    player: PlayerWithStats;
    stat: PlayerStat | null;
    picUrl: string | null;
    matchup: PlayerMatchupResult | null;
    side: 'a' | 'b';
}) {
    const edgeScore = side === 'a' ? matchup?.player_a_edge_score : matchup?.player_b_edge_score;
    const otherScore = side === 'a' ? matchup?.player_b_edge_score : matchup?.player_a_edge_score;
    const isLeading = edgeScore !== undefined && otherScore !== undefined && edgeScore >= otherScore;
    const accentClass = side === 'a' ? 'from-primary/20 border-primary/30' : 'from-blue-900/30 border-blue-500/20';

    return (
        <div className={`rounded-xl border ${accentClass} bg-card p-4 space-y-3 bg-gradient-to-br to-transparent relative overflow-hidden`}>
            <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top,rgba(255,255,255,0.03),transparent_70%)]" />

            <div className="flex items-center gap-3 relative">
                {/* Profile picture */}
                <div className="relative h-12 w-12 shrink-0">
                    {picUrl ? (
                        <img
                            src={picUrl}
                            alt=""
                            className="h-full w-full rounded-full object-cover border-2 border-border"
                        />
                    ) : (
                        <div className="h-full w-full rounded-full bg-primary/10 border-2 border-border flex items-center justify-center">
                            <svg viewBox="0 0 24 24" fill="none" className="h-6 w-6 text-muted-foreground/40" stroke="currentColor" strokeWidth={1.5}>
                                <circle cx="12" cy="7" r="4" />
                                <path d="M4 21v-2a8 8 0 0 1 16 0v2" strokeLinecap="round" />
                            </svg>
                        </div>
                    )}
                    {/* Active dot */}
                    {player.is_active && (
                        <span className="absolute bottom-0 right-0 h-3 w-3 rounded-full bg-emerald-400 border-2 border-card" />
                    )}
                </div>

                <div className="min-w-0">
                    <p className="font-display text-sm font-bold tracking-wide text-foreground leading-none truncate">
                        {player.first_name} {player.last_name}
                    </p>
                    <p className="mt-0.5 font-ui text-[11px] text-muted-foreground">
                        #{player.jersey_number}
                        {player.role && <> · <span className="text-accent/80">{player.role}</span></>}
                    </p>
                </div>
            </div>

            {/* Key stats row */}
            <div className="grid grid-cols-3 gap-1 relative">
                <MiniStat label="EDGE SCORE" value={matchup ? `${Math.round((side === 'a' ? matchup.player_a_edge_score : matchup.player_b_edge_score) * 100)}%` : '—'} highlight={isLeading} />
                <MiniStat label="GP" value={stat?.gp?.toString() ?? '—'} />
                <MiniStat label="+/-" value={fmtPM(stat?.plus_minus ?? null)} highlight={(stat?.plus_minus ?? 0) > 0} />
            </div>
        </div>
    );
}

function MiniStat({ label, value, highlight = false }: { label: string; value: string; highlight?: boolean }) {
    return (
        <div className="text-center">
            <p className="font-ui text-[9px] font-semibold uppercase tracking-widest text-muted-foreground">{label}</p>
            <p className={`font-display text-sm font-bold leading-tight ${highlight ? 'text-accent' : 'text-foreground'}`}>{value}</p>
        </div>
    );
}

// ── Edge bar ──────────────────────────────────────────────────────────────────

function EdgeBar({ label, score, isHigher, reversed = false }: {
    label: string;
    score: number;
    isHigher: boolean;
    reversed?: boolean;
}) {
    const pct = Math.round(score * 100);
    return (
        <div className={`flex flex-1 flex-col gap-1 ${reversed ? 'items-end' : 'items-start'}`}>
            <span className="text-xs font-ui font-semibold text-foreground truncate max-w-[120px]">{label}</span>
            <div className={`flex w-full items-center gap-2 ${reversed ? 'flex-row-reverse' : ''}`}>
                <div className="relative h-2.5 flex-1 overflow-hidden rounded-full bg-muted">
                    <div
                        className={`absolute inset-y-0 ${reversed ? 'right-0' : 'left-0'} rounded-full transition-all ${isHigher ? 'bg-accent' : 'bg-primary/40'}`}
                        style={{ width: `${pct}%` }}
                    />
                </div>
                <span className={`text-xs font-mono font-bold tabular-nums shrink-0 ${isHigher ? 'text-accent' : 'text-muted-foreground'}`}>
                    {pct}%
                </span>
            </div>
        </div>
    );
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function fmt(value: number | null | undefined): string {
    if (value === null || value === undefined) return '—';
    return value.toString();
}

function fmtPM(value: number | null): string {
    if (value === null) return '—';
    return value >= 0 ? `+${value.toFixed(1)}` : `${value.toFixed(1)}`;
}
