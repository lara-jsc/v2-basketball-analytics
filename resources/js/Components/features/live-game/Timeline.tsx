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
            className="flex max-h-48 flex-col overflow-hidden rounded-lg border border-border bg-card lg:max-h-none lg:min-h-0 lg:flex-1"
            aria-labelledby="timeline-heading"
        >
            <div className="flex shrink-0 items-center justify-between border-b border-border px-4 py-2.5">
                <h2
                    id="timeline-heading"
                    className="flex items-center gap-2 text-sm font-bold uppercase tracking-[0.1em] text-foreground"
                >
                    <History size={16} className="live-text-info" /> Timeline
                </h2>
                <span className="text-xs text-muted-foreground">
                    {recordedCount} recorded
                    {voidedIds.size > 0 ? ` · ${voidedIds.size} voided` : ''}
                </span>
            </div>
            {/* Parent column must be min-h-0 flex; this panel flex-1 fills leftover height and scrolls inside. */}
            <div
                tabIndex={0}
                aria-label="Recorded events, newest first"
                className="min-h-0 flex-1 overflow-y-auto focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-cyan-300"
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
