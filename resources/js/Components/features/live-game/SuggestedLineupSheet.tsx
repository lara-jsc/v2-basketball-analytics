import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle, SheetTrigger } from '@/Components/ui/sheet';
import { playerName } from './live-game-utils';
import type {
    LineupPlayer,
    LiveGamePlayerStat,
    LiveLineupReason,
    LiveLineupSuggestionResponse,
    Player,
} from '@/types';
import { Check, Sparkles } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

type Column = 'season' | 'tonight';

interface SuggestedLineupSheetProps {
    players: Player[];
    activePlayerIds: number[];
    stats: LiveGamePlayerStat[];
    /** Blocks the trigger entirely — the game isn't live, or the viewer can't record. */
    disabled: boolean;
    /** Blocks only the two Apply buttons — substitutions need a stopped clock. */
    applyBlockedReason: string | null;
    onRequest: () => Promise<LiveLineupSuggestionResponse | null>;
    onApply: (column: Column, playerIds: number[]) => void;
}

const POLL_INTERVAL_MS = 1500;
const MAX_POLLS = 8;

const REASON_LABELS: Record<LiveLineupReason, string> = {
    disqualified: 'Fouled out',
    foul_trouble: 'In foul trouble — held back',
    inactive: 'Not on the active roster',
    assigned_to_assistant: 'Assigned to your assistant',
};

