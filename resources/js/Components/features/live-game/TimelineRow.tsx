import { clockLabel, eventLabel } from './live-game-utils';
import type { LiveGameEvent, Player, Team } from '@/types';
import { Ban } from 'lucide-react';

interface TimelineRowProps {
    event: LiveGameEvent;
    players: Player[];
    teams: Team[];
    voided: boolean;
    disabled: boolean;
    onRequestVoid: (event: LiveGameEvent) => void;
}

export function TimelineRow({ event, players, teams, voided, disabled, onRequestVoid }: TimelineRowProps) {
    const voidable = event.type !== 'correction' && !voided;

    return (
        <div
            className={`grid min-h-[62px] grid-cols-[auto_1fr_auto] items-center gap-3 border-b border-border/70 px-4 py-2 last:border-b-0 ${
                voided ? 'opacity-45' : 'hover:bg-muted/25'
            }`}
        >
            <span className="font-mono text-xs text-cyan-200">
                Q{event.period}
                <br />
                {clockLabel(event.clock_seconds_remaining)}
            </span>
            <div className="min-w-0">
                <p className="truncate text-sm font-semibold capitalize text-foreground">
                    {eventLabel(event, players, teams)}
                </p>
                <p className="text-xs text-muted-foreground">
                    #{event.sequence} {event.type === 'correction' ? 'Correction' : voided ? 'Voided' : event.team_scope}
                </p>
            </div>
            {voidable ? (
                <button
                    type="button"
                    disabled={disabled}
                    onClick={() => onRequestVoid(event)}
                    aria-label={`Void event ${event.sequence}`}
                    title="Void event"
                    className="flex h-11 w-11 cursor-pointer items-center justify-center rounded-md border border-border text-muted-foreground transition-colors hover:border-red-300/60 hover:bg-red-400/10 hover:text-red-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    <Ban size={16} />
                </button>
            ) : (
                <span className="h-11 w-11" />
            )}
        </div>
    );
}
