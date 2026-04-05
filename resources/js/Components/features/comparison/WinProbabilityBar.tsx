import { type Team, type WinProbabilityResult } from '@/types';
import { Loader2 } from 'lucide-react';

interface WinProbabilityBarProps {
    teamA: Team;
    teamB: Team;
    result: WinProbabilityResult | null;
}

export function WinProbabilityBar({ teamA, teamB, result }: WinProbabilityBarProps) {
    const logoA = teamA.logo_path ? `/storage/${teamA.logo_path}` : null;
    const logoB = teamB.logo_path ? `/storage/${teamB.logo_path}` : null;

    if (result === null) {
        return (
            <div className="rounded-2xl p-6 space-y-5"
                 style={{ background: 'rgba(11,18,32,0.85)', border: '1px solid rgba(255,255,255,0.07)' }}>
                <div className="grid grid-cols-2 gap-4">
                    <ProbCard teamName={teamA.name} logoUrl={logoA} isHome loading />
                    <ProbCard teamName={teamB.name} logoUrl={logoB} isHome={false} loading />
                </div>
                <div className="flex items-center gap-2">
                    <Loader2 size={13} className="animate-spin" style={{ color: '#F9A01B' }} />
                    <span className="text-xs" style={{ fontFamily: 'Rajdhani, sans-serif', color: 'rgba(255,255,255,0.35)', fontWeight: 600 }}>
                        Computing win probability…
                    </span>
                </div>
            </div>
        );
    }

    const probA = Math.round(result.team_a_win_probability * 100);
    const probB = Math.round(result.team_b_win_probability * 100);
    const rateA = Math.round(result.team_a_win_rate * 100);
    const rateB = Math.round(result.team_b_win_rate * 100);
    const aLeads = probA >= probB;
    const winner = aLeads ? teamA.name : teamB.name;
    const confidence = Math.max(probA, probB);

    return (
        <div className="rounded-2xl p-5 space-y-5"
             style={{ background: 'rgba(11,18,32,0.85)', border: '1px solid rgba(255,255,255,0.07)' }}>

            {/* ── Probability cards ── */}
            <div className="grid grid-cols-2 gap-4">
                <ProbCard
                    teamName={teamA.name}
                    logoUrl={logoA}
                    probability={probA}
                    winRate={rateA}
                    isHome
                    isLeading={aLeads}
                />
                <ProbCard
                    teamName={teamB.name}
                    logoUrl={logoB}
                    probability={probB}
                    winRate={rateB}
                    isHome={false}
                    isLeading={!aLeads}
                />
            </div>

            {/* ── Power bar ── */}
            <div className="space-y-1.5">
                <div className="flex items-center justify-between">
                    <span className="text-[10px] font-bold uppercase tracking-widest"
                          style={{ fontFamily: 'Rajdhani, sans-serif', color: 'rgba(152,0,46,0.9)' }}>
                        {teamA.code} {probA}%
                    </span>
                    <span className="text-[10px] font-bold uppercase tracking-widest"
                          style={{ fontFamily: 'Rajdhani, sans-serif', color: 'rgba(100,150,255,0.9)' }}>
                        {probB}% {teamB.code}
                    </span>
                </div>
                <div className="h-3 w-full rounded-full overflow-hidden flex"
                     style={{ background: 'rgba(255,255,255,0.06)' }}>
                    <div className="h-full transition-all duration-700"
                         style={{
                             width: `${probA}%`,
                             background: 'linear-gradient(90deg, rgba(152,0,46,0.9), rgba(200,0,60,0.7))',
                             boxShadow: '2px 0 8px rgba(152,0,46,0.5)',
                         }} />
                    <div className="h-full flex-1"
                         style={{
                             background: 'linear-gradient(90deg, rgba(60,100,200,0.5), rgba(40,80,180,0.8))',
                         }} />
                </div>
            </div>

            {/* ── Insights + Outcome ── */}
            <div className="grid grid-cols-5 gap-4">
                <div className="col-span-3 space-y-2">
                    <h4 style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '10px', fontWeight: 700, color: 'rgba(255,255,255,0.7)', letterSpacing: '1.5px', textTransform: 'uppercase' }}>
                        Probability Insights
                    </h4>
                    <div className="space-y-2.5 rounded-xl p-3"
                         style={{ background: 'rgba(255,255,255,0.03)', border: '1px solid rgba(255,255,255,0.06)' }}>
                        <InsightRow dot="rgba(52,211,153,0.9)" text={`${winner} has a ${confidence}% win probability based on uploaded roster stats.`} />
                        <InsightRow dot="rgba(96,165,250,0.9)" text={`Home win rate: ${rateA}% · Away win rate: ${rateB}%.`} />
                    </div>
                </div>

                <div className="col-span-2 space-y-2">
                    <h4 style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '10px', fontWeight: 700, color: 'rgba(255,255,255,0.7)', letterSpacing: '1.5px', textTransform: 'uppercase' }}>
                        Predicted Winner
                    </h4>
                    <div className="rounded-xl p-3 text-center space-y-2"
                         style={{ background: 'rgba(255,255,255,0.03)', border: '1px solid rgba(255,255,255,0.06)' }}>
                        {/* Conic donut */}
                        <div className="flex items-center justify-center">
                            <div className="relative h-16 w-16 rounded-full flex items-center justify-center"
                                 style={{ background: `conic-gradient(#F9A01B 0% ${confidence}%, rgba(255,255,255,0.08) ${confidence}% 100%)` }}>
                                <div className="absolute h-10 w-10 rounded-full flex items-center justify-center"
                                     style={{ background: '#080C18' }}>
                                    <span style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '11px', fontWeight: 900, color: '#F9A01B' }}>
                                        {confidence}%
                                    </span>
                                </div>
                            </div>
                        </div>
                        <p style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '11px', fontWeight: 700, color: 'rgba(255,255,255,0.85)', letterSpacing: '0.5px', textTransform: 'uppercase' }}>
                            {winner}
                        </p>
                        <p style={{ fontFamily: 'Rajdhani, sans-serif', fontSize: '10px', fontWeight: 600, color: 'rgba(255,255,255,0.3)', letterSpacing: '0.5px', textTransform: 'uppercase' }}>
                            Confidence: {confidence}%
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
}

