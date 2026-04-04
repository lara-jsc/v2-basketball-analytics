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

/**
 * Lineup modal — redesigned to match Image 3.
 * Jersey numbers and positions come from real player data matched by player_id.
 */
export function LineupModal({ open, onClose, lineup, teamName, players }: LineupModalProps) {
    const netPlusMinus = lineup
        ? lineup.recommended_lineup.reduce((sum, p) => sum + p.plus_minus_score, 0)
        : null;

    return (
        <Dialog open={open} onOpenChange={(v) => !v && onClose()}>
            <DialogContent className="max-w-4xl p-0 overflow-hidden border-border bg-popover">
                {/* Header */}
                <DialogHeader className="relative border-b border-border bg-gradient-to-r from-primary/25 via-primary/10 to-transparent px-6 py-4">
                    <div className="pointer-events-none absolute inset-0 bg-[repeating-linear-gradient(90deg,transparent,transparent_48px,rgba(249,160,27,0.03)_48px,rgba(249,160,27,0.03)_49px)]" />
                    <div className="relative flex items-start justify-between gap-4">
                        <div>
                            <DialogTitle className="font-display text-lg font-bold tracking-widest uppercase text-foreground">
                                Recommended Lineup
                            </DialogTitle>
                            <p className="mt-0.5 font-ui text-xs text-muted-foreground">
                                AI-Recommended Optimal Lineup — {teamName}
                            </p>
                        </div>
                        <button
                            onClick={onClose}
                            className="flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                        >
                            <X size={15} />
                        </button>
                    </div>
                </DialogHeader>

                {lineup === null ? (
                    <div className="flex flex-col items-center justify-center gap-3 py-16">
                        <Loader2 size={28} className="animate-spin text-accent" />
                        <p className="font-ui text-sm text-muted-foreground">Computing lineup recommendation…</p>
                    </div>
                ) : (
                    <div className="flex gap-0">
                        {/* ── Left: 5 player cards ── */}
                        <div className="flex flex-1 flex-col gap-4 p-6">
                            <p className="font-display text-sm font-bold tracking-widest uppercase text-foreground">
                                Optimized Starting Lineup
                            </p>

                            {/* 5 player cards */}
                            <div className="flex gap-3">
                                {lineup.recommended_lineup.slice(0, 5).map((lineupPlayer) => {
                                    const match = players.find((p) => p.id === lineupPlayer.player_id);
                                    return (
                                        <PlayerCard
                                            key={lineupPlayer.player_id}
                                            name={lineupPlayer.name}
                                            plusMinus={lineupPlayer.plus_minus_score}
                                            jerseyNumber={match?.jersey_number ?? null}
                                            position={match?.role ?? null}
                                        />
                                    );
                                })}
                            </div>

                            {/* Net plus-minus footer */}
                            <div className="flex items-center gap-2 rounded-lg border border-accent/20 bg-accent/5 px-4 py-2.5">
                                <span className="font-display text-xl font-bold text-accent drop-shadow-[0_0_6px_rgba(249,160,27,0.5)]">
                                    {netPlusMinus !== null && netPlusMinus >= 0 ? '+' : ''}
                                    {netPlusMinus?.toFixed(1) ?? '—'}
                                </span>
                                <span className="font-ui text-xs text-muted-foreground">
                                    Net Plus-Minus Expected When Using This Lineup
                                </span>
                            </div>
                        </div>

                        {/* ── Right: Confidence panel + CTA ── */}
                        <div className="flex flex-col w-48 shrink-0 border-l border-border bg-card/50 p-5 gap-4">
                            {/* Large amber net value */}
                            <div className="text-center">
                                <p className="font-display text-4xl font-bold text-accent drop-shadow-[0_0_12px_rgba(249,160,27,0.6)] leading-none">
                                    {netPlusMinus !== null && netPlusMinus >= 0 ? '+' : ''}
                                    {netPlusMinus?.toFixed(1) ?? '—'}
                                </p>
                                <p className="mt-1 font-ui text-[10px] uppercase tracking-widest text-muted-foreground">
                                    Net Plus-Minus
                                </p>
                            </div>

                            {/* Confidence donut */}
                            <div className="flex flex-col items-center gap-2">
                                <div
                                    className="relative h-16 w-16 rounded-full flex items-center justify-center"
                                    style={{
                                        background: `conic-gradient(#F9A01B 0% ${Math.round(lineup.confidence * 100)}%, hsl(var(--muted)) ${Math.round(lineup.confidence * 100)}% 100%)`,
                                    }}
                                >
                                    <div className="absolute h-10 w-10 rounded-full bg-card flex items-center justify-center">
                                        <span className="font-display text-xs font-bold text-accent">
                                            {Math.round(lineup.confidence * 100)}%
                                        </span>
                                    </div>
                                </div>
                                <p className="font-ui text-[10px] uppercase tracking-widest text-muted-foreground text-center">
                                    Confidence
                                </p>
                            </div>

                            <div className="mt-auto" />

                            {/* Confirm CTA */}
                            <button
                                onClick={onClose}
                                className="w-full rounded-lg bg-accent px-4 py-2.5 font-ui text-sm font-semibold tracking-wide text-accent-foreground transition-all hover:opacity-90 hover:shadow-[0_0_16px_rgba(249,160,27,0.4)]"
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
    name,
    plusMinus,
    jerseyNumber,
    position,
}: {
    name: string;
    plusMinus: number;
    jerseyNumber: number | null;
    position: string | null;
}) {
    const [firstName, ...rest] = name.split(' ');
    const lastName = rest.join(' ');
    const isPositive = plusMinus >= 0;

    return (
        <div className="flex flex-1 flex-col items-center gap-2 rounded-xl border border-border bg-card p-3 text-center transition-all hover:border-accent/30 hover:shadow-[0_0_12px_rgba(249,160,27,0.08)]">
            {/* Jersey number circle */}
            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-primary border-2 border-primary/60 shadow-[0_0_8px_rgba(152,0,46,0.4)]">
                <span className="font-display text-sm font-bold text-primary-foreground leading-none">
                    {jerseyNumber ?? '—'}
                </span>
            </div>

            {/* Player silhouette placeholder */}
            <div className="h-12 w-12 rounded-full bg-muted/30 border border-border flex items-center justify-center">
                <svg viewBox="0 0 24 24" fill="none" className="h-7 w-7 text-muted-foreground/30" stroke="currentColor" strokeWidth={1.2}>
                    <circle cx="12" cy="7" r="4" />
                    <path d="M4 21v-2a8 8 0 0 1 16 0v2" strokeLinecap="round" />
                </svg>
            </div>

            {/* Name */}
            <div className="min-w-0 w-full">
                <p className="font-display text-xs font-bold text-foreground leading-tight truncate">
                    {firstName}
                </p>
                {lastName && (
                    <p className="font-display text-xs font-bold text-foreground leading-tight truncate">
                        {lastName}
                    </p>
                )}
            </div>

            {/* Position badge — only if available */}
            {position ? (
                <span className="rounded border border-accent/30 bg-accent/10 px-1.5 py-0.5 font-ui text-[9px] font-bold uppercase tracking-widest text-accent">
                    {position}
                </span>
            ) : (
                <span className="h-4" /> // spacer to keep layout consistent
            )}

            {/* Plus-minus */}
            <span className={`font-mono text-xs font-bold tabular-nums ${isPositive ? 'text-accent' : 'text-muted-foreground'}`}>
                {isPositive ? '+' : ''}{plusMinus.toFixed(1)}
            </span>
        </div>
    );
}
