import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/Components/ui/dialog';
import { useEffect, useState } from 'react';
import { Info, X } from 'lucide-react';

interface PlusMinusBreakdown {
    player_id: number;
    player_name: string;
    formula: string;
    formula_notation: string;
    games_with_data: number;
    games_total: number;
    computed_value: number | null;
    display_value: string | null;
    note: string;
}

interface PlusMinusBreakdownModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    playerId: number;
}

/**
 * Modal that explains how a player's plus/minus aggregate was computed.
 * Fetches the breakdown lazily on open — does not preload.
 *
 * Reusable pattern: wire a different endpoint to expose TS%/eFG% breakdowns
 * using the same loading/error/empty states.
 */
export function PlusMinusBreakdownModal({
    open,
    onOpenChange,
    playerId,
}: PlusMinusBreakdownModalProps) {
    const [loading, setLoading]       = useState(false);
    const [breakdown, setBreakdown]   = useState<PlusMinusBreakdown | null>(null);
    const [error, setError]           = useState<string | null>(null);

    useEffect(() => {
        if (! open) return;

        setLoading(true);
        setBreakdown(null);
        setError(null);

        fetch(route('players.stats.plusMinusBreakdown', { player: playerId }), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then(async (res) => {
                if (res.status === 404) {
                    setError('No stats found for this player yet.');
                    return;
                }
                if (! res.ok) {
                    setError('Failed to load breakdown. Please try again.');
                    return;
                }
                const data = await res.json() as PlusMinusBreakdown;
                setBreakdown(data);
            })
            .catch(() => setError('Network error. Please check your connection.'))
            .finally(() => setLoading(false));
    }, [open, playerId]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-sm bg-card border-border">
                <DialogHeader>
                    <DialogTitle className="font-display text-base font-bold tracking-widest uppercase text-foreground flex items-center gap-2">
                        <Info size={14} className="text-accent shrink-0" />
                        Plus/Minus (+/-)
                    </DialogTitle>
                    <DialogDescription className="text-xs text-muted-foreground font-ui">
                        How this player's +/- rating is calculated
                    </DialogDescription>
                </DialogHeader>

                {/* Loading skeleton */}
                {loading && (
                    <div className="space-y-3 py-2 animate-pulse">
                        <div className="h-16 rounded-lg bg-muted/40" />
                        <div className="h-8 rounded bg-muted/30" />
                        <div className="h-8 rounded bg-muted/30 w-3/4" />
                        <div className="h-4 rounded bg-muted/20 w-full" />
                    </div>
                )}

                {/* Error state */}
                {! loading && error && (
                    <div className="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive font-ui">
                        {error}
                    </div>
                )}

                {/* Not yet computed */}
                {! loading && breakdown && breakdown.computed_value === null && (
                    <div className="space-y-3">
                        <FormulaBox notation={breakdown.formula_notation} formula={breakdown.formula} />
                        <div className="rounded-lg border border-border bg-muted/20 px-4 py-3 text-xs text-muted-foreground font-ui">
                            {breakdown.note}
                        </div>
                    </div>
                )}

                {/* Computed breakdown */}
                {! loading && breakdown && breakdown.computed_value !== null && (
                    <div className="space-y-3">
                        <FormulaBox notation={breakdown.formula_notation} formula={breakdown.formula} />

                        <div className="flex items-center justify-between rounded-lg border border-border bg-muted/20 px-4 py-3">
                            <span className="text-xs font-ui font-semibold text-muted-foreground uppercase tracking-wider">
                                Computed Value
                            </span>
                            <span className={`font-mono text-xl font-bold ${(breakdown.computed_value ?? 0) >= 0 ? 'text-accent' : 'text-destructive'}`}>
                                {breakdown.display_value}
                            </span>
                        </div>

                        <div className="flex items-center justify-between rounded-lg border border-border bg-muted/20 px-4 py-3">
                            <span className="text-xs font-ui font-semibold text-muted-foreground uppercase tracking-wider">
                                Games with data
                            </span>
                            <span className="font-mono text-sm font-bold text-foreground">
                                {breakdown.games_with_data} / {breakdown.games_total}
                            </span>
                        </div>

                        {breakdown.note && (
                            <div className="flex items-start gap-2 rounded-lg border border-border/50 bg-muted/10 px-3 py-2.5 text-[11px] text-muted-foreground font-ui">
                                <Info size={11} className="mt-0.5 shrink-0 text-accent/60" />
                                {breakdown.note}
                            </div>
                        )}
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}

function FormulaBox({ notation, formula }: { notation: string; formula: string }) {
    return (
        <div className="rounded-lg border border-accent/20 bg-accent/5 px-4 py-3 space-y-1.5">
            <p className="text-[10px] font-ui font-bold tracking-widest text-muted-foreground uppercase">Formula</p>
            <p className="font-mono text-base font-bold text-accent">{notation}</p>
            <p className="text-[11px] text-muted-foreground font-ui">= {formula}</p>
        </div>
    );
}
