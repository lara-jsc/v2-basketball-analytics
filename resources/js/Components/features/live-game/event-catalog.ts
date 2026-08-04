import { CircleDot, Hand, ShieldAlert, Target, Timer, TrendingDown, Undo2 } from 'lucide-react';

/**
 * Mirror of App\Services\LiveGame\LiveGameEventRules. Change both together.
 * The server is authoritative; this exists so a button is dark for the same
 * reason the API would refuse it.
 */
export type ClockRequirement = 'running' | 'stopped' | 'any';
export type ClockState = 'running' | 'stopped' | 'expired';
export type EventGroupKey = 'scoring' | 'play' | 'fouls' | 'game';

/** FIBA: a player is disqualified on their 5th personal foul. */
export const MAX_PERSONAL_FOULS = 5;

export interface RecordableEvent {
    type: string;
    team_scope: 'own' | 'opponent' | 'game';
    player_id?: number;
    payload?: Record<string, unknown>;
}

export interface PadEvent {
    key: string;
    label: string;
    icon: typeof Target;
    group: EventGroupKey;
    clock: ClockRequirement;
    tone?: 'amber';
    isPersonalFoul?: boolean;
    /** Team-level events (a timeout) carry no player, so they need no selection. */
    requiresPlayer: boolean;
    event: Omit<RecordableEvent, 'player_id'>;
}

export const PAD_GROUPS: Array<{ key: EventGroupKey; label: string }> = [
    { key: 'scoring', label: 'Scoring' },
    { key: 'play', label: 'Play' },
    { key: 'fouls', label: 'Fouls' },
    { key: 'game', label: 'Game' },
];

export const PAD_EVENTS: PadEvent[] = [
    { key: '2pt-made', label: '2PT made', icon: Target, group: 'scoring', clock: 'running', tone: 'amber', requiresPlayer: true, event: { type: 'shot_made', team_scope: 'own', payload: { points: 2 } } },
    { key: '2pt-miss', label: '2PT miss', icon: TrendingDown, group: 'scoring', clock: 'running', requiresPlayer: true, event: { type: 'shot_missed', team_scope: 'own', payload: { points: 2 } } },
    { key: '3pt-made', label: '3PT made', icon: Target, group: 'scoring', clock: 'running', tone: 'amber', requiresPlayer: true, event: { type: 'shot_made', team_scope: 'own', payload: { points: 3 } } },
    { key: '3pt-miss', label: '3PT miss', icon: TrendingDown, group: 'scoring', clock: 'running', requiresPlayer: true, event: { type: 'shot_missed', team_scope: 'own', payload: { points: 3 } } },
    { key: 'ft-made', label: 'FT made', icon: CircleDot, group: 'scoring', clock: 'stopped', tone: 'amber', requiresPlayer: true, event: { type: 'free_throw_made', team_scope: 'own' } },
    { key: 'ft-miss', label: 'FT miss', icon: CircleDot, group: 'scoring', clock: 'stopped', requiresPlayer: true, event: { type: 'free_throw_missed', team_scope: 'own' } },

    { key: 'reb-off', label: 'Off reb', icon: Hand, group: 'play', clock: 'running', requiresPlayer: true, event: { type: 'rebound', team_scope: 'own', payload: { kind: 'offensive' } } },
    { key: 'reb-def', label: 'Def reb', icon: Hand, group: 'play', clock: 'running', requiresPlayer: true, event: { type: 'rebound', team_scope: 'own', payload: { kind: 'defensive' } } },
    { key: 'assist', label: 'Assist', icon: Undo2, group: 'play', clock: 'running', requiresPlayer: true, event: { type: 'assist', team_scope: 'own' } },
    { key: 'turnover', label: 'Turnover', icon: TrendingDown, group: 'play', clock: 'running', requiresPlayer: true, event: { type: 'turnover', team_scope: 'own' } },

    { key: 'foul-personal', label: 'Personal', icon: ShieldAlert, group: 'fouls', clock: 'any', isPersonalFoul: true, requiresPlayer: true, event: { type: 'foul', team_scope: 'own', payload: { kind: 'personal' } } },
    { key: 'foul-technical', label: 'Technical', icon: ShieldAlert, group: 'fouls', clock: 'any', requiresPlayer: true, event: { type: 'foul', team_scope: 'own', payload: { kind: 'technical' } } },
    { key: 'foul-flagrant', label: 'Flagrant', icon: ShieldAlert, group: 'fouls', clock: 'any', requiresPlayer: true, event: { type: 'foul', team_scope: 'own', payload: { kind: 'flagrant' } } },

    { key: 'timeout', label: 'Timeout', icon: Timer, group: 'game', clock: 'stopped', requiresPlayer: false, event: { type: 'timeout', team_scope: 'own' } },
];

export function isEventAllowed(clock: ClockRequirement, state: ClockState): boolean {
    if (state === 'expired') {
        return false;
    }

    return clock === 'any' || clock === state;
}

/** The reason a clock state blocks an event, phrased as the action that unblocks it. */
export function clockBlockReason(clock: ClockRequirement, state: ClockState): string | null {
    if (state === 'expired') {
        return 'The period has ended — advance the period to keep recording.';
    }

    if (isEventAllowed(clock, state)) {
        return null;
    }

    return clock === 'running'
        ? 'Recorded with the clock running — start the clock first.'
        : 'Recorded with the clock stopped — stop the clock first.';
}

/** The reason a single pad button is unavailable, or null when it is available. */
export function padEventBlockReason(
    padEvent: PadEvent,
    clockState: ClockState,
    hasSelectedPlayer: boolean,
    selectedPlayerPersonalFouls: number,
): string | null {
    if (padEvent.requiresPlayer && !hasSelectedPlayer) {
        return 'Select an active player first.';
    }

    const clockReason = clockBlockReason(padEvent.clock, clockState);
    if (clockReason !== null) {
        return clockReason;
    }

    if (padEvent.isPersonalFoul && selectedPlayerPersonalFouls >= MAX_PERSONAL_FOULS) {
        return `${MAX_PERSONAL_FOULS} personal fouls — this player is disqualified.`;
    }

    return null;
}
