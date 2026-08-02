import { ActiveLineup } from '@/Components/features/live-game/ActiveLineup';
import { AlertsPanel } from '@/Components/features/live-game/AlertsPanel';
import { BenchSubstitution } from '@/Components/features/live-game/BenchSubstitution';
import { EventPad, type RecordableEvent } from '@/Components/features/live-game/EventPad';
import { GameScoreboard } from '@/Components/features/live-game/GameScoreboard';
import { Timeline } from '@/Components/features/live-game/Timeline';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { LiveGame, LiveGameSnapshot, PageProps, Player, Team } from '@/types';
import axios from 'axios';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, ChevronLeft, CircleStop, Play, Radio } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

interface LiveGameShowProps extends PageProps {
    liveGame: LiveGame;
    snapshot: LiveGameSnapshot;
    teams: Team[];
    players: Player[];
}

export default function LiveGamesShow({ liveGame, snapshot: initialSnapshot, teams, players }: LiveGameShowProps) {
    const [snapshot, setSnapshot] = useState(initialSnapshot);
    const [selectedPlayerId, setSelectedPlayerId] = useState<number | null>(initialSnapshot.active_player_ids[0] ?? null);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [clockTick, setClockTick] = useState(() => Date.now());
    const homeTeam = teams.find((team) => team.id === liveGame.home_team_id) ?? liveGame.home_team;
    const opponentTeam = teams.find((team) => team.id === liveGame.opponent_team_id) ?? liveGame.opponent_team;
    const selectedPlayer = players.find((player) => player.id === selectedPlayerId);

    useEffect(() => {
        setSnapshot(initialSnapshot);
        setSelectedPlayerId(initialSnapshot.active_player_ids[0] ?? null);
    }, [initialSnapshot]);

    useEffect(() => {
        const channel = `live-game.${liveGame.id}`;
        window.Echo?.private(channel).listen('LiveGameStateUpdated', (nextSnapshot: LiveGameSnapshot) => {
            setSnapshot(nextSnapshot);
            setSelectedPlayerId((current) => nextSnapshot.active_player_ids.includes(current ?? -1) ? current : nextSnapshot.active_player_ids[0] ?? null);
            setClockTick(Date.now());
        });

        return () => window.Echo?.leave(channel);
    }, [liveGame.id]);

    useEffect(() => {
        if (!snapshot.clock.running) return;
        const interval = window.setInterval(() => setClockTick(Date.now()), 1000);
        return () => window.clearInterval(interval);
    }, [snapshot.clock.running]);

    const displayedClock = useMemo(() => {
        if (!snapshot.clock.running) return snapshot.clock;
        const elapsed = Math.floor((clockTick - Date.parse(snapshot.clock.server_now)) / 1000);
        return { ...snapshot.clock, seconds_remaining: Math.max(0, snapshot.clock.seconds_remaining - Math.max(0, elapsed)) };
    }, [clockTick, snapshot.clock]);

    async function postSnapshot(url: string, data: object = {}): Promise<void> {
        setProcessing(true);
        setError(null);
        try {
            const response = await axios.post<LiveGameSnapshot>(url, data);
            setSnapshot(response.data);
            setClockTick(Date.now());
        } catch (requestError) {
            if (axios.isAxiosError(requestError) && requestError.response?.data?.message) setError(requestError.response.data.message as string);
            else setError('The game state could not be updated. Check the connection and try again.');
        } finally { setProcessing(false); }
    }

    function record(event: RecordableEvent): void {
        void postSnapshot(route('live-games.events.store', { liveGame: liveGame.id }), event);
    }
    function clockAction(action: 'start' | 'stop' | 'reset_period' | 'set_period', period?: number): void {
        void postSnapshot(route('live-games.clock.store', { liveGame: liveGame.id }), { action, ...(period ? { period } : {}) });
    }
    function substitute(playerOutId: number, playerInId: number): void {
        record({ type: 'substitution', team_scope: 'game', payload: { player_out_id: playerOutId, player_in_id: playerInId } });
    }
    function voidEvent(eventId: number): void {
        void postSnapshot(route('live-games.correction', { liveGame: liveGame.id }), { voids_event_id: eventId });
    }
    function startGame(): void { router.post(route('live-games.start', { liveGame: liveGame.id })); }
    function finishGame(): void { if (window.confirm('Finish this live game? The game will be marked finished.')) router.post(route('live-games.finish', { liveGame: liveGame.id })); }

    const eventsDisabled = processing || snapshot.liveGame.status !== 'live';

    return <AuthenticatedLayout><Head title={`${homeTeam?.name ?? 'Live game'} vs ${opponentTeam?.name ?? 'Opponent'}`} /><div className="-mx-5 -my-6 flex min-h-full flex-col overflow-x-hidden"><GameScoreboard status={snapshot.liveGame.status} clock={displayedClock} homeTeam={homeTeam} opponentTeam={opponentTeam} score={snapshot.score} processing={processing} onClockAction={clockAction} /><div className="flex flex-wrap items-center justify-between gap-3 border-b border-border bg-background/75 px-4 py-2 sm:px-5"><Link href={route('live-games.index')} className="flex items-center gap-1.5 text-sm font-semibold text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300"><ChevronLeft size={15} /> Live games</Link><div className="flex items-center gap-2">{snapshot.liveGame.status === 'setup' && <button type="button" onClick={startGame} className="flex h-9 items-center gap-2 rounded-md bg-amber-400 px-3 text-xs font-bold uppercase tracking-wide text-black hover:bg-amber-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300"><Play size={14} /> Start game</button>}{snapshot.liveGame.status === 'live' && <button type="button" onClick={finishGame} className="flex h-9 items-center gap-2 rounded-md border border-red-300/50 bg-red-400/10 px-3 text-xs font-bold uppercase tracking-wide text-red-100 transition-colors hover:bg-red-400/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300"><CircleStop size={14} /> Finish</button>}<span className={`flex h-9 items-center gap-2 rounded-md px-3 text-xs font-bold uppercase tracking-wide ${snapshot.liveGame.status === 'live' ? 'bg-cyan-300/10 text-cyan-100' : 'bg-muted/50 text-muted-foreground'}`}><Radio size={14} /> {snapshot.liveGame.status}</span></div></div>{error && <div role="alert" className="mx-4 mt-4 flex items-center gap-2 rounded-md border border-red-300/40 bg-red-400/10 px-4 py-3 text-sm text-red-100 sm:mx-5"><AlertTriangle size={16} /> {error}</div>}<div className="grid flex-1 gap-4 p-4 sm:p-5 xl:grid-cols-[minmax(250px,0.72fr)_minmax(440px,1.35fr)_minmax(280px,0.85fr)]"><div className="flex flex-col gap-4"><ActiveLineup players={players} activePlayerIds={snapshot.active_player_ids} stats={snapshot.stats} selectedPlayerId={selectedPlayerId} onSelectPlayer={setSelectedPlayerId} /><BenchSubstitution players={players} activePlayerIds={snapshot.active_player_ids} disabled={eventsDisabled} onSubstitute={substitute} /></div><div className="flex min-w-0 flex-col gap-4"><EventPad selectedPlayer={selectedPlayer} disabled={eventsDisabled} onRecord={record} />{snapshot.liveGame.status !== 'live' && <div className="rounded-lg border border-dashed border-border bg-muted/20 px-4 py-3 text-sm text-muted-foreground">Start the game to enable event recording and substitutions.</div>}<Timeline events={snapshot.events} players={players} disabled={processing} onVoid={voidEvent} /></div><aside className="min-w-0"><AlertsPanel alerts={snapshot.alerts} players={players} /></aside></div></div></AuthenticatedLayout>;
}
