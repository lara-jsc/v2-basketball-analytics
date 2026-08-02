import { playerName } from './live-game-utils';
import type { LiveGamePlayerStat, Player } from '@/types';
import { UsersRound } from 'lucide-react';

interface ActiveLineupProps {
    players: Player[];
    activePlayerIds: number[];
    stats: LiveGamePlayerStat[];
    selectedPlayerId: number | null;
    onSelectPlayer: (playerId: number) => void;
}

export function ActiveLineup({ players, activePlayerIds, stats, selectedPlayerId, onSelectPlayer }: ActiveLineupProps) {
    const activePlayers = activePlayerIds.map((id) => players.find((player) => player.id === id)).filter((player): player is Player => Boolean(player));

    return (
        <section className="min-h-[250px] rounded-lg border border-border bg-card p-4" aria-labelledby="active-lineup-heading">
            <div className="mb-3 flex items-center justify-between">
                <h2 id="active-lineup-heading" className="flex items-center gap-2 text-sm font-bold uppercase tracking-[0.1em] text-foreground"><UsersRound size={16} className="text-amber-300" /> Active lineup</h2>
                <span className="text-xs font-semibold text-muted-foreground">{activePlayers.length}/5 on court</span>
            </div>
            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-1">
                {activePlayers.map((player) => {
                    const stat = stats.find((entry) => entry.player_id === player.id);
                    const selected = selectedPlayerId === player.id;
                    return (
                        <button key={player.id} type="button" onClick={() => onSelectPlayer(player.id)} className={`grid min-h-[58px] w-full grid-cols-[2.25rem_1fr_auto] items-center gap-2 rounded-md border px-2 text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300 ${selected ? 'border-amber-300/70 bg-amber-300/10' : 'border-border bg-muted/20 hover:bg-muted/50'}`}>
                            <span className="flex h-9 w-9 items-center justify-center rounded-full bg-muted font-mono text-sm font-bold text-amber-200">{player.jersey_number}</span>
                            <span className="min-w-0"><span className="block truncate text-sm font-semibold text-foreground">{playerName(player)}</span><span className="block truncate text-xs text-muted-foreground">{player.role ?? 'Player'}</span></span>
                            <span className="text-right font-mono text-xs text-cyan-200"><span className="block">{stat?.points ?? 0} PTS</span><span className="block text-muted-foreground">{(stat?.plus_minus ?? 0) >= 0 ? '+' : ''}{stat?.plus_minus ?? 0}</span></span>
                        </button>
                    );
                })}
                {activePlayers.length === 0 && <p className="py-8 text-center text-sm text-muted-foreground">Start the game with a five-player lineup.</p>}
            </div>
        </section>
    );
}
