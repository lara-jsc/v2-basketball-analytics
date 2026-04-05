import { type Team, type WinProbabilityResult } from '@/types';
import { Loader2 } from 'lucide-react';
import { PolarAngleAxis, RadialBar, RadialBarChart } from 'recharts';

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
            <div className="rounded-2xl p-6 space-y-5 bg-card"
                style={{ border: '1px solid hsl(var(--border))' }}>
                <div className="grid grid-cols-2 gap-4">
                    <ProbCard teamName={teamA.name} logoUrl={logoA} isHome loading />
                    <ProbCard teamName={teamB.name} logoUrl={logoB} isHome={false} loading />
                </div>
                <div className="flex items-center gap-2">
                    <Loader2 size={13} className="animate-spin" style={{ color: '#F9A01B' }} />
                    <span className="text-xs text-muted-foreground" style={{ fontFamily: 'Rajdhani, sans-serif', fontWeight: 600 }}>
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
        <div className="rounded-2xl p-5 space-y-5 bg-card"
            style={{ border: '1px solid hsl(var(--border))' }}>

            {/* ── Probability cards ── */}
            <div className="grid grid-cols-2 gap-4">
                <ProbCard teamName={teamA.name} logoUrl={logoA} probability={probA} winRate={rateA} isHome isLeading={aLeads} />
                <ProbCard teamName={teamB.name} logoUrl={logoB} probability={probB} winRate={rateB} isHome={false} isLeading={!aLeads} />
            </div>

            {/* ── Power bar ── */}
            <div className="space-y-1.5">
                <div className="flex items-center justify-between">
                    <span className="text-[10px] font-bold uppercase tracking-widest"
                        style={{ fontFamily: 'Rajdhani, sans-serif', color: '#98002E' }}>
                        {teamA.code} {probA}%
                    </span>
                    <span className="text-[10px] font-bold uppercase tracking-widest"
                        style={{ fontFamily: 'Rajdhani, sans-serif', color: '#3b5fb5' }}>
                        {probB}% {teamB.code}
                    </span>
                </div>
                <div className="h-3 w-full rounded-full overflow-hidden flex bg-muted/40">
                    <div className="h-full transition-all duration-700"
                        style={{
                            width: `${probA}%`,
                            background: 'linear-gradient(90deg, rgba(152,0,46,0.9), rgba(200,0,60,0.7))',
                            boxShadow: '2px 0 8px rgba(152,0,46,0.3)',
                        }} />
                    <div className="h-full flex-1"
                        style={{ background: 'linear-gradient(90deg, rgba(60,100,200,0.5), rgba(40,80,180,0.7))' }} />
                </div>
            </div>

            {/* ── Insights + Outcome ── */}
            <div className="grid grid-cols-5 gap-4">
                <div className="col-span-3 space-y-2">
                    <h4 style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '10px', fontWeight: 700, color: 'hsl(var(--foreground))', letterSpacing: '1.5px', textTransform: 'uppercase' }}>
                        Probability Insights
                    </h4>
                    <div className="space-y-2.5 rounded-xl p-3 bg-muted/20"
                        style={{ border: '1px solid hsl(var(--border))' }}>
                        <InsightRow dot="rgba(52,211,153,0.9)" text={`${winner} has a ${confidence}% win probability based on uploaded roster stats.`} />
                        <InsightRow dot="rgba(96,165,250,0.9)" text={`Home win rate: ${rateA}% · Away win rate: ${rateB}%.`} />
                    </div>
                </div>

                <div className="col-span-2 space-y-2">
                    <h4 style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '10px', fontWeight: 700, color: 'hsl(var(--foreground))', letterSpacing: '1.5px', textTransform: 'uppercase' }}>
                        Predicted Winner
                    </h4>
                    <div className="rounded-xl p-3 text-center space-y-1 bg-muted/20"
                        style={{ border: '1px solid hsl(var(--border))' }}>
                        <div className="flex items-center justify-center">
                            <div className="relative" style={{ width: 80, height: 80 }}>
                                <RadialBarChart
                                    width={80}
                                    height={80}
                                    cx={40}
                                    cy={40}
                                    innerRadius={26}
                                    outerRadius={38}
                                    startAngle={90}
                                    endAngle={-270}
                                    data={[{ value: confidence }]}
                                >
                                    <PolarAngleAxis type="number" domain={[0, 100]} angleAxisId={0} tick={false} />
                                    <RadialBar
                                        dataKey="value"
                                        fill="#F9A01B"
                                        cornerRadius={4}
                                        background={{ fill: 'rgba(122,147,184,0.12)' }}
                                        angleAxisId={0}
                                    />
                                </RadialBarChart>
                                <div className="absolute inset-0 flex items-center justify-center pointer-events-none">
                                    <span style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '11px', fontWeight: 900, color: '#F9A01B' }}>
                                        {confidence}%
                                    </span>
                                </div>
                            </div>
                        </div>
                        <p style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '11px', fontWeight: 700, color: 'hsl(var(--foreground))', letterSpacing: '0.5px', textTransform: 'uppercase' }}>
                            {winner}
                        </p>
                        <p className="text-muted-foreground" style={{ fontFamily: 'Rajdhani, sans-serif', fontSize: '10px', fontWeight: 600, letterSpacing: '0.5px', textTransform: 'uppercase' }}>
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
    const borderColor = isHome ? 'rgba(152,0,46,0.25)' : 'rgba(60,100,200,0.25)';
    const bgColor = isHome ? 'rgba(152,0,46,0.06)' : 'rgba(30,60,120,0.06)';

    return (
        <div className="relative rounded-2xl p-4 space-y-3 overflow-hidden bg-card"
            style={{ border: `1px solid ${borderColor}`, boxShadow: `0 0 16px ${bgColor}` }}>
            <div className="pointer-events-none absolute inset-0"
                style={{ background: `radial-gradient(ellipse at top, ${bgColor}, transparent 70%)` }} />

            {/* Team identity */}
            <div className="flex items-center gap-2.5">
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg overflow-hidden"
                    style={{
                        background: logoUrl ? 'transparent' : (isHome ? 'rgba(152,0,46,0.1)' : 'rgba(30,60,120,0.1)'),
                        border: `1px solid ${borderColor}`,
                    }}>
                    {logoUrl ? (
                        <img src={logoUrl} alt="" className="h-full w-full object-cover" />
                    ) : (
                        <span style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '10px', fontWeight: 900, color: isHome ? '#98002E' : '#3b5fb5' }}>
                            {teamName.slice(0, 3).toUpperCase()}
                        </span>
                    )}
                </div>
                <div>
                    <p className="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground"
                        style={{ fontFamily: 'Rajdhani, sans-serif' }}>
                        {isHome ? 'Home' : 'Away'}
                    </p>
                    <p className="text-sm font-bold truncate max-w-[120px] text-foreground"
                        style={{ fontFamily: 'Rajdhani, sans-serif', letterSpacing: '0.5px' }}>
                        {teamName}
                    </p>
                </div>
            </div>

            {loading ? (
                <div className="flex items-center gap-2 py-3">
                    <Loader2 size={16} className="animate-spin text-muted-foreground" />
                    <span className="text-muted-foreground" style={{ fontFamily: 'Rajdhani, sans-serif', fontSize: '13px', fontWeight: 600 }}>Computing…</span>
                </div>
            ) : (
                <div>
                    <p style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '42px', fontWeight: 900, color: isLeading ? '#F9A01B' : 'hsl(var(--foreground))', lineHeight: 1 }}>
                        {probability}%
                    </p>
                    <p className="mt-1 text-xs text-muted-foreground" style={{ fontFamily: 'Rajdhani, sans-serif', fontWeight: 600 }}>
                        Win Rate:{' '}
                        <span style={{ color: isLeading ? '#F9A01B' : 'hsl(var(--foreground))', fontWeight: 700 }}>
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
            <p className="text-[11px] leading-relaxed text-muted-foreground" style={{ fontFamily: 'Rajdhani, sans-serif', fontWeight: 600 }}>
                {text}
            </p>
        </div>
    );
}
