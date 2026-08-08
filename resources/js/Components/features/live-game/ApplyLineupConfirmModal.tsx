import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { playerName } from './live-game-utils';
import type { Player } from '@/types';
import { ArrowRight, UsersRound } from 'lucide-react';

export interface PendingLineupChange {
    /** Which column the coach chose, for the confirmation copy. */
    source: 'season' | 'tonight';
    playerIds: number[];
    outs: number[];
    ins: number[];
}

interface ApplyLineupConfirmModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    change: PendingLineupChange | null;
    players: Player[];
    onConfirm: () => void;
}

export function ApplyLineupConfirmModal({
    open,
    onOpenChange,
    change,
    players,
    onConfirm,
}: ApplyLineupConfirmModalProps) {
    const label = (playerId: number): string => {
        const player = players.find((candidate) => candidate.id === playerId);

        return player ? `${player.jersey_number} ${playerName(player)}` : `Player #${playerId}`;
    };

    const pairs = change ? change.outs.map((out, index) => ({ out, in: change.ins[index] })) : [];

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="border-border bg-card sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="font-display text-lg font-black uppercase tracking-[0.08em]">
                        Apply {pairs.length === 1 ? 'this substitution' : `these ${pairs.length} substitutions`}?
                    </DialogTitle>
                    <DialogDescription className="text-sm text-muted-foreground">
                        Recording {pairs.length === 1 ? 'one substitution' : `${pairs.length} substitutions`} from the{' '}
                        {change?.source === 'tonight' ? "tonight's-form" : 'season-history'} lineup. Each one is a real
                        event on the timeline and can be voided individually afterwards.
                    </DialogDescription>
                </DialogHeader>

                <ul className="grid gap-2">
                    {pairs.map((pair) => (
                        <li
                            key={pair.out}
                            className="flex min-h-11 items-center gap-2 rounded-md border border-border px-3 py-2 text-sm"
                        >
                            <span className="live-text-danger min-w-0 flex-1 truncate font-mono">{label(pair.out)}</span>
                            <ArrowRight size={14} className="shrink-0 text-muted-foreground" aria-label="is replaced by" />
                            <span className="live-text-info min-w-0 flex-1 truncate font-mono">{label(pair.in)}</span>
                        </li>
                    ))}
                </ul>

                <DialogFooter className="flex flex-col gap-2 sm:flex-col sm:space-x-0">
                    <button
                        type="button"
                        onClick={onConfirm}
                        className="flex min-h-11 w-full cursor-pointer items-center justify-center gap-2 rounded-md bg-amber-400 px-4 text-xs font-bold uppercase tracking-wide text-black transition-colors hover:bg-amber-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300"
                    >
                        <UsersRound size={16} /> Apply lineup
                    </button>
                    <button
                        type="button"
                        onClick={() => onOpenChange(false)}
                        className="flex min-h-11 w-full cursor-pointer items-center justify-center rounded-md border border-border px-4 text-xs font-bold uppercase tracking-wide text-muted-foreground transition-colors hover:bg-muted/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300"
                    >
                        Cancel
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
