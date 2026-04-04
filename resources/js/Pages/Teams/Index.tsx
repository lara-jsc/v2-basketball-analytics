import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { TeamCard } from '@/Components/features/teams/TeamCard';
import { type PageProps, type Team } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Plus, Users2 } from 'lucide-react';

interface TeamsIndexProps extends PageProps {
    teams: Team[];
}

export default function TeamsIndex({ teams }: TeamsIndexProps) {
    const { flash } = usePage<TeamsIndexProps>().props;

    return (
        <AuthenticatedLayout>
            <Head title="Teams & Players" />

            <div className="flex flex-col gap-5">
                {/* Page heading */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-bold tracking-wide text-foreground uppercase">
                            Teams &amp; Players
                        </h1>
                        <p className="mt-0.5 text-sm text-muted-foreground font-ui">
                            Create a team, upload a roster CSV, and manage players.
                        </p>
                    </div>
                    <Link
                        href={route('teams.create')}
                        className="flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-ui font-semibold tracking-wide text-primary-foreground transition-all hover:opacity-90 hover:shadow-[0_0_12px_rgba(152,0,46,0.4)]"
                    >
                        <Plus size={15} />
                        New Team
                    </Link>
                </div>

                {/* Flash */}
                {flash?.success && (
                    <div className="rounded-lg border border-emerald-700/40 bg-emerald-950/40 px-4 py-3 text-sm text-emerald-400 font-ui">
                        {flash.success}
                    </div>
                )}

                {/* Empty state */}
                {teams.length === 0 ? (
                    <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-border bg-card py-20 text-center gap-5 relative overflow-hidden">
                        {/* Arena glow backdrop */}
                        <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_center,rgba(152,0,46,0.06),transparent_70%)]" />

                        <div className="flex h-14 w-14 items-center justify-center rounded-xl bg-primary/10 border border-primary/20 relative">
                            <Users2 size={24} className="text-primary" />
                        </div>
                        <div>
                            <p className="font-display text-lg font-bold tracking-wide text-foreground uppercase">
                                No teams yet
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground max-w-sm font-ui">
                                Create your first team, then upload a roster CSV to start comparing teams.
                            </p>
                        </div>
                        <Link
                            href={route('teams.create')}
                            className="flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-ui font-semibold tracking-wide text-primary-foreground transition-all hover:opacity-90 hover:shadow-[0_0_16px_rgba(152,0,46,0.4)]"
                        >
                            <Plus size={14} />
                            Create your first team
                        </Link>
                    </div>
                ) : (
                    <>
                        {/* Journey hint */}
                        <div className="flex items-center gap-2 rounded-lg border border-accent/20 bg-accent/5 px-4 py-2.5 text-xs text-muted-foreground">
                            <span className="text-accent font-ui font-bold tracking-wide uppercase text-[11px]">TIP</span>
                            <span className="font-ui">
                                Open a team → upload a CSV → then go to{' '}
                                <Link href={route('comparison.index')} className="text-accent hover:underline font-semibold">
                                    Team Comparison
                                </Link>{' '}
                                to generate win probability and lineup recommendations.
                            </span>
                        </div>

                        {/* Team grid */}
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            {teams.map((team) => (
                                <TeamCard key={team.id} team={team} />
                            ))}
                        </div>
                    </>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
