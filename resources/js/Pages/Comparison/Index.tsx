import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { type PageProps, type Team } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Swords, Zap } from 'lucide-react';

interface ComparisonIndexProps extends PageProps {
    teams: Team[];
}

export default function ComparisonIndex({ teams }: ComparisonIndexProps) {
    const { data, setData, post, processing, errors } = useForm({
        team_a: '',
        team_b: '',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(route('comparison.select'));
    }

    const activeTeams = teams.filter((t) => t.is_active);
    const teamA = activeTeams.find((t) => String(t.id) === data.team_a);
    const teamB = activeTeams.find((t) => String(t.id) === data.team_b);

    return (
        <AuthenticatedLayout>
            <Head title="Team Comparison" />

            <div className="flex flex-col gap-6">
                {/* Page heading */}
                <div>
                    <h1 style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '20px', fontWeight: 900, color: 'rgba(255,255,255,0.95)', letterSpacing: '2px', textTransform: 'uppercase', textShadow: '0 0 20px rgba(255,140,0,0.3)' }}>
                        Team Comparison
                    </h1>
                    <p className="mt-1 text-sm" style={{ fontFamily: 'Rajdhani, sans-serif', color: 'rgba(255,255,255,0.4)', fontWeight: 600 }}>
                        Select two teams to generate win probability, win rate, and lineup recommendations.
                    </p>
                </div>

                {/* ── Arena matchup panel ── */}
                <div className="rounded-2xl overflow-hidden relative"
                     style={{ background: 'rgba(8,12,24,0.9)', border: '1px solid rgba(255,255,255,0.07)', boxShadow: '0 8px 48px rgba(0,0,0,0.5)' }}>

                    {/* Header bar */}
                    <div className="relative flex items-center gap-3 px-6 py-4 overflow-hidden"
                         style={{ borderBottom: '1px solid rgba(255,255,255,0.07)', background: 'linear-gradient(90deg, rgba(152,0,46,0.2), rgba(249,160,27,0.05), transparent)' }}>
                        <div className="pointer-events-none absolute inset-0"
                             style={{ backgroundImage: 'repeating-linear-gradient(90deg,transparent,transparent 60px,rgba(249,160,27,0.03) 60px,rgba(249,160,27,0.03) 61px)' }} />
                        <Swords size={18} style={{ color: '#F9A01B', filter: 'drop-shadow(0 0 6px rgba(249,160,27,0.6))' }} />
                        <div className="relative">
                            <h3 style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '12px', fontWeight: 700, color: 'rgba(255,255,255,0.9)', letterSpacing: '2px', textTransform: 'uppercase' }}>
                                Select Matchup
                            </h3>
                            <p className="text-[11px]" style={{ fontFamily: 'Rajdhani, sans-serif', color: 'rgba(255,255,255,0.35)', fontWeight: 600 }}>
                                Choose a home team and an opponent
                            </p>
                        </div>
                    </div>

                    <div className="px-6 py-6">
                        {activeTeams.length < 2 ? (
                            <div className="rounded-xl px-5 py-5 text-sm" style={{ fontFamily: 'Rajdhani, sans-serif', background: 'rgba(255,255,255,0.03)', border: '1px solid rgba(255,255,255,0.07)', color: 'rgba(255,255,255,0.45)', fontWeight: 600 }}>
                                You need at least two active teams to run a comparison. Go to{' '}
                                <a href={route('teams.index')} className="font-bold transition-colors hover:underline" style={{ color: '#F9A01B' }}>
                                    Teams &amp; Players
                                </a>{' '}
                                to create teams and upload rosters.
                            </div>
                        ) : (
                            <form onSubmit={submit} className="space-y-6">

                                {/* VS MATCHUP DISPLAY — shown when both selected */}
                                <div className="relative flex items-center justify-center gap-0 min-h-[100px]">
                                    {/* Team A slot */}
                                    <TeamSlot team={teamA} side="home" placeholder="Home Team" />

                                    {/* VS badge */}
                                    <div className="relative z-10 flex h-16 w-16 shrink-0 items-center justify-center rounded-full mx-4"
                                         style={{
                                             background: 'radial-gradient(circle, rgba(249,160,27,0.15), rgba(0,0,0,0.6))',
                                             border: '2px solid rgba(249,160,27,0.3)',
                                             boxShadow: '0 0 30px rgba(249,160,27,0.2)',
                                         }}>
                                        <span style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '14px', fontWeight: 900, color: '#F9A01B', letterSpacing: '1px', textShadow: '0 0 10px rgba(249,160,27,0.6)' }}>VS</span>
                                    </div>

                                    {/* Team B slot */}
                                    <TeamSlot team={teamB} side="away" placeholder="Opponent" />
                                </div>

                                {/* Selectors */}
                                <div className="grid grid-cols-2 gap-4">
                                    <TeamSelectField
                                        label="Home Team"
                                        id="team_a"
                                        value={data.team_a}
                                        onChange={(v) => setData('team_a', v)}
                                        teams={activeTeams}
                                        exclude={data.team_b}
                                        error={errors.team_a}
                                    />
                                    <TeamSelectField
                                        label="Opponent"
                                        id="team_b"
                                        value={data.team_b}
                                        onChange={(v) => setData('team_b', v)}
                                        teams={activeTeams}
                                        exclude={data.team_a}
                                        error={errors.team_b}
                                    />
                                </div>

                                <button
                                    type="submit"
                                    disabled={!data.team_a || !data.team_b || processing}
                                    className="flex w-full items-center justify-center gap-2.5 rounded-xl py-3.5 text-base font-bold uppercase tracking-widest transition-all hover:-translate-y-0.5 disabled:opacity-40 disabled:cursor-not-allowed disabled:translate-y-0"
                                    style={{
                                        fontFamily: 'Rajdhani, sans-serif',
                                        background: 'linear-gradient(135deg, #98002E 0%, #c0003a 50%, #98002E 100%)',
                                        border: '1px solid rgba(255,100,100,0.2)',
                                        color: '#fff',
                                        boxShadow: '0 0 30px rgba(152,0,46,0.5), inset 0 1px 0 rgba(255,255,255,0.1)',
                                        letterSpacing: '2px',
                                    }}
                                >
                                    <Zap size={16} />
                                    {processing ? 'Loading...' : 'Start Comparison'}
                                </button>
                            </form>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

