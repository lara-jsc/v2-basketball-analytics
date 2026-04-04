import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { type PageProps } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Loader2 } from 'lucide-react';
import { type FormEvent } from 'react';

interface CreateTeamFormData {
    code: string;
    name: string;
}

/**
 * Create team form — collects code and name, submits via Inertia POST.
 */
export default function TeamsCreate(_props: PageProps) {
    const { data, setData, post, processing, errors } = useForm<CreateTeamFormData>({
        code: '',
        name: '',
    });

    const handleSubmit = (e: FormEvent<HTMLFormElement>): void => {
        e.preventDefault();
        post(route('teams.store'));
    };

    return (
        <AuthenticatedLayout>
            <Head title="New Team" />

            <div className="flex flex-col gap-5 max-w-md">
                {/* Breadcrumb */}
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                    <Link
                        href={route('teams.index')}
                        className="flex items-center gap-1.5 hover:text-foreground transition-colors"
                    >
                        <ArrowLeft size={14} />
                        Teams &amp; Players
                    </Link>
                    <span>/</span>
                    <span className="text-foreground">New Team</span>
                </div>

                {/* Form card */}
                <div className="rounded-xl border border-border bg-card overflow-hidden">
                    <div className="border-b border-border bg-gradient-to-r from-primary/10 to-transparent px-5 py-4">
                        <h1 className="font-display text-lg font-bold tracking-wide text-foreground">
                            Create a Team
                        </h1>
                        <p className="mt-0.5 text-sm text-muted-foreground">
                            Give your team a short code and a full name.
                        </p>
                    </div>

                    <form onSubmit={handleSubmit} className="p-5 space-y-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="code" className="font-ui font-semibold tracking-wide text-xs uppercase text-muted-foreground">
                                Team Code
                            </Label>
                            <Input
                                id="code"
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
                            <Label htmlFor="name" className="font-ui font-semibold tracking-wide text-xs uppercase text-muted-foreground">
                                Team Name
                            </Label>
                            <Input
                                id="name"
                                type="text"
                                placeholder="e.g. Los Angeles Lakers"
                                maxLength={100}
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                disabled={processing}
                            />
                            {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
                        </div>

                        <div className="flex items-center gap-3 pt-2">
                            <button
                                type="submit"
                                disabled={processing}
                                className="flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-ui font-semibold tracking-wide text-primary-foreground transition-opacity hover:opacity-90 disabled:opacity-50"
                            >
                                {processing && <Loader2 size={14} className="animate-spin" />}
                                Create Team
                            </button>
                            <Link
                                href={route('teams.index')}
                                className="text-sm text-muted-foreground hover:text-foreground transition-colors"
                            >
                                Cancel
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
