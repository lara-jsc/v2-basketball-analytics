import { type PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ChevronRight, UserPlus } from 'lucide-react';

/**
 * Tells a main coach (or admin) that assistant coaches are waiting to join.
 */
export function JoinRequestQueueCallout() {
    const queue = usePage<PageProps>().props.joinRequestQueue ?? [];

    if (queue.length === 0) return null;

    const total = queue.reduce((sum, entry) => sum + entry.count, 0);

    return (
        <section
            aria-labelledby="join-queue-title"
            className="rounded-xl border border-accent/40 bg-accent/10 px-5 py-4"
        >
            <div className="flex items-center gap-3">
                <UserPlus size={18} className="shrink-0 text-accent" aria-hidden />
                <h2 id="join-queue-title" className="text-sm font-semibold text-foreground">
                    {total === 1 ? '1 coach is' : `${total} coaches are`} waiting for approval to join{' '}
                    {queue.length === 1 ? queue[0].team_name : `${queue.length} teams`}
                </h2>
            </div>

            <ul className="mt-3 flex flex-wrap gap-2 sm:pl-[30px]">
                {queue.map((entry) => (
                    <li key={entry.team_id}>
                        <Link
                            href={`${route('teams.show', entry.team_id)}#join-requests`}
                            className="inline-flex min-h-10 items-center gap-1.5 rounded-lg border border-border bg-card px-3 text-sm font-semibold text-foreground transition-colors hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            Review {entry.team_name}
                            <span className="rounded-full bg-accent px-1.5 text-xs font-bold text-accent-foreground">
                                {entry.count}
                                <span className="sr-only"> pending</span>
                            </span>
                            <ChevronRight size={14} aria-hidden />
                        </Link>
                    </li>
                ))}
            </ul>
        </section>
    );
}
