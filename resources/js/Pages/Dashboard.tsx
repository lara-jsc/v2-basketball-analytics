import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { type PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import {
    BarChart2,
    ChevronRight,
    Swords,
    TrendingUp,
    Trophy,
    Upload,
    Users2,
    Zap,
} from 'lucide-react';
import {
    Bar,
    BarChart,
    Cell,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

export default function Dashboard({ auth }: PageProps) {
    return (
        <AuthenticatedLayout>
            <Head title="Dashboard" />

            <div className="flex flex-col gap-5 h-full">
                {/* ── Page heading ── */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '20px', fontWeight: 900, color: 'hsl(var(--foreground))', letterSpacing: '2px', textTransform: 'uppercase' }}>
                            Dashboard
                        </h1>
                        <p className="mt-1 text-sm" style={{ fontFamily: 'Rajdhani, sans-serif', color: 'hsl(var(--muted-foreground))', fontWeight: 600 }}>
                            Welcome back,{' '}
                            <span style={{ color: '#F9A01B', fontWeight: 700 }}>{auth.user?.name}</span>
                        </p>
                    </div>
                    <div className="flex items-center gap-2 rounded-full px-4 py-1.5"
                         style={{ background: 'rgba(249,160,27,0.08)', border: '1px solid rgba(249,160,27,0.25)' }}>
                        <span className="h-2 w-2 rounded-full bg-[#F9A01B] animate-pulse" />
                        <span style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '10px', fontWeight: 700, color: '#F9A01B', letterSpacing: '2px', textTransform: 'uppercase' }}>
                            Pre-Game Mode
                        </span>
                    </div>
                </div>

                {/* ── Summary cards ── */}
                <div className="grid grid-cols-3 gap-4">
                    <SummaryCard label="Team Win Rate" icon={<Trophy size={16} />} emptyText="No data yet" />
                    <SummaryCard label="Live Plus-Minus" icon={<Zap size={16} />} emptyText="No data yet" accent />
                    <SummaryCard label="Active Teams" icon={<Users2 size={16} />} emptyText="No teams added" />
                </div>

                {/* ── Main grid ── */}
                <div className="grid grid-cols-5 gap-4 flex-1 min-h-0">
                    {/* Player Impact Analysis */}
                    <div className="col-span-3 flex flex-col rounded-2xl p-4 gap-4 relative overflow-hidden bg-card"
                         style={{ border: '1px solid hsl(var(--border))', boxShadow: '0 4px 24px rgba(0,0,0,0.1)' }}>
                        <div className="pointer-events-none absolute inset-0"
                             style={{ background: 'radial-gradient(ellipse at bottom, rgba(249,160,27,0.04) 0%, transparent 70%)' }} />

                        <div className="flex items-center justify-between relative">
                            <div className="flex items-center gap-2">
                                <BarChart2 size={15} style={{ color: '#F9A01B' }} />
                                <h2 style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '11px', fontWeight: 700, color: 'hsl(var(--foreground))', letterSpacing: '1.5px', textTransform: 'uppercase' }}>
                                    Player Impact Analysis
                                </h2>
                            </div>
                            <span className="text-[10px] rounded px-2 py-0.5"
                                  style={{ fontFamily: 'Rajdhani, sans-serif', fontWeight: 700, color: '#F9A01B', background: 'rgba(249,160,27,0.1)', border: '1px solid rgba(249,160,27,0.2)', letterSpacing: '1px', textTransform: 'uppercase' }}>
                                Plus-Minus
                            </span>
                        </div>

                        <div className="flex-1 min-h-0 relative" style={{ minHeight: 130 }}>
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart data={PLACEHOLDER_IMPACT_DATA} margin={{ top: 4, right: 4, left: -28, bottom: 0 }}>
                                    <defs>
                                        <linearGradient id="impactGrad" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="0%" stopColor="#F9A01B" stopOpacity={0.85} />
                                            <stop offset="100%" stopColor="#F9A01B" stopOpacity={0.18} />
                                        </linearGradient>
                                    </defs>
                                    <XAxis dataKey="name" axisLine={false} tickLine={false}
                                        tick={{ fill: 'rgba(122,147,184,0.6)', fontSize: 9, fontFamily: 'Rajdhani, sans-serif' }} />
                                    <YAxis axisLine={false} tickLine={false}
                                        tick={{ fill: 'rgba(122,147,184,0.5)', fontSize: 9, fontFamily: 'Rajdhani, sans-serif' }} />
                                    <Tooltip
                                        cursor={{ fill: 'rgba(249,160,27,0.05)' }}
                                        contentStyle={{ background: 'rgba(13,21,37,0.95)', border: '1px solid rgba(249,160,27,0.25)', borderRadius: 8, fontFamily: 'Rajdhani, sans-serif' }}
                                        labelStyle={{ color: '#F9A01B', fontWeight: 700, fontSize: 11 }}
                                        itemStyle={{ color: '#F0F4FF', fontSize: 11 }}
                                        formatter={(v) => [`+${v ?? 0}`, 'Plus-Minus']}
                                    />
                                    <Bar dataKey="value" fill="url(#impactGrad)" radius={[3, 3, 0, 0]}>
                                        {PLACEHOLDER_IMPACT_DATA.map((entry, i) => (
                                            <Cell key={i}
                                                fill={`rgba(249,160,27,${0.22 + (entry.value / PLACEHOLDER_IMPACT_DATA.reduce((a, b) => a.value > b.value ? a : b).value) * 0.65})`} />
                                        ))}
                                    </Bar>
                                </BarChart>
                            </ResponsiveContainer>
                        </div>

                        <EmptyOverlay
                            icon={<Upload size={18} />}
                            message="Upload a roster to see player impact"
                            cta="Go to Teams & Players"
                            href={route('teams.index')}
                        />
                    </div>

                    {/* Top Players Leaderboard */}
                    <div className="col-span-2 flex flex-col rounded-2xl p-4 gap-4 relative overflow-hidden bg-card"
                         style={{ border: '1px solid hsl(var(--border))', boxShadow: '0 4px 24px rgba(0,0,0,0.1)' }}>
                        <div className="flex items-center gap-2">
                            <TrendingUp size={15} style={{ color: '#F9A01B' }} />
                            <h2 style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '11px', fontWeight: 700, color: 'hsl(var(--foreground))', letterSpacing: '1.5px', textTransform: 'uppercase' }}>
                                Top Players
                            </h2>
                        </div>

                        <div className="flex-1 min-h-0" style={{ minHeight: 130 }}>
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart data={PLACEHOLDER_LEADERBOARD_DATA} layout="vertical"
                                    margin={{ top: 0, right: 36, left: 4, bottom: 0 }}>
                                    <XAxis type="number" hide />
                                    <YAxis type="category" dataKey="name" width={52} axisLine={false} tickLine={false}
                                        tick={{ fill: 'rgba(122,147,184,0.7)', fontSize: 10, fontFamily: 'Rajdhani, sans-serif', fontWeight: 600 }} />
                                    <Tooltip
                                        cursor={{ fill: 'rgba(249,160,27,0.05)' }}
                                        contentStyle={{ background: 'rgba(13,21,37,0.95)', border: '1px solid rgba(249,160,27,0.25)', borderRadius: 8, fontFamily: 'Rajdhani, sans-serif' }}
                                        labelStyle={{ color: '#F9A01B', fontWeight: 700, fontSize: 11 }}
                                        itemStyle={{ color: '#F0F4FF', fontSize: 11 }}
                                        formatter={(v) => [`+${v ?? 0}`, 'Plus-Minus']}
                                    />
                                    <Bar dataKey="value" radius={[0, 3, 3, 0]}
                                        label={{ position: 'right', fill: '#F9A01B', fontSize: 10, fontFamily: 'Rajdhani, sans-serif', fontWeight: 700, formatter: (v: unknown) => typeof v === 'number' ? `+${v}` : '' }}>
                                        {PLACEHOLDER_LEADERBOARD_DATA.map((_, i) => (
                                            <Cell key={i} fill={`rgba(249,160,27,${0.7 - i * 0.11})`} />
                                        ))}
                                    </Bar>
                                </BarChart>
                            </ResponsiveContainer>
                        </div>

                        <EmptyOverlay
                            icon={<Upload size={18} />}
                            message="Upload a roster to see rankings"
                            cta="Go to Teams & Players"
                            href={route('teams.index')}
                        />
                    </div>
                </div>

                {/* ── Recommended Lineup strip ── */}
                <div className="flex rounded-2xl overflow-hidden relative bg-card"
                     style={{ border: '1px solid hsl(var(--border))' }}>
                    <div className="flex flex-1 gap-3 p-4">
                        <div className="flex items-center gap-2 mr-2 shrink-0">
                            <Swords size={15} style={{ color: '#F9A01B', flexShrink: 0 }} />
                            <h2 className="whitespace-nowrap" style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '10px', fontWeight: 700, color: 'hsl(var(--foreground))', letterSpacing: '1.5px', textTransform: 'uppercase' }}>
                                Recommended Lineup
                            </h2>
                        </div>
                        <div className="flex flex-1 gap-2">
                            {[1, 2, 3, 4, 5].map((n) => (
                                <div key={n} className="flex-1 rounded-xl flex flex-col items-center justify-center py-3 gap-1.5 transition-colors bg-muted/30"
                                     style={{ border: '1px dashed hsl(var(--border))' }}>
                                    <div className="h-8 w-8 rounded-full flex items-center justify-center bg-muted/60"
                                         style={{ border: '1px solid hsl(var(--border))' }}>
                                        <PlayerSilhouette />
                                    </div>
                                    <div className="h-2 w-10 rounded bg-muted/60" />
                                    <div className="h-1.5 w-6 rounded bg-muted/40" />
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="flex flex-col items-center justify-center gap-2 px-6 relative"
                         style={{ borderLeft: '1px solid hsl(var(--border))', background: 'hsl(var(--muted) / 0.3)' }}>
                        <span style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '28px', fontWeight: 900, color: 'hsl(var(--muted-foreground) / 0.4)' }}>—</span>
                        <span className="text-[10px] text-center whitespace-nowrap"
                              style={{ fontFamily: 'Rajdhani, sans-serif', fontWeight: 700, color: 'hsl(var(--muted-foreground))', letterSpacing: '1px', textTransform: 'uppercase' }}>
                            Net Plus-Minus
                        </span>
                        <Link
                            href={route('comparison.index')}
                            className="mt-1 flex items-center gap-1.5 rounded-lg px-4 py-1.5 text-xs font-bold uppercase tracking-wider whitespace-nowrap transition-all hover:-translate-y-0.5"
                            style={{
                                fontFamily: 'Rajdhani, sans-serif',
                                background: 'linear-gradient(135deg, #98002E, #6d0020)',
                                border: '1px solid rgba(255,100,100,0.2)',
                                color: '#fff',
                                boxShadow: '0 0 16px rgba(152,0,46,0.3)',
                                letterSpacing: '1px',
                            }}
                        >
                            Run Comparison
                            <ChevronRight size={12} />
                        </Link>
                    </div>
                </div>

                {/* ── Quick-access cards ── */}
                <div className="grid grid-cols-3 gap-4">
                    <QuickCard href={route('teams.index')} icon={<Users2 size={18} />} title="Teams & Players" description="Create teams and upload rosters via CSV." />
                    <QuickCard href={route('comparison.index')} icon={<Swords size={18} />} title="Team Comparison" description="Win probability, win rate, and lineup recommendations." />
                    <QuickCard href={route('comparison.index')} icon={<BarChart2 size={18} />} title="Player Matchup" description="Head-to-head player comparison with edge prediction." />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

// ── Internal components ───────────────────────────────────────────────────────

function SummaryCard({ label, icon, emptyText, accent = false }: { label: string; icon: React.ReactNode; emptyText?: string; accent?: boolean }) {
    return (
        <div className="relative flex flex-col gap-3 rounded-2xl p-4 transition-all bg-card"
             style={{
                 border: accent ? '1px solid rgba(249,160,27,0.2)' : '1px solid hsl(var(--border))',
                 boxShadow: accent ? '0 0 20px rgba(249,160,27,0.06)' : '0 4px 16px rgba(0,0,0,0.06)',
             }}>
            {accent && (
                <div className="pointer-events-none absolute inset-0 rounded-2xl"
                     style={{ background: 'radial-gradient(ellipse at top right, rgba(249,160,27,0.06), transparent 70%)' }} />
            )}
            <div className="flex items-center justify-between">
                <span className="text-[10px] font-bold uppercase tracking-widest text-muted-foreground"
                      style={{ fontFamily: 'Rajdhani, sans-serif' }}>
                    {label}
                </span>
                <span style={{ color: accent ? 'rgba(249,160,27,0.6)' : 'hsl(var(--muted-foreground))' }}>{icon}</span>
            </div>
            <div className="flex flex-col gap-1">
                <div style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '28px', fontWeight: 900, color: accent ? 'rgba(249,160,27,0.3)' : 'hsl(var(--muted-foreground) / 0.4)' }}>—</div>
                <span className="text-xs text-muted-foreground" style={{ fontFamily: 'Rajdhani, sans-serif', fontWeight: 600 }}>{emptyText}</span>
            </div>
        </div>
    );
}


