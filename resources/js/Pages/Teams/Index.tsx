import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { TeamCard } from '@/Components/features/teams/TeamCard';
import { CreateTeamSheet } from '@/Components/features/teams/CreateTeamSheet';
import { type PageProps, type Team } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Plus, Swords, Users2 } from 'lucide-react';
import { useState } from 'react';

interface TeamsIndexProps extends PageProps {
    teams: Team[];
}

export default function TeamsIndex({ teams }: TeamsIndexProps) {
    const { flash } = usePage<TeamsIndexProps>().props;
    const [createOpen, setCreateOpen] = useState(false);

    return (
        <AuthenticatedLayout>
            <Head title="Teams & Players" />

            <div className="flex flex-col gap-5">
                {/* ── Page heading ── */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '20px', fontWeight: 900, color: 'hsl(var(--foreground))', letterSpacing: '2px', textTransform: 'uppercase' }}>
                            Teams &amp; Players
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground" style={{ fontFamily: 'Rajdhani, sans-serif', fontWeight: 600, letterSpacing: '0.5px' }}>
                            Build your roster, upload stats, and head to the arena.
                        </p>
                    </div>
                    <button
                        onClick={() => setCreateOpen(true)}
                        className="flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition-all hover:-translate-y-0.5"
                        style={{
                            fontFamily: 'Rajdhani, sans-serif',
                            background: 'linear-gradient(135deg, #98002E, #6d0020)',
                            border: '1px solid rgba(255,100,100,0.2)',
                            color: '#fff',
                            letterSpacing: '1px',
                            textTransform: 'uppercase',
                            boxShadow: '0 0 20px rgba(152,0,46,0.3)',
                        }}
                    >
                        <Plus size={14} />
                        New Team
                    </button>
                </div>

                {/* Flash */}
                {flash?.success && (
                    <div className="rounded-xl px-4 py-3 text-sm"
                         style={{ fontFamily: 'Rajdhani, sans-serif', background: 'rgba(16,185,129,0.1)', border: '1px solid rgba(16,185,129,0.3)', color: '#059669', fontWeight: 600 }}>
                        {flash.success}
                    </div>
                )}

                {/* ── Empty state ── */}
                {teams.length === 0 ? (
                    <div className="flex flex-col items-center justify-center rounded-2xl py-24 text-center gap-6 relative overflow-hidden bg-card"
                         style={{ border: '1px dashed hsl(var(--border))' }}>
                        <div className="pointer-events-none absolute inset-0"
                             style={{ background: 'radial-gradient(ellipse at center, rgba(152,0,46,0.05), transparent 70%)' }} />

                        <div className="flex h-16 w-16 items-center justify-center rounded-2xl relative"
                             style={{ background: 'rgba(152,0,46,0.1)', border: '1px solid rgba(152,0,46,0.25)', boxShadow: '0 0 24px rgba(152,0,46,0.15)' }}>
                            <Users2 size={28} style={{ color: '#98002E' }} />
                        </div>
                        <div>
                            <p style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '16px', fontWeight: 700, color: 'hsl(var(--foreground))', letterSpacing: '1px', textTransform: 'uppercase' }}>
                                No Teams Yet
                            </p>
                            <p className="mt-2 text-sm text-muted-foreground" style={{ fontFamily: 'Rajdhani, sans-serif', fontWeight: 600, maxWidth: '320px' }}>
                                Create your first team, upload a roster CSV, and start dominating the competition.
                            </p>
                        </div>
                        <button
                            onClick={() => setCreateOpen(true)}
                            className="flex items-center gap-2 rounded-xl px-6 py-3 text-sm font-semibold transition-all hover:-translate-y-0.5"
                            style={{
                                fontFamily: 'Rajdhani, sans-serif',
                                background: 'linear-gradient(135deg, #98002E, #6d0020)',
                                border: '1px solid rgba(255,100,100,0.2)',
                                color: '#fff',
                                letterSpacing: '1px',
                                textTransform: 'uppercase',
                                boxShadow: '0 0 24px rgba(152,0,46,0.3)',
                            }}
                        >
                            <Plus size={14} />
                            Create First Team
                        </button>
                    </div>
                ) : (
                    <>
                        {/* Journey tip */}
                        <div className="flex items-center gap-3 rounded-xl px-4 py-3"
                             style={{ background: 'rgba(249,160,27,0.05)', border: '1px solid rgba(249,160,27,0.15)' }}>
                            <Swords size={14} style={{ color: '#F9A01B', flexShrink: 0 }} />
                            <span className="text-xs text-muted-foreground" style={{ fontFamily: 'Rajdhani, sans-serif', fontWeight: 600 }}>
                                Open a team → upload CSV → go to{' '}
                                <Link href={route('comparison.index')} className="font-bold transition-colors hover:underline"
                                      style={{ color: '#F9A01B' }}>
                                    Team Comparison
                                </Link>{' '}
                                to generate win probability and lineup recommendations.
                            </span>
                        </div>

                        {/* Team grid */}
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            {teams.map((team) => (
                                <TeamCard key={team.id} team={team} />
                            ))}
                        </div>
                    </>
                )}
            </div>
            <CreateTeamSheet open={createOpen} onOpenChange={setCreateOpen} />
        </AuthenticatedLayout>
    );
}
