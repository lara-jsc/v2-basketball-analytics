import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Separator } from '@/Components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/Components/ui/sheet';
import { type Team } from '@/types';
import { useForm } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import type { FormEventHandler } from 'react';

interface TeamFormSheetProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    team: Team;
}

/**
 * Side-drawer for editing a team's name, code, status, and logo.
 * Replaces the full-page Edit route so users stay in context.
 */
export function TeamFormSheet({ open, onOpenChange, team }: TeamFormSheetProps) {
    // ── Team details form ─────────────────────────────────────────────────────
    const { data, setData, put, processing, errors, reset } = useForm({
        name:      team.name,
        code:      team.code,
        is_active: team.is_active ? 'true' : 'false',
    });

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('teams.update', { id: team.id }), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    // ── Logo upload form ──────────────────────────────────────────────────────
    const {
        data: logoData,
        setData: setLogoData,
        post: postLogo,
        processing: logoProcessing,
        errors: logoErrors,
        reset: resetLogo,
    } = useForm<{ logo: File | null }>({ logo: null });

    const handleLogoSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        if (!logoData.logo) return;
        postLogo(route('teams.uploadLogo', { id: team.id }), {
            forceFormData: true,
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => resetLogo(),
        });
    };

    const logoUrl = team.logo_path ? `/storage/${team.logo_path}` : null;

    return (
        <Sheet open={open} onOpenChange={(v) => { if (!v) reset(); onOpenChange(v); }}>
            <SheetContent side="right" className="w-full sm:max-w-md p-0 flex flex-col">
                <SheetHeader className="px-6 pt-6 pb-4 border-b border-border shrink-0">
                    <SheetTitle>Edit Team</SheetTitle>
                    <SheetDescription>Update {team.name}'s details and logo.</SheetDescription>
                </SheetHeader>

                <div className="flex-1 overflow-y-auto px-6 py-5 space-y-6">
                    {/* ── Team Details ──────────────────────────────────── */}
                    <form id="team-form" onSubmit={handleSubmit} className="space-y-4">
                        <h3 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Team Details
                        </h3>

                        <div className="space-y-1.5">
                            <Label htmlFor="team-name" className="text-xs">Team Name *</Label>
                            <Input
                                id="team-name"
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className="h-8 text-sm"
                                required
                            />
                            {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="team-code" className="text-xs">Team Code *</Label>
                            <Input
                                id="team-code"
                                type="text"
                                value={data.code}
                                onChange={(e) => setData('code', e.target.value.toUpperCase())}
                                maxLength={10}
                                className="h-8 text-sm font-mono uppercase tracking-widest"
                                required
                            />
                            <p className="text-xs text-muted-foreground">Short code, e.g. LAL, GSW (max 10 chars)</p>
                            {errors.code && <p className="text-xs text-destructive">{errors.code}</p>}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="team-status" className="text-xs">Status</Label>
                            <Select value={data.is_active} onValueChange={(v) => setData('is_active', v)}>
                                <SelectTrigger id="team-status" className="h-8 text-sm">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="true">Active</SelectItem>
                                    <SelectItem value="false">Inactive</SelectItem>
                                </SelectContent>
                            </Select>
                            {errors.is_active && <p className="text-xs text-destructive">{errors.is_active}</p>}
                        </div>
                    </form>

                    <Separator />

                    {/* ── Team Logo ─────────────────────────────────────── */}
                    <div className="space-y-4">
                        <h3 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Team Logo
                        </h3>

                        <div className="flex items-center gap-4">
                            <div className="h-14 w-14 shrink-0 rounded-lg overflow-hidden border border-border bg-muted flex items-center justify-center">
                                {logoUrl ? (
                                    <img src={logoUrl} alt={`${team.name} logo`} className="h-full w-full object-cover" />
                                ) : (
                                    <Building2 size={20} className="text-muted-foreground" />
                                )}
                            </div>
                            <div>
                                <p className="text-sm font-medium text-foreground">
                                    {logoUrl ? 'Current logo' : 'No logo uploaded'}
                                </p>
                                <p className="text-xs text-muted-foreground mt-0.5">JPEG, PNG or WebP · max 2 MB</p>
                            </div>
                        </div>

                        <form onSubmit={handleLogoSubmit} className="space-y-3">
                            <div className="space-y-1.5">
                                <Label htmlFor="team-logo" className="text-xs">Upload New Logo</Label>
                                <Input
                                    id="team-logo"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    className="cursor-pointer h-8 text-sm"
                                    onChange={(e) => setLogoData('logo', e.target.files?.[0] ?? null)}
                                />
                                {logoErrors.logo && <p className="text-xs text-destructive">{logoErrors.logo}</p>}
                            </div>
                            <Button
                                type="submit"
                                variant="outline"
                                size="sm"
                                disabled={!logoData.logo || logoProcessing}
                                className="w-full"
                            >
                                {logoProcessing ? 'Uploading…' : 'Upload Logo'}
                            </Button>
                        </form>
                    </div>
                </div>

                {/* ── Footer ───────────────────────────────────────────── */}
                <div className="px-6 py-4 border-t border-border flex items-center justify-end gap-3 shrink-0">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => { reset(); onOpenChange(false); }}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        form="team-form"
                        size="sm"
                        disabled={processing}
                    >
                        {processing ? 'Saving…' : 'Save Changes'}
                    </Button>
                </div>
            </SheetContent>
        </Sheet>
    );
}
