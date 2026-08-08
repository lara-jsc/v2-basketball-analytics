import { playerName } from '@/Components/features/live-game/live-game-utils';
import type { Player } from '@/types';
import { UserRound } from 'lucide-react';
import { useState } from 'react';

export type CoachOption = { id: number; name: string };

export type AssignAssistantValue = {
    assistantCoachUserId: number | null;
    delegatedPlayerIds: number[];
};

type AssignAssistantPanelProps = {
    coaches: CoachOption[];
    players: Player[];
    /** Opponent active players eligible for shot-only delegation. */
    opponentPlayers?: Player[];
    value: AssignAssistantValue;
    onChange: (value: AssignAssistantValue) => void;
    visible: boolean;
    expanded?: boolean;
    onExpandedChange?: (expanded: boolean) => void;
    errors?: {
        assistant_coach_user_id?: string;
        delegated_player_ids?: string;
    };
};

export function AssignAssistantPanel({
    coaches,
    players,
    opponentPlayers,
    value,
    onChange,
    visible,
    expanded: expandedProp,
    onExpandedChange,
    errors,
}: AssignAssistantPanelProps) {
    const [internalExpanded, setInternalExpanded] = useState(false);
    const isControlled = expandedProp !== undefined;
    const expanded = isControlled ? expandedProp : internalExpanded;

    function setExpanded(next: boolean): void {
        onExpandedChange?.(next);
        if (!isControlled) {
            setInternalExpanded(next);
        }
    }

    if (!visible || coaches.length === 0) {
        return null;
    }

    const selectedCoach = coaches.find((coach) => coach.id === value.assistantCoachUserId) ?? null;
    const delegatedCount = value.delegatedPlayerIds.length;
    const hasAssignment = selectedCoach !== null && delegatedCount > 0;

    function selectCoach(coachId: number): void {
        onChange({
            assistantCoachUserId: coachId,
            delegatedPlayerIds: value.delegatedPlayerIds,
        });
    }

    function setOwner(playerId: number, owner: 'you' | 'assistant'): void {
        const next = new Set(value.delegatedPlayerIds);
        if (owner === 'assistant') {
            next.add(playerId);
        } else {
            next.delete(playerId);
        }
        onChange({
            assistantCoachUserId: value.assistantCoachUserId,
            delegatedPlayerIds: Array.from(next),
        });
    }

    function removeAssistant(): void {
        onChange({ assistantCoachUserId: null, delegatedPlayerIds: [] });
        setExpanded(false);
    }

    return (
        <div className="rounded-lg border border-border bg-card p-4">
            <button
                type="button"
                aria-expanded={expanded}
                onClick={() => setExpanded(!expanded)}
                className="flex min-h-11 w-full cursor-pointer items-center justify-between gap-3 rounded-md border border-border bg-muted/30 px-3 text-left transition-colors hover:bg-muted/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300"
            >
                <span className="flex items-center gap-2 text-sm font-semibold text-foreground">
                    <UserRound size={16} className="live-text-info" />
                    {hasAssignment
                        ? `${selectedCoach.name} · ${delegatedCount} player${delegatedCount === 1 ? '' : 's'}`
                        : 'Assign an assistant'}
                </span>
                <span className="text-xs font-bold uppercase tracking-wide text-muted-foreground">
                    {expanded ? 'Hide' : 'Show'}
                </span>
            </button>

            {expanded && (
                <div className="mt-4 space-y-4">
                    <div>
                        <p className="text-xs font-bold uppercase tracking-wide text-muted-foreground">Assistant coach</p>
                        <ul className="mt-2 grid gap-2" role="listbox" aria-label="Assistant coach">
                            {coaches.map((coach) => {
                                const selected = value.assistantCoachUserId === coach.id;
                                return (
                                    <li key={coach.id}>
                                        <button
                                            type="button"
                                            role="option"
                                            aria-selected={selected}
                                            onClick={() => selectCoach(coach.id)}
                                            className={`flex min-h-11 w-full cursor-pointer items-center rounded-md border px-3 text-left text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300 ${
                                                selected
                                                    ? 'live-badge-info'
                                                    : 'border-border text-foreground hover:bg-muted/40'
                                            }`}
                                        >
                                            {coach.name}
                                        </button>
                                    </li>
                                );
                            })}
                        </ul>
                        {errors?.assistant_coach_user_id && (
                            <p className="live-text-danger mt-2 text-xs">{errors.assistant_coach_user_id}</p>
                        )}
                    </div>

                    <div>
                        <p className="text-xs font-bold uppercase tracking-wide text-muted-foreground">Who controls each player</p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Players marked Assistant are recorded by that coach. Everyone else stays with you.
                        </p>
                        <ul className="mt-3 grid gap-2 sm:grid-cols-2">
                            {players.map((player) => {
                                const isAssistant = value.delegatedPlayerIds.includes(player.id);
                                return (
                                    <li
                                        key={player.id}
                                        className="flex min-h-14 flex-wrap items-center gap-2 rounded-md border border-border px-3 py-2"
                                    >
                                        <span className="live-text-warn font-mono">{player.jersey_number}</span>
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate text-sm font-semibold text-foreground">{playerName(player)}</span>
                                            <span className="block truncate text-xs text-muted-foreground">{player.role ?? 'Player'}</span>
                                        </span>
                                        <div className="flex rounded-md border border-border p-0.5" role="group" aria-label={`Owner for ${playerName(player)}`}>
                                            <button
                                                type="button"
                                                onClick={() => setOwner(player.id, 'you')}
                                                className={`min-h-10 cursor-pointer rounded px-2.5 text-xs font-bold uppercase tracking-wide transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300 ${
                                                    !isAssistant
                                                        ? 'bg-amber-400 text-black'
                                                        : 'text-muted-foreground hover:bg-muted/50'
                                                }`}
                                            >
                                                You
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => setOwner(player.id, 'assistant')}
                                                disabled={value.assistantCoachUserId === null}
                                                className={`min-h-10 cursor-pointer rounded px-2.5 text-xs font-bold uppercase tracking-wide transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300 disabled:cursor-not-allowed disabled:opacity-40 ${
                                                    isAssistant
                                                        ? 'bg-cyan-100 text-cyan-900 dark:bg-cyan-300/20 dark:text-cyan-100'
                                                        : 'text-muted-foreground hover:bg-muted/50'
                                                }`}
                                            >
                                                Assistant
                                            </button>
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                        {errors?.delegated_player_ids && (
                            <p className="live-text-danger mt-2 text-xs">{errors.delegated_player_ids}</p>
                        )}
                    </div>

                    {opponentPlayers && opponentPlayers.length > 0 && (
                        <div>
                            <p className="text-xs font-bold uppercase tracking-wide text-muted-foreground">
                                Opponent — shots only
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Assistants assigned here can log shots only — not fouls or substitutions.
                                Unassigned opponent players stay with the head coach for shots.
                            </p>
                            <ul className="mt-3 grid gap-2 sm:grid-cols-2">
                                {opponentPlayers.map((player) => {
                                    const isAssistant = value.delegatedPlayerIds.includes(player.id);
                                    return (
                                        <li
                                            key={player.id}
                                            className="flex min-h-14 flex-wrap items-center gap-2 rounded-md border border-border px-3 py-2"
                                        >
                                            <span className="live-text-warn font-mono">{player.jersey_number}</span>
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate text-sm font-semibold text-foreground">{playerName(player)}</span>
                                                <span className="block truncate text-xs text-muted-foreground">{player.role ?? 'Player'}</span>
                                            </span>
                                            <div className="flex rounded-md border border-border p-0.5" role="group" aria-label={`Owner for ${playerName(player)}`}>
                                                <button
                                                    type="button"
                                                    onClick={() => setOwner(player.id, 'you')}
                                                    className={`min-h-10 cursor-pointer rounded px-2.5 text-xs font-bold uppercase tracking-wide transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300 ${
                                                        !isAssistant
                                                            ? 'bg-amber-400 text-black'
                                                            : 'text-muted-foreground hover:bg-muted/50'
                                                    }`}
                                                >
                                                    You
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => setOwner(player.id, 'assistant')}
                                                    disabled={value.assistantCoachUserId === null}
                                                    className={`min-h-10 cursor-pointer rounded px-2.5 text-xs font-bold uppercase tracking-wide transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300 disabled:cursor-not-allowed disabled:opacity-40 ${
                                                        isAssistant
                                                            ? 'bg-cyan-100 text-cyan-900 dark:bg-cyan-300/20 dark:text-cyan-100'
                                                            : 'text-muted-foreground hover:bg-muted/50'
                                                    }`}
                                                >
                                                    Assistant
                                                </button>
                                            </div>
                                        </li>
                                    );
                                })}
                            </ul>
                        </div>
                    )}

                    <div className="flex flex-wrap items-center justify-end gap-2">
                        {hasAssignment && (
                            <button
                                type="button"
                                onClick={removeAssistant}
                                className="flex min-h-11 cursor-pointer items-center rounded-md border border-border px-4 text-xs font-bold uppercase tracking-wide text-muted-foreground transition-colors hover:bg-muted/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300"
                            >
                                Remove assistant
                            </button>
                        )}
                        <button
                            type="button"
                            onClick={() => setExpanded(false)}
                            className="flex min-h-11 cursor-pointer items-center rounded-md bg-amber-400 px-4 text-xs font-bold uppercase tracking-wide text-black transition-colors hover:bg-amber-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300"
                        >
                            Done
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
