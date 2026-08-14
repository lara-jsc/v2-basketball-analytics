import type { KeyToWin, Player } from '@/types';
import { Target } from 'lucide-react';

interface KeysToWinPanelProps {
    keys: KeyToWin[];
    players: Player[];
    controlledPlayerIds: number[];
    className?: string;
}

const STATUS_LABELS: Record<KeyToWin['live_status'], string> = {
    season: 'Season',
    confirmed: 'Live',
    fading: 'Fading',
};

const STATUS_CLASSES: Record<KeyToWin['live_status'], string> = {
    season: 'live-badge-info',
    confirmed: 'live-badge-warn',
    fading: 'bg-muted text-muted-foreground',
};

const TAG_LABELS: Record<string, string> = {
    scorer: 'Scorer',
    playmaker: 'Playmaker',
    boarder: 'Boarder',
    rim_protector: 'Rim protector',
    disruptor: 'Disruptor',
};

export function KeysToWinPanel({ keys, players, controlledPlayerIds, className = '' }: KeysToWinPanelProps) {
    const controlled = new Set(controlledPlayerIds);

    return (
        <section
            className={`flex min-h-0 flex-col overflow-hidden rounded-lg border border-border bg-card ${className}`}
            aria-labelledby="keys-to-win-heading"
        >
            <div className="flex shrink-0 items-center justify-between border-b border-border px-4 py-2.5">
                <h2
                    id="keys-to-win-heading"
                    className="flex items-center gap-2 text-sm font-bold uppercase tracking-[0.1em] text-foreground"
                >
                    <Target size={16} className="text-cyan-600 dark:text-cyan-300" /> Keys to win
                </h2>
                <span className="text-xs text-muted-foreground">{keys.length} active</span>
            </div>
            <div className="min-h-0 flex-1 overflow-y-auto">
                {keys.length === 0 && (
                    <p className="p-4 text-sm text-muted-foreground">
                        Not enough season stats to rank opponent strengths.
                    </p>
                )}
                {keys.map((key) => {
                    const threat = players.find((player) => player.id === key.opponent_player_id);
                    const counter = key.counter_player_id
                        ? players.find((player) => player.id === key.counter_player_id)
                        : undefined;
                    const actionable = key.counter_player_id !== null && controlled.has(key.counter_player_id);
                    const counterOnCourt = key.context.counter_on_court === true;

                    return (
                        <div
                            key={`${key.opponent_player_id}-${key.strength_tag}`}
                            className="border-b border-border/70 px-4 py-3 last:border-b-0"
                        >
                            <div className="flex items-start justify-between gap-2">
                                <div className="min-w-0">
                                    <p className="text-sm font-semibold text-foreground">
                                        {threat
                                            ? `${threat.first_name} ${threat.last_name}`
                                            : `Player #${key.opponent_player_id}`}
                                        <span className="ml-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                            {TAG_LABELS[key.strength_tag] ?? key.strength_tag}
                                        </span>
                                    </p>
                                    <p className="mt-1 text-sm text-foreground/90">{key.defense_key}</p>
                                    <p className="mt-1 text-xs font-semibold text-muted-foreground">
                                        {counter
                                            ? `Put ${counter.first_name} ${counter.last_name} on them${counterOnCourt ? '' : ' (bench)'}`
                                            : 'No counter available'}
                                        {!actionable && counter ? (
                                            <span className="ml-1 font-normal">— best available, not your slot</span>
                                        ) : null}
                                    </p>
                                </div>
                                <span
                                    className={`shrink-0 rounded px-2 py-0.5 text-xs font-bold uppercase ${STATUS_CLASSES[key.live_status]}`}
                                >
                                    {STATUS_LABELS[key.live_status]}
                                </span>
                            </div>
                        </div>
                    );
                })}
            </div>
        </section>
    );
}
