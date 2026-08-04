import { ActiveLineup } from '@/Components/features/live-game/ActiveLineup';
import { AlertsPanel } from '@/Components/features/live-game/AlertsPanel';
import { AssignAssistantPanel } from '@/Components/features/live-game/AssignAssistantPanel';
import { LineupConfirmModal } from '@/Components/features/live-game/LineupConfirmModal';
import { BenchSubstitution } from '@/Components/features/live-game/BenchSubstitution';
import { EventPad, type RecordableEvent } from '@/Components/features/live-game/EventPad';
import { GameScoreboard } from '@/Components/features/live-game/GameScoreboard';
import { Timeline } from '@/Components/features/live-game/Timeline';
import { VoidEventConfirmModal } from '@/Components/features/live-game/VoidEventConfirmModal';
import { clockBlockReason, type ClockState } from '@/Components/features/live-game/event-catalog';
import { eventLabel, playerName } from '@/Components/features/live-game/live-game-utils';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { LiveGame, LiveGameEvent, LiveGameSnapshot, PageProps, Player, Team } from '@/types';
import axios from 'axios';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { AlertTriangle, Check, ChevronLeft, CircleStop, Copy, Link2, Play, Radio, UsersRound } from 'lucide-react';
import { FormEvent, useCallback, useEffect, useMemo, useState } from 'react';

interface LiveGameShowProps extends PageProps {
    liveGame: LiveGame;
    snapshot: LiveGameSnapshot;
    teams: Team[];
    homePlayers: Player[];
    opponentPlayers: Player[];
    players: Player[];
    viewerSide: 'home' | 'opponent' | null;
    isCreator: boolean;
    can_stop_clock: boolean;
    controlled_player_ids: number[];
    is_main_coach: boolean;
    team_coaches: Array<{ id: number; name: string }>;
    errors?: { game?: string };
}

