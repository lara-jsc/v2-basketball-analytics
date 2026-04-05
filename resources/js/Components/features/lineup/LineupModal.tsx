import { type LineupRecommendation, type PlayerWithStats } from '@/types';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Loader2, X } from 'lucide-react';

interface LineupModalProps {
    open: boolean;
    onClose: () => void;
    lineup: LineupRecommendation | null;
    teamName: string;
    players: PlayerWithStats[];
}

function computeOvr(player: PlayerWithStats | undefined): number | null {
    if (!player || !player.stats[0]) return null;
    const s = player.stats[0];
    if (s.pts == null && s.fg_pct == null && s.ast == null && s.reb == null) return null;
    const pts = Number(s.pts ?? 0);
    const fg = Number(s.fg_pct ?? 0);
    const ast = Number(s.ast ?? 0);
    const reb = Number(s.reb ?? 0);
    const pm = Number(s.plus_minus ?? 0);
    const raw = (pts * 1.8) + (fg * 30) + (ast * 1.2) + (reb * 0.8) + (pm * 0.5);
    return Math.min(99, Math.max(60, Math.round(raw)));
}

export function LineupModal({ open, onClose, lineup, teamName, players }: LineupModalProps) {
    const netPlusMinus = lineup
        ? lineup.recommended_lineup.reduce((sum, p) => sum + p.plus_minus_score, 0)
        : null;

    return (
        <Dialog open={open} onOpenChange={(v) => !v && onClose()}>
            <DialogContent className="max-w-5xl p-0 overflow-hidden bg-card"
                           style={{ border: '1px solid hsl(var(--border))' }}>

                {/* ── Header ── */}
                <DialogHeader className="relative overflow-hidden px-6 py-5"
                              style={{ borderBottom: '1px solid hsl(var(--border))' }}>
                    <div className="pointer-events-none absolute inset-0"
                         style={{ backgroundImage: 'repeating-linear-gradient(90deg,transparent,transparent 60px,rgba(249,160,27,0.03) 60px,rgba(249,160,27,0.03) 61px)' }} />
                    <div className="relative flex items-start justify-between gap-4">
                        <div>
                            <DialogTitle style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '16px', fontWeight: 900, color: 'hsl(var(--foreground))', letterSpacing: '2px', textTransform: 'uppercase' }}>
                                Recommended Lineup
                            </DialogTitle>
                            <p className="mt-1 text-muted-foreground" style={{ fontFamily: 'Rajdhani, sans-serif', fontSize: '12px', fontWeight: 600, letterSpacing: '0.5px' }}>
                                AI-Optimal Starting 5 — {teamName}
                            </p>
                        </div>
                        <button onClick={onClose}
                                className="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg transition-colors hover:bg-muted text-muted-foreground">
                            <X size={15} />
                        </button>
                    </div>
                </DialogHeader>

                {lineup === null ? (
                    <div className="flex flex-col items-center justify-center gap-3 py-20">
                        <Loader2 size={28} className="animate-spin" style={{ color: '#F9A01B' }} />
                        <p className="text-muted-foreground" style={{ fontFamily: 'Rajdhani, sans-serif', fontSize: '14px', fontWeight: 600 }}>
                            Computing lineup recommendation…
                        </p>
                    </div>
                ) : (
                    <div className="flex gap-0">
                        {/* ── Left: 5 player cards ── */}
                        <div className="flex flex-1 flex-col gap-5 p-6">
                            <p style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '11px', fontWeight: 700, color: 'hsl(var(--muted-foreground))', letterSpacing: '2px', textTransform: 'uppercase' }}>
                                Optimized Starting Lineup
                            </p>

                            <div className="flex gap-3">
                                {lineup.recommended_lineup.slice(0, 5).map((lp) => {
                                    const match = players.find((p) => p.id === lp.player_id);
                                    const ovr = computeOvr(match);
                                    const photoUrl = match?.profile_picture_path
                                        ? `/storage/${match.profile_picture_path}`
                                        : null;
                                    return (
                                        <PlayerCard
                                            key={lp.player_id}
                                            name={lp.name}
                                            plusMinus={lp.plus_minus_score}
                                            jerseyNumber={match?.jersey_number ?? null}
                                            position={match?.role ?? null}
                                            photoUrl={photoUrl}
                                            ovr={ovr}
                                        />
                                    );
                                })}
                            </div>

                            {/* Net plus-minus footer */}
                            <div className="flex items-center gap-3 rounded-xl px-4 py-3"
                                 style={{ background: 'rgba(249,160,27,0.06)', border: '1px solid rgba(249,160,27,0.15)' }}>
                                <span style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '24px', fontWeight: 900, color: '#F9A01B', textShadow: '0 0 12px rgba(249,160,27,0.3)' }}>
                                    {netPlusMinus !== null && netPlusMinus >= 0 ? '+' : ''}
                                    {netPlusMinus?.toFixed(1) ?? '—'}
                                </span>
                                <div>
                                    <p style={{ fontFamily: 'Rajdhani, sans-serif', fontSize: '11px', fontWeight: 700, color: 'hsl(var(--foreground))', letterSpacing: '1px', textTransform: 'uppercase' }}>
                                        Net Plus-Minus
                                    </p>
                                    <p className="text-muted-foreground" style={{ fontFamily: 'Rajdhani, sans-serif', fontSize: '11px', fontWeight: 600 }}>
                                        Expected when using this lineup
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* ── Right: confidence + CTA ── */}
                        <div className="flex flex-col w-52 shrink-0 p-5 gap-5"
                             style={{ borderLeft: '1px solid hsl(var(--border))', background: 'hsl(var(--muted) / 0.3)' }}>
                            <div className="text-center">
                                <p style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '40px', fontWeight: 900, color: '#F9A01B', lineHeight: 1, textShadow: '0 0 16px rgba(249,160,27,0.3)' }}>
                                    {netPlusMinus !== null && netPlusMinus >= 0 ? '+' : ''}
                                    {netPlusMinus?.toFixed(1) ?? '—'}
                                </p>
                                <p className="mt-1 text-muted-foreground" style={{ fontFamily: 'Rajdhani, sans-serif', fontSize: '10px', fontWeight: 700, letterSpacing: '1.5px', textTransform: 'uppercase' }}>
                                    Net Plus-Minus
                                </p>
                            </div>

                            {/* Confidence ring */}
                            <div className="flex flex-col items-center gap-2">
                                <div className="relative h-20 w-20 rounded-full flex items-center justify-center"
                                     style={{ background: `conic-gradient(#F9A01B 0% ${Math.round(lineup.confidence * 100)}%, hsl(var(--muted)) ${Math.round(lineup.confidence * 100)}% 100%)` }}>
                                    <div className="absolute rounded-full flex items-center justify-center bg-card"
                                         style={{ height: '52px', width: '52px' }}>
                                        <span style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '13px', fontWeight: 900, color: '#F9A01B' }}>
                                            {Math.round(lineup.confidence * 100)}%
                                        </span>
                                    </div>
                                </div>
                                <p className="text-muted-foreground" style={{ fontFamily: 'Rajdhani, sans-serif', fontSize: '10px', fontWeight: 700, letterSpacing: '1.5px', textTransform: 'uppercase' }}>
                                    Confidence
                                </p>
                            </div>

                            <div className="mt-auto" />

                            <button
                                onClick={onClose}
                                className="w-full rounded-xl py-3 text-sm font-bold uppercase tracking-widest transition-all hover:-translate-y-0.5"
                                style={{
                                    fontFamily: 'Rajdhani, sans-serif',
                                    background: 'linear-gradient(135deg, #F9A01B, #d4860f)',
                                    border: '1px solid rgba(249,160,27,0.3)',
                                    color: '#080C18',
                                    boxShadow: '0 0 16px rgba(249,160,27,0.2)',
                                    letterSpacing: '1.5px',
                                }}
                            >
                                Confirm Lineup →
                            </button>
                        </div>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}

