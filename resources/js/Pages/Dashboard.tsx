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
                            <span style={{ color: '#F9A01B', fontWeight: 700 }}>{auth.user.name}</span>
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

                        <div className="flex flex-1 items-end gap-2 pt-2 min-h-0 relative">
                            {PLACEHOLDER_BARS.map((h, i) => (
                                <div key={i} className="flex flex-1 flex-col items-center gap-1.5">
                                    <div className="w-full rounded-t-sm"
                                         style={{
                                             height: `${h}%`,
                                             maxHeight: '120px',
                                             minHeight: '8px',
                                             background: `linear-gradient(to top, rgba(249,160,27,0.6), rgba(249,160,27,0.15))`,
                                             borderTop: '1px solid rgba(249,160,27,0.5)',
                                         }} />
                                    <div className="h-2 w-6 rounded bg-muted/60" />
                                </div>
                            ))}
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

                        <div className="flex flex-col gap-3 flex-1">
                            {PLACEHOLDER_PLAYERS.map((p, i) => (
                                <PlaceholderLeaderRow key={i} width={p} rank={i + 1} value={PLACEHOLDER_VALUES[i]} />
                            ))}
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
                    <div className="pointer-events-none absolute inset-0"
                         style={{ background: 'linear-gradient(90deg, rgba(152,0,46,0.08), transparent, rgba(249,160,27,0.03))' }} />

                    <div className="flex flex-1 gap-3 p-4 relative">
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

function PlaceholderLeaderRow({ width, rank, value }: { width: number; rank: number; value: string }) {
    return (
        <div className="flex items-center gap-2">
            <span className="w-4 shrink-0 text-right text-[10px] font-bold text-muted-foreground/50"
                  style={{ fontFamily: 'Rajdhani, sans-serif' }}>
                {rank}
            </span>
            <div className="h-2 w-16 rounded shrink-0 bg-muted/60" />
            <div className="flex-1 h-2.5 rounded-full overflow-hidden bg-muted/40">
                <div className="h-full rounded-full"
                     style={{ width: `${width}%`, background: 'linear-gradient(to right, rgba(249,160,27,0.5), rgba(249,160,27,0.15))' }} />
            </div>
            <span className="text-xs font-bold shrink-0 w-8 text-right"
                  style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '10px', color: 'rgba(249,160,27,0.5)' }}>
                {value}
            </span>
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

const PLACEHOLDER_BARS = [55, 80, 35, 65, 90, 45, 70, 40, 60, 75];
const PLACEHOLDER_PLAYERS = [92, 78, 65, 54, 42];
const PLACEHOLDER_VALUES = ['+14', '+11', '+9', '+8', '+6'];
