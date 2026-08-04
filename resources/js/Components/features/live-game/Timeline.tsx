import { TimelineRow } from './TimelineRow';
import type { LiveGameEvent, Player, Team } from '@/types';
import { History } from 'lucide-react';

interface TimelineProps {
    events: LiveGameEvent[];
    players: Player[];
    teams: Team[];
    disabled: boolean;
    onRequestVoid: (event: LiveGameEvent) => void;
}

export function Timeline({ events, players, teams, disabled, onRequestVoid }: TimelineProps) {
    const voidedIds = new Set(
        events
            .filter((event) => event.type === 'correction' && event.voids_event_id)
            .map((event) => event.voids_event_id),
    );
    const recordedCount = events.filter(
        (event) => event.type !== 'correction' && !voidedIds.has(event.id),
    ).length;

    return (
        <section
            className="flex min-h-[300px] flex-col rounded-lg border border-border bg-card"
            aria-labelledby="timeline-heading"
        >
            <div className="flex items-center justify-between border-b border-border px-4 py-3">
                <h2
                    id="timeline-heading"
                    className="flex items-center gap-2 text-sm font-bold uppercase tracking-[0.1em] text-foreground"
                >
                    <History size={16} className="text-cyan-300" /> Timeline
                </h2>
                <span className="text-xs text-muted-foreground">
                    {recordedCount} recorded
                    {voidedIds.size > 0 ? ` · ${voidedIds.size} voided` : ''}
                </span>
            </div>
            {/*
              The scroller needs an explicit cap: without one the section grows with its
              content and the page scrolls instead of the panel.
            */}
            <div
                tabIndex={0}
                aria-label="Recorded events, newest first"
                className="min-h-0 flex-1 overflow-y-auto focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-cyan-300"
                style={{ maxHeight: 'min(55vh, 520px)' }}
            >
                {[...events].reverse().map((event) => (
                    <TimelineRow
                        key={event.id}
                        event={event}
                        players={players}
                        teams={teams}
                        voided={voidedIds.has(event.id)}
                        disabled={disabled}
                        onRequestVoid={onRequestVoid}
                    />
                ))}
                {events.length === 0 && (
                    <p className="p-6 text-center text-sm text-muted-foreground">
                        Events will appear here as they are recorded.
                    </p>
                )}
            </div>
        </section>
    );
}
