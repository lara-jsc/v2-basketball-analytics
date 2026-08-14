import { playerName } from '@/Components/features/live-game/live-game-utils';
import type { Player } from '@/types';
import { UserRound } from 'lucide-react';
import { useState } from 'react';

export type CoachOption = { id: number; name: string };

export type AssistantAssignment = {
    coachUserId: number;
    playerIds: number[];
};

export type AssignAssistantValue = {
    assistantAssignments: AssistantAssignment[];
};

type AssignAssistantPanelProps = {
    coaches: CoachOption[];
    players: Player[];
    value: AssignAssistantValue;
    onChange: (value: AssignAssistantValue) => void;
    visible: boolean;
    expanded?: boolean;
    onExpandedChange?: (expanded: boolean) => void;
    errors?: {
        assistant_assignments?: string;
    };
};

export function AssignAssistantPanel({
    coaches,
    players,
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
    const assignedPlayerCount = value.assistantAssignments.reduce(
        (count, assignment) => count + assignment.playerIds.length,
        0,
    );
    const hasAssignment = assignedPlayerCount > 0;

    function setExpanded(next: boolean): void {
        onExpandedChange?.(next);
        if (!isControlled) {
            setInternalExpanded(next);
        }
    }

    if (!visible || coaches.length === 0) {
        return null;
    }

    function normalizeAssignments(assignments: AssistantAssignment[]): AssistantAssignment[] {
        return assignments
            .map((assignment) => ({
                coachUserId: assignment.coachUserId,
                playerIds: Array.from(new Set(assignment.playerIds)),
            }))
            .filter((assignment) => assignment.playerIds.length > 0);
    }

    function ownerForPlayer(playerId: number): number | 'you' {
        for (const assignment of value.assistantAssignments) {
            if (assignment.playerIds.includes(playerId)) {
                return assignment.coachUserId;
            }
        }

        return 'you';
    }

    function setPlayerOwner(playerId: number, owner: 'you' | number): void {
        const nextAssignments = value.assistantAssignments.map((assignment) => ({
            ...assignment,
            playerIds: assignment.playerIds.filter((id) => id !== playerId),
        }));

        if (owner !== 'you') {
            const target = nextAssignments.find((assignment) => assignment.coachUserId === owner);

            if (target) {
                target.playerIds = [...target.playerIds, playerId];
            } else {
                nextAssignments.push({ coachUserId: owner, playerIds: [playerId] });
            }
        }

        onChange({
            assistantAssignments: normalizeAssignments(nextAssignments),
        });
    }

    function clearAssignments(): void {
        onChange({ assistantAssignments: [] });
    }

    const assignedSummaries = value.assistantAssignments
        .map((assignment) => {
            const coach = coaches.find((candidate) => candidate.id === assignment.coachUserId);

            if (!coach) {
                return null;
            }

            return {
                id: coach.id,
                label: `${coach.name} · ${assignment.playerIds.length} player${assignment.playerIds.length === 1 ? '' : 's'}`,
            };
        })
        .filter((summary): summary is { id: number; label: string } => summary !== null);
    const assistantCount = assignedSummaries.length;

    const buttonLabel = hasAssignment
        ? `${assistantCount} assistant${assistantCount === 1 ? '' : 's'} · ${assignedPlayerCount} player${assignedPlayerCount === 1 ? '' : 's'}`
        : 'Assign assistants';

    function ownerSelectId(playerId: number): string {
        return `owner-player-${playerId}`;
    }

    function ownerLabel(player: Player): string {
        return `Owner for ${playerName(player)}`;
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
                    {buttonLabel}
                </span>
                <span className="text-xs font-bold uppercase tracking-wide text-muted-foreground">
                    {expanded ? 'Hide' : 'Show'}
                </span>
            </button>

            {expanded && (
                <div className="mt-4 space-y-4">
                    <div>
                        <p className="text-xs font-bold uppercase tracking-wide text-muted-foreground">Assistant summary</p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Assign each player to yourself or one assistant coach.
                        </p>
                        {assignedSummaries.length > 0 ? (
                            <ul className="mt-3 flex flex-wrap gap-2">
                                {assignedSummaries.map((summary) => (
                                    <li key={summary.id} className="live-badge-info rounded-md px-3 py-1 text-xs font-bold">
                                        {summary.label}
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="mt-3 rounded-md border border-dashed border-border px-3 py-3 text-xs text-muted-foreground">
                                No players are delegated yet.
                            </p>
                        )}
                    </div>

                    <div>
                        <p className="text-xs font-bold uppercase tracking-wide text-muted-foreground">Who controls each player</p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Choosing You keeps the player with the main coach. Choosing an assistant assigns that player only to them.
                        </p>
                        <ul className="mt-3 grid gap-2 sm:grid-cols-2">
                            {players.map((player) => {
                                const owner = ownerForPlayer(player.id);
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
                                        <label htmlFor={ownerSelectId(player.id)} className="sr-only">
                                            {ownerLabel(player)}
                                        </label>
                                        <select
                                            id={ownerSelectId(player.id)}
                                            aria-label={ownerLabel(player)}
                                            value={owner === 'you' ? 'you' : String(owner)}
                                            onChange={(event) =>
                                                setPlayerOwner(
                                                    player.id,
                                                    event.target.value === 'you' ? 'you' : Number(event.target.value),
                                                )
                                            }
                                            className="h-10 rounded-md border border-input bg-background px-3 text-sm text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300"
                                        >
                                            <option value="you">You</option>
                                            {coaches.map((coach) => (
                                                <option key={coach.id} value={coach.id}>
                                                    {coach.name}
                                                </option>
                                            ))}
                                        </select>
                                    </li>
                                );
                            })}
                        </ul>
                        {errors?.assistant_assignments && (
                            <p className="live-text-danger mt-2 text-xs">{errors.assistant_assignments}</p>
                        )}
                    </div>

                    <div className="flex flex-wrap items-center justify-end gap-2">
                        {hasAssignment && (
                            <button
                                type="button"
                                onClick={clearAssignments}
                                className="flex min-h-11 cursor-pointer items-center rounded-md border border-border px-4 text-xs font-bold uppercase tracking-wide text-muted-foreground transition-colors hover:bg-muted/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300"
                            >
                                Clear assignments
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
