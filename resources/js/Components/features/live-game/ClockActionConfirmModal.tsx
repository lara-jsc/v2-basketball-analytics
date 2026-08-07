import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { RotateCcw, TimerReset } from 'lucide-react';

export type ConfirmableClockAction = 'reset_period' | 'set_period';

interface ClockActionConfirmModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    action: ConfirmableClockAction | null;
    period: number;
    onConfirm: () => void;
}

export function ClockActionConfirmModal({
    open,
    onOpenChange,
    action,
    period,
    onConfirm,
}: ClockActionConfirmModalProps) {
    const advancing = action === 'set_period';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="border-border bg-card sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="font-display text-lg font-black uppercase tracking-[0.08em]">
                        {advancing ? `Advance to Q${period + 1}?` : `Reset the Q${period} clock?`}
                    </DialogTitle>
                    <DialogDescription className="text-sm text-muted-foreground">
                        {advancing
                            ? `A period cannot be moved back. Every event recorded from now on is stamped Q${period + 1}, and the other bench sees the change immediately.`
                            : 'The elapsed time is discarded, which changes minutes played for everyone on court.'}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter className="flex flex-col gap-2 sm:flex-col sm:space-x-0">
                    <button
                        type="button"
                        onClick={onConfirm}
                        className="live-badge-warn flex min-h-11 w-full cursor-pointer items-center justify-center gap-2 rounded-md px-4 text-xs font-bold uppercase tracking-wide transition-colors hover:bg-amber-200/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300 dark:hover:bg-amber-300/25"
                    >
                        {advancing ? (
                            <>
                                <RotateCcw size={16} /> Advance to Q{period + 1}
                            </>
                        ) : (
                            <>
                                <TimerReset size={16} /> Reset clock
                            </>
                        )}
                    </button>
                    <button
                        type="button"
                        onClick={() => onOpenChange(false)}
                        className="flex min-h-11 w-full cursor-pointer items-center justify-center rounded-md border border-border px-4 text-xs font-bold uppercase tracking-wide text-muted-foreground transition-colors hover:bg-muted/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300"
                    >
                        Keep {advancing ? `Q${period}` : 'the current clock'}
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