// ── Probability card ──────────────────────────────────────────────────────────

function ProbCard({
    teamName, logoUrl, probability, winRate, isHome, isLeading = false, loading = false,
}: {
    teamName: string;
    logoUrl: string | null;
    probability?: number;
    winRate?: number;
    isHome: boolean;
    isLeading?: boolean;
    loading?: boolean;
}) {
    const borderColor = isHome ? 'rgba(152,0,46,0.3)' : 'rgba(60,100,200,0.3)';
    const glowColor = isHome ? 'rgba(152,0,46,0.15)' : 'rgba(60,100,200,0.15)';
    const numColor = isLeading ? '#F9A01B' : 'rgba(255,255,255,0.5)';

    return (
        <div className="relative rounded-2xl p-4 space-y-3 overflow-hidden"
             style={{
                 background: `rgba(11,18,32,0.9)`,
                 border: `1px solid ${borderColor}`,
                 boxShadow: `0 0 20px ${glowColor}`,
             }}>
            <div className="pointer-events-none absolute inset-0"
                 style={{ background: 'radial-gradient(ellipse at top, rgba(255,255,255,0.02), transparent 70%)' }} />

            {/* Team identity row */}
            <div className="flex items-center gap-2.5">
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg overflow-hidden"
                     style={{
                         background: logoUrl ? 'transparent' : (isHome ? 'rgba(152,0,46,0.3)' : 'rgba(30,60,120,0.3)'),
                         border: `1px solid ${borderColor}`,
                     }}>
                    {logoUrl ? (
                        <img src={logoUrl} alt="" className="h-full w-full object-cover" />
                    ) : (
                        <span style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '10px', fontWeight: 900, color: 'rgba(255,255,255,0.7)' }}>
                            {teamName.slice(0, 3).toUpperCase()}
                        </span>
                    )}
                </div>
                <div>
                    <p className="text-[10px] font-semibold uppercase tracking-widest"
                       style={{ fontFamily: 'Rajdhani, sans-serif', color: 'rgba(255,255,255,0.4)' }}>
                        {isHome ? 'Home' : 'Away'}
                    </p>
                    <p className="text-sm font-bold truncate max-w-[120px]"
                       style={{ fontFamily: 'Rajdhani, sans-serif', color: 'rgba(255,255,255,0.85)', letterSpacing: '0.5px' }}>
                        {teamName}
                    </p>
                </div>
            </div>

            {loading ? (
                <div className="flex items-center gap-2 py-3">
                    <Loader2 size={16} className="animate-spin" style={{ color: 'rgba(255,255,255,0.3)' }} />
                    <span style={{ fontFamily: 'Rajdhani, sans-serif', fontSize: '13px', color: 'rgba(255,255,255,0.3)', fontWeight: 600 }}>Computing…</span>
                </div>
            ) : (
                <div>
                    <p style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '42px', fontWeight: 900, color: numColor, lineHeight: 1, textShadow: isLeading ? '0 0 20px rgba(249,160,27,0.4)' : 'none' }}>
                        {probability}%
                    </p>
                    <p className="mt-1 text-xs"
                       style={{ fontFamily: 'Rajdhani, sans-serif', color: 'rgba(255,255,255,0.3)', fontWeight: 600 }}>
                        Win Rate:{' '}
                        <span style={{ color: isLeading ? '#F9A01B' : 'rgba(255,255,255,0.5)', fontWeight: 700 }}>
                            {winRate}%
                        </span>
                    </p>
                </div>
            )}
        </div>
    );
}

function InsightRow({ dot, text }: { dot: string; text: string }) {
    return (
        <div className="flex items-start gap-2">
            <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full" style={{ background: dot }} />
            <p className="text-[11px] leading-relaxed" style={{ fontFamily: 'Rajdhani, sans-serif', color: 'rgba(255,255,255,0.45)', fontWeight: 600 }}>
                {text}
            </p>
        </div>
    );
}