function EmptyOverlay({ icon, message, cta, href }: { icon: React.ReactNode; message: string; cta: string; href: string }) {
    return (
        <div className="flex flex-col items-center justify-center gap-2 py-1">
            <span className="text-muted-foreground/30">{icon}</span>
            <p className="text-xs text-center text-muted-foreground" style={{ fontFamily: 'Rajdhani, sans-serif', fontWeight: 600 }}>{message}</p>
            <Link href={href} className="flex items-center gap-1 text-xs font-bold transition-colors hover:underline"
                  style={{ fontFamily: 'Rajdhani, sans-serif', color: '#F9A01B', letterSpacing: '0.5px' }}>
                {cta} <ChevronRight size={11} />
            </Link>
        </div>
    );
}

function QuickCard({ href, icon, title, description }: { href: string; icon: React.ReactNode; title: string; description: string }) {
    return (
        <Link href={href} className="group flex flex-col gap-3 rounded-2xl p-4 transition-all hover:-translate-y-0.5 bg-card"
              style={{ border: '1px solid hsl(var(--border))', boxShadow: '0 4px 16px rgba(0,0,0,0.06)' }}>
            <div className="flex h-10 w-10 items-center justify-center rounded-xl transition-all"
                 style={{ background: 'rgba(152,0,46,0.1)', border: '1px solid rgba(152,0,46,0.2)', color: '#98002E' }}>
                {icon}
            </div>
            <div>
                <h3 style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '11px', fontWeight: 700, color: 'hsl(var(--foreground))', letterSpacing: '1px', textTransform: 'uppercase' }}>
                    {title}
                </h3>
                <p className="mt-1 text-xs text-muted-foreground" style={{ fontFamily: 'Rajdhani, sans-serif', fontWeight: 600 }}>{description}</p>
            </div>
            <div className="flex items-center gap-1 text-[11px] font-bold opacity-0 transition-opacity group-hover:opacity-100"
                 style={{ fontFamily: 'Rajdhani, sans-serif', color: '#F9A01B', letterSpacing: '0.5px' }}>
                Open <ChevronRight size={11} />
            </div>
        </Link>
    );
}

function PlayerSilhouette() {
    return (
        <svg viewBox="0 0 24 24" fill="none" className="h-4 w-4 text-muted-foreground/30" stroke="currentColor" strokeWidth={1.5}>
            <circle cx="12" cy="7" r="3" />
            <path d="M5 21v-2a7 7 0 0 1 14 0v2" strokeLinecap="round" />
        </svg>
    );
}

const PLACEHOLDER_IMPACT_DATA = [
    { name: 'P1',  value: 7.7  },
    { name: 'P2',  value: 11.2 },
    { name: 'P3',  value: 4.9  },
    { name: 'P4',  value: 9.1  },
    { name: 'P5',  value: 12.6 },
    { name: 'P6',  value: 6.3  },
    { name: 'P7',  value: 9.8  },
    { name: 'P8',  value: 5.6  },
    { name: 'P9',  value: 8.4  },
    { name: 'P10', value: 10.5 },
];

const PLACEHOLDER_LEADERBOARD_DATA = [
    { name: 'Player 1', value: 14 },
    { name: 'Player 2', value: 11 },
    { name: 'Player 3', value: 9  },
    { name: 'Player 4', value: 8  },
    { name: 'Player 5', value: 6  },
];
