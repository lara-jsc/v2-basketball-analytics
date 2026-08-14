import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/Components/ui/sheet';
import type { PlayerHistory } from '@/types/PlayerHistory.types';
import type { Team } from '@/types';
import { PlayerHistoryForm } from './PlayerHistoryForm';

interface PlayerHistorySheetProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    playerId: number;
    playerName: string;
    playingTeam: Pick<Team, 'id' | 'code' | 'name'>;
    teams: Pick<Team, 'id' | 'code' | 'name'>[];
    /** When provided, the sheet is in edit mode. */
    history?: PlayerHistory;
}

/**
 * Side-drawer for adding or editing a single game history entry.
 */
export function PlayerHistorySheet({
    open,
    onOpenChange,
    playerId,
    playerName,
    playingTeam,
    teams,
    history,
}: PlayerHistorySheetProps) {
    const isEdit = !!history;
    const opponentTeams = teams.filter((team) => team.id !== playingTeam.id);

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent side="right" className="w-full sm:max-w-xl p-0 flex flex-col">
                <SheetHeader className="px-6 pt-6 pb-4 border-b border-border shrink-0">
                    <SheetTitle>{isEdit ? 'Edit Game Entry' : 'Add Game Entry'}</SheetTitle>
                    <SheetDescription>
                        {isEdit
                            ? `Editing game vs ${history.opponent_team?.code ?? '—'} · ${new Date(history.game_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })}`
                            : `Record a game for ${playerName}.`}
                    </SheetDescription>
                </SheetHeader>

                <div className="flex-1 min-h-0 overflow-y-auto px-6 py-5">
                    <PlayerHistoryForm
                        action={
                            isEdit
                                ? route('player-histories.update', history.id)
                                : route('player-histories.store', playerId)
                        }
                        method={isEdit ? 'put' : 'post'}
                        playingTeam={playingTeam}
                        opponentTeams={opponentTeams}
                        history={history}
                        onSuccess={() => onOpenChange(false)}
                    />
                </div>
            </SheetContent>
        </Sheet>
    );
}
