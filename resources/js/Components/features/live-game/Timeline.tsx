import { clockLabel, eventLabel } from './live-game-utils';
import type { LiveGameEvent, Player } from '@/types';
import { Ban, History } from 'lucide-react';

interface TimelineProps {
    events: LiveGameEvent[];
    players: Player[];
    disabled: boolean;
    onVoid: (eventId: number) => void;
}

export function Timeline({ events, players, disabled, onVoid }: TimelineProps) {
    const voidedIds = new Set(events.filter((event) => event.type === 'correction' && event.voids_event_id).map((event) => event.voids_event_id));

    return <section className="flex min-h-[300px] flex-col rounded-lg border border-border bg-card" aria-labelledby="timeline-heading"><div className="flex items-center justify-between border-b border-border px-4 py-3"><h2 id="timeline-heading" className="flex items-center gap-2 text-sm font-bold uppercase tracking-[0.1em] text-foreground"><History size={16} className="text-cyan-300" /> Timeline</h2><span className="text-xs text-muted-foreground">{events.length} events</span></div><div className="min-h-0 flex-1 overflow-y-auto">{[...events].reverse().map((event) => { const voided = voidedIds.has(event.id); return <div key={event.id} className={`grid min-h-[62px] grid-cols-[auto_1fr_auto] items-center gap-3 border-b border-border/70 px-4 py-2 last:border-b-0 ${voided ? 'opacity-45' : 'hover:bg-muted/25'}`}><span className="font-mono text-xs text-cyan-200">Q{event.period}<br />{clockLabel(event.clock_seconds_remaining)}</span><div className="min-w-0"><p className="truncate text-sm font-semibold capitalize text-foreground">{eventLabel(event, players)}</p><p className="text-xs text-muted-foreground">#{event.sequence} {event.type === 'correction' ? 'Correction' : voided ? 'Voided' : event.team_scope}</p></div>{event.type !== 'correction' && !voided ? <button type="button" disabled={disabled} onClick={() => onVoid(event.id)} aria-label={`Void event ${event.sequence}`} title="Void event" className="flex h-8 w-8 items-center justify-center rounded-md border border-border text-muted-foreground transition-colors hover:border-red-300/60 hover:bg-red-400/10 hover:text-red-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300 disabled:opacity-40"><Ban size={14} /></button> : <span className="h-8 w-8" />}</div>; })}{events.length === 0 && <p className="p-6 text-center text-sm text-muted-foreground">Events will appear here as they are recorded.</p>}</div></section>;
}
