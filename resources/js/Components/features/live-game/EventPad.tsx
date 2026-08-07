import {
    MAX_PERSONAL_FOULS,
    PAD_EVENTS,
    PAD_GROUPS,
    padEventBlockReason,
    type ClockState,
    type PadEvent,
    type RecordableEvent,
} from './event-catalog';
import type { Player } from '@/types';

export type { RecordableEvent } from './event-catalog';

interface EventPadProps {
    selectedPlayer?: Player;
    clockState: ClockState;
    selectedPlayerPersonalFouls: number;
    /** Non-clock reason the whole pad is unavailable: not live, not a coach, request in flight. */
    blockedReason: string | null;
    onRecord: (event: RecordableEvent) => void;
}

export function EventPad({
    selectedPlayer,
    clockState,
    selectedPlayerPersonalFouls,
    blockedReason,
    onRecord,
}: EventPadProps) {
    const disqualified = selectedPlayerPersonalFouls >= MAX_PERSONAL_FOULS;

    return (
        <section className="shrink-0 rounded-lg border border-amber-300/30 bg-card p-3 shadow-sm" aria-labelledby="event-pad-heading">
            <div className="mb-3">
                <h2 id="event-pad-heading" className="text-sm font-bold uppercase tracking-[0.1em] text-foreground">
                    Event pad
                </h2>
                <p className="mt-1 truncate text-xs text-muted-foreground">
                    {selectedPlayer
                        ? `${selectedPlayer.first_name} ${selectedPlayer.last_name} selected`
                        : 'Select an active player'}
                    {selectedPlayer && disqualified ? (
                        <span className="live-text-danger"> · disqualified ({MAX_PERSONAL_FOULS} fouls)</span>
                    ) : null}
                </p>
            </div>

            <div className="grid gap-3">
                {PAD_GROUPS.map((group) => {
                    const groupEvents = PAD_EVENTS.filter((padEvent) => padEvent.group === group.key);

                    return (
                        <div key={group.key}>
                            <p className="mb-1.5 text-[11px] font-bold uppercase tracking-[0.12em] text-muted-foreground">
                                {group.label}
                            </p>
                            <div className="grid grid-cols-2 gap-1.5 sm:grid-cols-3 lg:grid-cols-3">
                                {groupEvents.map((padEvent) => (
                                    <PadButton
                                        key={padEvent.key}
                                        padEvent={padEvent}
                                        reason={
                                            blockedReason ??
                                            padEventBlockReason(
                                                padEvent,
                                                clockState,
                                                Boolean(selectedPlayer),
                                                selectedPlayerPersonalFouls,
                                            )
                                        }
                                        onRecord={() =>
                                            onRecord({
                                                ...padEvent.event,
                                                ...(padEvent.requiresPlayer && selectedPlayer
                                                    ? { player_id: selectedPlayer.id }
                                                    : {}),
                                            })
                                        }
                                    />
                                ))}
                            </div>
                        </div>
                    );
                })}
            </div>
        </section>
    );
}

function PadButton({
    padEvent,
    reason,
    onRecord,
}: {
    padEvent: PadEvent;
    reason: string | null;
    onRecord: () => void;
}) {
    const Icon = padEvent.icon;
    const blocked = reason !== null;

    return (
        <button
            type="button"
            title={reason ?? padEvent.label}
            aria-label={blocked ? `${padEvent.label} — ${reason}` : padEvent.label}
            disabled={blocked}
            onClick={onRecord}
            className={`flex h-14 cursor-pointer flex-col items-center justify-center gap-1 rounded-md border text-xs font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300 disabled:cursor-not-allowed disabled:opacity-40 ${
                padEvent.tone === 'amber'
                    ? 'live-badge-warn hover:bg-amber-200/90 dark:hover:bg-amber-300/20'
                    : 'border-border bg-muted/30 text-foreground hover:bg-muted/70'
            }`}
        >
            <Icon size={16} />
            <span>{padEvent.label}</span>
        </button>
    );
}
