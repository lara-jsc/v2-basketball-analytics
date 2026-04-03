import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { type PlayerWithStats } from '@/types';
import { router } from '@inertiajs/react';
import { useState } from 'react';

interface DeletePlayerDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    player: PlayerWithStats;
}

/**
 * Confirms hard deletion of a player.
 * Per spec: toggle is_active before hard delete is the intended UX flow —
 * this dialog handles the destructive final step.
 */
export function DeletePlayerDialog({ open, onOpenChange, player }: DeletePlayerDialogProps) {
    const [processing, setProcessing] = useState(false);

    function handleDelete() {
        setProcessing(true);
        router.delete(route('players.destroy', { id: player.id }), {
            onFinish: () => setProcessing(false),
            onSuccess: () => onOpenChange(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>Remove Player</DialogTitle>
                    <DialogDescription>
                        This will permanently delete{' '}
                        <span className="font-medium text-foreground">
                            {player.first_name} {player.last_name}
                        </span>{' '}
                        and all their stats. This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter className="gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => onOpenChange(false)}
                        disabled={processing}
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        size="sm"
                        onClick={handleDelete}
                        disabled={processing}
                    >
                        {processing ? 'Removing…' : 'Remove Player'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
