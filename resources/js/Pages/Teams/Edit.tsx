import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Separator } from '@/Components/ui/separator';
import { type PageProps, type Team } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Building2 } from 'lucide-react';
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
    const {
        data,
        setData,
        put,
        processing,
        errors,
    } = useForm({
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
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-3">
                    <Link href={route('teams.show', { id: team.id })}>
                        <Button variant="ghost" size="sm" className="gap-1.5 text-muted-foreground">
                            <ArrowLeft size={14} />
                            {team.name}
                        </Button>
                    </Link>
                    <span className="text-muted-foreground">/</span>
                    <h2 className="text-lg font-semibold text-foreground">Edit Team</h2>
                </div>
            }
        >
            <Head title={`Edit ${team.name}`} />

            {flash?.success && (
                <div className="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-800 dark:bg-green-950 dark:text-green-400">
                    {flash.success}
                </div>
            )}

            <div className="mx-auto max-w-lg space-y-8">

                {/* ── Team Details ──────────────────────────────────────── */}
                <section className="rounded-xl border border-border bg-card p-6 space-y-5">
                    <h3 className="font-semibold text-foreground">Team Details</h3>
                    <Separator />
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="name">Team Name *</Label>
                            <Input
                                id="name"
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                required
                            />
                            {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="code">Team Code *</Label>
                            <Input
                                id="code"
                                type="text"
                                value={data.code}
                                onChange={(e) => setData('code', e.target.value.toUpperCase())}
                                maxLength={10}
                                className="font-mono uppercase"
                                required
                            />
                            <p className="text-xs text-muted-foreground">Short code, e.g. LAL, GSW (max 10 chars)</p>
                            {errors.code && <p className="text-sm text-destructive">{errors.code}</p>}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="is_active">Status</Label>
                            <Select
                                value={data.is_active}
                                onValueChange={(v) => setData('is_active', v)}
                            >
                                <SelectTrigger id="is_active">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="true">Active</SelectItem>
                                    <SelectItem value="false">Inactive</SelectItem>
                                </SelectContent>
                            </Select>
                            {errors.is_active && <p className="text-sm text-destructive">{errors.is_active}</p>}
                        </div>

                        <div className="flex justify-end pt-2">
                            <Button type="submit" disabled={processing}>
                                {processing ? 'Saving…' : 'Save Changes'}
                            </Button>
                        </div>
                    </form>
                </section>

                {/* ── Team Logo ─────────────────────────────────────────── */}
                <section className="rounded-xl border border-border bg-card p-6 space-y-5">
                    <h3 className="font-semibold text-foreground">Team Logo</h3>
                    <Separator />

                    <div className="flex items-center gap-4">
                        {/* Logo preview */}
                        <div className="h-16 w-16 shrink-0 rounded-lg overflow-hidden border border-border bg-muted flex items-center justify-center">
                            {logoUrl ? (
                                <img
                                    src={logoUrl}
                                    alt={`${team.name} logo`}
                                    className="h-full w-full object-cover"
                                />
                            ) : (
                                <Building2 size={24} className="text-muted-foreground" />
                            )}
                        </div>
                        <div className="flex-1">
                            <p className="text-sm font-medium text-foreground">
                                {logoUrl ? 'Current logo' : 'No logo uploaded'}
                            </p>
                            <p className="text-xs text-muted-foreground mt-0.5">
                                JPEG, PNG or WebP · max 2 MB
                            </p>
                        </div>
                    </div>

                    <form onSubmit={handleLogoSubmit} className="space-y-3">
                        <div className="space-y-1.5">
                            <Label htmlFor="logo">Upload New Logo</Label>
                            <Input
                                id="logo"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                className="cursor-pointer"
                                onChange={(e) => setLogoData('logo', e.target.files?.[0] ?? null)}
                            />
                            {logoErrors.logo && (
                                <p className="text-sm text-destructive">{logoErrors.logo}</p>
                            )}
                        </div>
                        <div className="flex justify-end">
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={!logoData.logo || logoProcessing}
                            >
                                {logoProcessing ? 'Uploading…' : 'Upload Logo'}
                            </Button>
                        </div>
                    </form>
                </section>

            </div>
        </AuthenticatedLayout>
    );
}
