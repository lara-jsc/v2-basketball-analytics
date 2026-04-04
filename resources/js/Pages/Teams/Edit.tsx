import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { type PageProps, type Team } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Building2, Loader2 } from 'lucide-react';
import type { FormEventHandler } from 'react';

interface TeamsEditProps extends PageProps {
    team: Team;
}

/**
 * Team edit page — update name, code, active status, and logo.
 */
export default function TeamsEdit({ team }: TeamsEditProps) {
    const { flash } = usePage<TeamsEditProps>().props;

    // ── Team detail form ───────────────────────────────────────────────────
    const { data, setData, put, processing, errors } = useForm({
        name:      team.name,
        code:      team.code,
        is_active: team.is_active ? 'true' : 'false',
    });

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('teams.update', { id: team.id }));
    };

    // ── Logo upload form ───────────────────────────────────────────────────
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
            onSuccess: () => resetLogo(),
        });
    };

    const logoUrl = team.logo_path ? `/storage/${team.logo_path}` : null;

    return (
        <AuthenticatedLayout>
            <Head title={`Edit ${team.name}`} />

            <div className="flex flex-col gap-5 max-w-lg">
                {/* Breadcrumb */}
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                    <Link
                        href={route('teams.show', { id: team.id })}
                        className="flex items-center gap-1.5 hover:text-foreground transition-colors"
                    >
                        <ArrowLeft size={14} />
                        {team.name}
                    </Link>
                    <span>/</span>
                    <span className="text-foreground">Edit Team</span>
                </div>

                {flash?.success && (
                    <div className="rounded-lg border border-green-700/40 bg-green-950/40 px-4 py-3 text-sm text-green-400">
                        {flash.success}
                    </div>
                )}

                {/* ── Team Details ──────────────────────────────────────── */}
                <div className="rounded-xl border border-border bg-card overflow-hidden">
                    <div className="border-b border-border bg-gradient-to-r from-primary/10 to-transparent px-5 py-4">
                        <h2 className="font-display text-base font-bold tracking-wide text-foreground">
                            Team Details
                        </h2>
                    </div>

                    <form onSubmit={handleSubmit} className="p-5 space-y-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="name" className="font-ui font-semibold tracking-wide text-xs uppercase text-muted-foreground">
                                Team Name *
                            </Label>
                            <Input
                                id="name"
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                required
                            />
                            {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="code" className="font-ui font-semibold tracking-wide text-xs uppercase text-muted-foreground">
                                Team Code *
                            </Label>
                            <Input
                                id="code"
                                type="text"
                                value={data.code}
                                onChange={(e) => setData('code', e.target.value.toUpperCase())}
                                maxLength={10}
                                className="font-mono uppercase tracking-widest"
                                required
                            />
                            <p className="text-xs text-muted-foreground">Short code, e.g. LAL, GSW (max 10 chars)</p>
                            {errors.code && <p className="text-xs text-destructive">{errors.code}</p>}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="is_active" className="font-ui font-semibold tracking-wide text-xs uppercase text-muted-foreground">
                                Status
                            </Label>
                            <Select value={data.is_active} onValueChange={(v) => setData('is_active', v)}>
                                <SelectTrigger id="is_active">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="true">Active</SelectItem>
                                    <SelectItem value="false">Inactive</SelectItem>
                                </SelectContent>
                            </Select>
                            {errors.is_active && <p className="text-xs text-destructive">{errors.is_active}</p>}
                        </div>

                        <div className="flex justify-end pt-2">
                            <button
                                type="submit"
                                disabled={processing}
                                className="flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-ui font-semibold tracking-wide text-primary-foreground transition-opacity hover:opacity-90 disabled:opacity-50"
                            >
                                {processing && <Loader2 size={14} className="animate-spin" />}
                                {processing ? 'Saving…' : 'Save Changes'}
                            </button>
                        </div>
                    </form>
                </div>

                {/* ── Team Logo ─────────────────────────────────────────── */}
                <div className="rounded-xl border border-border bg-card overflow-hidden">
                    <div className="border-b border-border bg-gradient-to-r from-primary/10 to-transparent px-5 py-4">
                        <h2 className="font-display text-base font-bold tracking-wide text-foreground">
                            Team Logo
                        </h2>
                    </div>

                    <div className="p-5 space-y-4">
                        <div className="flex items-center gap-4">
                            <div className="h-16 w-16 shrink-0 rounded-lg overflow-hidden border border-border bg-muted flex items-center justify-center">
                                {logoUrl ? (
                                    <img src={logoUrl} alt={`${team.name} logo`} className="h-full w-full object-cover" />
                                ) : (
                                    <Building2 size={22} className="text-muted-foreground" />
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
                                <Label htmlFor="logo" className="font-ui font-semibold tracking-wide text-xs uppercase text-muted-foreground">
                                    Upload New Logo
                                </Label>
                                <Input
                                    id="logo"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    className="cursor-pointer"
                                    onChange={(e) => setLogoData('logo', e.target.files?.[0] ?? null)}
                                />
                                {logoErrors.logo && <p className="text-xs text-destructive">{logoErrors.logo}</p>}
                            </div>
                            <div className="flex justify-end">
                                <button
                                    type="submit"
                                    disabled={!logoData.logo || logoProcessing}
                                    className="flex items-center gap-2 rounded-lg border border-border bg-card px-4 py-2 text-sm font-ui font-semibold tracking-wide text-foreground transition-colors hover:bg-muted disabled:opacity-50"
                                >
                                    {logoProcessing ? 'Uploading…' : 'Upload Logo'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
