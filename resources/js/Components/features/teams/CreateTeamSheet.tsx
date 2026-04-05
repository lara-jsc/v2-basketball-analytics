import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/Components/ui/sheet';
import { useForm } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import type { FormEventHandler } from 'react';

interface CreateTeamSheetProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

export function CreateTeamSheet({ open, onOpenChange }: CreateTeamSheetProps) {
    const { data, setData, post, processing, errors, reset } = useForm({
        code: '',
        name: '',
    });

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('teams.store'), {
            onSuccess: () => { reset(); onOpenChange(false); },
        });
    };

    return (
        <Sheet open={open} onOpenChange={(v) => { if (!v) reset(); onOpenChange(v); }}>
            <SheetContent side="right" className="w-full sm:max-w-md p-0 flex flex-col">
                <SheetHeader className="px-6 pt-6 pb-4 border-b border-border shrink-0">
                    <SheetTitle>Create a Team</SheetTitle>
                    <SheetDescription>Give your team a short code and a full name.</SheetDescription>
                </SheetHeader>

                <form id="create-team-form" onSubmit={handleSubmit}
                    className="flex-1 overflow-y-auto px-6 py-5 space-y-4">

                    <div className="space-y-1.5">
                        <Label htmlFor="create-code" className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Team Code
                        </Label>
                        <Input
                            id="create-code"
                            type="text"
                            placeholder="e.g. LAL"
                            maxLength={10}
                            value={data.code}
                            onChange={(e) => setData('code', e.target.value.toUpperCase())}
                            disabled={processing}
                            className="font-mono uppercase tracking-widest"
                        />
                        <p className="text-xs text-muted-foreground">Max 10 characters. Must be unique.</p>
                        {errors.code && <p className="text-xs text-destructive">{errors.code}</p>}
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="create-name" className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Team Name
                        </Label>
                        <Input
                            id="create-name"
                            type="text"
                            placeholder="e.g. Los Angeles Lakers"
                            maxLength={100}
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            disabled={processing}
                        />
                        {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
                    </div>
                </form>

                <div className="px-6 py-4 border-t border-border flex items-center justify-end gap-3 shrink-0">
                    <button
                        type="button"
                        onClick={() => { reset(); onOpenChange(false); }}
                        className="rounded-lg px-4 py-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        form="create-team-form"
                        disabled={processing}
                        className="flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground transition-all hover:opacity-90 disabled:opacity-50"
                    >
                        {processing && <Loader2 size={14} className="animate-spin" />}
                        Create Team
                    </button>
                </div>
            </SheetContent>
        </Sheet>
    );
}
