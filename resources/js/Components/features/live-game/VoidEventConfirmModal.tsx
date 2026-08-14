import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Ban } from 'lucide-react';

interface VoidEventConfirmModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    label: string | null;
    isSubstitution: boolean;
    onConfirm: () => void;
}

export function VoidEventConfirmModal({
    open,
    onOpenChange,
    label,
    isSubstitution,
    onConfirm,
}: VoidEventConfirmModalProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="border-border bg-card sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="font-display text-lg font-black uppercase tracking-[0.08em]">
                        Void this event?
                    </DialogTitle>
                    <DialogDescription className="text-sm text-muted-foreground">
                        {label ? `${label} will be removed from the score and stats. ` : ''}
                        {isSubstitution
                            ? 'This is a substitution — voiding it changes who was on court for every later event, and plus-minus is recalculated for both teams.'
                            : 'Stats and plus-minus are recalculated for both teams. Voiding cannot be undone.'}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter className="flex flex-col gap-2 sm:flex-col sm:space-x-0">
                    <button
                        type="button"
                        onClick={onConfirm}
                        className="live-badge-danger flex min-h-11 w-full cursor-pointer items-center justify-center gap-2 rounded-md px-4 text-xs font-bold uppercase tracking-wide transition-colors hover:bg-red-200/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300 dark:hover:bg-red-400/25"
                    >
                        <Ban size={16} /> Void event
                    </button>
                    <button
                        type="button"
                        onClick={() => onOpenChange(false)}
                        className="flex min-h-11 w-full cursor-pointer items-center justify-center rounded-md border border-border px-4 text-xs font-bold uppercase tracking-wide text-muted-foreground transition-colors hover:bg-muted/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300"
                    >
                        Keep event
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
