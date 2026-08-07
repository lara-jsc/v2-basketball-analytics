import { playerName } from './live-game-utils';
import type {
    LineupPlayer,
    LiveGamePlayerStat,
    LiveLineupReason,
    LiveLineupSuggestionResponse,
    Player,
} from '@/types';
import { Check, RefreshCw, Sparkles } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

type Column = 'season' | 'tonight';

interface SuggestedLineupPanelProps {
    players: Player[];
    activePlayerIds: number[];
    stats: LiveGamePlayerStat[];
    disabled: boolean;
    applyBlockedReason: string | null;
    onRequest: () => Promise<LiveLineupSuggestionResponse | null>;
    onApply: (column: Column, playerIds: number[]) => void;
    className?: string;
}

const POLL_INTERVAL_MS = 1500;
const MAX_POLLS = 8;

const REASON_LABELS: Record<LiveLineupReason, string> = {
    disqualified: 'Fouled out',
    foul_trouble: 'In foul trouble — held back',
    inactive: 'Not on the active roster',
    assigned_to_assistant: 'Assigned to your assistant',
};

export function SuggestedLineupPanel({
    players,
    activePlayerIds,
    stats,
    disabled,
    applyBlockedReason,
    onRequest,
    onApply,
    className = '',
}: SuggestedLineupPanelProps) {
    const [data, setData] = useState<LiveLineupSuggestionResponse | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const pollsRef = useRef(0);

    const load = useCallback(async (): Promise<void> => {
        if (disabled) return;

        setLoading(true);
        setError(null);

        try {
            const response = await onRequest();

            if (response === null) {
                setError('The suggestion could not be loaded. Check the connection and try again.');

                return;
            }

            setData(response);

            if (response.pending) {
                pollsRef.current += 1;

                if (pollsRef.current >= MAX_POLLS) {
                    setError('The suggestion is taking longer than expected. Make sure a queue worker is running.');

                    return;
                }

                window.setTimeout(() => void load(), POLL_INTERVAL_MS);
            }
        } catch {
            setError('The suggestion could not be loaded. Check the connection and try again.');
        } finally {
            setLoading(false);
        }
    }, [disabled, onRequest]);

    useEffect(() => {
        if (disabled) return;

        pollsRef.current = 0;
        setData(null);
        void load();
    }, [disabled, load]);

    const suggestion = data?.suggestion ?? null;
    const seasonIds = (suggestion?.season.recommended_lineup ?? []).map((row) => row.player_id);
    const tonightIds = (suggestion?.tonight.recommended_lineup ?? []).map((row) => row.player_id);
    const consensusIds = seasonIds.filter((id) => tonightIds.includes(id));
    const fixedIds = data?.fixed_player_ids ?? [];
    const slotCount = data?.slot_count ?? 0;
    const reasons = data?.reasons ?? {};

    const held = Object.entries(reasons)
        .map(([playerId, reason]) => ({ playerId: Number(playerId), reason }))
        .filter((entry) => !fixedIds.includes(entry.playerId));

    return (
        <section
            className={`flex min-h-0 flex-col overflow-hidden rounded-lg border border-border bg-card ${className}`}
            aria-labelledby="suggested-lineup-heading"
        >
            <div className="flex shrink-0 items-center justify-between gap-2 border-b border-border px-3 py-2.5">
                <div className="min-w-0">
                    <h2
                        id="suggested-lineup-heading"
                        className="flex items-center gap-2 text-sm font-bold uppercase tracking-[0.1em] text-foreground"
                    >
                        <Sparkles size={15} className="live-text-info shrink-0" /> Suggested five
                    </h2>
                </div>
                <button
                    type="button"
                    onClick={() => {
                        pollsRef.current = 0;
                        setData(null);
                        void load();
                    }}
                    disabled={disabled || loading}
                    aria-label="Refresh lineup suggestions"
                    className="flex h-9 w-9 shrink-0 cursor-pointer items-center justify-center rounded-md border border-border text-muted-foreground transition-colors hover:bg-muted/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <RefreshCw size={14} className={loading ? 'animate-spin' : ''} />
                </button>
            </div>

            <div className="min-h-0 flex-1 overflow-y-auto p-2">
                {error && (
                    <p role="alert" className="live-badge-danger mb-2 rounded-md px-3 py-2 text-xs">
                        {error}
                    </p>
                )}

                {!error && (loading || data?.pending !== false) && (
                    <p className="px-1 py-2 text-xs text-muted-foreground">Ranking the roster…</p>
                )}

                {suggestion && slotCount === 0 && (
                    <p className="rounded-md border border-dashed border-border bg-muted/20 px-3 py-3 text-xs text-muted-foreground">
                        Your assistant holds all five players on court. Nothing here is yours to change.
                    </p>
                )}

                {suggestion && slotCount > 0 && (
                    <>
                        <div className="grid grid-cols-1 gap-2">
                            <LineupColumn
                                heading="By season"
                                caption="Ranked on imported game history"
                                rows={suggestion.season.recommended_lineup}
                                players={players}
                                activePlayerIds={activePlayerIds}
                                consensusIds={consensusIds}
                                fixedIds={fixedIds}
                                slotCount={slotCount}
                                metric={(row) => formatScore(row.plus_minus_score)}
                                metricLabel="Score"
                                applyBlockedReason={applyBlockedReason}
                                onApply={() => onApply('season', [...fixedIds, ...seasonIds])}
                            />
                            <LineupColumn
                                heading="By tonight"
                                caption="Ranked on this game's box score"
                                rows={suggestion.tonight.recommended_lineup}
                                players={players}
                                activePlayerIds={activePlayerIds}
                                consensusIds={consensusIds}
                                fixedIds={fixedIds}
                                slotCount={slotCount}
                                metric={(row) => formatTonight(stats, row.player_id)}
                                metricLabel="Tonight"
                                emptyMessage="No one has played enough minutes yet for tonight's numbers to mean anything."
                                applyBlockedReason={applyBlockedReason}
                                onApply={() => onApply('tonight', [...fixedIds, ...tonightIds])}
                            />
                        </div>

                        <div className="mt-2 grid gap-1 text-[11px] text-muted-foreground">
                            <p className="flex items-center gap-1.5">
                                <Check size={12} className="live-text-info shrink-0" aria-hidden="true" />
                                {consensusIds.length} of {slotCount} appear in both lists.
                            </p>
                        </div>

                        {held.length > 0 && (
                            <details className="mt-2 rounded-md border border-border/70 bg-muted/10 px-2 py-1.5">
                                <summary className="cursor-pointer text-[11px] font-bold uppercase tracking-wide text-muted-foreground">
                                    Held back ({held.length})
                                </summary>
                                <ul className="mt-2 grid gap-1">
                                    {held.map((entry) => {
                                        const player = players.find((candidate) => candidate.id === entry.playerId);

                                        return (
                                            <li key={entry.playerId} className="flex min-h-9 items-center gap-2 text-xs">
                                                <span className="live-text-warn font-mono">
                                                    {player?.jersey_number ?? '—'}
                                                </span>
                                                <span className="min-w-0 flex-1 truncate text-foreground">
                                                    {player ? playerName(player) : `Player #${entry.playerId}`}
                                                </span>
                                                <span className="shrink-0 text-[10px] text-muted-foreground">
                                                    {REASON_LABELS[entry.reason]}
                                                </span>
                                            </li>
                                        );
                                    })}
                                </ul>
                            </details>
                        )}
                    </>
                )}
            </div>
        </section>
    );
}

