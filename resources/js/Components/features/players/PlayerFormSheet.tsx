import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { ScrollArea } from '@/Components/ui/scroll-area';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Separator } from '@/Components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/Components/ui/sheet';
import { type PlayerWithStats, type Team } from '@/types';
import { useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';
import { ProfilePictureUpload } from './ProfilePictureUpload';

interface PlayerFormSheetProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    team: Team;
    /** When provided, the form is in edit mode. */
    player?: PlayerWithStats;
}

type PlayerFormData = {
    first_name: string;
    last_name: string;
    jersey_number: string;
    role: string;
    height_feet: string;
    weight_kg: string;
    is_active: string;
};

/**
 * Side-drawer form for creating or editing a player's profile info.
 * Stats are computed from game history — not editable here.
 * Profile picture is uploaded separately via ProfilePictureUpload (edit mode only).
 */
export function PlayerFormSheet({ open, onOpenChange, team, player }: PlayerFormSheetProps) {
    const isEdit = !!player;

    const { data, setData, post, put, processing, errors, reset } = useForm<PlayerFormData>({
        first_name:    player?.first_name              ?? '',
        last_name:     player?.last_name               ?? '',
        jersey_number: player?.jersey_number?.toString() ?? '',
        role:          player?.role                    ?? '',
        height_feet:   player?.height_feet?.toString()  ?? '',
        weight_kg:     player?.weight_kg?.toString()   ?? '',
        is_active:     (player?.is_active ?? true) ? 'true' : 'false',
    });

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();

        if (isEdit) {
            put(route('players.update', { id: player.id }), {
                onSuccess: () => {
                    onOpenChange(false);
                    reset();
                },
            });
        } else {
            post(route('players.store', { id: team.id }), {
                onSuccess: () => {
                    onOpenChange(false);
                    reset();
                },
            });
        }
    };

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent side="right" className="w-full sm:max-w-xl p-0 flex flex-col">
                <SheetHeader className="px-6 pt-6 pb-4 border-b border-border shrink-0">
                    <SheetTitle>{isEdit ? 'Edit Player' : 'Add Player'}</SheetTitle>
                    <SheetDescription>
                        {isEdit
                            ? `Update ${player.first_name} ${player.last_name}'s profile details.`
                            : `Add a new player to ${team.name}.`}
                    </SheetDescription>
                </SheetHeader>

                <ScrollArea className="flex-1 min-h-0">
                    <form id="player-form" onSubmit={handleSubmit} className="px-6 py-5 space-y-6">

                        {/* ── Profile Picture (edit mode only) ─────────── */}
                        {player && (
                            <>
                                <section className="space-y-4">
                                    <h3 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                        Profile Picture
                                    </h3>
                                    <ProfilePictureUpload player={player} />
                                </section>
                                <Separator />
                            </>
                        )}

                        {/* ── Player Info ──────────────────────────────── */}
                        <section className="space-y-4">
                            <h3 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Player Info
                            </h3>
                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-1.5">
                                    <Label htmlFor="first_name" className="text-xs">First Name *</Label>
                                    <Input
                                        id="first_name"
                                        type="text"
                                        value={data.first_name}
                                        onChange={(e) => setData('first_name', e.target.value)}
                                        className="h-8 text-sm"
                                        required
                                    />
                                    {errors.first_name && <p className="text-xs text-destructive">{errors.first_name}</p>}
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="last_name" className="text-xs">Last Name *</Label>
                                    <Input
                                        id="last_name"
                                        type="text"
                                        value={data.last_name}
                                        onChange={(e) => setData('last_name', e.target.value)}
                                        className="h-8 text-sm"
                                        required
                                    />
                                    {errors.last_name && <p className="text-xs text-destructive">{errors.last_name}</p>}
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="jersey_number" className="text-xs">Jersey # *</Label>
                                    <Input
                                        id="jersey_number"
                                        type="number"
                                        min={0}
                                        max={99}
                                        value={data.jersey_number}
                                        onChange={(e) => setData('jersey_number', e.target.value)}
                                        className="h-8 text-sm"
                                        required
                                    />
                                    {errors.jersey_number && <p className="text-xs text-destructive">{errors.jersey_number}</p>}
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="role" className="text-xs">Role / Position</Label>
                                    <Input
                                        id="role"
                                        type="text"
                                        value={data.role}
                                        onChange={(e) => setData('role', e.target.value)}
                                        className="h-8 text-sm"
                                        placeholder="e.g. Point Guard"
                                    />
                                    {errors.role && <p className="text-xs text-destructive">{errors.role}</p>}
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="height_feet" className="text-xs">Height (ft)</Label>
                                    <Input
                                        id="height_feet"
                                        type="number"
                                        step="0.01"
                                        value={data.height_feet}
                                        onChange={(e) => setData('height_feet', e.target.value)}
                                        className="h-8 text-sm"
                                        placeholder="6.25"
                                    />
                                    {errors.height_feet && <p className="text-xs text-destructive">{errors.height_feet}</p>}
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="weight_kg" className="text-xs">Weight (kg)</Label>
                                    <Input
                                        id="weight_kg"
                                        type="number"
                                        step="0.01"
                                        value={data.weight_kg}
                                        onChange={(e) => setData('weight_kg', e.target.value)}
                                        className="h-8 text-sm"
                                        placeholder="90.5"
                                    />
                                    {errors.weight_kg && <p className="text-xs text-destructive">{errors.weight_kg}</p>}
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="is_active" className="text-xs">Status</Label>
                                    <Select
                                        value={data.is_active}
                                        onValueChange={(v) => setData('is_active', v)}
                                    >
                                        <SelectTrigger id="is_active" className="h-8 text-sm">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="true">Active</SelectItem>
                                            <SelectItem value="false">Inactive</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </section>

                    </form>
                </ScrollArea>

                {/* ── Footer ───────────────────────────────────────────── */}
                <div className="px-6 py-4 border-t border-border flex items-center justify-end gap-3 shrink-0">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => onOpenChange(false)}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        form="player-form"
                        size="sm"
                        disabled={processing}
                    >
                        {processing ? 'Saving…' : isEdit ? 'Save Changes' : 'Add Player'}
                    </Button>
                </div>
            </SheetContent>
        </Sheet>
    );
}
