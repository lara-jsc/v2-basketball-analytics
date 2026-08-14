import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { AssignAssistantPanel } from '@/Components/features/live-game/AssignAssistantPanel';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { playerName } from '@/Components/features/live-game/live-game-utils';
import type { PageProps, Player, Team } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Check, Play, UsersRound } from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';

interface TeamWithPlayers extends Team { players: Player[]; }

interface LiveGameCreateProps extends PageProps {
    homeTeam: TeamWithPlayers | null;
    opponentTeams: Team[];
    preselectedPlayerIds: number[];
    preselectedOpponentTeamId: number | null;
    assistantCoachOptions: Array<{ id: number; name: string }>;
    defaultAssistantCoachUserId: number | null;
}

export default function LiveGamesCreate({
    homeTeam,
    opponentTeams,
    preselectedPlayerIds,
    preselectedOpponentTeamId,
    assistantCoachOptions,
    defaultAssistantCoachUserId,
}: LiveGameCreateProps) {
    const [initialized, setInitialized] = useState(false);
    const { data, setData, post, processing, errors } = useForm({
        opponent_team_id: preselectedOpponentTeamId ? String(preselectedOpponentTeamId) : '',
        period_length_seconds: 600,
        starting_player_ids: [] as number[],
        assistant_assignments: [] as Array<{ coach_user_id: number; player_ids: number[] }>,
    });

    useEffect(() => {
        if (!homeTeam || initialized) return;

        const homeIds = new Set(homeTeam.players.map((player) => player.id));
        const fromQuery = preselectedPlayerIds.filter((id) => homeIds.has(id)).slice(0, 5);
        setData(
            'starting_player_ids',
            fromQuery.length === 5
                ? fromQuery
                : homeTeam.players.slice(0, 5).map((player) => player.id),
        );
        setInitialized(true);
    }, [homeTeam, initialized, preselectedPlayerIds, setData]);

    function togglePlayer(playerId: number): void {
        const selected = data.starting_player_ids;
        setData(
            'starting_player_ids',
            selected.includes(playerId)
                ? selected.filter((id) => id !== playerId)
                : selected.length < 5
                    ? [...selected, playerId]
                    : selected,
        );
    }

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        post(route('live-games.store'));
    }

    const lineupReady = data.starting_player_ids.length === 5 && data.opponent_team_id !== '';
    const assistantAssignmentError = Object.entries(errors as Record<string, string | undefined>)
        .find(([key]) => key.startsWith('assistant_assignments'))?.[1];

    return (
        <AuthenticatedLayout>
            <Head title="Set Up Live Game" />
            <div className="mx-auto flex max-w-5xl flex-col gap-5">
                <div>
                    <Link href={route('live-games.index')} className="inline-flex items-center gap-2 text-sm font-semibold text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300">
                        <ArrowLeft size={15} /> Live games
                    </Link>
                    <h1 className="mt-3 font-display text-xl font-black uppercase tracking-[0.12em] text-foreground">Set up live game</h1>
                    <p className="mt-1 text-sm text-muted-foreground">Pick your starting five and an opponent. Share the game link so their coach can submit their lineup.</p>
                </div>

                {!homeTeam ? (
                    <div className="live-badge-warn rounded-lg px-4 py-5 text-sm">
                        Your account is not assigned to a team. Ask an admin to set your team before creating a live game.
                    </div>
                ) : (
                    <form onSubmit={submit} className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(300px,0.8fr)]">
                        <section className="rounded-lg border border-border bg-card p-5">
                            <h2 className="text-sm font-bold uppercase tracking-[0.1em] text-foreground">Game details</h2>
                            <div className="mt-5 grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="home-team">Your team</Label>
                                    <Input id="home-team" value={homeTeam.name} readOnly className="h-11 bg-muted/40" />
                                    {(errors as Record<string, string>).home_team_id && (
                                        <p className="live-text-danger text-xs">{(errors as Record<string, string>).home_team_id}</p>
                                    )}
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="opponent-team">Opponent team</Label>
                                    <select
                                        id="opponent-team"
                                        value={data.opponent_team_id}
                                        onChange={(event) => setData('opponent_team_id', event.target.value)}
                                        className="h-11 rounded-md border border-input bg-background px-3 text-sm text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300"
                                    >
                                        <option value="">Select opponent team</option>
                                        {opponentTeams.map((team) => (
                                            <option key={team.id} value={team.id}>{team.name}</option>
                                        ))}
                                    </select>
                                    {errors.opponent_team_id && <p className="live-text-danger text-xs">{errors.opponent_team_id}</p>}
                                </div>
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="period-length">Quarter length in seconds</Label>
                                    <Input
                                        id="period-length"
                                        type="number"
                                        min="60"
                                        max="1200"
                                        value={data.period_length_seconds}
                                        onChange={(event) => setData('period_length_seconds', Number(event.target.value))}
                                        className="h-11"
                                    />
                                    <p className="text-xs text-muted-foreground">Four quarters. The default 600 seconds is a 10-minute quarter.</p>
                                    {errors.period_length_seconds && <p className="live-text-danger text-xs">{errors.period_length_seconds}</p>}
                                </div>
                            </div>
                        </section>

                        <aside className="rounded-lg border border-cyan-600/25 bg-cyan-100/50 p-5 dark:border-cyan-300/25 dark:bg-cyan-300/5">
                            <UsersRound size={20} className="live-text-info" />
                            <h2 className="mt-3 text-sm font-bold uppercase tracking-[0.1em] text-foreground">Your starting five</h2>
                            <p className="mt-1 text-sm text-muted-foreground">The opponent coach will submit their five after you share the game link.</p>
                            <button
                                type="submit"
                                disabled={processing || !lineupReady}
                                className="mt-5 flex h-11 w-full items-center justify-center gap-2 rounded-md bg-amber-400 px-4 text-sm font-bold uppercase tracking-wide text-black transition-colors hover:bg-amber-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <Play size={16} /> Create game
                            </button>
                        </aside>

                        <section className="rounded-lg border border-border bg-card p-5 lg:col-span-2">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h2 className="text-sm font-bold uppercase tracking-[0.1em] text-foreground">{homeTeam.name} lineup</h2>
                                    <p className="mt-1 text-sm text-muted-foreground">Select exactly five active players.</p>
                                </div>
                                <span className={`rounded px-2 py-1 text-xs font-bold uppercase tracking-wide ${data.starting_player_ids.length === 5 ? 'live-badge-info' : 'live-badge-warn'}`}>
                                    {data.starting_player_ids.length}/5 selected
                                </span>
                            </div>
                            <div className="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                {homeTeam.players.map((player) => {
                                    const selected = data.starting_player_ids.includes(player.id);
                                    return (
                                        <label
                                            key={player.id}
                                            className={`flex min-h-14 cursor-pointer items-center gap-3 rounded-md border px-3 transition-colors ${selected ? 'border-amber-300/60 bg-amber-300/10' : 'border-border hover:bg-muted/40'}`}
                                        >
                                            <input type="checkbox" checked={selected} onChange={() => togglePlayer(player.id)} className="h-4 w-4 accent-amber-400" />
                                            <span className="live-text-warn font-mono">{player.jersey_number}</span>
                                            <span className="min-w-0">
                                                <span className="block truncate text-sm font-semibold text-foreground">{playerName(player)}</span>
                                                <span className="block truncate text-xs text-muted-foreground">{player.role ?? 'Player'}</span>
                                            </span>
                                            {selected && <Check size={15} className="live-text-info ml-auto" />}
                                        </label>
                                    );
                                })}
                            </div>
                            {errors.starting_player_ids && <p className="live-text-danger mt-3 text-xs">{errors.starting_player_ids}</p>}
                        </section>

                        <div className="lg:col-span-2">
                            <AssignAssistantPanel
                                coaches={assistantCoachOptions}
                                players={homeTeam.players}
                                visible={lineupReady}
                                value={{
                                    assistantAssignments: data.assistant_assignments.map((assignment) => ({
                                        coachUserId: assignment.coach_user_id,
                                        playerIds: assignment.player_ids,
                                    })),
                                }}
                                onChange={(next) => {
                                    setData({
                                        ...data,
                                        assistant_assignments: next.assistantAssignments
                                            .map((assignment) => ({
                                                coach_user_id: assignment.coachUserId,
                                                player_ids: assignment.playerIds,
                                            }))
                                            .filter((assignment) => assignment.player_ids.length > 0),
                                    });
                                }}
                                errors={{
                                    assistant_assignments: assistantAssignmentError,
                                }}
                            />
                        </div>
                    </form>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
