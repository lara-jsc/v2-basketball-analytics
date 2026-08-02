import type { Player } from '@/types';
import { CircleDot, Hand, ShieldAlert, Target, Timer, TrendingDown, Undo2 } from 'lucide-react';

export interface RecordableEvent {
    type: string;
    team_scope: 'own' | 'opponent' | 'game';
    player_id?: number;
    payload?: Record<string, unknown>;
}

interface EventPadProps {
    selectedPlayer?: Player;
    disabled: boolean;
    onRecord: (event: RecordableEvent) => void;
}

const eventButtons: Array<{ label: string; icon: typeof Target; event: Omit<RecordableEvent, 'player_id'>; tone?: 'amber' | 'neutral' }> = [
    { label: '2PT made', icon: Target, event: { type: 'shot_made', team_scope: 'own', payload: { points: 2 } }, tone: 'amber' },
    { label: '3PT made', icon: Target, event: { type: 'shot_made', team_scope: 'own', payload: { points: 3 } }, tone: 'amber' },
    { label: '2PT miss', icon: TrendingDown, event: { type: 'shot_missed', team_scope: 'own', payload: { points: 2 } } },
    { label: '3PT miss', icon: TrendingDown, event: { type: 'shot_missed', team_scope: 'own', payload: { points: 3 } } },
    { label: 'FT made', icon: CircleDot, event: { type: 'free_throw_made', team_scope: 'own' }, tone: 'amber' },
    { label: 'FT miss', icon: CircleDot, event: { type: 'free_throw_missed', team_scope: 'own' } },
    { label: 'Rebound', icon: Hand, event: { type: 'rebound', team_scope: 'own', payload: { kind: 'defensive' } } },
    { label: 'Assist', icon: Undo2, event: { type: 'assist', team_scope: 'own' } },
    { label: 'Turnover', icon: TrendingDown, event: { type: 'turnover', team_scope: 'own' } },
    { label: 'Foul', icon: ShieldAlert, event: { type: 'foul', team_scope: 'own', payload: { kind: 'personal' } } },
    { label: 'Timeout', icon: Timer, event: { type: 'timeout', team_scope: 'game' } },
];

export function EventPad({ selectedPlayer, disabled, onRecord }: EventPadProps) {
    return (
        <section className="rounded-lg border border-border bg-card p-4" aria-labelledby="event-pad-heading">
            <div className="mb-3">
                <h2 id="event-pad-heading" className="text-sm font-bold uppercase tracking-[0.1em] text-foreground">Event pad</h2>
                <p className="mt-1 truncate text-xs text-muted-foreground">
                    {selectedPlayer ? `${selectedPlayer.first_name} ${selectedPlayer.last_name} selected` : 'Select an active player'}
                </p>
            </div>
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-5 xl:grid-cols-2">
                {eventButtons.map(({ label, icon: Icon, event, tone }) => {
                    const requiresPlayer = event.team_scope === 'own';
                    return (
                        <button
                            key={label}
                            type="button"
                            title={label}
                            disabled={disabled || (requiresPlayer && !selectedPlayer)}
                            onClick={() => onRecord({ ...event, ...(requiresPlayer && selectedPlayer ? { player_id: selectedPlayer.id } : {}) })}
                            className={`flex h-14 flex-col items-center justify-center gap-1 rounded-md border text-xs font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300 disabled:cursor-not-allowed disabled:opacity-40 ${tone === 'amber' ? 'border-amber-300/35 bg-amber-300/10 text-amber-100 hover:bg-amber-300/20' : 'border-border bg-muted/30 text-foreground hover:bg-muted/70'}`}
                        >
                            <Icon size={16} />
                            <span>{label}</span>
                        </button>
                    );
                })}
            </div>
        </section>
    );
}
