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

export function PlayerMatchupTable({ playerA, playerB, matchup, isPending }: PlayerMatchupTableProps) {
    const statA = playerA.stats[0] ?? null;
    const statB = playerB.stats[0] ?? null;

    const strongerA = matchup?.stronger_stats_a ?? [];
    const strongerB = matchup?.stronger_stats_b ?? [];

    const picA = playerA.profile_picture_path ? `/storage/${playerA.profile_picture_path}` : null;
    const picB = playerB.profile_picture_path ? `/storage/${playerB.profile_picture_path}` : null;

    const radarData = RADAR_KEYS.map(({ label, statKey, max }) => ({
        stat: label,
        A: normalize(statA?.[statKey] as number | null, max),
        B: normalize(statB?.[statKey] as number | null, max),
    }));

    return (
        <div className="space-y-4">
            {/* ── Player headers — large game-style cards ── */}
            <div className="grid grid-cols-5 gap-3 items-stretch">
                {/* Player A */}
                <div className="col-span-2">
                    <PlayerHeroCard player={playerA} stat={statA} picUrl={picA} matchup={matchup} side="a" />
                </div>

                {/* Radar chart center */}
                <div className="col-span-1 flex flex-col items-center justify-center gap-3 rounded-2xl py-4"
                     style={{ background: 'rgba(11,18,32,0.6)', border: '1px solid rgba(255,255,255,0.06)' }}>
                    <div className="h-44 w-full">
                        <ResponsiveContainer width="100%" height="100%">
                            <RadarChart data={radarData} margin={{ top: 8, right: 20, bottom: 8, left: 20 }}>
                                <PolarGrid stroke="rgba(255,255,255,0.08)" />
                                <PolarAngleAxis
                                    dataKey="stat"
                                    tick={{ fontSize: 9, fill: '#F9A01B', fontFamily: 'Rajdhani', fontWeight: 700 }}
                                />
                                <Radar
                                    name={`${playerA.first_name} ${playerA.last_name}`}
                                    dataKey="A"
                                    stroke="#98002E"
                                    fill="#98002E"
                                    fillOpacity={0.35}
                                    strokeWidth={2}
                                />
                                <Radar
                                    name={`${playerB.first_name} ${playerB.last_name}`}
                                    dataKey="B"
                                    stroke="#60a5fa"
                                    fill="#60a5fa"
                                    fillOpacity={0.25}
                                    strokeWidth={2}
                                />
                                <Tooltip
                                    contentStyle={{ background: '#0D1525', border: '1px solid rgba(255,255,255,0.1)', borderRadius: '8px', fontSize: '11px', fontFamily: 'Rajdhani' }}
                                    formatter={(value, name) => [`${value}`, String(name)]}
                                />
                            </RadarChart>
                        </ResponsiveContainer>
                    </div>
                    {/* Legend */}
                    <div className="flex flex-col items-center gap-1">
                        <span className="flex items-center gap-1.5 text-[10px] font-semibold"
                              style={{ fontFamily: 'Rajdhani, sans-serif', color: 'rgba(152,0,46,0.9)' }}>
                            <span className="h-2 w-2 rounded-full bg-[#98002E] inline-block" />
                            {playerA.first_name} {playerA.last_name}
                        </span>
                        <span className="flex items-center gap-1.5 text-[10px] font-semibold"
                              style={{ fontFamily: 'Rajdhani, sans-serif', color: 'rgba(96,165,250,0.9)' }}>
                            <span className="h-2 w-2 rounded-full bg-blue-400 inline-block" />
                            {playerB.first_name} {playerB.last_name}
                        </span>
                    </div>
                </div>

                {/* Player B */}
                <div className="col-span-2">
                    <PlayerHeroCard player={playerB} stat={statB} picUrl={picB} matchup={matchup} side="b" />
                </div>
            </div>

            {/* ── Edge scores ── */}
            {isPending ? (
                <div className="flex items-center gap-2 rounded-xl px-5 py-3"
                     style={{ background: 'rgba(11,18,32,0.6)', border: '1px solid rgba(255,255,255,0.06)' }}>
                    <Loader2 size={13} className="animate-spin" style={{ color: '#F9A01B' }} />
                    <span className="text-sm" style={{ fontFamily: 'Rajdhani, sans-serif', color: 'rgba(255,255,255,0.35)', fontWeight: 600 }}>
                        Computing matchup prediction…
                    </span>
                </div>
            ) : matchup ? (
                <div className="rounded-2xl px-5 py-4 space-y-3"
                     style={{ background: 'rgba(11,18,32,0.85)', border: '1px solid rgba(255,255,255,0.07)' }}>
                    <p style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '10px', fontWeight: 700, color: 'rgba(255,255,255,0.5)', letterSpacing: '2px', textTransform: 'uppercase' }}>
                        Matchup Edge Scores
                    </p>
                    <div className="flex items-center gap-4">
                        <EdgeBar
                            label={`${playerA.first_name} ${playerA.last_name}`}
                            score={matchup.player_a_edge_score}
                            isHigher={matchup.player_a_edge_score >= matchup.player_b_edge_score}
                        />
                        <span style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '12px', fontWeight: 900, color: 'rgba(249,160,27,0.6)' }}>VS</span>
                        <EdgeBar
                            label={`${playerB.first_name} ${playerB.last_name}`}
                            score={matchup.player_b_edge_score}
                            isHigher={matchup.player_b_edge_score >= matchup.player_a_edge_score}
                            reversed
                        />
                    </div>
                </div>
            ) : null}

            {/* ── Stat rows ── */}
            <div className="rounded-2xl overflow-hidden"
                 style={{ background: 'rgba(11,18,32,0.85)', border: '1px solid rgba(255,255,255,0.07)' }}>
                {/* Header */}
                <div className="grid grid-cols-3 px-4 py-3"
                     style={{ borderBottom: '1px solid rgba(255,255,255,0.07)', background: 'linear-gradient(90deg, rgba(152,0,46,0.15), rgba(11,18,32,0.5), rgba(60,100,200,0.1))' }}>
                    <span style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '11px', fontWeight: 700, color: 'rgba(255,255,255,0.7)', letterSpacing: '1px', textTransform: 'uppercase' }}>
                        {playerA.first_name} {playerA.last_name}
                    </span>
                    <span className="text-center" style={{ fontFamily: 'Rajdhani, sans-serif', fontSize: '10px', fontWeight: 700, color: 'rgba(255,255,255,0.3)', letterSpacing: '2px', textTransform: 'uppercase' }}>
                        STAT
                    </span>
                    <span className="text-right" style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '11px', fontWeight: 700, color: 'rgba(255,255,255,0.7)', letterSpacing: '1px', textTransform: 'uppercase' }}>
                        {playerB.first_name} {playerB.last_name}
                    </span>
                </div>

                {STAT_ROWS.map((row, i) => {
                    const aStr = statA ? row.getValue(statA) : '—';
                    const bStr = statB ? row.getValue(statB) : '—';
                    const aHighlighted = strongerA.includes(row.dbKey);
                    const bHighlighted = strongerB.includes(row.dbKey);

                    return (
                        <div key={row.label}
                             className="grid grid-cols-3 px-4 py-2 transition-colors hover:bg-white/[0.02] last:border-0"
                             style={{
                                 borderBottom: '1px solid rgba(255,255,255,0.04)',
                                 background: i % 2 === 1 ? 'rgba(152,0,46,0.02)' : 'transparent',
                             }}>
                            <span style={{
                                fontFamily: 'Rajdhani, sans-serif',
                                fontSize: '13px',
                                fontWeight: aHighlighted ? 800 : 600,
                                color: aHighlighted ? '#F9A01B' : 'rgba(255,255,255,0.6)',
                                fontVariantNumeric: 'tabular-nums',
                            }}>
                                {aStr}
                                {aHighlighted && (
                                    <span className="ml-1.5 inline-flex items-center justify-center rounded px-1 py-0.5 text-[9px] font-bold"
                                          style={{ background: 'rgba(249,160,27,0.15)', color: '#F9A01B' }}>▲</span>
                                )}
                            </span>
                            <span className="text-center" style={{ fontFamily: 'Rajdhani, sans-serif', fontSize: '11px', fontWeight: 700, color: 'rgba(255,255,255,0.25)', letterSpacing: '1px' }}>
                                {row.label}
                            </span>
                            <span className="text-right" style={{
                                fontFamily: 'Rajdhani, sans-serif',
                                fontSize: '13px',
                                fontWeight: bHighlighted ? 800 : 600,
                                color: bHighlighted ? '#F9A01B' : 'rgba(255,255,255,0.6)',
                                fontVariantNumeric: 'tabular-nums',
                            }}>
                                {bHighlighted && (
                                    <span className="mr-1.5 inline-flex items-center justify-center rounded px-1 py-0.5 text-[9px] font-bold"
                                          style={{ background: 'rgba(249,160,27,0.15)', color: '#F9A01B' }}>▲</span>
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

// ── Player hero card ──────────────────────────────────────────────────────────

function PlayerHeroCard({
    player, stat, picUrl, matchup, side,
}: {
    player: PlayerWithStats;
    stat: PlayerStat | null;
    picUrl: string | null;
    matchup: PlayerMatchupResult | null;
    side: 'a' | 'b';
}) {
    const isHome = side === 'a';
    const edgeScore = side === 'a' ? matchup?.player_a_edge_score : matchup?.player_b_edge_score;
    const otherScore = side === 'a' ? matchup?.player_b_edge_score : matchup?.player_a_edge_score;
    const isLeading = edgeScore !== undefined && otherScore !== undefined && edgeScore >= otherScore;

    const borderColor = isHome ? 'rgba(152,0,46,0.3)' : 'rgba(60,100,200,0.3)';
    const glowColor = isHome ? 'rgba(152,0,46,0.15)' : 'rgba(60,100,200,0.15)';
    const gradBg = isHome
        ? 'linear-gradient(160deg, rgba(152,0,46,0.2) 0%, rgba(11,18,32,0.95) 50%)'
        : 'linear-gradient(160deg, rgba(30,60,150,0.2) 0%, rgba(11,18,32,0.95) 50%)';

    return (
        <div className="h-full rounded-2xl overflow-hidden relative"
             style={{ background: gradBg, border: `1px solid ${borderColor}`, boxShadow: `0 0 24px ${glowColor}` }}>
            <div className="pointer-events-none absolute inset-0"
                 style={{ background: 'radial-gradient(ellipse at top, rgba(255,255,255,0.02), transparent 70%)' }} />

            <div className="relative flex items-center gap-4 p-4">
                {/* Large player photo */}
                <div className="relative flex-shrink-0">
                    <div className="flex h-20 w-20 items-center justify-center rounded-2xl overflow-hidden"
                         style={{ border: `2px solid ${borderColor}`, boxShadow: `0 0 20px ${glowColor}` }}>
                        {picUrl ? (
                            <img src={picUrl} alt="" className="h-full w-full object-cover" />
                        ) : (
                            <div className="h-full w-full flex items-center justify-center"
                                 style={{ background: isHome ? 'rgba(152,0,46,0.2)' : 'rgba(30,60,150,0.2)' }}>
                                <svg viewBox="0 0 24 24" fill="none" className="h-10 w-10" style={{ color: 'rgba(255,255,255,0.2)' }} stroke="currentColor" strokeWidth={1.2}>
                                    <circle cx="12" cy="7" r="4" />
                                    <path d="M4 21v-2a8 8 0 0 1 16 0v2" strokeLinecap="round" />
                                </svg>
                            </div>
                        )}
                    </div>
                    {/* Jersey number overlay */}
                    <div className="absolute -bottom-1 -right-1 flex h-6 w-6 items-center justify-center rounded-full"
                         style={{ background: isHome ? '#98002E' : '#1e3c96', border: '2px solid rgba(0,0,0,0.5)' }}>
                        <span style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '9px', fontWeight: 900, color: '#fff' }}>
                            {player.jersey_number ?? '—'}
                        </span>
                    </div>
                </div>

                {/* Player info */}
                <div className="flex-1 min-w-0">
                    <p style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '12px', fontWeight: 700, color: 'rgba(255,255,255,0.9)', letterSpacing: '0.5px', textTransform: 'uppercase' }}
                       className="truncate">
                        {player.first_name} {player.last_name}
                    </p>
                    {player.role && (
                        <span className="mt-1 inline-block rounded px-2 py-0.5 text-[9px] font-bold uppercase tracking-widest"
                              style={{ fontFamily: 'Rajdhani, sans-serif', background: 'rgba(249,160,27,0.1)', border: '1px solid rgba(249,160,27,0.25)', color: '#F9A01B' }}>
                            {player.role}
                        </span>
                    )}

                    {/* Mini stat strip */}
                    <div className="mt-2 grid grid-cols-3 gap-1">
                        <MiniStat label="+/-" value={fmtPM(stat?.plus_minus ?? null)} highlight={(stat?.plus_minus ?? 0) > 0} />
                        <MiniStat label="PTS" value={fmt(stat?.pts)} />
                        <MiniStat label="GP" value={stat?.gp?.toString() ?? '—'} />
                    </div>
                </div>
            </div>

            {/* Edge score footer */}
            {matchup && (
                <div className="px-4 pb-3 relative">
                    <div className="flex items-center justify-between">
                        <span style={{ fontFamily: 'Rajdhani, sans-serif', fontSize: '10px', fontWeight: 700, color: 'rgba(255,255,255,0.3)', letterSpacing: '1px', textTransform: 'uppercase' }}>
                            Edge Score
                        </span>
                        <span style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '13px', fontWeight: 900, color: isLeading ? '#F9A01B' : 'rgba(255,255,255,0.4)', textShadow: isLeading ? '0 0 8px rgba(249,160,27,0.4)' : 'none' }}>
                            {Math.round((edgeScore ?? 0) * 100)}%
                        </span>
                    </div>
                </div>
            )}
        </div>
    );
}

