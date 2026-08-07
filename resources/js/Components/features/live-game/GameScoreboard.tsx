import { clockLabel } from './live-game-utils';
import type { LiveGame, LiveGameClock, Team } from '@/types';
import { Pause, Play, RotateCcw, TimerReset } from 'lucide-react';

interface GameScoreboardProps {
    status: LiveGame['status'];
    clock: LiveGameClock;
    homeTeam?: Team;
    opponentTeam?: Team;
    score: { home: number; opponent: number };
    processing: boolean;
    /** True for either bench's main coach — clock authority is not creator-only. */
    canControlClock?: boolean;
    /** Both benches have a main coach, so the other side can move the clock too. */
    clockShared?: boolean;
    viewerSide?: 'home' | 'opponent' | null;
    /** Immediate actions: start and stop, both reversible by either coach. */
    onClockAction: (action: 'start' | 'stop', period?: number) => void;
    /** Destructive actions the parent confirms first: reset discards elapsed time, advance cannot be undone. */
    onClockActionRequest: (action: 'reset_period' | 'set_period') => void;
}

export function GameScoreboard({ status, clock, homeTeam, opponentTeam, score, processing, canControlClock = true, clockShared = false, viewerSide = null, onClockAction, onClockActionRequest }: GameScoreboardProps) {
    const isFinished = status === 'finished';
    const periodEnded = !isFinished && clock.seconds_remaining === 0;
    const canStart = !periodEnded;
    const clockNote = isFinished
        ? null
        : canControlClock
            ? (clockShared ? '' : null)
            : 'Clock controlled by the team coaches.';

    return (
        <section className="grid gap-3 border-b border-border bg-background/90 px-3 py-3 backdrop-blur-sm sm:grid-cols-[1fr_auto_1fr] sm:items-center sm:px-5" aria-label="Game scoreboard">
            <TeamScore align="left" team={homeTeam} score={score.home} highlighted={viewerSide === 'home'} sideLabel="Your team" />
            <div className="order-first flex min-w-[250px] flex-col items-center gap-1 sm:order-none">
                <div className={`flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.12em] ${periodEnded ? 'live-text-warn' : 'live-text-info'}`}>
                    <span className={`h-2 w-2 rounded-full ${clock.running ? 'bg-cyan-400 animate-pulse' : periodEnded ? 'bg-amber-400' : 'bg-slate-500'}`} />
                    {isFinished ? 'Final' : periodEnded ? `Q${clock.period} ended` : clock.running ? 'Clock synced' : 'Clock stopped'}
                </div>
                <div className="font-mono text-4xl font-black tabular-nums text-foreground sm:text-5xl">{clockLabel(clock.seconds_remaining)}</div>
                <div className="flex items-center gap-2">
                    <span className="min-w-12 text-center text-xs font-bold uppercase tracking-[0.1em] text-muted-foreground">Q{clock.period}</span>
                    {!isFinished && canControlClock && (
                        <>
                            <button type="button" onClick={() => onClockAction(clock.running ? 'stop' : 'start')} disabled={processing || (!clock.running && !canStart)} aria-label={clock.running ? 'Stop clock' : 'Start clock'} title={clock.running ? 'Stop clock' : periodEnded ? `Q${clock.period} has ended — advance the period or reset the clock` : 'Start clock'} className="live-badge-info flex h-9 w-9 cursor-pointer items-center justify-center rounded-md transition-colors hover:bg-cyan-200/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300 disabled:cursor-not-allowed disabled:opacity-50 dark:hover:bg-cyan-300/20">
                                {clock.running ? <Pause size={16} /> : <Play size={16} />}
                            </button>
                            <button type="button" onClick={() => onClockActionRequest('reset_period')} disabled={processing} aria-label="Reset period clock" title="Reset period clock" className="flex h-9 w-9 cursor-pointer items-center justify-center rounded-md border border-border bg-muted/40 text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300 disabled:opacity-50">
                                <TimerReset size={16} />
                            </button>
                            {clock.period < 4 && <button type="button" onClick={() => onClockActionRequest('set_period')} disabled={processing} aria-label="Advance period" title="Advance period" className={`flex h-9 w-9 cursor-pointer items-center justify-center rounded-md border transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300 disabled:opacity-50 ${periodEnded ? 'live-badge-warn hover:bg-amber-200/90 dark:hover:bg-amber-300/25' : 'border-border bg-muted/40 text-muted-foreground hover:text-foreground'}`}>
                                <RotateCcw size={16} />
                            </button>}
                        </>
                    )}
                </div>
                {clockNote && (
                    <p className="text-center text-[11px] font-semibold uppercase tracking-[0.1em] text-muted-foreground">
                        {clockNote}
                    </p>
                )}
            </div>
            <TeamScore align="right" team={opponentTeam} score={score.opponent} highlighted={viewerSide === 'opponent'} sideLabel="Your team" />
        </section>
    );
}

function TeamScore({ team, score, align, highlighted, sideLabel }: { team?: Team; score: number; align: 'left' | 'right'; highlighted: boolean; sideLabel: string }) {
    return (
        <div
            className={`flex min-w-0 items-center gap-3 rounded-lg px-2 py-1.5 transition-colors ${align === 'right' ? 'justify-self-end text-right' : ''} ${
                highlighted ? 'border border-amber-300/50 bg-amber-300/10 ring-1 ring-amber-300/30' : 'border border-transparent'
            }`}
            aria-current={highlighted ? 'true' : undefined}
        >
            {team?.logo_path && <img src={team.logo_path} alt="" className="h-9 w-9 shrink-0 object-contain" />}
            <div className="min-w-0">
                {highlighted && (
                    <p className="live-text-warn truncate text-[10px] font-bold uppercase tracking-[0.14em]">{sideLabel}</p>
                )}
                <p className="truncate text-xs font-semibold uppercase tracking-[0.1em] text-muted-foreground">{team?.code ?? 'Team'}</p>
                <p className="truncate text-base font-bold text-foreground">{team?.name ?? 'Opponent'}</p>
            </div>
            <div className="font-mono text-4xl font-black tabular-nums text-foreground sm:text-5xl">{score}</div>
        </div>
    );
}
