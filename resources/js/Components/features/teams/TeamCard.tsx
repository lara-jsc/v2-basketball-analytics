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
            className="group block rounded-2xl overflow-hidden transition-all duration-200 hover:-translate-y-0.5"
            style={{
                background: 'rgba(11,18,32,0.85)',
                border: '1px solid rgba(255,255,255,0.07)',
                boxShadow: '0 4px 24px rgba(0,0,0,0.4)',
            }}
        >
            {/* ── Hero section: logo + team identity ── */}
            <div className="relative flex flex-col items-center justify-center py-7 gap-4 overflow-hidden"
                 style={{
                     background: 'linear-gradient(180deg, rgba(152,0,46,0.18) 0%, rgba(11,18,32,0) 100%)',
                     borderBottom: '1px solid rgba(255,255,255,0.06)',
                 }}
            >
                {/* Court line texture */}
                <div className="pointer-events-none absolute inset-0 opacity-30"
                     style={{
                         backgroundImage: 'repeating-linear-gradient(90deg, transparent, transparent 60px, rgba(249,160,27,0.04) 60px, rgba(249,160,27,0.04) 61px)',
                     }} />

                {/* Team logo — big */}
                <div className="relative flex h-20 w-20 items-center justify-center rounded-2xl overflow-hidden transition-all group-hover:scale-105 duration-300"
                     style={{
                         background: logoUrl ? 'transparent' : 'linear-gradient(135deg, rgba(152,0,46,0.4), rgba(80,0,20,0.6))',
                         border: '2px solid rgba(255,140,0,0.2)',
                         boxShadow: '0 0 30px rgba(152,0,46,0.3), inset 0 1px 0 rgba(255,255,255,0.1)',
                     }}
                >
                    {logoUrl ? (
                        <img src={logoUrl} alt={`${team.name} logo`} className="h-full w-full object-cover" />
                    ) : (
                        <span style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '22px', fontWeight: 900, color: 'rgba(255,200,200,0.9)', letterSpacing: '-1px' }}>
                            {team.code.slice(0, 3).toUpperCase()}
                        </span>
                    )}
                    {/* Glow overlay */}
                    <div className="pointer-events-none absolute inset-0 rounded-2xl"
                         style={{ boxShadow: 'inset 0 0 20px rgba(255,140,0,0.08)' }} />
                </div>

                {/* Team name */}
                <div className="text-center px-4">
                    <h3 style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '13px', fontWeight: 700, color: 'rgba(255,255,255,0.95)', letterSpacing: '1px', textTransform: 'uppercase', textShadow: '0 0 12px rgba(255,255,255,0.2)' }}>
                        {team.name}
                    </h3>
                    <div className="mt-2 flex items-center justify-center gap-2">
                        <span className="rounded px-2 py-0.5 text-[10px] font-semibold tracking-widest uppercase"
                              style={{ fontFamily: 'Rajdhani, sans-serif', background: 'rgba(249,160,27,0.12)', border: '1px solid rgba(249,160,27,0.3)', color: '#F9A01B' }}>
                            {team.code}
                        </span>
                        {team.is_active ? (
                            <span className="flex items-center gap-1 text-[10px] font-semibold text-emerald-400"
                                  style={{ fontFamily: 'Rajdhani, sans-serif' }}>
                                <CheckCircle2 size={10} /> Active
                            </span>
                        ) : (
                            <span className="flex items-center gap-1 text-[10px] font-semibold"
                                  style={{ fontFamily: 'Rajdhani, sans-serif', color: 'rgba(255,255,255,0.3)' }}>
                                <XCircle size={10} /> Inactive
                            </span>
                        )}
                    </div>
                </div>
            </div>

            {/* ── Footer CTA ── */}
            <div className="flex items-center justify-between px-4 py-3">
                <span className="text-xs" style={{ fontFamily: 'Rajdhani, sans-serif', color: 'rgba(255,255,255,0.35)', fontWeight: 600, letterSpacing: '0.5px' }}>
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