export default function LiveGamesShow({
    liveGame,
    snapshot: initialSnapshot,
    teams,
    homePlayers,
    opponentPlayers,
    players,
    viewerSide,
    isCreator,
    can_stop_clock,
    controlled_player_ids,
    team_coaches,
    auth,
    errors = {},
}: LiveGameShowProps) {
    const ownPlayers = viewerSide === 'opponent' ? opponentPlayers : viewerSide === 'home' ? homePlayers : players;
    const [snapshot, setSnapshot] = useState(initialSnapshot);
    const [snapshotReceivedAt, setSnapshotReceivedAt] = useState(() => Date.now());

    const controlledPlayerIdsSet = useMemo(() => new Set(controlled_player_ids), [controlled_player_ids]);
    const ownActiveIds = viewerSide === 'opponent'
        ? (snapshot.opponent_active_player_ids ?? [])
        : (snapshot.active_player_ids ?? []);

    const controlledActiveIds = ownActiveIds.filter((id) => controlledPlayerIdsSet.has(id));
    const controlledRosterPlayers = ownPlayers.filter((player) => controlledPlayerIdsSet.has(player.id));

    const [selectedPlayerId, setSelectedPlayerId] = useState<number | null>(controlledActiveIds[0] ?? null);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [copyFeedback, setCopyFeedback] = useState<string | null>(null);
    const [clockTick, setClockTick] = useState(() => Date.now());
    const [confirmLineupOpen, setConfirmLineupOpen] = useState(false);
    const [assignPanelVisible, setAssignPanelVisible] = useState(false);
    const [assignExpanded, setAssignExpanded] = useState(false);
    const [pendingVoidEvent, setPendingVoidEvent] = useState<LiveGameEvent | null>(null);

    const applySnapshot = useCallback((next: LiveGameSnapshot): void => {
        setSnapshot(next);
        setSnapshotReceivedAt(Date.now());
        setClockTick(Date.now());
    }, []);
    const homeTeam = teams.find((team) => team.id === liveGame.home_team_id) ?? liveGame.home_team;
    const opponentTeam = teams.find((team) => team.id === liveGame.opponent_team_id) ?? liveGame.opponent_team;
    // Resolved from active ∩ controlled, not the roster — a benched player must never
    // leave the pad enabled for an action the server would reject.
    const selectedPlayer = controlledRosterPlayers.find(
        (player) => player.id === selectedPlayerId && controlledActiveIds.includes(player.id),
    );
    const selectedPlayerPersonalFouls = selectedPlayer
        ? (snapshot.stats.find((stat) => stat.player_id === selectedPlayer.id)?.personal_fouls ?? 0)
        : 0;
    const canRecord = viewerSide !== null;
    const isSetup = snapshot.liveGame.status === 'setup';
    const ownLineupReady = viewerSide === 'opponent'
        ? snapshot.opponent_lineup_ready
        : viewerSide === 'home'
            ? snapshot.home_lineup_ready
            : false;
    const waitingForOther = isSetup && ownLineupReady && !snapshot.both_lineups_ready;
    const waitingTeamName = snapshot.home_lineup_ready
        ? (opponentTeam?.name ?? 'opponent')
        : (homeTeam?.name ?? 'home team');

    const lineupForm = useForm({
        starting_player_ids: ownPlayers.slice(0, 5).map((player) => player.id),
        assistant_coach_user_id: null as number | null,
        delegated_player_ids: [] as number[],
    });

    const viewerTeamName = viewerSide === 'opponent'
        ? (opponentTeam?.name ?? 'Opponent')
        : viewerSide === 'home'
            ? (homeTeam?.name ?? 'Home')
            : null;

    const assistantCoachOptions = useMemo(
        () => team_coaches.filter((coach) => coach.id !== auth.user?.id),
        [team_coaches, auth.user?.id],
    );

    useEffect(() => {
        applySnapshot(initialSnapshot);
    }, [initialSnapshot, applySnapshot]);

    useEffect(() => {
        const channel = `live-game.${liveGame.id}`;
        window.Echo?.private(channel).listen('LiveGameStateUpdated', applySnapshot);

        return () => window.Echo?.leave(channel);
    }, [liveGame.id, applySnapshot]);

    // One place clamps the selection to a player who is both controlled and on court —
    // every snapshot source (initial, Echo, own request) flows through this.
    const controlledActiveKey = controlledActiveIds.join(',');

    useEffect(() => {
        setSelectedPlayerId((current) =>
            current !== null && controlledActiveIds.includes(current)
                ? current
                : controlledActiveIds[0] ?? null,
        );
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [controlledActiveKey]);

    useEffect(() => {
        if (!snapshot.clock.running) return;
        const interval = window.setInterval(() => setClockTick(Date.now()), 1000);
        return () => window.clearInterval(interval);
    }, [snapshot.clock.running]);

    const displayedClock = useMemo(() => {
        if (!snapshot.clock.running) return snapshot.clock;

        // seconds_remaining already accounts for server-side elapsed time, so measure from
        // when this snapshot arrived. Never compare Date.now() to server_now — a device with
        // a skewed wall clock would show a wildly wrong clock.
        const elapsed = Math.max(0, Math.floor((clockTick - snapshotReceivedAt) / 1000));

        return { ...snapshot.clock, seconds_remaining: Math.max(0, snapshot.clock.seconds_remaining - elapsed) };
    }, [clockTick, snapshot.clock, snapshotReceivedAt]);

    const clockState: ClockState = displayedClock.seconds_remaining === 0
        ? 'expired'
        : displayedClock.running
            ? 'running'
            : 'stopped';

    async function postSnapshot(url: string, data: object = {}): Promise<void> {
        setProcessing(true);
        setError(null);
        try {
            const response = await axios.post<LiveGameSnapshot>(url, data);
            applySnapshot(response.data);
        } catch (requestError) {
            if (axios.isAxiosError(requestError) && requestError.response?.data?.message) setError(requestError.response.data.message as string);
            else setError('The game state could not be updated. Check the connection and try again.');
        } finally {
            setProcessing(false);
        }
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
    function confirmVoid(): void {
        const target = pendingVoidEvent;
        setPendingVoidEvent(null);

        if (target !== null) {
            void postSnapshot(route('live-games.correction', { liveGame: liveGame.id }), { voids_event_id: target.id });
        }
    }
    function startGame(): void { router.post(route('live-games.start', { liveGame: liveGame.id })); }
    function finishGame(): void {
        if (window.confirm('Finish this live game? The game will be marked finished.')) {
            router.post(route('live-games.finish', { liveGame: liveGame.id }));
        }
    }

    async function copyLink(): Promise<void> {
        try {
            await navigator.clipboard.writeText(window.location.href);
            setCopyFeedback('Link copied — send it to the opponent coach.');
        } catch {
            setCopyFeedback(window.location.href);
        }
    }

    function toggleLineupPlayer(playerId: number): void {
        const selected = lineupForm.data.starting_player_ids;
        lineupForm.setData(
            'starting_player_ids',
            selected.includes(playerId)
                ? selected.filter((id) => id !== playerId)
                : selected.length < 5
                    ? [...selected, playerId]
                    : selected,
        );
    }

    function postLineup(clearAssistant = false): void {
        setConfirmLineupOpen(false);
        if (clearAssistant) {
            lineupForm.setData({
                starting_player_ids: lineupForm.data.starting_player_ids,
                assistant_coach_user_id: null,
                delegated_player_ids: [],
            });
        }
        lineupForm.post(route('live-games.lineup', { liveGame: liveGame.id }));
    }

    function submitLineup(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        if (!lineupReady) {
            return;
        }
        if (assistantCoachOptions.length === 0) {
            postLineup();
            return;
        }
        setConfirmLineupOpen(true);
    }

    const selectedAssistantName =
        assistantCoachOptions.find((coach) => coach.id === lineupForm.data.assistant_coach_user_id)?.name ?? null;
    const hasAssistantAssignment =
        selectedAssistantName !== null && lineupForm.data.delegated_player_ids.length > 0;

    // One reason string covers every non-clock block; the pad adds per-button clock reasons.
    const recordingBlockedReason = !canRecord
        ? 'You are viewing this game. Only coaches tied to the home or opponent team can record events.'
        : snapshot.liveGame.status === 'setup'
            ? (snapshot.both_lineups_ready
                ? 'Start the game to enable event recording and substitutions.'
                : 'Both coaches must submit a starting five before the game can start.')
            : snapshot.liveGame.status === 'finished'
                ? 'This game is finished. Existing events can still be voided from the timeline.'
                : controlledActiveIds.length === 0
                    ? 'None of the players assigned to you are on court.'
                    : processing
                        ? 'Recording…'
                        : null;

    const padUnavailableReason = recordingBlockedReason ?? clockBlockReason('running', clockState);
    const allPlayers = [...homePlayers, ...opponentPlayers];
    const lineupReady = lineupForm.data.starting_player_ids.length === 5;

    return (
        <AuthenticatedLayout>
            <Head title={`${homeTeam?.name ?? 'Live game'} vs ${opponentTeam?.name ?? 'Opponent'}`} />
            <div className="-mx-5 -my-6 flex min-h-full flex-col overflow-x-hidden">
                <GameScoreboard
                    status={snapshot.liveGame.status}
                    clock={displayedClock}
                    homeTeam={homeTeam}
                    opponentTeam={opponentTeam}
                    score={snapshot.score}
                    processing={processing}
                    canControlClock={isCreator && snapshot.both_lineups_ready}
                    canStopClock={can_stop_clock && snapshot.both_lineups_ready}
                    viewerSide={viewerSide}
                    onClockAction={clockAction}
                />
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border bg-background/75 px-4 py-2 sm:px-5">
                    <Link href={route('live-games.index')} className="flex items-center gap-1.5 text-sm font-semibold text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300">
                        <ChevronLeft size={15} /> Live games
                    </Link>
                    <div className="flex flex-wrap items-center gap-2">
                        {isSetup && (
                            <button
                                type="button"
                                onClick={() => void copyLink()}
                                className="flex h-11 min-w-11 items-center gap-2 rounded-md border border-cyan-300/40 bg-cyan-300/10 px-3 text-xs font-bold uppercase tracking-wide text-cyan-100 transition-colors hover:bg-cyan-300/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300"
                            >
                                <Copy size={14} /> Copy link
                            </button>
                        )}
                        {isSetup && isCreator && (
                            <button
                                type="button"
                                onClick={startGame}
                                disabled={!snapshot.both_lineups_ready}
                                className="flex h-11 items-center gap-2 rounded-md bg-amber-400 px-3 text-xs font-bold uppercase tracking-wide text-black hover:bg-amber-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <Play size={14} /> Start game
                            </button>
                        )}
                        {snapshot.liveGame.status === 'live' && isCreator && (
                            <button type="button" onClick={finishGame} className="flex h-11 items-center gap-2 rounded-md border border-red-300/50 bg-red-400/10 px-3 text-xs font-bold uppercase tracking-wide text-red-100 transition-colors hover:bg-red-400/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300">
                                <CircleStop size={14} /> Finish
                            </button>
                        )}
                        <span className={`flex h-11 items-center gap-2 rounded-md px-3 text-xs font-bold uppercase tracking-wide ${snapshot.liveGame.status === 'live' ? 'bg-cyan-300/10 text-cyan-100' : 'bg-muted/50 text-muted-foreground'}`}>
                            <Radio size={14} /> {snapshot.liveGame.status}
                            {viewerTeamName ? ` · ${viewerTeamName}` : ''}
                        </span>
                    </div>
                </div>
                {copyFeedback && (
                    <div className="mx-4 mt-4 flex items-center gap-2 rounded-md border border-cyan-300/40 bg-cyan-300/10 px-4 py-3 text-sm text-cyan-100 sm:mx-5">
                        <Link2 size={16} /> {copyFeedback}
                    </div>
                )}
                {(error || errors.game) && (
                    <div role="alert" className="mx-4 mt-4 flex items-center gap-2 rounded-md border border-red-300/40 bg-red-400/10 px-4 py-3 text-sm text-red-100 sm:mx-5">
                        <AlertTriangle size={16} /> {error ?? errors.game}
                    </div>
                )}

                {isSetup && viewerSide && !ownLineupReady && (
                    <form onSubmit={submitLineup} className="mx-4 mt-4 space-y-4 sm:mx-5">
                        <div className="rounded-lg border border-border bg-card p-5">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h2 className="text-sm font-bold uppercase tracking-[0.1em] text-foreground">Submit your starting five</h2>
                                    <p className="mt-1 text-sm text-muted-foreground">Select five active players from your roster.</p>
                                </div>
                                <span className={`rounded px-2 py-1 text-xs font-bold uppercase tracking-wide ${lineupReady ? 'bg-cyan-300/10 text-cyan-100' : 'bg-amber-300/10 text-amber-100'}`}>
                                    {lineupForm.data.starting_player_ids.length}/5 selected
                                </span>
                            </div>
                            <div className="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                {ownPlayers.map((player) => {
                                    const selected = lineupForm.data.starting_player_ids.includes(player.id);
                                    return (
                                        <label
                                            key={player.id}
                                            className={`flex min-h-14 cursor-pointer items-center gap-3 rounded-md border px-3 transition-colors ${selected ? 'border-amber-300/60 bg-amber-300/10' : 'border-border hover:bg-muted/40'}`}
                                        >
                                            <input type="checkbox" checked={selected} onChange={() => toggleLineupPlayer(player.id)} className="h-4 w-4 accent-amber-400" />
                                            <span className="font-mono text-amber-200">{player.jersey_number}</span>
                                            <span className="min-w-0">
                                                <span className="block truncate text-sm font-semibold text-foreground">{playerName(player)}</span>
                                                <span className="block truncate text-xs text-muted-foreground">{player.role ?? 'Player'}</span>
                                            </span>
                                            {selected && <Check size={15} className="ml-auto text-cyan-200" />}
                                        </label>
                                    );
                                })}
                            </div>
                            {lineupForm.errors.starting_player_ids && (
                                <p className="mt-3 text-xs text-red-300">{lineupForm.errors.starting_player_ids}</p>
                            )}
                            <button
                                type="submit"
                                disabled={lineupForm.processing || !lineupReady}
                                className="mt-5 flex h-11 w-full items-center justify-center gap-2 rounded-md bg-amber-400 px-4 text-sm font-bold uppercase tracking-wide text-black transition-colors hover:bg-amber-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto"
                            >
                                <UsersRound size={16} /> Submit lineup
                            </button>
                        </div>

                        <AssignAssistantPanel
                            coaches={assistantCoachOptions}
                            players={ownPlayers}
                            visible={assignPanelVisible && lineupReady}
                            expanded={assignExpanded}
                            onExpandedChange={setAssignExpanded}
                            value={{
                                assistantCoachUserId: lineupForm.data.assistant_coach_user_id,
                                delegatedPlayerIds: lineupForm.data.delegated_player_ids,
                            }}
                            onChange={(next) => {
                                lineupForm.setData({
                                    ...lineupForm.data,
                                    assistant_coach_user_id: next.assistantCoachUserId,
                                    delegated_player_ids: next.delegatedPlayerIds,
                                });
                            }}
                            errors={{
                                assistant_coach_user_id: lineupForm.errors.assistant_coach_user_id,
                                delegated_player_ids: lineupForm.errors.delegated_player_ids,
                            }}
                        />
                    </form>
                )}

                <LineupConfirmModal
                    open={confirmLineupOpen}
                    onOpenChange={setConfirmLineupOpen}
                    assistantName={selectedAssistantName}
                    hasAssignment={hasAssistantAssignment}
                    onConfirmLineup={() => postLineup(true)}
                    onConfirmWithAssistant={() => postLineup(false)}
                    onAssignAssistant={() => {
                        setConfirmLineupOpen(false);
                        setAssignPanelVisible(true);
                        setAssignExpanded(true);
                    }}
                />

                {waitingForOther && (
                    <div className="mx-4 mt-4 rounded-lg border border-dashed border-border bg-muted/20 px-4 py-10 text-center sm:mx-5">
                        <UsersRound size={28} className="mx-auto text-muted-foreground" />
                        <h2 className="mt-3 text-sm font-bold uppercase tracking-[0.1em] text-foreground">Waiting for {waitingTeamName} lineup</h2>
                        <p className="mx-auto mt-2 max-w-md text-sm text-muted-foreground">
                            Share the game link so their coach can open this page and submit their starting five. Start unlocks when both sides are ready.
                        </p>
                        <button
                            type="button"
                            onClick={() => void copyLink()}
                            className="mx-auto mt-5 flex h-11 items-center gap-2 rounded-md border border-cyan-300/40 bg-cyan-300/10 px-4 text-xs font-bold uppercase tracking-wide text-cyan-100 transition-colors hover:bg-cyan-300/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300"
                        >
                            <Copy size={14} /> Copy link
                        </button>
                    </div>
                )}

                {isSetup && snapshot.both_lineups_ready && (
                    <div className="mx-4 mt-4 rounded-lg border border-cyan-300/30 bg-cyan-300/5 px-4 py-4 text-sm text-cyan-100 sm:mx-5">
                        Both starting fives are ready{isCreator ? '. You can start the game.' : '. Waiting for the creator to start.'}
                    </div>
                )}

                <div className="grid flex-1 gap-4 p-4 sm:p-5 xl:grid-cols-[minmax(250px,0.72fr)_minmax(440px,1.35fr)_minmax(280px,0.85fr)]">
                    <div className="flex flex-col gap-4">
                        <ActiveLineup
                            players={controlledRosterPlayers}
                            activePlayerIds={controlledActiveIds}
                            stats={snapshot.stats}
                            selectedPlayerId={selectedPlayerId}
                            onSelectPlayer={setSelectedPlayerId}
                        />
                        <BenchSubstitution
                            players={controlledRosterPlayers}
                            activePlayerIds={controlledActiveIds}
                            disabled={recordingBlockedReason !== null || clockState !== 'stopped'}
                            onSubstitute={substitute}
                        />
                    </div>
                    <div className="flex min-w-0 flex-col gap-4">
                        <EventPad
                            selectedPlayer={selectedPlayer}
                            clockState={clockState}
                            selectedPlayerPersonalFouls={selectedPlayerPersonalFouls}
                            blockedReason={recordingBlockedReason}
                            onRecord={record}
                        />
                        {padUnavailableReason && (
                            <div className="rounded-lg border border-dashed border-border bg-muted/20 px-4 py-3 text-sm text-muted-foreground">
                                {padUnavailableReason}
                            </div>
                        )}
                        <Timeline
                            events={snapshot.events}
                            players={allPlayers}
                            teams={teams}
                            disabled={processing}
                            onRequestVoid={setPendingVoidEvent}
                        />
                        <VoidEventConfirmModal
                            open={pendingVoidEvent !== null}
                            onOpenChange={(open) => !open && setPendingVoidEvent(null)}
                            label={pendingVoidEvent ? eventLabel(pendingVoidEvent, allPlayers, teams) : null}
                            isSubstitution={pendingVoidEvent?.type === 'substitution'}
                            onConfirm={confirmVoid}
                        />
                    </div>
                    <aside className="min-w-0">
                        <AlertsPanel alerts={snapshot.alerts} players={allPlayers} />
                    </aside>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