// ── Team slot (logo + name preview) ──────────────────────────────────────────

function TeamSlot({ team, side, placeholder }: { team?: Team; side: 'home' | 'away'; placeholder: string }) {
    const logoUrl = team?.logo_path ? `/storage/${team.logo_path}` : null;
    const isHome = side === 'home';

    return (
        <div className={`flex flex-1 flex-col items-center gap-2 ${isHome ? 'items-end pr-2' : 'items-start pl-2'}`}>
            {/* Logo circle */}
            <div className="flex h-16 w-16 items-center justify-center rounded-2xl overflow-hidden transition-all duration-300"
                 style={{
                     background: team
                         ? (isHome ? 'linear-gradient(135deg, rgba(152,0,46,0.4), rgba(80,0,20,0.6))' : 'linear-gradient(135deg, rgba(30,60,120,0.4), rgba(10,25,60,0.6))')
                         : 'rgba(255,255,255,0.04)',
                     border: team ? '2px solid rgba(255,140,0,0.25)' : '2px dashed rgba(255,255,255,0.1)',
                     boxShadow: team ? '0 0 24px rgba(255,140,0,0.15)' : 'none',
                 }}>
                {logoUrl ? (
                    <img src={logoUrl} alt="" className="h-full w-full object-cover" />
                ) : team ? (
                    <span style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '16px', fontWeight: 900, color: 'rgba(255,200,200,0.9)' }}>
                        {team.code.slice(0, 3)}
                    </span>
                ) : (
                    <span style={{ fontSize: '22px', opacity: 0.2 }}>?</span>
                )}
            </div>

            {/* Team name */}
            <div className={`text-center ${isHome ? 'text-right' : 'text-left'}`}>
                {team ? (
                    <>
                        <p style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '11px', fontWeight: 700, color: 'rgba(255,255,255,0.9)', letterSpacing: '0.5px', textTransform: 'uppercase' }}>
                            {team.name}
                        </p>
                        <p style={{ fontFamily: 'Rajdhani, sans-serif', fontSize: '11px', fontWeight: 600, color: '#F9A01B', letterSpacing: '1px', textTransform: 'uppercase' }}>
                            {team.code}
                        </p>
                    </>
                ) : (
                    <p style={{ fontFamily: 'Rajdhani, sans-serif', fontSize: '11px', fontWeight: 600, color: 'rgba(255,255,255,0.2)', letterSpacing: '1px', textTransform: 'uppercase' }}>
                        {placeholder}
                    </p>
                )}
            </div>
        </div>
    );
}

// ── Select field ──────────────────────────────────────────────────────────────

function TeamSelectField({
    label, id, value, onChange, teams, exclude, error,
}: {
    label: string;
    id: string;
    value: string;
    onChange: (v: string) => void;
    teams: Team[];
    exclude: string;
    error?: string;
}) {
    return (
        <div className="space-y-2">
            <label htmlFor={id} className="block text-[11px] font-bold uppercase tracking-widest"
                   style={{ fontFamily: 'Rajdhani, sans-serif', color: 'rgba(255,255,255,0.4)' }}>
                {label}
            </label>
            <select
                id={id}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className="w-full rounded-xl px-4 py-3 text-sm transition-all focus:outline-none"
                style={{
                    fontFamily: 'Rajdhani, sans-serif',
                    fontWeight: 600,
                    background: 'rgba(255,255,255,0.04)',
                    border: '1px solid rgba(255,255,255,0.1)',
                    color: 'rgba(255,255,255,0.8)',
                    appearance: 'none',
                }}
            >
                <option value="" style={{ background: '#080C18' }}>Select a team…</option>
                {teams
                    .filter((t) => String(t.id) !== exclude)
                    .map((t) => (
                        <option key={t.id} value={String(t.id)} style={{ background: '#080C18' }}>
                            {t.name} ({t.code})
                        </option>
                    ))}
            </select>
            {error && <p className="text-xs" style={{ fontFamily: 'Rajdhani, sans-serif', color: '#f87171', fontWeight: 600 }}>{error}</p>}
        </div>
    );
}