// ── Player card ───────────────────────────────────────────────────────────────

function PlayerCard({
    name, plusMinus, jerseyNumber, position, photoUrl, ovr,
}: {
    name: string;
    plusMinus: number;
    jerseyNumber: number | null;
    position: string | null;
    photoUrl: string | null;
    ovr: number | null;
}) {
    const [firstName, ...rest] = name.split(' ');
    const lastName = rest.join(' ');
    const isPositive = plusMinus >= 0;

    return (
        <div className="flex flex-1 flex-col items-center gap-2 rounded-2xl overflow-hidden transition-all bg-card"
             style={{
                 border: '1px solid hsl(var(--border))',
                 boxShadow: '0 4px 16px rgba(0,0,0,0.06)',
             }}>

            {/* Jersey # + OVR */}
            <div className="relative w-full flex items-center justify-between px-2.5 pt-2.5">
                <span style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '22px', fontWeight: 900, color: 'hsl(var(--muted-foreground) / 0.2)', lineHeight: 1 }}>
                    {jerseyNumber ?? '—'}
                </span>
                {ovr !== null && (
                    <div className="flex flex-col items-center leading-none"
                         style={{ background: 'rgba(249,160,27,0.1)', border: '1px solid rgba(249,160,27,0.3)', borderRadius: '6px', padding: '2px 6px' }}>
                        <span style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '14px', fontWeight: 900, color: '#F9A01B', lineHeight: 1 }}>
                            {ovr}
                        </span>
                        <span style={{ fontFamily: 'Rajdhani, sans-serif', fontSize: '9px', fontWeight: 700, color: 'rgba(249,160,27,0.7)', letterSpacing: '0.5px', textTransform: 'uppercase' }}>
                            OVR
                        </span>
                    </div>
                )}
            </div>

            {/* Player photo */}
            <div className="flex h-16 w-16 items-center justify-center rounded-full overflow-hidden bg-muted/40"
                 style={{ border: '2px solid hsl(var(--border))' }}>
                {photoUrl ? (
                    <img src={photoUrl} alt={name} className="h-full w-full object-cover" />
                ) : (
                    <svg viewBox="0 0 24 24" fill="none" className="h-9 w-9 text-muted-foreground/30" stroke="currentColor" strokeWidth={1.2}>
                        <circle cx="12" cy="7" r="4" />
                        <path d="M4 21v-2a8 8 0 0 1 16 0v2" strokeLinecap="round" />
                    </svg>
                )}
            </div>

            {/* Name */}
            <div className="text-center px-2 min-w-0 w-full">
                <p className="truncate text-foreground" style={{ fontFamily: 'Rajdhani, sans-serif', fontSize: '13px', fontWeight: 700, lineHeight: 1.2, letterSpacing: '0.5px' }}>
                    {firstName}
                </p>
                {lastName && (
                    <p className="truncate text-foreground" style={{ fontFamily: 'Rajdhani, sans-serif', fontSize: '13px', fontWeight: 700, lineHeight: 1.2 }}>
                        {lastName}
                    </p>
                )}
            </div>

            {/* Position badge */}
            {position ? (
                <span className="rounded px-2 py-0.5 text-[9px] font-bold uppercase tracking-widest"
                      style={{ fontFamily: 'Rajdhani, sans-serif', background: 'rgba(249,160,27,0.1)', border: '1px solid rgba(249,160,27,0.25)', color: '#F9A01B' }}>
                    {position}
                </span>
            ) : (
                <span className="h-4" />
            )}

            {/* Plus-minus */}
            <div className="w-full px-2 pb-3 text-center">
                <span style={{
                    fontFamily: 'Orbitron, sans-serif',
                    fontSize: '14px',
                    fontWeight: 900,
                    color: isPositive ? '#F9A01B' : 'hsl(var(--muted-foreground))',
                }}>
                    {isPositive ? '+' : ''}{plusMinus.toFixed(1)}
                </span>
            </div>
        </div>
    );
}
