import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { UserRound, UsersRound } from 'lucide-react';

type LineupConfirmModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    assistantName: string | null;
    hasAssignment: boolean;
    onConfirmLineup: () => void;
    onConfirmWithAssistant: () => void;
    onAssignAssistant: () => void;
};

export function LineupConfirmModal({
    open,
    onOpenChange,
    assistantName,
    hasAssignment,
    onConfirmLineup,
    onConfirmWithAssistant,
    onAssignAssistant,
}: LineupConfirmModalProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="border-border bg-card sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="font-display text-lg font-black uppercase tracking-[0.08em]">
                        Confirm lineup?
                    </DialogTitle>
                    <DialogDescription className="text-sm text-muted-foreground">
                        {hasAssignment && assistantName
                            ? `Submit with ${assistantName} controlling the players you assigned, or change the assignment.`
                            : 'Submit your starting five now, or optionally assign players to an assistant coach first.'}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter className="flex flex-col gap-2 sm:flex-col sm:space-x-0">
                    {hasAssignment && assistantName ? (
                        <button
                            type="button"
                            onClick={onConfirmWithAssistant}
                            className="flex min-h-11 w-full cursor-pointer items-center justify-center gap-2 rounded-md bg-amber-400 px-4 text-xs font-bold uppercase tracking-wide text-black transition-colors hover:bg-amber-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300"
                        >
                            <UsersRound size={16} /> Confirm with {assistantName}
                        </button>
                    ) : (
                        <button
                            type="button"
                            onClick={onConfirmLineup}
                            className="flex min-h-11 w-full cursor-pointer items-center justify-center gap-2 rounded-md bg-amber-400 px-4 text-xs font-bold uppercase tracking-wide text-black transition-colors hover:bg-amber-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300"
                        >
                            <UsersRound size={16} /> Confirm lineup
                        </button>
                    )}
                    <button
                        type="button"
                        onClick={onAssignAssistant}
                        className="live-badge-info flex min-h-11 w-full cursor-pointer items-center justify-center gap-2 rounded-md px-4 text-xs font-bold uppercase tracking-wide transition-colors hover:bg-cyan-200/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300 dark:hover:bg-cyan-300/20"
                    >
                        <UserRound size={16} /> Assign assistant
                    </button>
                    {hasAssignment && (
                        <button
                            type="button"
                            onClick={onConfirmLineup}
                            className="flex min-h-11 w-full cursor-pointer items-center justify-center rounded-md border border-border px-4 text-xs font-bold uppercase tracking-wide text-muted-foreground transition-colors hover:bg-muted/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300"
                        >
                            Confirm without assistant
                        </button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
