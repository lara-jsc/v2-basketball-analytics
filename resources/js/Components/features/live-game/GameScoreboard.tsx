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
    onClockAction: (action: 'start' | 'stop' | 'reset_period' | 'set_period', period?: number) => void;
}

export function GameScoreboard({ status, clock, homeTeam, opponentTeam, score, processing, onClockAction }: GameScoreboardProps) {
    const isFinished = status === 'finished';

    return (
        <section className="grid gap-3 border-b border-border bg-background/90 px-3 py-3 backdrop-blur-sm sm:grid-cols-[1fr_auto_1fr] sm:items-center sm:px-5" aria-label="Game scoreboard">
            <TeamScore align="left" team={homeTeam} score={score.home} />
            <div className="order-first flex min-w-[250px] flex-col items-center gap-1 sm:order-none">
                <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.12em] text-cyan-300">
                    <span className={`h-2 w-2 rounded-full ${clock.running ? 'bg-cyan-400 animate-pulse' : 'bg-slate-500'}`} />
                    {isFinished ? 'Final' : clock.running ? 'Clock synced' : 'Clock stopped'}
                </div>
                <div className="font-mono text-4xl font-black tabular-nums text-foreground sm:text-5xl">{clockLabel(clock.seconds_remaining)}</div>
                <div className="flex items-center gap-2">
                    <span className="min-w-12 text-center text-xs font-bold uppercase tracking-[0.1em] text-muted-foreground">Q{clock.period}</span>
                    {!isFinished && (
                        <>
                            <button type="button" onClick={() => onClockAction(clock.running ? 'stop' : 'start')} disabled={processing} aria-label={clock.running ? 'Stop clock' : 'Start clock'} title={clock.running ? 'Stop clock' : 'Start clock'} className="flex h-9 w-9 items-center justify-center rounded-md border border-cyan-400/35 bg-cyan-400/10 text-cyan-200 transition-colors hover:bg-cyan-400/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300 disabled:opacity-50">
                                {clock.running ? <Pause size={16} /> : <Play size={16} />}
                            </button>
                            <button type="button" onClick={() => onClockAction('reset_period')} disabled={processing} aria-label="Reset period clock" title="Reset period clock" className="flex h-9 w-9 items-center justify-center rounded-md border border-border bg-muted/40 text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300 disabled:opacity-50">
                                <TimerReset size={16} />
                            </button>
                            <button type="button" onClick={() => onClockAction('set_period', clock.period === 4 ? 1 : clock.period + 1)} disabled={processing} aria-label="Advance period" title="Advance period" className="flex h-9 w-9 items-center justify-center rounded-md border border-border bg-muted/40 text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300 disabled:opacity-50">
                                <RotateCcw size={16} />
                            </button>
                        </>
                    )}
                </div>
            </div>
            <TeamScore align="right" team={opponentTeam} score={score.opponent} />
        </section>
    );
}

function TeamScore({ team, score, align }: { team?: Team; score: number; align: 'left' | 'right' }) {
    return (
        <div className={`flex min-w-0 items-center gap-3 ${align === 'right' ? 'justify-self-end text-right' : ''}`}>
            {team?.logo_path && <img src={team.logo_path} alt="" className="h-9 w-9 shrink-0 object-contain" />}
            <div className="min-w-0">
                <p className="truncate text-xs font-semibold uppercase tracking-[0.1em] text-muted-foreground">{team?.code ?? 'Team'}</p>
                <p className="truncate text-base font-bold text-foreground">{team?.name ?? 'Opponent'}</p>
            </div>
            <div className="font-mono text-4xl font-black tabular-nums text-amber-300 sm:text-5xl">{score}</div>
        </div>
    );
}
