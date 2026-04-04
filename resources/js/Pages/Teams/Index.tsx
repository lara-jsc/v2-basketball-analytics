import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { TeamCard } from '@/Components/features/teams/TeamCard';
import { type PageProps, type Team } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Plus, Users2 } from 'lucide-react';

interface TeamsIndexProps extends PageProps {
    teams: Team[];
}

/**
 * Teams index — list all teams with navigation to each team's management page.
 */
export default function TeamsIndex({ teams }: TeamsIndexProps) {
    const { flash } = usePage<TeamsIndexProps>().props;

    return (
        <AuthenticatedLayout>
            <Head title="Teams & Players" />

            <div className="flex flex-col gap-5">
                {/* Page heading */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-bold tracking-wide text-foreground">
                            Teams &amp; Players
                        </h1>
                        <p className="mt-0.5 text-sm text-muted-foreground">
                            Create a team, upload a roster CSV, and manage players.
                        </p>
                    </div>
                    <Link
                        href={route('teams.create')}
                        className="flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-ui font-semibold tracking-wide text-primary-foreground transition-opacity hover:opacity-90"
                    >
                        <Plus size={15} />
                        New Team
                    </Link>
                </div>

                {/* Flash */}
                {flash?.success && (
                    <div className="rounded-lg border border-green-700/40 bg-green-950/40 px-4 py-3 text-sm text-green-400">
                        {flash.success}
                    </div>
                )}

                {/* How it works — shown only when no teams yet */}
                {teams.length === 0 ? (
                    <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-border bg-card py-16 text-center gap-4">
                        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <Users2 size={22} />
                        </div>
                        <div>
                            <p className="font-display text-base font-bold tracking-wide text-foreground">
                                No teams yet
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground max-w-sm">
                                Create your first team, then upload a roster CSV to start comparing teams.
                            </p>
                        </div>
                        <Link
                            href={route('teams.create')}
                            className="flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-ui font-semibold tracking-wide text-primary-foreground transition-opacity hover:opacity-90"
                        >
                            <Plus size={14} />
                            Create your first team
                        </Link>
                    </div>
                ) : (
                    <>
                        {/* Journey hint */}
                        <div className="flex items-center gap-2 rounded-lg border border-accent/20 bg-accent/5 px-4 py-2.5 text-xs text-muted-foreground">
                            <span className="text-accent font-ui font-semibold tracking-wide">TIP</span>
                            <span>
                                Open a team → upload a CSV → then go to{' '}
                                <Link href={route('comparison.index')} className="text-accent hover:underline">
                                    Team Comparison
                                </Link>{' '}
                                to generate win probability and lineup recommendations.
                            </span>
                        </div>

                        {/* Team grid — 2 columns on tablet */}
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
