import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { playerName } from '@/Components/features/live-game/live-game-utils';
import type { PageProps, Player, Team } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Check, Play, UsersRound } from 'lucide-react';
import { FormEvent, useEffect } from 'react';

interface TeamWithPlayers extends Team { players: Player[]; }
interface LiveGameCreateProps extends PageProps { teams: TeamWithPlayers[]; }

export default function LiveGamesCreate({ teams }: LiveGameCreateProps) {
    const { data, setData, post, processing, errors } = useForm({ home_team_id: '', opponent_team_id: '', period_length_seconds: 600, starting_player_ids: [] as number[] });
    const homeTeam = teams.find((team) => team.id === Number(data.home_team_id));

    useEffect(() => { if (homeTeam) setData('starting_player_ids', homeTeam.players.slice(0, 5).map((player) => player.id)); }, [homeTeam?.id]);

    function togglePlayer(playerId: number): void {
        setData('starting_player_ids', data.starting_player_ids.includes(playerId) ? data.starting_player_ids.filter((id) => id !== playerId) : data.starting_player_ids.length < 5 ? [...data.starting_player_ids, playerId] : data.starting_player_ids);
    }
    function submit(event: FormEvent<HTMLFormElement>): void { event.preventDefault(); post(route('live-games.store')); }

    return (
        <AuthenticatedLayout>
            <Head title="Set Up Live Game" />
            <div className="mx-auto flex max-w-5xl flex-col gap-5">
                <div>
                    <Link href={route('live-games.index')} className="inline-flex items-center gap-2 text-sm font-semibold text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300"><ArrowLeft size={15} /> Live games</Link>
                    <h1 className="mt-3 font-display text-xl font-black uppercase tracking-[0.12em] text-foreground">Set up live game</h1>
                    <p className="mt-1 text-sm text-muted-foreground">Choose the teams, period length, and the five players opening on court.</p>
                </div>
                <form onSubmit={submit} className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(300px,0.8fr)]">
                    <section className="rounded-lg border border-border bg-card p-5">
                        <h2 className="text-sm font-bold uppercase tracking-[0.1em] text-foreground">Game details</h2>
                        <div className="mt-5 grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2"><Label htmlFor="home-team">Home team</Label><select id="home-team" value={data.home_team_id} onChange={(event) => setData('home_team_id', event.target.value)} className="h-10 rounded-md border border-input bg-background px-3 text-sm text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300"><option value="">Select home team</option>{teams.map((team) => <option key={team.id} value={team.id}>{team.name}</option>)}</select>{errors.home_team_id && <p className="text-xs text-red-300">{errors.home_team_id}</p>}</div>
                            <div className="grid gap-2"><Label htmlFor="opponent-team">Opponent team</Label><select id="opponent-team" value={data.opponent_team_id} onChange={(event) => setData('opponent_team_id', event.target.value)} className="h-10 rounded-md border border-input bg-background px-3 text-sm text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300"><option value="">Select opponent team</option>{teams.filter((team) => team.id !== Number(data.home_team_id)).map((team) => <option key={team.id} value={team.id}>{team.name}</option>)}</select>{errors.opponent_team_id && <p className="text-xs text-red-300">{errors.opponent_team_id}</p>}</div>
                            <div className="grid gap-2 sm:col-span-2"><Label htmlFor="period-length">Quarter length in seconds</Label><Input id="period-length" type="number" min="60" max="1200" value={data.period_length_seconds} onChange={(event) => setData('period_length_seconds', Number(event.target.value))} /><p className="text-xs text-muted-foreground">Four quarters. The default 600 seconds is a 10-minute quarter.</p>{errors.period_length_seconds && <p className="text-xs text-red-300">{errors.period_length_seconds}</p>}</div>
                        </div>
                    </section>
                    <aside className="rounded-lg border border-cyan-300/25 bg-cyan-300/5 p-5"><UsersRound size={20} className="text-cyan-200" /><h2 className="mt-3 text-sm font-bold uppercase tracking-[0.1em] text-foreground">Starting five</h2><p className="mt-1 text-sm text-muted-foreground">A suggested five is selected when you choose the home team.</p><button type="submit" disabled={processing || data.starting_player_ids.length !== 5} className="mt-5 flex h-11 w-full items-center justify-center gap-2 rounded-md bg-amber-400 px-4 text-sm font-bold uppercase tracking-wide text-black transition-colors hover:bg-amber-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300 disabled:cursor-not-allowed disabled:opacity-50"><Play size={16} /> Create game</button></aside>
                    <section className="rounded-lg border border-border bg-card p-5 lg:col-span-2">
                        <div className="flex flex-wrap items-center justify-between gap-3"><div><h2 className="text-sm font-bold uppercase tracking-[0.1em] text-foreground">Home lineup</h2><p className="mt-1 text-sm text-muted-foreground">Select exactly five active players.</p></div><span className={`rounded px-2 py-1 text-xs font-bold uppercase tracking-wide ${data.starting_player_ids.length === 5 ? 'bg-cyan-300/10 text-cyan-100' : 'bg-amber-300/10 text-amber-100'}`}>{data.starting_player_ids.length}/5 selected</span></div>
                        {homeTeam ? <div className="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">{homeTeam.players.map((player) => {
                            const selected = data.starting_player_ids.includes(player.id);
                            return <label key={player.id} className={`flex min-h-14 cursor-pointer items-center gap-3 rounded-md border px-3 transition-colors ${selected ? 'border-amber-300/60 bg-amber-300/10' : 'border-border hover:bg-muted/40'}`}><input type="checkbox" checked={selected} onChange={() => togglePlayer(player.id)} className="h-4 w-4 accent-amber-400" /><span className="font-mono text-amber-200">{player.jersey_number}</span><span className="min-w-0"><span className="block truncate text-sm font-semibold text-foreground">{playerName(player)}</span><span className="block truncate text-xs text-muted-foreground">{player.role ?? 'Player'}</span></span>{selected && <Check size={15} className="ml-auto text-cyan-200" />}</label>;
                        })}</div> : <p className="mt-5 rounded-md border border-dashed border-border px-4 py-10 text-center text-sm text-muted-foreground">Select a home team to load its active players.</p>}
                        {errors.starting_player_ids && <p className="mt-3 text-xs text-red-300">{errors.starting_player_ids}</p>}
                    </section>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
