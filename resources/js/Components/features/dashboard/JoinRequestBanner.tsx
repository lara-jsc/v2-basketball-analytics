import { type JoinableTeam, type PageProps } from '@/types';
import { router, useForm, usePage } from '@inertiajs/react';
import { Clock, Loader2, Send, UserPlus, XCircle } from 'lucide-react';
import { type FormEvent, useState } from 'react';

interface JoinRequestBannerProps {
    joinableTeams: JoinableTeam[];
}

/**
 * Team membership status for a coach who isn't on a team yet: a pending
 * request (with cancel), or a form to request a team (first time, after a
 * decline, or after cancelling).
 */
export function JoinRequestBanner({ joinableTeams }: JoinRequestBannerProps) {
    const user = usePage<PageProps>().props.auth.user;

    if (!user || user.is_admin || user.team_id !== null) return null;

    const request = user.join_request;

    if (request?.status === 'pending') {
        return <PendingRequest teamName={request.team_name} requestedAt={request.requested_at} />;
    }

    return (
        <RequestTeamForm
            joinableTeams={joinableTeams}
            declinedTeamName={request?.status === 'rejected' ? request.team_name : null}
        />
    );
}

function PendingRequest({ teamName, requestedAt }: { teamName: string; requestedAt: string | null }) {
    const [cancelling, setCancelling] = useState(false);

    const cancel = () => {
        setCancelling(true);
        router.delete(route('join-request.destroy'), {
            preserveScroll: true,
            onFinish: () => setCancelling(false),
        });
    };

    return (
        <section
            role="status"
            aria-labelledby="join-request-title"
            className="flex flex-col gap-4 rounded-xl border border-accent/40 bg-accent/10 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div className="flex items-start gap-3">
                <Clock size={18} className="mt-0.5 shrink-0 text-accent" aria-hidden />
                <div>
                    <h2 id="join-request-title" className="text-sm font-semibold text-foreground">
                        Request pending · {teamName}
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {requestedAt ? `Sent ${formatDate(requestedAt)}. ` : ''}
                        The {teamName} main coach will approve or decline it. Until then you can look around, but you
                        won&apos;t be on the team&apos;s staff for live games.
                    </p>
                </div>
            </div>

            <button
                type="button"
                onClick={cancel}
                disabled={cancelling}
                className="inline-flex min-h-10 shrink-0 items-center justify-center gap-2 rounded-lg border border-border bg-card px-4 text-sm font-semibold text-foreground transition-colors hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-60"
            >
                {cancelling && <Loader2 size={14} className="animate-spin" aria-hidden />}
                Cancel request
            </button>
        </section>
    );
}

function RequestTeamForm({
    joinableTeams,
    declinedTeamName,
}: {
    joinableTeams: JoinableTeam[];
    declinedTeamName: string | null;
}) {
    const { data, setData, post, processing, errors } = useForm({ team_id: '' });
    const hasTeams = joinableTeams.length > 0;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('join-request.store'), { preserveScroll: true });
    };

    return (
        <section
            aria-labelledby="request-team-title"
            className="rounded-xl border border-border bg-card px-5 py-4"
        >
            <div className="flex items-start gap-3">
                {declinedTeamName ? (
                    <XCircle size={18} className="mt-0.5 shrink-0 text-destructive" aria-hidden />
                ) : (
                    <UserPlus size={18} className="mt-0.5 shrink-0 text-accent" aria-hidden />
                )}
                <div className="min-w-0 flex-1">
                    <h2 id="request-team-title" className="text-sm font-semibold text-foreground">
                        {declinedTeamName ? `${declinedTeamName} declined your request` : "You're not on a team yet"}
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {hasTeams
                            ? "Pick the team you coach with. Its main coach gets your request."
                            : 'No team has a main coach to approve requests yet. Ask your main coach to sign up first.'}
                    </p>

                    {hasTeams && (
                        <form onSubmit={submit} className="mt-3 flex flex-col gap-2 sm:flex-row">
                            <label htmlFor="request-team" className="sr-only">
                                Team
                            </label>
                            <select
                                id="request-team"
                                value={data.team_id}
                                onChange={(e) => setData('team_id', e.target.value)}
                                required
                                aria-invalid={errors.team_id ? true : undefined}
                                aria-describedby={errors.team_id ? 'request-team-error' : undefined}
                                className="h-10 min-w-0 flex-1 rounded-lg border border-input bg-background px-3 text-sm text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            >
                                <option value="" disabled>
                                    Select a team
                                </option>
                                {joinableTeams.map((team) => (
                                    <option key={team.id} value={team.id}>
                                        {team.code} · {team.name}
                                    </option>
                                ))}
                            </select>
                            <button
                                type="submit"
                                disabled={processing || data.team_id === ''}
                                className="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground transition-opacity hover:opacity-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:opacity-50"
                            >
                                {processing ? (
                                    <Loader2 size={14} className="animate-spin" aria-hidden />
                                ) : (
                                    <Send size={14} aria-hidden />
                                )}
                                Send request
                            </button>
                        </form>
                    )}

                    {errors.team_id && (
                        <p id="request-team-error" className="mt-2 text-sm text-destructive">
                            {errors.team_id}
                        </p>
                    )}
                </div>
            </div>
        </section>
    );
}

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}