export function SuggestedLineupSheet({
    players,
    activePlayerIds,
    stats,
    disabled,
    applyBlockedReason,
    onRequest,
    onApply,
}: SuggestedLineupSheetProps) {
    const [open, setOpen] = useState(false);
    const [data, setData] = useState<LiveLineupSuggestionResponse | null>(null);
    const [error, setError] = useState<string | null>(null);
    const pollsRef = useRef(0);

    // The suggestion is computed in a queued job, so the first request almost always
    // answers `pending`. Poll until it lands rather than making the coach tap again.
    const load = useCallback(async (): Promise<void> => {
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
        }
    }, [onRequest]);

    useEffect(() => {
        if (!open) return;

        pollsRef.current = 0;
        setData(null);
        void load();
    }, [open, load]);

    const suggestion = data?.suggestion ?? null;
    const seasonIds = (suggestion?.season.recommended_lineup ?? []).map((row) => row.player_id);
    const tonightIds = (suggestion?.tonight.recommended_lineup ?? []).map((row) => row.player_id);
    const consensusIds = seasonIds.filter((id) => tonightIds.includes(id));
    const fixedIds = data?.fixed_player_ids ?? [];
    const slotCount = data?.slot_count ?? 0;
    const reasons = data?.reasons ?? {};

    // Fixed players are listed in their own rows, so repeating them under "Held back"
    // would say the same thing twice.
    const held = Object.entries(reasons)
        .map(([playerId, reason]) => ({ playerId: Number(playerId), reason }))
        .filter((entry) => !fixedIds.includes(entry.playerId));

    return (
        <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger asChild>
                <button
                    type="button"
                    disabled={disabled}
                    className="live-badge-info flex h-9 items-center gap-2 rounded-md px-3 text-xs font-bold uppercase tracking-wide transition-colors hover:bg-cyan-200/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300 disabled:opacity-50 dark:hover:bg-cyan-300/20"
                >
                    <Sparkles size={15} /> Suggest 5
                </button>
            </SheetTrigger>
            <SheetContent side="left" className="w-full overflow-y-auto border-border bg-background p-5 sm:max-w-xl">
                <SheetHeader>
                    <SheetTitle className="font-display uppercase tracking-wide">Suggested five</SheetTitle>
                    <SheetDescription>
                        Two independent rankings of the same eligible players. Season history and tonight&apos;s form
                        often disagree — that disagreement is the point, so pick the one you trust right now.
                    </SheetDescription>
                </SheetHeader>

                {error && (
                    <p role="alert" className="live-badge-danger mt-5 rounded-md px-4 py-3 text-sm">
                        {error}
                    </p>
                )}

                {!error && data?.pending !== false && (
                    <p className="mt-5 text-sm text-muted-foreground">Ranking the roster…</p>
                )}

                {suggestion && slotCount === 0 && (
                    <p className="mt-5 rounded-md border border-dashed border-border bg-muted/20 px-4 py-4 text-sm text-muted-foreground">
                        Your assistant holds all five players on court. Nothing here is yours to change.
                    </p>
                )}

                {suggestion && slotCount > 0 && (
                    <>
                        <div className="mt-6 grid gap-5 sm:grid-cols-2">
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

                        <div className="mt-4 grid gap-1.5 text-xs text-muted-foreground">
                            <p className="flex items-center gap-2">
                                <Check size={13} className="live-text-info" aria-hidden="true" />
                                Marked players appear in both lists — {consensusIds.length} of {slotCount} agree.
                            </p>
                            <p>
                                {slotCount} of 5 slots {slotCount === 1 ? 'is' : 'are'} yours to change
                                {fixedIds.length > 0
                                    ? `; your assistant holds the other ${fixedIds.length}.`
                                    : '.'}
                            </p>
                        </div>

                        {held.length > 0 && (
                            <section className="mt-6 rounded-lg border border-border bg-card p-4" aria-labelledby="held-back-heading">
                                <h3
                                    id="held-back-heading"
                                    className="text-xs font-bold uppercase tracking-[0.12em] text-muted-foreground"
                                >
                                    Held back
                                </h3>
                                <ul className="mt-3 grid gap-2">
                                    {held.map((entry) => {
                                        const player = players.find((candidate) => candidate.id === entry.playerId);

                                        return (
                                            <li key={entry.playerId} className="flex min-h-11 items-center gap-3 text-sm">
                                                <span className="live-text-warn font-mono">
                                                    {player?.jersey_number ?? '—'}
                                                </span>
                                                <span className="min-w-0 flex-1 truncate text-foreground">
                                                    {player ? playerName(player) : `Player #${entry.playerId}`}
                                                </span>
                                                <span className="shrink-0 text-xs text-muted-foreground">
                                                    {REASON_LABELS[entry.reason]}
                                                </span>
                                            </li>
                                        );
                                    })}
                                </ul>
                            </section>
                        )}
                    </>
                )}
            </SheetContent>
        </Sheet>
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
    // Every candidate is one the coach controls, so the clock is the only thing that can
    // block applying. The old check also disabled on a fixed player who was merely staying
    // on court — a change the server would have accepted.
    const blockedReason = applyBlockedReason;
    const alreadyOnCourt = rows.length > 0 && rows.every((row) => activePlayerIds.includes(row.player_id));

    return (
        <section className="rounded-lg border border-border bg-card" aria-labelledby={`column-${heading}`}>
            <div className="border-b border-border px-4 py-3">
                <h3
                    id={`column-${heading}`}
                    className="text-sm font-bold uppercase tracking-[0.1em] text-foreground"
                >
                    {heading}
                </h3>
                <p className="mt-1 text-xs text-muted-foreground">{caption}</p>
            </div>

            {rows.length === 0 ? (
                <p className="px-4 py-5 text-sm text-muted-foreground">
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
                                    className="flex min-h-12 items-center gap-2 bg-muted/20 px-4 py-2"
                                >
                                    <span className="w-6 shrink-0 font-mono text-muted-foreground">
                                        {player?.jersey_number ?? '—'}
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate text-sm font-semibold text-muted-foreground">
                                            {player ? playerName(player) : `Player #${playerId}`}
                                        </span>
                                        <span className="block truncate text-xs text-muted-foreground">
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
                                <li key={row.player_id} className="flex min-h-12 items-center gap-2 px-4 py-2">
                                    <span className="live-text-warn w-6 shrink-0 font-mono">
                                        {player?.jersey_number ?? '—'}
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="flex items-center gap-1.5">
                                            <span className="min-w-0 truncate text-sm font-semibold text-foreground">
                                                {player ? playerName(player) : row.name}
                                            </span>
                                            {inBoth && (
                                                <Check
                                                    size={13}
                                                    className="live-text-info shrink-0"
                                                    aria-label="in both lists"
                                                />
                                            )}
                                        </span>
                                        <span className="block truncate text-xs text-muted-foreground">
                                            {onCourt ? 'On court' : 'Sub in'}
                                        </span>
                                    </span>
                                    <span className="shrink-0 text-right">
                                        <span className="block font-mono text-sm text-foreground">{metric(row)}</span>
                                        <span className="block text-[10px] uppercase tracking-wide text-muted-foreground">
                                            {metricLabel}
                                        </span>
                                    </span>
                                </li>
                            );
                        })}
                    </ul>

                    <div className="border-t border-border p-3">
                        <button
                            type="button"
                            onClick={onApply}
                            disabled={blockedReason !== null || rows.length !== slotCount || alreadyOnCourt}
                            className="flex min-h-11 w-full items-center justify-center rounded-md bg-amber-400 px-4 text-xs font-bold uppercase tracking-wide text-black transition-colors hover:bg-amber-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            Use this five
                        </button>
                        {blockedReason !== null && (
                            <p className="mt-2 text-xs text-muted-foreground">{blockedReason}</p>
                        )}
                        {blockedReason === null && rows.length !== slotCount && (
                            <p className="mt-2 text-xs text-muted-foreground">
                                Only {rows.length} of your {slotCount} slots can be filled from this sample.
                            </p>
                        )}
                        {blockedReason === null && rows.length === slotCount && alreadyOnCourt && (
                            <p className="mt-2 text-xs text-muted-foreground">
                                Your five is already the recommended one.
                            </p>
                        )}
                    </div>
                </>
            )}
        </section>
    );
}

/**
 * The season column prints the ranker's own score, labelled as a score — it is a weighted
 * sum, not a plus-minus, and calling it "+/−" would invite a comparison with tonight's
 * raw margin that the two quantities do not support.
 */
function formatScore(score: number | null): string {
    return score === null ? '—' : score.toFixed(1);
}

/**
 * Tonight's column prints facts rather than a score, deliberately. Two number columns
 * that look alike get subtracted by eye, which would recreate the blend the design
 * refuses to make.
 */
function formatTonight(stats: LiveGamePlayerStat[], playerId: number): string {
    const stat = stats.find((candidate) => candidate.player_id === playerId);

    if (stat === undefined) return '—';

    return `${stat.points}p · ${stat.turnovers}to · ${stat.personal_fouls}pf`;
}
