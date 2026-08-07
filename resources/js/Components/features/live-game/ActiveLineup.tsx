import { playerName } from './live-game-utils';
import type { LiveGamePlayerStat, Player } from '@/types';
import { UsersRound } from 'lucide-react';
import { useMemo } from 'react';

interface ActiveLineupProps {
    players: Player[];
    activePlayerIds: number[];
    controlledPlayerIds: number[];
    stats: LiveGamePlayerStat[];
    selectedPlayerId: number | null;
    onSelectPlayer: (playerId: number) => void;
}

function statBlock(stat: LiveGamePlayerStat | undefined, muted = false): JSX.Element {
    const plusMinus = stat?.plus_minus ?? 0;

    return (
        <span className={`text-right font-mono text-xs ${muted ? 'text-muted-foreground' : 'live-text-info'}`}>
            <span className="block">{stat?.points ?? 0} PTS</span>
            <span className="block text-muted-foreground">
                {plusMinus >= 0 ? '+' : ''}
                {plusMinus}
            </span>
        </span>
    );
}

export function ActiveLineup({
    players,
    activePlayerIds,
    controlledPlayerIds,
    stats,
    selectedPlayerId,
    onSelectPlayer,
}: ActiveLineupProps) {
    const controlledSet = useMemo(() => new Set(controlledPlayerIds), [controlledPlayerIds]);

    const { controlledOnCourt, fixedOnCourt } = useMemo(() => {
        const controlled: Player[] = [];
        const fixed: Player[] = [];

        for (const id of activePlayerIds) {
            const player = players.find((candidate) => candidate.id === id);

            if (!player) {
                continue;
            }

            if (controlledSet.has(id)) {
                controlled.push(player);
            } else {
                fixed.push(player);
            }
        }

        return { controlledOnCourt: controlled, fixedOnCourt: fixed };
    }, [activePlayerIds, controlledSet, players]);

    const headerDetail =
        fixedOnCourt.length > 0
            ? `${activePlayerIds.length} on court · ${controlledOnCourt.length} yours · ${fixedOnCourt.length} assistant's`
            : `${activePlayerIds.length} on court`;

    return (
        <section className="rounded-lg border border-border bg-card p-3" aria-labelledby="active-lineup-heading">
            <div className="mb-2 flex items-center justify-between gap-2">
                <h2
                    id="active-lineup-heading"
                    className="flex items-center gap-2 text-sm font-bold uppercase tracking-[0.1em] text-foreground"
                >
                    <UsersRound size={16} className="live-text-warn" /> Active lineup
                </h2>
                <span className="shrink-0 text-right text-xs font-semibold text-muted-foreground">{headerDetail}</span>
            </div>
            <div className="grid grid-cols-1 gap-1.5 lg:grid-cols-1">
                {controlledOnCourt.map((player) => {
                    const stat = stats.find((entry) => entry.player_id === player.id);
                    const selected = selectedPlayerId === player.id;

                    return (
                        <button
                            key={player.id}
                            type="button"
                            onClick={() => onSelectPlayer(player.id)}
                            className={`grid min-h-11 w-full cursor-pointer grid-cols-[2.25rem_1fr_auto] items-center gap-2 rounded-md border px-2 text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300 ${selected ? 'border-amber-300/70 bg-amber-300/10' : 'border-border bg-muted/20 hover:bg-muted/50'}`}
                        >
                            <span className="live-text-warn flex h-9 w-9 items-center justify-center rounded-full bg-muted font-mono text-sm font-bold">
                                {player.jersey_number}
                            </span>
                            <span className="min-w-0">
                                <span className="block truncate text-sm font-semibold text-foreground">
                                    {playerName(player)}
                                </span>
                                <span className="block truncate text-xs text-muted-foreground">
                                    {player.role ?? 'Player'}
                                </span>
                            </span>
                            {statBlock(stat)}
                        </button>
                    );
                })}
                {fixedOnCourt.map((player) => {
                    const stat = stats.find((entry) => entry.player_id === player.id);

                    return (
                        <div
                            key={player.id}
                            aria-label={`${playerName(player)}, on court, assigned to assistant coach, not selectable`}
                            className="grid min-h-11 w-full cursor-default grid-cols-[2.25rem_1fr_auto] items-center gap-2 rounded-md border border-border bg-muted/20 px-2"
                        >
                            <span className="flex h-9 w-9 items-center justify-center rounded-full bg-muted font-mono text-sm font-bold text-muted-foreground">
                                {player.jersey_number}
                            </span>
                            <span className="min-w-0">
                                <span className="block truncate text-sm font-semibold text-muted-foreground">
                                    {playerName(player)}
                                </span>
                                <span className="block truncate text-[10px] text-muted-foreground">
                                    Fixed · assistant&apos;s
                                </span>
                            </span>
                            {statBlock(stat, true)}
                        </div>
                    );
                })}
                {activePlayerIds.length === 0 && (
                    <p className="py-4 text-center text-sm text-muted-foreground">No players on court.</p>
                )}
            </div>
        </section>
    );
}