function MiniStat({ label, value, highlight = false }: { label: string; value: string; highlight?: boolean }) {
    return (
        <div className="text-center rounded-lg py-1"
             style={{ background: 'rgba(255,255,255,0.03)' }}>
            <p style={{ fontFamily: 'Rajdhani, sans-serif', fontSize: '9px', fontWeight: 700, color: 'rgba(255,255,255,0.3)', letterSpacing: '1px', textTransform: 'uppercase' }}>
                {label}
            </p>
            <p style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '12px', fontWeight: 900, color: highlight ? '#F9A01B' : 'rgba(255,255,255,0.7)', lineHeight: 1.2 }}>
                {value}
            </p>
        </div>
    );
}

function EdgeBar({ label, score, isHigher, reversed = false }: {
    label: string; score: number; isHigher: boolean; reversed?: boolean;
}) {
    const pct = Math.round(score * 100);
    return (
        <div className={`flex flex-1 flex-col gap-1.5 ${reversed ? 'items-end' : 'items-start'}`}>
            <span className="text-xs font-semibold truncate max-w-[120px]"
                  style={{ fontFamily: 'Rajdhani, sans-serif', color: 'rgba(255,255,255,0.6)' }}>{label}</span>
            <div className={`flex w-full items-center gap-2 ${reversed ? 'flex-row-reverse' : ''}`}>
                <div className="relative h-2.5 flex-1 overflow-hidden rounded-full"
                     style={{ background: 'rgba(255,255,255,0.08)' }}>
                    <div className={`absolute inset-y-0 ${reversed ? 'right-0' : 'left-0'} rounded-full transition-all`}
                         style={{
                             width: `${pct}%`,
                             background: isHigher
                                 ? 'linear-gradient(90deg, #F9A01B, #d4860f)'
                                 : 'rgba(152,0,46,0.5)',
                             boxShadow: isHigher ? '0 0 6px rgba(249,160,27,0.4)' : 'none',
                         }} />
                </div>
                <span className="text-xs font-bold tabular-nums shrink-0"
                      style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '11px', color: isHigher ? '#F9A01B' : 'rgba(255,255,255,0.3)' }}>
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
