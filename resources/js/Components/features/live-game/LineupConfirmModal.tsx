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
    hasAssignment: boolean;
    assignedPlayerCount: number;
    onConfirmLineup: () => void;
    onConfirmWithAssistant: () => void;
    onAssignAssistant: () => void;
};

export function LineupConfirmModal({
    open,
    onOpenChange,
    hasAssignment,
    assignedPlayerCount,
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
                        {hasAssignment
                            ? `Submit with assistant assignments for ${assignedPlayerCount} player${assignedPlayerCount === 1 ? '' : 's'}, or change them first.`
                            : 'Submit your starting five now, or optionally assign players to assistant coaches first.'}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter className="flex flex-col gap-2 sm:flex-col sm:space-x-0">
                    {hasAssignment ? (
                        <button
                            type="button"
                            onClick={onConfirmWithAssistant}
                            className="flex min-h-11 w-full cursor-pointer items-center justify-center gap-2 rounded-md bg-amber-400 px-4 text-xs font-bold uppercase tracking-wide text-black transition-colors hover:bg-amber-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300"
                        >
                            <UsersRound size={16} /> Confirm with assistant assignments
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
                        <UserRound size={16} /> Assign assistants
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