interface LineupColumnProps {
    heading: string;
    caption: string;
    rows: LineupPlayer[];
    players: Player[];
    activePlayerIds: number[];
    consensusIds: number[];
    fixedIds: number[];
    slotCount: number;
    metric: (row: LineupPlayer) => string;
    metricLabel: string;
    emptyMessage?: string;
    applyBlockedReason: string | null;
    onApply: () => void;
}

function LineupColumn({
    heading,
    caption,
    rows,
    players,
    activePlayerIds,
    consensusIds,
    fixedIds,
    slotCount,
    metric,
    metricLabel,
    emptyMessage,
    applyBlockedReason,
    onApply,
}: LineupColumnProps) {
    const blockedReason = applyBlockedReason;
    const alreadyOnCourt = rows.length > 0 && rows.every((row) => activePlayerIds.includes(row.player_id));

    return (
        <section className="rounded-lg border border-border bg-card" aria-labelledby={`column-${heading}`}>
            <div className="border-b border-border px-3 py-2">
                <h3
                    id={`column-${heading}`}
                    className="text-xs font-bold uppercase tracking-[0.1em] text-foreground"
                >
                    {heading}
                </h3>
                <p className="mt-0.5 text-[10px] text-muted-foreground">{caption}</p>
            </div>

            {rows.length === 0 ? (
                <p className="px-3 py-4 text-xs text-muted-foreground">
                    {emptyMessage ?? 'No eligible players to rank.'}
                </p>
            ) : (
                <>
                    <ul className="divide-y divide-border/70">
                        {fixedIds.map((playerId) => {
                            const player = players.find((candidate) => candidate.id === playerId);

                            return (
                                <li
                                    key={`fixed-${playerId}`}
                                    className="flex min-h-10 items-center gap-2 bg-muted/20 px-3 py-1.5"
                                >
                                    <span className="w-5 shrink-0 font-mono text-xs text-muted-foreground">
                                        {player?.jersey_number ?? '—'}
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate text-xs font-semibold text-muted-foreground">
                                            {player ? playerName(player) : `Player #${playerId}`}
                                        </span>
                                        <span className="block truncate text-[10px] text-muted-foreground">
                                            Fixed · assistant&apos;s
                                        </span>
                                    </span>
                                </li>
                            );
                        })}
                        {rows.map((row) => {
                            const player = players.find((candidate) => candidate.id === row.player_id);
                            const onCourt = activePlayerIds.includes(row.player_id);
                            const inBoth = consensusIds.includes(row.player_id);

                            return (
                                <li key={row.player_id} className="flex min-h-10 items-center gap-2 px-3 py-1.5">
                                    <span className="live-text-warn w-5 shrink-0 font-mono text-xs">
                                        {player?.jersey_number ?? '—'}
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="flex items-center gap-1">
                                            <span className="min-w-0 truncate text-xs font-semibold text-foreground">
                                                {player ? playerName(player) : row.name}
                                            </span>
                                            {inBoth && (
                                                <Check
                                                    size={12}
                                                    className="live-text-info shrink-0"
                                                    aria-label="in both lists"
                                                />
                                            )}
                                        </span>
                                        <span className="block truncate text-[10px] text-muted-foreground">
                                            {onCourt ? 'On court' : 'Sub in'}
                                        </span>
                                    </span>
                                    <span className="shrink-0 text-right">
                                        <span className="block font-mono text-xs text-foreground">{metric(row)}</span>
                                        <span className="block text-[9px] uppercase tracking-wide text-muted-foreground">
                                            {metricLabel}
                                        </span>
                                    </span>
                                </li>
                            );
                        })}
                    </ul>

                    <div className="border-t border-border p-2">
                        <button
                            type="button"
                            onClick={onApply}
                            disabled={blockedReason !== null || rows.length !== slotCount || alreadyOnCourt}
                            className="flex min-h-11 w-full cursor-pointer items-center justify-center rounded-md bg-amber-400 px-3 text-[10px] font-bold uppercase tracking-wide text-black transition-colors hover:bg-amber-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            Use this five
                        </button>
                        {blockedReason !== null && (
                            <p className="mt-1.5 text-[10px] text-muted-foreground">{blockedReason}</p>
                        )}
                        {blockedReason === null && rows.length === slotCount && alreadyOnCourt && (
                            <p className="mt-1.5 text-[10px] text-muted-foreground">
                                Your five is already the recommended one.
                            </p>
                        )}
                    </div>
                </>
            )}
        </section>
    );
}

function formatScore(score: number | null): string {
    return score === null ? '—' : score.toFixed(1);
}

function formatTonight(stats: LiveGamePlayerStat[], playerId: number): string {
    const stat = stats.find((candidate) => candidate.player_id === playerId);

    if (stat === undefined) return '—';

    return `${stat.points}p · ${stat.turnovers}to · ${stat.personal_fouls}pf`;
}
