import { type Team } from '@/types';
import { Link } from '@inertiajs/react';
import { ArrowRight, CheckCircle2, XCircle } from 'lucide-react';

interface TeamCardProps {
    team: Team;
}

export function TeamCard({ team }: TeamCardProps) {
    const logoUrl = team.logo_path ? `/storage/${team.logo_path}` : null;

    return (
        <Link
            href={route('teams.show', { id: team.id })}
            className="group block rounded-2xl overflow-hidden transition-all duration-200 hover:-translate-y-0.5 bg-card"
            style={{
                border: '1px solid hsl(var(--border))',
                boxShadow: '0 4px 16px rgba(0,0,0,0.08)',
            }}
        >
            {/* ── Hero section ── */}
            <div className="relative flex flex-col items-center justify-center py-7 gap-4 overflow-hidden"
                 style={{ borderBottom: '1px solid hsl(var(--border))' }}
            >
                <div className="pointer-events-none absolute inset-0 opacity-20"
                     style={{ backgroundImage: 'repeating-linear-gradient(90deg, transparent, transparent 60px, rgba(249,160,27,0.06) 60px, rgba(249,160,27,0.06) 61px)' }} />

                {/* Team logo */}
                <div className="relative flex h-20 w-20 items-center justify-center rounded-2xl overflow-hidden transition-all group-hover:scale-105 duration-300"
                     style={{
                         background: logoUrl ? 'transparent' : 'linear-gradient(135deg, rgba(152,0,46,0.3), rgba(80,0,20,0.5))',
                         border: '2px solid rgba(255,140,0,0.2)',
                         boxShadow: '0 0 24px rgba(152,0,46,0.2)',
                     }}
                >
                    {logoUrl ? (
                        <img src={logoUrl} alt={`${team.name} logo`} className="h-full w-full object-cover" />
                    ) : (
                        <span style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '22px', fontWeight: 900, color: '#98002E', letterSpacing: '-1px' }}>
                            {team.code.slice(0, 3).toUpperCase()}
                        </span>
                    )}
                </div>

                {/* Team name */}
                <div className="text-center px-4">
                    <h3 style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '13px', fontWeight: 700, color: 'hsl(var(--foreground))', letterSpacing: '1px', textTransform: 'uppercase' }}>
                        {team.name}
                    </h3>
                    <div className="mt-2 flex items-center justify-center gap-2">
                        <span className="rounded px-2 py-0.5 text-[10px] font-semibold tracking-widest uppercase"
                              style={{ fontFamily: 'Rajdhani, sans-serif', background: 'rgba(249,160,27,0.1)', border: '1px solid rgba(249,160,27,0.3)', color: '#F9A01B' }}>
                            {team.code}
                        </span>
                        {team.is_active ? (
                            <span className="flex items-center gap-1 text-[10px] font-semibold text-emerald-600 dark:text-emerald-400"
                                  style={{ fontFamily: 'Rajdhani, sans-serif' }}>
                                <CheckCircle2 size={10} /> Active
                            </span>
                        ) : (
                            <span className="flex items-center gap-1 text-[10px] font-semibold text-muted-foreground"
                                  style={{ fontFamily: 'Rajdhani, sans-serif' }}>
                                <XCircle size={10} /> Inactive
                            </span>
                        )}
                    </div>
                </div>
            </div>

            {/* ── Footer CTA ── */}
            <div className="flex items-center justify-between px-4 py-3">
                <span className="text-xs text-muted-foreground" style={{ fontFamily: 'Rajdhani, sans-serif', fontWeight: 600, letterSpacing: '0.5px' }}>
                    Manage roster &amp; stats
                </span>
                <span className="flex items-center gap-1 text-xs font-semibold transition-all opacity-40 group-hover:opacity-100 group-hover:gap-2"
                      style={{ fontFamily: 'Rajdhani, sans-serif', color: '#FF8C00' }}>
                    Open <ArrowRight size={12} />
                </span>
            </div>

            {/* Bottom glow line on hover */}
            <div className="h-[2px] w-full opacity-0 group-hover:opacity-100 transition-opacity duration-300"
                 style={{ background: 'linear-gradient(90deg, transparent, #FF8C00, transparent)' }} />
        </Link>
    );
}
